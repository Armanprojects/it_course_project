import { ArrowUpIcon, ArrowDownIcon, CaretLeftIcon, PlusIcon, TrashIcon } from '@phosphor-icons/react'
import { useCallback, useEffect, useState } from 'react'
import { Link, Navigate, useNavigate, useParams } from 'react-router-dom'
import {
  libraryApi,
  positionAdminApi,
  tokenStorage,
} from '../api/client'
import type {
  AccessRule,
  AttributeType,
  FilterOperator,
  LibraryAttribute,
  PositionInput,
  TemplateAttribute,
} from '../api/types'
import { AccessRuleEditor } from '../components/AccessRuleEditor'
import { AppHeader } from '../components/AppHeader'
import { AttributePicker } from '../components/AttributePicker'
import { TagInput } from '../components/TagInput'
import { useCurrentUser } from '../lib/useCurrentUser'
import { useAttributeLabels } from '../i18n/useAttributeLabels'
import { useTranslation } from '../i18n/context'
import { useErrorText } from '../i18n/useErrorText'

const LEVELS = ['Junior', 'Middle', 'Senior', 'C-level']

const BLANK: PositionInput = {
  title: '',
  shortDescription: '',
  company: '',
  level: '',
  public: true,
  maxProjects: 3,
  attributes: [],
  accessRules: [],
  projectTags: [],
}

/**
 * Создание и редактирование позиции.
 *
 * Владения позицией нет — по заданию любой рекрутер правит любую, — поэтому
 * здесь нет проверки автора, только роль.
 */
export function PositionEditPage() {
  if (!tokenStorage.isValid()) {
    return <Navigate to="/login" replace />
  }

  return <EditorGate />
}

function EditorGate() {
  const t = useTranslation()
  const { isRecruiter, loading } = useCurrentUser()

  if (loading) {
    return (
      <>
        <AppHeader />
        <main className="page">
          <p className="muted" role="status">
            {t('common.loading')}
          </p>
        </main>
      </>
    )
  }

  if (!isRecruiter) {
    return <Navigate to="/positions" replace />
  }

  return <PositionEditor />
}

