import { PencilSimpleIcon, PlusIcon, TrashIcon } from '@phosphor-icons/react'
import { lazy, Suspense, useState, type FormEvent } from 'react'
import { profileApi, type ProfileTarget } from '../api/client'
import type { ProfileProject, ProjectInput } from '../api/types'
import { useTranslation } from '../i18n/context'
import { useDateFormat } from '../i18n/useDateFormat'
import { TagInput } from './TagInput'
import { useErrorText } from '../i18n/useErrorText'

// Рендерер Markdown весит больше, чем всё остальное приложение, и нужен
// только здесь — грузим его отдельным чанком, а не в общем бандле.
const Markdown = lazy(() => import('react-markdown'))

interface Props {
  projects: ProfileProject[]
  onChanged: () => void
  /** Чей профиль правим: свой или чужой (админом). По умолчанию свой. */
  target?: ProfileTarget
}

const EMPTY: ProjectInput = {
  name: '',
  description: '',
  periodFrom: null,
  periodTo: null,
  tags: [],
}

function formatPeriod(
  project: ProfileProject,
  formatMonth: (value: string | Date | null | undefined) => string,
  ongoing: string,
): string | null {
  if (!project.periodFrom && !project.periodTo) {
    return null
  }

  const from = project.periodFrom ? formatMonth(project.periodFrom) : '…'
  const to = project.periodTo ? formatMonth(project.periodTo) : ongoing

  return `${from} — ${to}`
}

/**
 * Раздел «Проекты»: список с Markdown-описанием и тегами, плюс форма
 * добавления и редактирования.
 *
 * Проекты сохраняются явно, а не автосохранением: у них своя форма с
 * подтверждением, и удалять проект по таймеру было бы опасно.
 */
export function ProjectsSection({ projects, onChanged, target = 'me' }: Props) {
  const t = useTranslation()
  // null — форма закрыта, число — правим проект, 'new' — создаём.
  const [editing, setEditing] = useState<number | 'new' | null>(null)
  const [selected, setSelected] = useState<Set<number>>(new Set())
  const [busy, setBusy] = useState(false)

  const toggle = (id: number) => {
    setSelected((current) => {
      const next = new Set(current)

      if (!next.delete(id)) {
        next.add(id)
      }

      return next
    })
  }

  const removeSelected = async () => {
    setBusy(true)

    try {
      for (const id of selected) {
        await profileApi.deleteProject(id, target)
      }

      setSelected(new Set())
      onChanged()
    } finally {
      setBusy(false)
    }
  }

  const editTarget = typeof editing === 'number'
    ? projects.find((project) => project.id === editing)
    : undefined

  return (
    <section className="panel">
      <div className="panel__head">
        <div>
          <h2 className="h2">{t('projects.title')}</h2>
          <p className="panel__hint muted-3">{t('projects.hint')}</p>
        </div>

        {editing === null && (
          <button type="button" className="btn btn--outline" onClick={() => setEditing('new')}>
            <PlusIcon size={14} aria-hidden="true" />
            {t('projects.add')}
          </button>
        )}
      </div>

      {/* Форма живёт над списком: карточка остаётся записью, которую
          открывают, а не превращается в редактор на месте. */}
      {editing === 'new' && (
        <ProjectForm
          initial={EMPTY}
          target={target}
          onCancel={() => setEditing(null)}
          onSaved={() => {
            setEditing(null)
            onChanged()
          }}
        />
      )}

      {editTarget && (
        <ProjectForm
          key={editTarget.id}
          projectId={editTarget.id}
          target={target}
          initial={{
            name: editTarget.name,
            description: editTarget.description ?? '',
            periodFrom: editTarget.periodFrom,
            periodTo: editTarget.periodTo,
            tags: editTarget.tags.map((tag) => tag.name),
          }}
          onCancel={() => setEditing(null)}
          onSaved={() => {
            setEditing(null)
            onChanged()
          }}
        />
      )}

      {projects.length === 0 && editing !== 'new' ? (
        <p className="muted table__empty">{t('projects.empty')}</p>
      ) : (
        <>
          {/* Действия — в панели над списком, а не кнопками в каждой карточке:
              задание снимает 20% за N кнопок в N записях. */}
          <ProjectToolbar
            count={selected.size}
            busy={busy}
            onEdit={() => setEditing([...selected][0] ?? null)}
            onRemove={() => void removeSelected()}
            onClear={() => setSelected(new Set())}
          />

          <div className="col g4">
            {projects.map((project) => (
              <ProjectCard
                key={project.id}
                project={project}
                checked={selected.has(project.id)}
                onToggle={() => toggle(project.id)}
                onOpen={() => setEditing(project.id)}
              />
            ))}
          </div>
        </>
      )}
    </section>
  )
}

/**
 * Панель действий над выделенными проектами — вместо кнопок в каждой карточке.
 */
