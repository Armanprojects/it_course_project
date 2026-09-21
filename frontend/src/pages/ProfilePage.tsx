import { CheckCircleIcon, CloudArrowUpIcon, PlusIcon, WarningCircleIcon } from '@phosphor-icons/react'
import { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import { Link, Navigate, useNavigate, useParams } from 'react-router-dom'
import { profileApi, RequestError, tokenStorage, type ProfileTarget } from '../api/client'
import type { AttributeValue, ProfileAttribute, ProfileData } from '../api/types'
import { AppHeader } from '../components/AppHeader'
import { AttributeField } from '../components/AttributeField'
import { AttributePicker } from '../components/AttributePicker'
import { ProjectsSection } from '../components/ProjectsSection'
import { useAutosave, type SaveState } from '../lib/useAutosave'
import { useTranslation } from '../i18n/context'
import { useDateFormat } from '../i18n/useDateFormat'
import { useErrorText } from '../i18n/useErrorText'

type Draft = Record<number, AttributeValue>

const EMPTY_DRAFT: Draft = {}
const isDraftEmpty = (draft: Draft) => Object.keys(draft).length === 0

export function ProfilePage() {
  if (!tokenStorage.isValid()) {
    return <Navigate to="/login" replace />
  }

  return <ProfileView />
}

function ProfileView() {
  const { id } = useParams<{ id: string }>()
  const target: ProfileTarget = id === undefined ? 'me' : Number(id)
  const t = useTranslation()
  const errorText = useErrorText()
  const navigate = useNavigate()
  const [profile, setProfile] = useState<ProfileData | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [picking, setPicking] = useState(false)

  const [draft, setDraft] = useState<Draft>({})

  const versionRef = useRef(0)

  const load = useCallback(async () => {
    try {
      const data = target === 'me' ? await profileApi.me() : await profileApi.byId(target)
      versionRef.current = data.version
      setProfile(data)
      setError(null)
    } catch (requestError: unknown) {
      if (requestError instanceof RequestError && requestError.code === 'unauthorized') {
        tokenStorage.clear()
        navigate('/login', { replace: true })

        return
      }

      setError(
        errorText(requestError, 'common.unexpectedError'),
      )
    }
  }, [navigate, errorText])

  useEffect(() => {
    void load()
  }, [load])

  const onSave = useCallback(async (changes: Draft) => {
    const data = await profileApi.save(versionRef.current, changes, target)
    versionRef.current = data.version
    setProfile(data)
    setDraft({})

    return true
  }, [])

  const autosave = useAutosave<Draft>({
    onSave: async (changes) => {
      try {
        return await onSave(changes)
      } catch (requestError: unknown) {
        if (requestError instanceof RequestError && requestError.isConflict) {
          await load()
          setDraft({})

          return false
        }

        throw requestError
      }
    },
    isEmpty: isDraftEmpty,
    empty: EMPTY_DRAFT,
  })

  const { schedule, flush, reset } = autosave

  const change = useCallback(
    (attributeId: number, value: AttributeValue) => {
      setDraft((current) => ({ ...current, [attributeId]: value }))
      schedule((pending) => ({ ...pending, [attributeId]: value }))
    },
    [schedule],
  )

  const mutateAttributes = useCallback(
    async (run: (version: number) => Promise<ProfileData>) => {
      await flush()
      reset()

      try {
        const data = await run(versionRef.current)
        versionRef.current = data.version
        setProfile(data)
        setDraft({})
        setError(null)
      } catch (requestError: unknown) {
        if (requestError instanceof RequestError && requestError.isConflict) {
          await load()

          return
        }

        setError(
          errorText(requestError, 'common.unexpectedError'),
        )
      }
    },
    [flush, load, reset, errorText],
  )

  const ownedIds = useMemo(
    () =>
      new Set([
        ...(profile?.me ?? []).map((attribute) => attribute.attributeId),
        ...(profile?.info ?? []).map((attribute) => attribute.attributeId),
      ]),
    [profile],
  )

  const valueOf = (attribute: ProfileAttribute): AttributeValue =>
    attribute.attributeId in draft ? draft[attribute.attributeId] : attribute.value

  if (error && profile === null) {
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

  if (profile === null) {
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
        <div className="panel__head">
          <div className="col g1">
            <h1 className="h1">{t(target === 'me' ? 'profile.title' : 'profile.titleOther')}</h1>

            <p className="muted" style={{ margin: 0 }}>
              {profile.user.email}
            </p>

          </div>

          <SaveBadge state={autosave.state} />

        </div>

        {error && (
          <div className="notice notice--error" role="alert">
            <span>{error}</span>

          </div>

        )}

        <section className="panel">
          <div className="panel__head">
            <div>
              <h2 className="h2">{t('profile.about')}</h2>

              <p className="panel__hint muted-3">
                {t('profile.aboutHint')}
              </p>

            </div>

          </div>

          <div className="attrgrid">
            {profile.me.map((attribute) => (
              <AttributeField
                key={attribute.attributeId}
                attribute={attribute}
                value={valueOf(attribute)}
                onChange={(value) => change(attribute.attributeId, value)}
              />

            ))}
          </div>

        </section>

        <section className="panel">
          <div className="panel__head">
            <div>
              <h2 className="h2">{t('profile.info')}</h2>

              <p className="panel__hint muted-3">{t('profile.infoHint')}</p>

            </div>

            {!picking && (
              <button type="button" className="btn btn--outline" onClick={() => setPicking(true)}>
                <PlusIcon size={14} aria-hidden="true" />
                {t('profile.addAttribute')}
              </button>

            )}
          </div>

          {picking && (
            <AttributePicker
              ownedIds={ownedIds}
              onClose={() => setPicking(false)}
              onPick={(attribute) => {
                setPicking(false)
                void mutateAttributes((version) =>
                  profileApi.addAttribute(attribute.id, version, target),
                )
              }}
            />

          )}

          {profile.info.length === 0 ? (
            <p className="muted table__empty">
              {t('profile.infoEmpty')}
            </p>

          ) : (
            <div className="attrgrid">
              {profile.info.map((attribute) => (
                <AttributeField
                  key={attribute.attributeId}
                  attribute={attribute}
                  value={valueOf(attribute)}
                  onChange={(value) => change(attribute.attributeId, value)}
                  onRemove={() =>
                    void mutateAttributes((version) =>
                      profileApi.removeAttribute(attribute.attributeId, version, target),
                    )
                  }
                />

              ))}
            </div>

          )}
        </section>

        <ProjectsSection projects={profile.projects} onChanged={() => void load()} target={target} />

        <CvSection cvs={profile.cvs} />

      </main>

    </>

  )
}

function SaveBadge({ state }: { state: SaveState }) {
  const t = useTranslation()
  if (state === 'idle') {
    return null
  }

  const text: Record<Exclude<SaveState, 'idle'>, string> = {
    pending: t('profile.savePending'),
    saving: t('profile.saving'),
    saved: t('profile.saved'),
    conflict: t('profile.saveConflict'),
    error: t('profile.saveError'),
  }

  const tone = state === 'conflict' || state === 'error' ? 'is-warn' : ''

  return (
    <span className={`savebadge ${tone}`} role="status">
      {state === 'saved' && <CheckCircleIcon size={14} aria-hidden="true" />}
      {(state === 'conflict' || state === 'error') && (
        <WarningCircleIcon size={14} aria-hidden="true" />
      )}
      {(state === 'saving' || state === 'pending') && (
        <CloudArrowUpIcon size={14} aria-hidden="true" />
      )}
      {text[state]}
    </span>

  )
}

function CvSection({ cvs }: { cvs: ProfileData['cvs'] }) {
  const t = useTranslation()
  const formatDate = useDateFormat()
  return (
    <section className="panel">
      <div className="panel__head">
        <div>
          <h2 className="h2">{t('profile.cvs')}</h2>

          <p className="panel__hint muted-3">{t('profile.cvsHint')}</p>

        </div>

        <Link to="/positions">{t('profile.findPosition')}</Link>

      </div>

      {cvs.length === 0 ? (
        <p className="muted table__empty">
          {t('profile.cvsEmpty')}
        </p>

      ) : (
        <div className="table__scroll">
          <table className="table">
            <thead>
              <tr>
                <th scope="col">{t('positions.colTitle')}</th>

                <th scope="col">{t('positions.colCompany')}</th>

                <th scope="col">{t('cvTable.status')}</th>

                <th scope="col" className="is-secondary">
                  {t('cvTable.likes')}
                </th>

                <th scope="col" className="is-secondary">
                  {t('cvTable.updated')}
                </th>

              </tr>

            </thead>

            <tbody>
              {cvs.map((cv) => (
                <tr key={cv.id}>
                  <td>
                    <Link className="table__link" to={`/cvs/${cv.id}`}>
                      {cv.position.title}
                    </Link>

                  </td>

                  <td>{cv.position.company ?? <span className="muted-3">—</span>}</td>
                  <td>
                    <span className={`chip${cv.status === 'published' ? ' chip--ok' : ''}`}>
                      {t(cv.status === 'published' ? 'cv.published' : 'cv.draft')}
                    </span>

                  </td>

                  <td className="is-secondary num">{cv.likesCount}</td>

                  <td className="is-secondary">{formatDate(cv.updatedAt)}</td>

                </tr>

              ))}
            </tbody>

          </table>

        </div>

      )}
    </section>

  )
}
