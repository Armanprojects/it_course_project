import { CaretLeftIcon, HeartIcon, HeartStraightIcon } from '@phosphor-icons/react'
import { lazy, Suspense, useEffect, useState } from 'react'
import { Link, Navigate, useParams } from 'react-router-dom'
import { cvApi, RequestError, tokenStorage } from '../api/client'
import type { AttributeValue, CvDetail, CvSectionAttribute } from '../api/types'
import { AppHeader } from '../components/AppHeader'
import { CATEGORY_LABELS } from '../lib/attributeLabels'

const Markdown = lazy(() => import('react-markdown'))

const dateFormat = new Intl.DateTimeFormat('ru-RU', { dateStyle: 'medium' })

/**
 * Сгенерированное резюме.
 *
 * Значения не хранятся в резюме — они читаются из профиля кандидата через
 * шаблон позиции. Рекрутер видит страницу только для чтения, владелец может
 * публиковать и снимать с публикации.
 */
export function CvPage() {
  if (!tokenStorage.get()) {
    return <Navigate to="/login" replace />
  }

  return <CvView />
}

function CvView() {
  const { id } = useParams<{ id: string }>()
  const [cv, setCv] = useState<CvDetail | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)

  useEffect(() => {
    let active = true

    cvApi
      .show(Number(id))
      .then((loaded) => {
        if (active) {
          setCv(loaded)
        }
      })
      .catch((requestError: unknown) => {
        if (active) {
          setError(
            requestError instanceof RequestError ? requestError.message : 'Резюме недоступно.',
          )
        }
      })

    return () => {
      active = false
    }
  }, [id])

  const toggleLike = async () => {
    if (cv === null) {
      return
    }

    setBusy(true)

    try {
      const result = cv.likedByMe
        ? await cvApi.unlike(cv.id)
        : await cvApi.like(cv.id)

      setCv({ ...cv, likesCount: result.likesCount, likedByMe: result.likedByMe })
    } finally {
      setBusy(false)
    }
  }

  const togglePublish = async () => {
    if (cv === null) {
      return
    }

    setBusy(true)
    setError(null)

    try {
      setCv(cv.status === 'published' ? await cvApi.unpublish(cv.id) : await cvApi.publish(cv.id))
    } catch (requestError: unknown) {
      setError(
        requestError instanceof RequestError ? requestError.message : 'Не удалось изменить статус.',
      )
    } finally {
      setBusy(false)
    }
  }

  if (error !== null && cv === null) {
    return (
      <>
        <AppHeader />
        <main className="page">
          <div className="notice notice--error" role="alert">
            <span>{error}</span>
          </div>
        </main>
      </>
    )
  }

  if (cv === null) {
    return (
      <>
        <AppHeader />
        <main className="page">
          <p className="muted" role="status">
            Загружаем…
          </p>
        </main>
      </>
    )
  }

  return (
    <>
      <AppHeader />

      <main className="page">
        <Link to={`/positions/${cv.position.id}`} className="backlink">
          <CaretLeftIcon size={14} aria-hidden="true" />
          {cv.position.title}
        </Link>

        {error && (
          <div className="notice notice--error" role="alert">
            <span>{error}</span>
          </div>
        )}

        <section className="panel cvsheet">
          <div className="panel__head">
            <div className="col g1">
              <h1 className="h1">{cv.candidate.name}</h1>
              <p className="muted" style={{ margin: 0 }}>
                {cv.position.title}
                {cv.position.company && ` · ${cv.position.company}`}
              </p>
            </div>

            <div className="row g2">
              <span className={`chip${cv.status === 'published' ? ' chip--ok' : ''}`}>
                {cv.status === 'published' ? 'Опубликовано' : 'Черновик'}
              </span>

              {/* Лайкать могут только рекрутеры — сервер решает, клиент лишь
                  не показывает кнопку остальным. */}
              {cv.canLike && (
                <button
                  type="button"
                  className={`likebtn${cv.likedByMe ? ' is-on' : ''}`}
                  onClick={() => void toggleLike()}
                  disabled={busy}
                  aria-pressed={cv.likedByMe}
                  aria-label={cv.likedByMe ? 'Убрать лайк' : 'Поставить лайк'}
                >
                  {cv.likedByMe ? (
                    <HeartIcon size={15} weight="fill" aria-hidden="true" />
                  ) : (
                    <HeartStraightIcon size={15} aria-hidden="true" />
                  )}
                  {cv.likesCount}
                </button>
              )}
            </div>
          </div>

          {cv.missing.length > 0 && (
            <div className="notice notice--error">
              <span>Не заполнено: {cv.missing.join(', ')}. Пока резюме нельзя опубликовать.</span>
            </div>
          )}

          {cv.sections.map((section) => (
            <div key={section.section} className="col g2">
              <h2 className="section__title">
                {CATEGORY_LABELS[section.section] ?? section.section}
              </h2>

              <dl className="cvsheet__grid">
                {section.attributes.map((attribute) => (
                  <CvValue key={attribute.attributeId} attribute={attribute} />
                ))}
              </dl>
            </div>
          ))}

          {cv.projects.length > 0 && (
            <div className="col g3">
              <h2 className="section__title">Проекты</h2>

              {cv.projects.map((project) => (
                <article key={project.id} className="col g2">
                  <div className="col g1">
                    <h3 className="project__title">{project.name}</h3>
                    {(project.periodFrom || project.periodTo) && (
                      <span className="t-xs muted-3">
                        {project.periodFrom
                          ? dateFormat.format(new Date(project.periodFrom))
                          : '…'}{' '}
                        —{' '}
                        {project.periodTo
                          ? dateFormat.format(new Date(project.periodTo))
                          : 'по настоящее время'}
                      </span>
                    )}
                  </div>

                  {project.description && (
                    <div className="prose prose--md">
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
              ))}
            </div>
          )}
        </section>

        {/* Публикация — действие владельца (и админа от его имени). Сервер
            присылает canEdit отдельно от canLike: у админа есть оба права. */}
        {cv.canEdit && (
          <div className="row g3">
            <button
              type="button"
              className="btn btn--primary"
              disabled={busy || (cv.status !== 'published' && !cv.complete)}
              onClick={() => void togglePublish()}
            >
              {cv.status === 'published' ? 'Снять с публикации' : 'Опубликовать'}
            </button>

            <Link to="/profile" className="btn btn--ghost">
              Заполнить профиль
            </Link>
          </div>
        )}
      </main>
    </>
  )
}

/** Пустое значение подсвечиваем красным — прямое требование задания. */
function CvValue({ attribute }: { attribute: CvSectionAttribute }) {
  return (
    <div className={`cvsheet__row${attribute.empty ? ' is-empty' : ''}`}>
      <dt className="label">{attribute.name}</dt>
      <dd className="cvsheet__value">
        {attribute.empty ? (
          <span className="cvsheet__blank">не заполнено</span>
        ) : (
          renderValue(attribute.value, attribute.type)
        )}
      </dd>
    </div>
  )
}

function renderValue(value: AttributeValue, type: string) {
  if (value === null) {
    return null
  }

  if (type === 'boolean') {
    return value === true ? 'да' : 'нет'
  }

  if (type === 'image' && typeof value === 'string') {
    return <img className="attr__preview" src={value} alt="" />
  }

  if (type === 'period' && typeof value === 'object') {
    const period = value as { from: string | null; to: string | null }

    return `${period.from ?? '…'} — ${period.to ?? 'по настоящее время'}`
  }

  if (type === 'numeric' && typeof value === 'string') {
    // decimal(20,6) приходит строкой — незначащие нули не показываем.
    return value.includes('.') ? value.replace(/\.?0+$/, '') : value
  }

  return String(value)
}
