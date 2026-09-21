import {
  CaretLeftIcon,
  FilePdfIcon,
  HeartIcon,
  HeartStraightIcon,
  PencilSimpleIcon,
} from '@phosphor-icons/react'
import { lazy, Suspense, useEffect, useState } from 'react'
import { Link, Navigate, useParams } from 'react-router-dom'
import { cvApi, RequestError, tokenStorage } from '../api/client'
import type { AttributeValue, CvDetail, CvSectionAttribute } from '../api/types'
import { AppHeader } from '../components/AppHeader'
import { AttributeInput } from '../components/AttributeField'
import { useAttributeLabels } from '../i18n/useAttributeLabels'
import { useTranslation } from '../i18n/context'
import type { MessageKey } from '../i18n/messages'
import { useDateFormat } from '../i18n/useDateFormat'
import { useErrorText } from '../i18n/useErrorText'

const Markdown = lazy(() => import('react-markdown'))

type Translate = (key: MessageKey, params?: Record<string, string | number>) => string

export function CvPage() {
  if (!tokenStorage.isValid()) {
    return <Navigate to="/login" replace />
  }

  return <CvView />
}

function CvView() {
  const t = useTranslation()
  const errorText = useErrorText()
  const formatDate = useDateFormat()
  const { categoryLabel } = useAttributeLabels()
  const { id } = useParams<{ id: string }>()
  const [cv, setCv] = useState<CvDetail | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)
  const [downloading, setDownloading] = useState(false)

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
            errorText(requestError, 'cv.unavailableShort'),
          )
        }
      })

    return () => {
      active = false
    }
  }, [id, errorText])

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

  const saveAttribute = async (attributeId: number, value: AttributeValue) => {
    if (cv === null) {
      return
    }

    const previous = cv

    setCv(applyAttribute(cv, attributeId, value))
    setBusy(true)
    setError(null)

    try {
      setCv(await cvApi.editAttribute(cv.id, attributeId, value, cv.profileVersion))
    } catch (requestError: unknown) {
      if (requestError instanceof RequestError && requestError.isConflict) {
        setError(t('cv.conflict'))
        setCv(await cvApi.show(cv.id))
      } else {
        setError(
          errorText(requestError, 'cv.saveFailed'),
        )
        setCv(previous)
      }
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
        errorText(requestError, 'cv.statusFailed'),
      )
    } finally {
      setBusy(false)
    }
  }

  const downloadPdf = async () => {
    if (cv === null) {
      return
    }

    setDownloading(true)
    setError(null)

    try {
      await cvApi.pdf(cv.id)
    } catch (requestError: unknown) {
      setError(errorText(requestError, 'cv.pdfFailed'))
    } finally {
      setDownloading(false)
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
            {t('common.loading')}
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
                {t(cv.status === 'published' ? 'cv.published' : 'cv.draft')}
              </span>

              {cv.canLike && (
                <button
                  type="button"
                  className={`likebtn${cv.likedByMe ? ' is-on' : ''}`}
                  onClick={() => void toggleLike()}
                  disabled={busy}
                  aria-pressed={cv.likedByMe}
                  aria-label={t(cv.likedByMe ? 'cv.unlike' : 'cv.like')}
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
              <span>{t('cv.notFilledMissing', { names: cv.missing.join(', ') })}</span>

            </div>

          )}

          {cv.sections.map((section) => {
            const photo = section.attributes.find((attribute) => attribute.type === 'image')
            const rest = photo
              ? section.attributes.filter((attribute) => attribute !== photo)
              : section.attributes

            const fields = (
              <dl className="cvsheet__grid">
                {rest.map((attribute) => (
                  <CvValue
                    key={attribute.attributeId}
                    attribute={attribute}
                    editable={cv.canEdit}
                    busy={busy}
                    onSave={(value) => saveAttribute(attribute.attributeId, value)}
                  />

                ))}
              </dl>

            )

            return (
              <div key={section.section} className="col g2">
                <h2 className="section__title">
                  {categoryLabel(section.section)}
                </h2>

                {photo ? (
                  <div className="cvsheet__withphoto">
                    <dl className="cvsheet__photo">
                      <CvValue
                        attribute={photo}
                        editable={cv.canEdit}
                        busy={busy}
                        onSave={(value) => saveAttribute(photo.attributeId, value)}
                      />

                    </dl>

                    {fields}
                  </div>

                ) : (
                  fields
                )}
              </div>

            )
          })}

          {cv.projects.length > 0 && (
            <div className="col g3">
              <h2 className="section__title">{t('cv.projects')}</h2>

              {cv.projects.map((project) => (
                <article key={project.id} className="col g2">
                  <div className="col g1">
                    <h3 className="project__title">{project.name}</h3>

                    {(project.periodFrom || project.periodTo) && (
                      <span className="t-xs muted-3">
                        {project.periodFrom
                          ? formatDate(project.periodFrom)
                          : '…'}{' '}
                        —{' '}
                        {project.periodTo
                          ? formatDate(project.periodTo)
                          : t('cv.ongoing')}
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

        <div className="row g3">
          {cv.canEdit && (
            <>
              <button
                type="button"
                className="btn btn--primary"
                disabled={busy || (cv.status !== 'published' && !cv.complete)}
                onClick={() => void togglePublish()}
              >
                {t(cv.status === 'published' ? 'cv.unpublish' : 'cv.publish')}
              </button>

              <Link to="/profile" className="btn btn--ghost">
                {t('cv.fillProfile')}
              </Link>

            </>

          )}

          <button
            type="button"
            className="btn btn--outline"
            disabled={downloading}
            onClick={() => void downloadPdf()}
          >
            <FilePdfIcon size={14} aria-hidden="true" />
            {t('cv.downloadPdf')}
          </button>

        </div>

      </main>

    </>

  )
}

function isValueEmpty(value: AttributeValue): boolean {
  if (value === null || value === undefined || value === '') {
    return true
  }

  if (typeof value === 'object') {
    const period = value as { from: string | null; to: string | null }

    return !period.from && !period.to
  }

  return false
}

function applyAttribute(cv: CvDetail, attributeId: number, value: AttributeValue): CvDetail {
  let name: string | null = null

  const sections = cv.sections.map((section) => ({
    ...section,
    attributes: section.attributes.map((attribute) => {
      if (attribute.attributeId !== attributeId) {
        return attribute
      }

      name = attribute.name

      return { ...attribute, value, empty: isValueEmpty(value) }
    }),
  }))

  const missing = isValueEmpty(value)
    ? cv.missing
    : cv.missing.filter((item) => item !== name)

  return { ...cv, sections, missing, complete: missing.length === 0 }
}

function CvValue({
  attribute,
  editable,
  busy,
  onSave,
}: {
  attribute: CvSectionAttribute
  editable: boolean
  busy: boolean
  onSave: (value: AttributeValue) => void | Promise<void>
}) {
  const t = useTranslation()
  const [editing, setEditing] = useState(false)
  const [draft, setDraft] = useState<AttributeValue>(attribute.value)

  const open = () => {
    setDraft(attribute.value)
    setEditing(true)
  }

  const commit = () => {
    setEditing(false)

    if (draft !== attribute.value) {
      void onSave(draft)
    }
  }

  if (editing) {
    return (
      <div className={`cvsheet__row is-editing${attribute.empty ? ' is-empty' : ''}`}>
        <dt className="label" id={`cvattr-${attribute.attributeId}-label`}>
          {attribute.name}
        </dt>

        <dd className="cvsheet__value">
          <AttributeInput
            id={`cvattr-${attribute.attributeId}`}
            attribute={attribute}
            value={draft}
            onChange={setDraft}
            autoFocus
            onBlur={
              attribute.type === 'image' || attribute.type === 'period' ? undefined : commit
            }
          />

          {(attribute.type === 'image' || attribute.type === 'period') && (
            <div className="row g2" style={{ marginTop: 'var(--s2)' }}>
              <button type="button" className="btn btn--primary" onClick={commit} disabled={busy}>
                {t('common.save')}
              </button>

              <button type="button" className="btn btn--ghost" onClick={() => setEditing(false)}>
                {t('common.cancel')}
              </button>

            </div>

          )}
        </dd>

      </div>

    )
  }

  return (
    <div className={`cvsheet__row${attribute.empty ? ' is-empty' : ''}`}>
      <dt className="label">{attribute.name}</dt>

      <dd className="cvsheet__value">
        {editable ? (
          <button
            type="button"
            className="cvsheet__edit"
            onClick={open}
            disabled={busy}
            aria-label={t('cv.editAttribute', { name: attribute.name })}
          >
            {attribute.empty ? (
              <span className="cvsheet__blank">{t('common.notFilled')}</span>

            ) : (
              renderValue(attribute.value, attribute.type, t)
            )}
            <PencilSimpleIcon size={13} aria-hidden="true" className="cvsheet__pencil" />
          </button>

        ) : attribute.empty ? (
          <span className="cvsheet__blank">{t('common.notFilled')}</span>

        ) : (
          renderValue(attribute.value, attribute.type, t)
        )}
      </dd>

    </div>

  )
}

function renderValue(value: AttributeValue, type: string, t: Translate) {
  if (value === null) {
    return null
  }

  if (type === 'boolean') {
    return t(value === true ? 'common.yes' : 'common.no')
  }

  if (type === 'image' && typeof value === 'string') {
    return <img className="attr__preview" src={value} alt="" />
  }

  if (type === 'period' && typeof value === 'object') {
    const period = value as { from: string | null; to: string | null }

    return `${period.from ?? '…'} — ${period.to ?? t('cv.ongoing')}`
  }

  if (type === 'numeric' && typeof value === 'string') {
    return value.includes('.') ? value.replace(/\.?0+$/, '') : value
  }

  return String(value)
}
