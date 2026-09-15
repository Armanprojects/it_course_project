import { CaretDownIcon, CaretUpIcon } from '@phosphor-icons/react'
import { useNavigate } from 'react-router-dom'
import type { PositionRow, PositionSort, SortDirection } from '../api/types'
import { useTranslation } from '../i18n/context'
import type { MessageKey } from '../i18n/messages'
import { useDateFormat } from '../i18n/useDateFormat'

interface Column {
  key: PositionSort | 'attributeCount' | 'cvCount'
  /** Ключ словаря, а не готовая строка: заголовок зависит от языка. */
  label: MessageKey
  sortable: boolean
  /** Узкие числовые колонки прячем на телефоне: там важны название и компания. */
  hideOnMobile?: boolean
}

const COLUMNS: Column[] = [
  { key: 'title', label: 'positions.colTitle', sortable: true },
  { key: 'company', label: 'positions.colCompany', sortable: true },
  { key: 'level', label: 'positions.colLevel', sortable: true },
  { key: 'attributeCount', label: 'positions.colFields', sortable: false, hideOnMobile: true },
  { key: 'cvCount', label: 'positions.colCvs', sortable: false, hideOnMobile: true },
  { key: 'updatedAt', label: 'positions.colUpdated', sortable: true, hideOnMobile: true },
]

interface Props {
  rows: PositionRow[]
  sort?: PositionSort
  direction?: SortDirection
  onSort?: (column: PositionSort) => void
  /** Колонки со счётчиками не нужны на компактной таблице главной страницы. */
  compact?: boolean
  emptyMessage?: string
}

/**
 * Таблица позиций — единственное представление каталога: задание прямо
 * запрещает плитки и галереи.
 *
 * Кнопок в строках тоже нет (за них снимают 20%): строка сама по себе ссылка
 * на позицию, а групповые действия для рекрутёра встанут в панель над таблицей.
 */
export function PositionsTable({
  rows,
  sort,
  direction = 'desc',
  onSort,
  compact = false,
  emptyMessage,
}: Props) {
  const navigate = useNavigate()
  const t = useTranslation()
  const formatDate = useDateFormat()
  const columns = compact ? COLUMNS.filter((column) => !column.hideOnMobile) : COLUMNS

  if (rows.length === 0) {
    return (
      <p className="muted table__empty" role="status">
        {emptyMessage ?? t('positions.notFound')}
      </p>
    )
  }

  return (
    <div className="table__scroll">
      <table className="table">
        <thead>
          <tr>
            {columns.map((column) => {
              const active = sort === column.key
              const canSort = column.sortable && onSort !== undefined

              return (
                <th
                  key={column.key}
                  scope="col"
                  className={column.hideOnMobile ? 'is-secondary' : undefined}
                  aria-sort={
                    active ? (direction === 'asc' ? 'ascending' : 'descending') : undefined
                  }
                >
                  {canSort ? (
                    <button
                      type="button"
                      className="table__sort"
                      onClick={() => onSort(column.key as PositionSort)}
                    >
                      {t(column.label)}
                      {active &&
                        (direction === 'asc' ? (
                          <CaretUpIcon size={12} weight="bold" aria-hidden="true" />
                        ) : (
                          <CaretDownIcon size={12} weight="bold" aria-hidden="true" />
                        ))}
                    </button>
                  ) : (
                    t(column.label)
                  )}
                </th>
              )
            })}
          </tr>
        </thead>

        <tbody>
          {rows.map((row) => (
            <tr
              key={row.id}
              className="table__row"
              onClick={() => navigate(`/positions/${row.id}`)}
            >
              <td>
                {/* Ссылка настоящая, а не onClick на строке: так работают
                    клавиатура, «открыть в новой вкладке» и скринридер. */}
                <a
                  className="table__link"
                  href={`/positions/${row.id}`}
                  onClick={(event) => {
                    event.preventDefault()
                    navigate(`/positions/${row.id}`)
                  }}
                >
                  {row.title}
                </a>

                {row.shortDescription && !compact && (
                  <span className="table__sub">{row.shortDescription}</span>
                )}
              </td>

              <td>{row.company ?? <span className="muted-3">—</span>}</td>

              <td>
                {row.level ? (
                  <span className="chip">{row.level}</span>
                ) : (
                  <span className="muted-3">—</span>
                )}
              </td>

              {!compact && (
                <>
                  <td className="is-secondary num">{row.attributeCount}</td>
                  <td className="is-secondary num">{row.cvCount}</td>
                  <td className="is-secondary">{formatDate(row.updatedAt)}</td>
                </>
              )}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}
