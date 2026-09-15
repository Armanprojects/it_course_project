import { PlusIcon } from '@phosphor-icons/react'
import { useEffect, useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { catalogApi } from '../api/client'
import type { PositionPage, PositionSort, SortDirection } from '../api/types'
import { AppHeader } from '../components/AppHeader'
import { PositionsTable } from '../components/PositionsTable'
import { useCurrentUser } from '../lib/useCurrentUser'
import { useTranslation } from '../i18n/context'
import { useErrorText } from '../i18n/useErrorText'

const SORTS: PositionSort[] = ['title', 'company', 'level', 'createdAt', 'updatedAt']

/** Выдача вместе с запросом, которому она отвечает. */
interface Loaded {
  key: string
  page: PositionPage
}

function parseSort(value: string | null): PositionSort {
  return SORTS.includes(value as PositionSort) ? (value as PositionSort) : 'updatedAt'
}

/**
 * Каталог позиций: таблица, сортировка, поиск и пагинация.
 *
 * Всё состояние живёт в query-строке, а не в useState. Так ссылку на
 * отфильтрованную выдачу можно переслать, а «назад» возвращает ту же
 * страницу, а не первую.
 */
export function PositionsPage() {
  const t = useTranslation()
  const errorText = useErrorText()
  const [params, setParams] = useSearchParams()
  const [result, setResult] = useState<Loaded | null>(null)
  const [error, setError] = useState<string | null>(null)
  const { isRecruiter } = useCurrentUser()

  const search = params.get('search') ?? ''
  const sort = parseSort(params.get('sort'))
  const direction: SortDirection = params.get('direction') === 'asc' ? 'asc' : 'desc'
  const pageNumber = Math.max(1, Number(params.get('page') ?? '1') || 1)

  const queryKey = `${search}|${sort}|${direction}|${pageNumber}`

  // Загрузка выводится из данных, а не хранится отдельным флагом: пока
  // загруженная выдача отвечает другому запросу, идёт загрузка. Флаг пришлось
  // бы взводить прямо в эффекте, вызывая лишний каскадный рендер.
  const loading = result?.key !== queryKey
  const page = result?.page ?? null

  useEffect(() => {
    let active = true

    catalogApi
      .positions({ search: search || undefined, sort, direction, page: pageNumber })
      .then((loaded) => {
        if (active) {
          setResult({ key: queryKey, page: loaded })
          setError(null)
        }
      })
      .catch((requestError: unknown) => {
        if (active) {
          setError(
            errorText(requestError, 'common.unexpectedError')
          )
        }
      })

    return () => {
      active = false
    }
  }, [search, sort, direction, pageNumber, queryKey, errorText])

  /** Клик по той же колонке разворачивает порядок, по другой — сортирует по ней. */
  const changeSort = (column: PositionSort) => {
    const next = new URLSearchParams(params)
    next.set('sort', column)
    next.set('direction', sort === column && direction === 'desc' ? 'asc' : 'desc')
    // Сортировка меняет всю выдачу — оставаться на пятой странице бессмысленно.
    next.delete('page')
    setParams(next)
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

  const clearSearch = () => {
    const next = new URLSearchParams(params)
    next.delete('search')
    next.delete('page')
    setParams(next)
  }

  return (
    <>
      <AppHeader />

      <main className="page">
        <div className="panel">
          <div className="panel__head">
            <div>
              <h1 className="h1">{t('positions.title')}</h1>
              <p className="panel__hint muted-3">
                {page
                  ? t('positions.found', { count: page.total })
                  : t('positions.guestHint')}
              </p>
            </div>

            {/* Панель инструментов над таблицей — по заданию действия должны
                жить здесь, а не кнопками в каждой строке. */}
            <div className="row g2">
              {search && (
                <button type="button" className="btn btn--outline" onClick={clearSearch}>
                  {t('positions.clearSearch', { query: search })}
                </button>
              )}

              {isRecruiter && (
                <Link to="/positions/new" className="btn btn--primary">
                  <PlusIcon size={14} aria-hidden="true" />
                  {t('positions.new')}
                </Link>
              )}
            </div>
          </div>

          {error && (
            <div className="notice notice--error" role="alert">
              <span>{error}</span>
            </div>
          )}

          {/* Таблица остаётся на месте, пока грузится следующая страница:
              подмена её спиннером дёргала бы layout на каждой сортировке. */}
          <div aria-busy={loading}>
            {page && (
              <PositionsTable
                rows={page.items}
                sort={sort}
                direction={direction}
                onSort={changeSort}
                emptyMessage={
                  search ? t('positions.notFoundFor', { query: search }) : t('positions.empty')
                }
              />
            )}

            {!page && loading && (
              <p className="muted table__empty" role="status">
                {t('common.loading')}
              </p>
            )}
          </div>

          {page && page.pages > 1 && (
            <nav className="pager" aria-label={t('positions.pager')}>
              <button
                type="button"
                className="btn btn--outline"
                disabled={page.page <= 1}
                onClick={() => goToPage(page.page - 1)}
              >
                {t('positions.prev')}
              </button>

              <span className="muted t-sm">
                {t('positions.pageOf', { page: page.page, pages: page.pages })}
              </span>

              <button
                type="button"
                className="btn btn--outline"
                disabled={page.page >= page.pages}
                onClick={() => goToPage(page.page + 1)}
              >
                {t('positions.next')}
              </button>
            </nav>
          )}
        </div>
      </main>
    </>
  )
}
