import { CaretLeftIcon, CopyIcon, LockSimpleIcon, PencilSimpleIcon } from '@phosphor-icons/react'
import { useEffect, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { catalogApi, cvApi, positionAdminApi, RequestError, tokenStorage } from '../api/client'
import type { CvRow, PositionAttribute, PositionDetail } from '../api/types'
import { AppHeader } from '../components/AppHeader'
import { CvTable } from '../components/CvTable'
import { DiscussionPanel } from '../components/DiscussionPanel'
import { useCurrentUser } from '../lib/useCurrentUser'
import { useAttributeLabels } from '../i18n/useAttributeLabels'
import { useTranslation } from '../i18n/context'
import { useDateFormat } from '../i18n/useDateFormat'
import { useErrorText } from '../i18n/useErrorText'

type State =
  | { kind: 'loading' }
  | { kind: 'ready'; position: PositionDetail }
  | { kind: 'error'; message: string }

/**
 * Позиция в режиме чтения — то, что доступно гостю.
 *
 * Показываем структуру шаблона: какие поля попадут в резюме. Сами резюме,
 * обсуждение и правила доступа остаются за входом, поэтому здесь их нет.
 */
export function PositionDetailPage() {
  const { id } = useParams<{ id: string }>()

  // key на id: переход с одной позиции на другую должен начинаться с чистого
  // состояния, иначе на экране на мгновение осталась бы предыдущая позиция.
  // Сброс через эффект стоил бы лишнего рендера.
  return <PositionView key={id} id={id} />
}

function PositionView({ id }: { id: string | undefined }) {
  const t = useTranslation()
  const errorText = useErrorText()
  const numericId = Number(id)
  const valid = Number.isInteger(numericId) && numericId > 0

  const [state, setState] = useState<State>(() =>
    valid ? { kind: 'loading' } : { kind: 'error', message: t('position.notFound') },
  )
  const authenticated = tokenStorage.isValid()

  useEffect(() => {
    if (!valid) {
      return
    }

    let active = true

    catalogApi
      .position(numericId)
      .then((position) => {
        if (active) {
          setState({ kind: 'ready', position })
        }
      })
      .catch((error: unknown) => {
        if (active) {
          setState({
            kind: 'error',
            message:
              error instanceof RequestError && error.code === 'http_error'
                ? t('position.notFound')
                : errorText(error, 'common.unexpectedError')
          })
        }
      })

    return () => {
      active = false
    }
  }, [numericId, valid, errorText, t])

  return (
    <>
      <AppHeader />

      <main className="page">
        <Link to="/positions" className="backlink">
          <CaretLeftIcon size={14} aria-hidden="true" />
          {t('position.allPositions')}
        </Link>

        {state.kind === 'loading' && (
          <p className="muted" role="status">
            {t('common.loading')}
          </p>
        )}

        {state.kind === 'error' && (
          <div className="notice notice--error" role="alert">
            <span>{state.message}</span>
          </div>
        )}

        {state.kind === 'ready' && (
          <PositionBody position={state.position} authenticated={authenticated} />
        )}

      </main>
    </>
  )
}

function PositionBody({
  position,
  authenticated,
}: {
  position: PositionDetail
  authenticated: boolean
}) {
  const t = useTranslation()
  const formatDate = useDateFormat()
  const { categoryLabel } = useAttributeLabels()
  // Группируем по секции: сгенерированное резюме будет разбито так же,
  // и структура шаблона должна быть видна заранее.
  const sections = new Map<string, PositionAttribute[]>()

  for (const attribute of position.attributes) {
    const key = attribute.section ?? attribute.category
    sections.set(key, [...(sections.get(key) ?? []), attribute])
  }

  return (
    <div className="col g6">
      <section className="panel">
        <div className="panel__head">
          <div className="col g2">
            <h1 className="h1">{position.title}</h1>
            <p className="muted" style={{ margin: 0 }}>
              {position.company ?? t('position.noCompany')}
              {position.level && ` · ${position.level}`}
            </p>
          </div>

          <div className="row g2">
            {!position.public && (
              <span className="chip chip--muted">
                <LockSimpleIcon size={12} aria-hidden="true" />
                {t('position.restricted')}
              </span>
            )}

            <PositionActions position={position} authenticated={authenticated} />
          </div>
        </div>

        {position.shortDescription && <p className="prose">{position.shortDescription}</p>}

        {position.projectTags.length > 0 && (
          <div className="cloud">
            {position.projectTags.map((tag) => (
              <span key={tag.id} className="chip">
                {tag.name}
              </span>
            ))}
          </div>
        )}

        <p className="t-xs muted-3" style={{ margin: 0 }}>
          {t('position.updatedMeta', {
            date: formatDate(position.updatedAt),
            count: position.maxProjects,
          })}
        </p>
      </section>

      <section className="panel">
        <div className="panel__head">
          <div>
            <h2 className="h2">{t('position.templateTitle')}</h2>
          </div>
        </div>

        {position.attributes.length === 0 ? (
          <p className="muted table__empty">{t('position.noFields')}</p>
        ) : (
          <div className="col g4">
            {[...sections].map(([section, attributes]) => (
              <div key={section} className="col g2">
                <h3 className="section__title">{categoryLabel(section)}</h3>

                {/* Тип поля и слово «обязательное» кандидату ничего не
                    говорят: он видит готовую анкету, а не её схему. Остаётся
                    название, а обязательность — привычной звёздочкой. */}
                <ul className="fieldlist">
                  {attributes.map((attribute) => (
                    <li key={attribute.id} className="fieldlist__item">
                      {attribute.name}
                      {attribute.required && (
                        <abbr className="fieldlist__req" title={t('position.requiredTitle')}>
                          *
                        </abbr>
                      )}
                    </li>
                  ))}
                </ul>
              </div>
            ))}
          </div>
        )}
      </section>

      {!authenticated && (
        <div className="notice">
          <span>
            {t('position.signInPrompt')}
            <Link to="/login">{t('position.signInLink')}</Link>.
          </span>
        </div>
      )}

      {authenticated && <PositionTabs positionId={position.id} />}
    </div>
  )
}

/**
 * Панель действий над позицией.
 *
 * Кнопки живут здесь, а не в строках таблицы каталога: за кнопки в строках
 * задание снимает оценку.
 */
function PositionActions({
  position,
  authenticated,
}: {
  position: PositionDetail
  authenticated: boolean
}) {
  const t = useTranslation()
  const errorText = useErrorText()
  const navigate = useNavigate()
  const { isRecruiter, isCandidate } = useCurrentUser()
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)

  if (!authenticated) {
    return null
  }

  const duplicate = async () => {
    setBusy(true)

    try {
      const copy = await positionAdminApi.duplicate(position.id)
      navigate(`/positions/${copy.id}/edit`)
    } catch (requestError: unknown) {
      setError(
        errorText(requestError, 'position.copyFailed'),
      )
      setBusy(false)
    }
  }

  const apply = async () => {
    setBusy(true)
    setError(null)

    try {
      const cv = await cvApi.start(position.id)
      navigate(`/cvs/${cv.id}`)
    } catch (requestError: unknown) {
      setError(
        errorText(requestError, 'position.createCvFailed')
      )
      setBusy(false)
    }
  }

  return (
    <div className="col g2" style={{ alignItems: 'flex-end' }}>
      <div className="row g2">
        {isRecruiter && (
          <>
            <Link to={`/positions/${position.id}/edit`} className="btn btn--outline">
              <PencilSimpleIcon size={14} aria-hidden="true" />
              {t('position.edit')}
            </Link>
            <button
              type="button"
              className="btn btn--outline"
              onClick={() => void duplicate()}
              disabled={busy}
            >
              <CopyIcon size={14} aria-hidden="true" />
              {t('position.duplicate')}
            </button>
          </>
        )}

        {isCandidate && !isRecruiter && (
          <button
            type="button"
            className="btn btn--primary"
            onClick={() => void apply()}
            disabled={busy}
          >
            {t(busy ? 'position.creating' : 'position.composeCv')}
          </button>
        )}
      </div>

      {error && (
        <span className="field__error" role="alert">
          {error}
        </span>
      )}
    </div>
  )
}

