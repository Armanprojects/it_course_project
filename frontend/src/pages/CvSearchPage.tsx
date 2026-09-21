import { MagnifyingGlassIcon } from '@phosphor-icons/react'
import { useEffect, useState } from 'react'
import { Navigate, useSearchParams } from 'react-router-dom'
import { cvApi, tokenStorage } from '../api/client'
import type { CvRow } from '../api/types'
import { AppHeader } from '../components/AppHeader'
import { CvTable } from '../components/CvTable'
import { useCurrentUser } from '../lib/useCurrentUser'
import { useTranslation } from '../i18n/context'
import { useErrorText } from '../i18n/useErrorText'

export function CvSearchPage() {
  if (!tokenStorage.isValid()) {
    return <Navigate to="/login" replace />
  }

  return <SearchGate />
}

function SearchGate() {
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

  return isRecruiter ? <CvSearch /> : <Navigate to="/" replace />
}

function CvSearch() {
  const t = useTranslation()
  const errorText = useErrorText()
  const [params, setParams] = useSearchParams()
  const [found, setFound] = useState<{ query: string; items: CvRow[] } | null>(null)
  const [error, setError] = useState<string | null>(null)

  const query = params.get('q') ?? ''
  const [draft, setDraft] = useState(query)

  const rows = query.trim() === '' ? null : found?.query === query ? found.items : null

  useEffect(() => {
    if (query.trim() === '') {
      return
    }

    let active = true

    cvApi
      .search(query)
      .then((result) => {
        if (active) {
          setFound({ query, items: result.items })
          setError(null)
        }
      })
      .catch((requestError: unknown) => {
        if (active) {
          setError(
            errorText(requestError, 'cvSearch.failed'),
          )
        }
      })

    return () => {
      active = false
    }
  }, [query, errorText])

  return (
    <>
      <AppHeader />

      <main className="page">
        <div className="col g1">
          <h1 className="h1">{t('cvSearch.title')}</h1>

          <p className="muted" style={{ margin: 0 }}>
            {t('cvSearch.lead')}
          </p>

        </div>

        <form
          className="picker__filters"
          onSubmit={(event) => {
            event.preventDefault()
            setParams(draft.trim() ? { q: draft.trim() } : {})
          }}
        >
          <div className="apphead__search picker__search">
            <MagnifyingGlassIcon size={16} aria-hidden="true" />
            <input
              type="search"
              className="apphead__input"
              placeholder={t('cvSearch.placeholder')}
              aria-label={t('cvSearch.label')}
              value={draft}
              onChange={(event) => setDraft(event.target.value)}
            />

          </div>

          <button type="submit" className="btn btn--primary">
            {t('cvSearch.submit')}
          </button>

        </form>

        {error && (
          <div className="notice notice--error" role="alert">
            <span>{error}</span>

          </div>

        )}

        <section className="panel">
          {query.trim() === '' ? (
            <p className="muted table__empty">{t('cvSearch.prompt')}</p>

          ) : rows === null ? (
            <p className="muted table__empty" role="status">
              {t('cvSearch.searching')}
            </p>

          ) : (
            <>
              <p className="panel__hint muted-3" style={{ margin: 0 }}>
                {t('cvSearch.found', { count: rows.length })}
              </p>

              <CvTable
                rows={rows}
                emptyMessage={t('cvSearch.nothingFor', { query })}
              />

            </>

          )}
        </section>

      </main>

    </>

  )
}
