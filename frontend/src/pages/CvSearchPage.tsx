import { MagnifyingGlassIcon } from '@phosphor-icons/react'
import { useEffect, useState } from 'react'
import { Navigate, useSearchParams } from 'react-router-dom'
import { cvApi, tokenStorage } from '../api/client'
import type { CvPage } from '../api/types'
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
  const [page, setPage] = useState<CvPage | null>(null)
  const [error, setError] = useState<string | null>(null)

  const query = params.get('q') ?? ''
  const current = Number(params.get('page') ?? '1')
  const pageNumber = Number.isInteger(current) && current > 0 ? current : 1

  const [draft, setDraft] = useState(query)

  useEffect(() => {
    let active = true

    // Пустой запрос — не пустой экран: сервер отдаёт каталог всех
    // опубликованных резюме, поиск лишь сужает его.
    cvApi
      .search(query, pageNumber)
      .then((result) => {
        if (active) {
          setPage(result)
          setError(null)
        }
      })
      .catch((requestError: unknown) => {
        if (active) {
          setError(errorText(requestError, 'cvSearch.failed'))
          setPage(null)
        }
      })

    return () => {
      active = false
    }
  }, [query, pageNumber, errorText])

  const submit = (next: string) => {
    // Новый запрос всегда открывается с первой страницы: номер от прошлого
    // поиска мог бы увести за пределы новой выдачи.
    setParams(next.trim() ? { q: next.trim() } : {})
  }

  const goToPage = (target: number) => {
    const next = new URLSearchParams(params)

    if (target <= 1) {
      next.delete('page')
    } else {
      next.set('page', String(target))
    }

    setParams(next)
  }

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
            submit(draft)
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

          {query !== '' && (
            <button
              type="button"
              className="btn btn--outline"
              onClick={() => {
                setDraft('')
                submit('')
              }}
            >
              {t('cvSearch.clear')}
            </button>
          )}
        </form>

        {error !== null && (
          <div className="notice notice--error" role="alert">
            <span>{error}</span>
          </div>
        )}

        <section className="panel">
          {page === null ? (
            <p className="muted table__empty" role="status">
              {t('cvSearch.searching')}
            </p>
          ) : (
            <>
              <p className="panel__hint muted-3" style={{ margin: 0 }}>
                {query === ''
                  ? t('cvSearch.total', { count: page.total })
                  : t('cvSearch.found', { count: page.total })}
              </p>

              <CvTable
                rows={page.items}
                emptyMessage={
                  query === '' ? t('cvSearch.empty') : t('cvSearch.nothingFor', { query })
                }
              />

              {page.pages > 1 && (
                <nav className="pager" aria-label={t('cvSearch.pager')}>
                  <button
                    type="button"
                    className="btn btn--outline"
                    disabled={page.page <= 1}
                    onClick={() => goToPage(page.page - 1)}
                  >
                    {t('cvSearch.prev')}
                  </button>

                  <span className="muted t-sm">
                    {t('cvSearch.pageOf', { page: page.page, pages: page.pages })}
                  </span>

                  <button
                    type="button"
                    className="btn btn--outline"
                    disabled={page.page >= page.pages}
                    onClick={() => goToPage(page.page + 1)}
                  >
                    {t('cvSearch.next')}
                  </button>
                </nav>
              )}
            </>
          )}
        </section>
      </main>
    </>
  )
}