function ProjectToolbar({
  count,
  busy,
  onEdit,
  onRemove,
  onClear,
}: {
  count: number
  busy: boolean
  onEdit: () => void
  onRemove: () => void
  onClear: () => void
}) {
  const t = useTranslation()
  const [confirming, setConfirming] = useState(false)

  if (count === 0) {
    return <p className="muted t-sm toolbar__hint">{t('projects.pickHint')}</p>
  }

  return (
    <div className="toolbar">
      <span className="t-sm">{t('projects.selected', { count })}</span>

      <div className="row g2">
        <button
          type="button"
          className="btn btn--outline"
          disabled={count !== 1 || busy}
          onClick={onEdit}
          title={count === 1 ? undefined : t('projects.editOnlyOne')}
        >
          <PencilSimpleIcon size={14} aria-hidden="true" />
          {t('projects.edit')}
        </button>

        <button
          type="button"
          className="btn btn--outline"
          disabled={busy}
          onClick={() => setConfirming(true)}
        >
          <TrashIcon size={14} aria-hidden="true" />
          {t('projects.remove', { count })}
        </button>

        <button type="button" className="btn btn--ghost" onClick={onClear} disabled={busy}>
          {t('projects.clear')}
        </button>
      </div>

      {confirming && (
        <div className="notice notice--error toolbar__confirm" role="alert">
          <span>
            {count === 1 ? t('projects.confirmOne') : t('projects.confirmMany', { count })}
          </span>
          <div className="row g2">
            <button type="button" className="btn btn--ghost" onClick={() => setConfirming(false)}>
              {t('projects.cancel')}
            </button>
            <button
              type="button"
              className="btn btn--primary"
              disabled={busy}
              onClick={() => {
                setConfirming(false)
                onRemove()
              }}
            >
              {t('projects.confirmRemove')}
            </button>
          </div>
        </div>
      )}
    </div>
  )
}

function ProjectCard({
  project,
  checked,
  onToggle,
  onOpen,
}: {
  project: ProfileProject
  checked: boolean
  onToggle: () => void
  onOpen: () => void
}) {
  const t = useTranslation()
  const formatMonth = useDateFormat({ year: 'numeric', month: 'short' })
  const period = formatPeriod(project, formatMonth, t('projects.ongoing'))

  return (
    <article
      className={`project project--pick${checked ? ' is-picked' : ''}`}
      onClick={onOpen}
      aria-selected={checked}
    >
      <div className="project__head">
        {/* Флажок не должен открывать карточку — иначе выделить её мышью
            было бы нечем. */}
        <label className="project__pick" onClick={(event) => event.stopPropagation()}>
          <input
            type="checkbox"
            checked={checked}
            onChange={onToggle}
            aria-label={t('projects.pick', { name: project.name })}
          />
        </label>

        <div className="col g1" style={{ flex: 1, minWidth: 0 }}>
          <h3 className="project__title">{project.name}</h3>
          {period && <span className="t-xs muted-3">{period}</span>}
        </div>
      </div>

      {project.description && (
        <div className="prose prose--md">
          {/* Пока чанк грузится, показываем исходный текст: он читаем и без
              разметки, так что подмена спиннером только мигала бы. */}
          <Suspense fallback={<p>{project.description}</p>}>
            <Markdown>{project.description}</Markdown>
          </Suspense>
        </div>
      )}

      {project.tags.length > 0 && (
        <div className="cloud">
          {project.tags.map((tag) => (
            <span key={tag.id} className="chip">
              {tag.name}
            </span>
          ))}
        </div>
      )}
    </article>
  )
}
function ProjectForm({
  projectId,
  initial,
  onCancel,
  onSaved,
  target,
}: {
  projectId?: number
  initial: ProjectInput
  onCancel: () => void
  onSaved: () => void
  target: ProfileTarget
}) {
  const t = useTranslation()
  const errorText = useErrorText()
  const [form, setForm] = useState<ProjectInput>(initial)
  const [error, setError] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)

  const submit = async (event: FormEvent) => {
    event.preventDefault()
    setBusy(true)
    setError(null)

    const payload: ProjectInput = {
      ...form,
      description: form.description?.trim() || null,
    }

    try {
      if (projectId === undefined) {
        await profileApi.createProject(payload, target)
      } else {
        await profileApi.updateProject(projectId, payload, target)
      }

      onSaved()
    } catch (requestError: unknown) {
      setError(
        errorText(requestError, 'projects.saveFailed')
      )
      setBusy(false)
    }
  }

  return (
    <form className="project project--form" onSubmit={submit}>
      <div className="field">
        <label className="label" htmlFor="project-name">
          {t('projects.name')}
        </label>
        <input
          id="project-name"
          className="input"
          required
          maxLength={180}
          value={form.name}
          onChange={(event) => setForm({ ...form, name: event.target.value })}
        />
      </div>

      <div className="period">
        <div className="field">
          <label className="label" htmlFor="project-from">
            {t('projects.from')}
          </label>
          <input
            id="project-from"
            type="date"
            className="input"
            value={form.periodFrom ?? ''}
            onChange={(event) => setForm({ ...form, periodFrom: event.target.value || null })}
          />
        </div>

        <div className="field">
          <label className="label" htmlFor="project-to">
            {t('projects.to')}
          </label>
          <input
            id="project-to"
            type="date"
            className="input"
            value={form.periodTo ?? ''}
            onChange={(event) => setForm({ ...form, periodTo: event.target.value || null })}
          />
          <span className="t-xs muted-3">{t('projects.toHint')}</span>
        </div>
      </div>

      <div className="field">
        <label className="label" htmlFor="project-description">
          {t('projects.description')}
        </label>
        <textarea
          id="project-description"
          className="input input--area"
          rows={5}
          placeholder={t('projects.descriptionPlaceholder')}
          value={form.description ?? ''}
          onChange={(event) => setForm({ ...form, description: event.target.value })}
        />
      </div>

      <div className="field">
        <span className="label">{t('projects.tech')}</span>
        <TagInput tags={form.tags} onChange={(tags) => setForm({ ...form, tags })} />
      </div>

      {error && (
        <div className="notice notice--error" role="alert">
          <span>{error}</span>
        </div>
      )}

      <div className="row g2">
        <button type="submit" className="btn btn--primary" disabled={busy}>
          {busy ? t('projects.saving') : t('projects.save')}
        </button>
        <button type="button" className="btn btn--ghost" onClick={onCancel} disabled={busy}>
          {t('projects.cancel')}
        </button>
      </div>
    </form>
  )
}