/**
 * Вкладки позиции: обсуждение доступно всем вошедшим, список резюме — только
 * рекрутерам и админам.
 */
function PositionTabs({ positionId }: { positionId: number }) {
  const t = useTranslation()
  const { isRecruiter } = useCurrentUser()
  const [tab, setTab] = useState<'discussion' | 'cvs'>('discussion')
  const [cvs, setCvs] = useState<CvRow[] | null>(null)

  useEffect(() => {
    if (tab !== 'cvs' || !isRecruiter) {
      return
    }

    let active = true

    positionAdminApi
      .cvs(positionId)
      .then((result) => {
        if (active) {
          setCvs(result.items)
        }
      })
      .catch(() => {
        if (active) {
          setCvs([])
        }
      })

    return () => {
      active = false
    }
  }, [tab, isRecruiter, positionId])

  return (
    <section className="panel">
      <div className="authtabs" role="tablist">
        <button
          type="button"
          role="tab"
          aria-selected={tab === 'discussion'}
          className={`authtabs__btn${tab === 'discussion' ? ' is-on' : ''}`}
          onClick={() => setTab('discussion')}
        >
          {t('position.discussion')}
        </button>

        {isRecruiter && (
          <button
            type="button"
            role="tab"
            aria-selected={tab === 'cvs'}
            className={`authtabs__btn${tab === 'cvs' ? ' is-on' : ''}`}
            onClick={() => setTab('cvs')}
          >
            {t('position.cvs')}
          </button>
        )}
      </div>

      {tab === 'discussion' ? (
        <DiscussionPanel positionId={positionId} />
      ) : cvs === null ? (
        <p className="muted table__empty">{t('common.loading')}</p>
      ) : (
        <CvTable
          rows={cvs}
          showPosition={false}
          emptyMessage={t('position.noCvs')}
        />
      )}
    </section>
  )
}
