import { MagnifyingGlassIcon } from '@phosphor-icons/react'
import { useEffect, useState } from 'react'
import { Navigate, useSearchParams } from 'react-router-dom'
import { cvApi, RequestError, tokenStorage } from '../api/client'
import type { CvRow } from '../api/types'
import { AppHeader } from '../components/AppHeader'
import { CvTable } from '../components/CvTable'
import { useCurrentUser } from '../lib/useCurrentUser'

/**
 * Полнотекстовый поиск по резюме — один из трёх путей к данным кандидата,
 * которые задание даёт рекрутеру (ещё через позицию и через персональные
 * страницы).
 *
 * Ищет по значениям атрибутов профиля, названиям и описаниям проектов,
 * названию позиции и адресу кандидата.
 */
export function CvSearchPage() {
  if (!tokenStorage.get()) {
    return <Navigate to="/login" replace />
  }

  return <SearchGate />
}

function SearchGate() {
  const { isRecruiter, loading } = useCurrentUser()

  if (loading) {
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

  return isRecruiter ? <CvSearch /> : <Navigate to="/" replace />
}

function CvSearch() {
  const [params, setParams] = useSearchParams()
  const [found, setFound] = useState<{ query: string; items: CvRow[] } | null>(null)
  const [error, setError] = useState<string | null>(null)

  const query = params.get('q') ?? ''
  const [draft, setDraft] = useState(query)

  // Выводим при рендере: результат показываем, только если он отвечает
  // текущему запросу. Иначе пришлось бы обнулять его прямо в эффекте.
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
            requestError instanceof RequestError ? requestError.message : 'Поиск не удался.',
          )
        }
      })

    return () => {
      active = false
    }
  }, [query])

  return (
    <>
      <AppHeader />

      <main className="page">
        <div className="col g1">
          <h1 className="h1">Поиск по резюме</h1>
          <p className="muted" style={{ margin: 0 }}>
            По навыкам, проектам, городу — по любому тексту в профиле кандидата
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
              placeholder="Например: kubernetes, Berlin, аналитика…"
              aria-label="Запрос"
              value={draft}
              onChange={(event) => setDraft(event.target.value)}
            />
          </div>

          <button type="submit" className="btn btn--primary">
            Найти
          </button>
        </form>

        {error && (
          <div className="notice notice--error" role="alert">
            <span>{error}</span>
          </div>
        )}

        <section className="panel">
          {query.trim() === '' ? (
            <p className="muted table__empty">Введите запрос, чтобы найти кандидатов.</p>
          ) : rows === null ? (
            <p className="muted table__empty" role="status">
              Ищем…
            </p>
          ) : (
            <>
              <p className="panel__hint muted-3" style={{ margin: 0 }}>
                Найдено: {rows.length}
              </p>
              <CvTable
                rows={rows}
                emptyMessage={`По запросу «${query}» ничего не найдено.`}
              />
            </>
          )}
        </section>
      </main>
    </>
  )
}