function PositionEditor() {
  const t = useTranslation()
  const errorText = useErrorText()
  const { categoryLabel, typeLabel } = useAttributeLabels()
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()
  const isNew = id === undefined

  const [form, setForm] = useState<PositionInput>(BLANK)
  const [attributes, setAttributes] = useState<TemplateAttribute[]>([])
  const [library, setLibrary] = useState<LibraryAttribute[]>([])
  const [operators, setOperators] = useState<Record<AttributeType, FilterOperator[]>>(
    {} as Record<AttributeType, FilterOperator[]>,
  )
  const [rules, setRules] = useState<AccessRule[]>([])
  const [version, setVersion] = useState<number | undefined>()
  const [picking, setPicking] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)
  const [loaded, setLoaded] = useState(isNew)

  // Библиотека и список операторов нужны всегда: по ним строятся и шаблон,
  // и правила доступа.
  useEffect(() => {
    let active = true

    Promise.all([libraryApi.attributes(), positionAdminApi.operators()])
      .then(([lib, ops]) => {
        if (active) {
          setLibrary(lib.items)
          setOperators(ops.operators)
        }
      })
      .catch(() => {
        if (active) {
          setError(t('edit.libraryFailed'))
        }
      })

    return () => {
      active = false
    }
  }, [t])

  useEffect(() => {
    if (isNew) {
      return
    }

    let active = true

    positionAdminApi
      .edit(Number(id))
      .then((position) => {
        if (!active) {
          return
        }

        setForm({
          title: position.title,
          shortDescription: position.shortDescription ?? '',
          company: position.company ?? '',
          level: position.level ?? '',
          public: position.public,
          maxProjects: position.maxProjects,
          attributes: [],
          accessRules: [],
          projectTags: position.projectTags,
        })
        setAttributes(position.attributes)
        setRules(position.accessRules)
        setVersion(position.version)
        setLoaded(true)
      })
      .catch((requestError: unknown) => {
        if (active) {
          setError(
            errorText(requestError, 'position.notFound'),
          )
          setLoaded(true)
        }
      })

    return () => {
      active = false
    }
  }, [id, isNew, errorText])

  const move = (index: number, delta: number) => {
    const next = [...attributes]
    const target = index + delta

    if (target < 0 || target >= next.length) {
      return
    }

    ;[next[index], next[target]] = [next[target], next[index]]
    setAttributes(next.map((attribute, i) => ({ ...attribute, sortOrder: i })))
  }

  const submit = useCallback(async () => {
    setBusy(true)
    setError(null)

    const payload: PositionInput = {
      ...form,
      shortDescription: form.shortDescription?.trim() || null,
      company: form.company?.trim() || null,
      level: form.level?.trim() || null,
      attributes: attributes.map((attribute, index) => ({
        attributeId: attribute.attributeId,
        required: attribute.required,
        section: attribute.section,
        sortOrder: index,
      })),
      accessRules: rules.map((rule) => ({
        attributeId: rule.attributeId,
        operator: rule.operator,
        value: rule.value,
      })),
      version,
    }

    try {
      const saved = isNew
        ? await positionAdminApi.create(payload)
        : await positionAdminApi.update(Number(id), payload)

      navigate(`/positions/${saved.id}`)
    } catch (requestError: unknown) {
      setError(
        errorText(requestError, 'edit.saveFailed'),
      )
      setBusy(false)
    }
  }, [attributes, form, id, isNew, navigate, rules, version, errorText])

  const remove = async () => {
    if (isNew) {
      return
    }

    setBusy(true)

    try {
      await positionAdminApi.remove(Number(id))
      navigate('/positions')
    } catch (requestError: unknown) {
      setError(
        errorText(requestError, 'edit.deleteFailed'),
      )
      setBusy(false)
    }
  }

  if (!loaded) {
    return (
      <>
        <AppHeader />
        <main className="page">
          <p className="muted" role="status">
            {t('common.loading')}
          </p>
        </main>
      </>
    )
  }

  const ownedIds = new Set(attributes.map((attribute) => attribute.attributeId))

  return (
    <>
      <AppHeader />

      <main className="page">
        <Link to={isNew ? '/positions' : `/positions/${id}`} className="backlink">
          <CaretLeftIcon size={14} aria-hidden="true" />
          {t(isNew ? 'edit.toCatalogue' : 'edit.toPosition')}
        </Link>

        <div className="panel__head">
          <h1 className="h1">{t(isNew ? 'edit.newTitle' : 'edit.editTitle')}</h1>

          {!isNew && (
            <DangerButton busy={busy} onConfirm={remove} label={t('edit.deletePosition')} />
          )}
        </div>

        {error && (
          <div className="notice notice--error" role="alert">
            <span>{error}</span>
          </div>
        )}

        <section className="panel">
          <h2 className="h2">{t('edit.basics')}</h2>

          <div className="field">
            <label className="label" htmlFor="pos-title">
              {t('edit.name')}
            </label>
            <input
              id="pos-title"
              className="input"
              required
              maxLength={180}
              value={form.title}
              onChange={(event) => setForm({ ...form, title: event.target.value })}
            />
          </div>

          <div className="period">
            <div className="field">
              <label className="label" htmlFor="pos-company">
                {t('edit.company')}
              </label>
              <input
                id="pos-company"
                className="input"
                value={form.company ?? ''}
                onChange={(event) => setForm({ ...form, company: event.target.value })}
              />
            </div>

            <div className="field">
              <label className="label" htmlFor="pos-level">
                {t('edit.level')}
              </label>
              <select
                id="pos-level"
                className="input"
                value={form.level ?? ''}
                onChange={(event) => setForm({ ...form, level: event.target.value })}
              >
                <option value="">{t('edit.levelNone')}</option>
                {LEVELS.map((level) => (
                  <option key={level} value={level}>
                    {level}
                  </option>
                ))}
              </select>
            </div>
          </div>

          <div className="field">
            <label className="label" htmlFor="pos-description">
              {t('edit.shortDescription')}
            </label>
            <textarea
              id="pos-description"
              className="input input--area"
              rows={4}
              value={form.shortDescription ?? ''}
              onChange={(event) => setForm({ ...form, shortDescription: event.target.value })}
            />
          </div>
        </section>

        <section className="panel">
          <div className="panel__head">
            <div>
              <h2 className="h2">{t('edit.template')}</h2>
              <p className="panel__hint muted-3">
                {t('edit.templateHint')}
              </p>
            </div>

            {!picking && (
              <button type="button" className="btn btn--outline" onClick={() => setPicking(true)}>
                <PlusIcon size={14} aria-hidden="true" />
                {t('edit.addField')}
              </button>
            )}
          </div>

          {picking && (
            <AttributePicker
              ownedIds={ownedIds}
              onClose={() => setPicking(false)}
              onPick={(attribute) => {
                setPicking(false)
                setAttributes((current) => [
                  ...current,
                  {
                    attributeId: attribute.id,
                    name: attribute.name,
                    category: attribute.category,
                    type: attribute.type,
                    options: attribute.options,
                    required: false,
                    section: null,
                    sortOrder: current.length,
                  },
                ])
              }}
            />
          )}

          {attributes.length === 0 ? (
            <p className="muted table__empty">{t('edit.noFields')}</p>
          ) : (
            <div className="col g2">
              {attributes.map((attribute, index) => (
                <div className="tmplrow" key={attribute.attributeId}>
                  <div className="col g1">
                    <span className="picker__name">{attribute.name}</span>
                    <span className="t-xs muted-3">
                      {categoryLabel(attribute.category)} ·{' '}
                      {typeLabel(attribute.type)}
                    </span>
                  </div>

                  <label className="checkline t-sm">
                    <input
                      type="checkbox"
                      checked={attribute.required}
                      onChange={(event) =>
                        setAttributes((current) =>
                          current.map((item, i) =>
                            i === index ? { ...item, required: event.target.checked } : item,
                          ),
                        )
                      }
                    />
                    {t('edit.required')}
                  </label>

                  <div className="row g1">
                    <button
                      type="button"
                      className="attr__remove"
                      onClick={() => move(index, -1)}
                      disabled={index === 0}
                      aria-label={t('edit.moveUp')}
                    >
                      <ArrowUpIcon size={13} aria-hidden="true" />
                    </button>
                    <button
                      type="button"
                      className="attr__remove"
                      onClick={() => move(index, 1)}
                      disabled={index === attributes.length - 1}
                      aria-label={t('edit.moveDown')}
                    >
                      <ArrowDownIcon size={13} aria-hidden="true" />
                    </button>
                    <button
                      type="button"
                      className="attr__remove"
                      onClick={() =>
                        setAttributes((current) => current.filter((_, i) => i !== index))
                      }
                      aria-label={t('edit.removeField', { name: attribute.name })}
                    >
                      <TrashIcon size={13} aria-hidden="true" />
                    </button>
                  </div>
                </div>
              ))}
            </div>
          )}
        </section>

        <section className="panel">
          <div className="panel__head">
            <div>
              <h2 className="h2">{t('edit.access')}</h2>
              <p className="panel__hint muted-3">
                {t('edit.accessHint')}
              </p>
            </div>
          </div>

          <label className="checkline">
            <input
              type="checkbox"
              checked={form.public}
              onChange={(event) => setForm({ ...form, public: event.target.checked })}
            />
            <span className="t-sm">
              {t('edit.publicLabel')}
            </span>
          </label>

          {!form.public && (
            <>
              <p className="section__title">{t('edit.rulesTitle')}</p>
              <AccessRuleEditor
                rules={rules}
                attributes={library}
                operators={operators}
                onChange={setRules}
              />
            </>
          )}
        </section>

        <section className="panel">
          <div className="panel__head">
            <div>
              <h2 className="h2">{t('edit.projectsTitle')}</h2>
              <p className="panel__hint muted-3">
                {t('edit.projectsHint')}
              </p>
            </div>
          </div>

          <div className="field">
            <span className="label">{t('edit.projectTags')}</span>
            <TagInput
              tags={form.projectTags}
              onChange={(projectTags) => setForm({ ...form, projectTags })}
            />
          </div>

          <div className="field" style={{ maxWidth: 220 }}>
            <label className="label" htmlFor="pos-max">
              {t('edit.maxProjects')}
            </label>
            <input
              id="pos-max"
              type="number"
              min={0}
              max={50}
              className="input"
              value={form.maxProjects}
              onChange={(event) =>
                setForm({ ...form, maxProjects: Number(event.target.value) || 0 })
              }
            />
          </div>
        </section>

        <div className="row g3">
          <button
            type="button"
            className="btn btn--primary btn--lg"
            disabled={busy || form.title.trim() === ''}
            onClick={() => void submit()}
          >
            {t(busy ? 'edit.saving' : 'common.save')}
          </button>

          <Link to={isNew ? '/positions' : `/positions/${id}`} className="btn btn--ghost btn--lg">
            {t('common.cancel')}
          </Link>
        </div>
      </main>
    </>
  )
}

/** Удаление в два шага: позиция уносит с собой резюме и обсуждение. */
function DangerButton({
  busy,
  onConfirm,
  label,
}: {
  busy: boolean
  onConfirm: () => void
  label: string
}) {
  const t = useTranslation()
  const [confirming, setConfirming] = useState(false)

  if (!confirming) {
    return (
      <button type="button" className="btn btn--outline" onClick={() => setConfirming(true)}>
        <TrashIcon size={14} aria-hidden="true" />
        {label}
      </button>
    )
  }

  return (
    <div className="notice notice--error" role="alert">
      <span>{t('edit.confirmDelete')}</span>
      <div className="row g2">
        <button type="button" className="btn btn--ghost" onClick={() => setConfirming(false)}>
          {t('common.cancel')}
        </button>
        <button type="button" className="btn btn--primary" disabled={busy} onClick={onConfirm}>
          {t('edit.delete')}
        </button>
      </div>
    </div>
  )
}
