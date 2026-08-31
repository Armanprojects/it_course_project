import { CaretDownIcon, CaretUpIcon } from '@phosphor-icons/react'
import { useNavigate } from 'react-router-dom'
import type { PositionRow, PositionSort, SortDirection } from '../api/types'

interface Column {
  key: PositionSort | 'attributeCount' | 'cvCount'
  label: string
  sortable: boolean
  /** Узкие числовые колонки прячем на телефоне: там важны название и компания. */
  hideOnMobile?: boolean
}

const COLUMNS: Column[] = [
  { key: 'title', label: 'Позиция', sortable: true },
  { key: 'company', label: 'Компания', sortable: true },
  { key: 'level', label: 'Уровень', sortable: true },
  { key: 'attributeCount', label: 'Полей', sortable: false, hideOnMobile: true },
  { key: 'cvCount', label: 'Резюме', sortable: false, hideOnMobile: true },
  { key: 'updatedAt', label: 'Обновлена', sortable: true, hideOnMobile: true },
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

const dateFormat = new Intl.DateTimeFormat('ru-RU', { dateStyle: 'medium' })

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
  emptyMessage = 'Позиции не найдены.',
}: Props) {
  const navigate = useNavigate()
  const columns = compact ? COLUMNS.filter((column) => !column.hideOnMobile) : COLUMNS

  if (rows.length === 0) {
    return (
      <p className="muted table__empty" role="status">
        {emptyMessage}
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
                      {column.label}
                      {active &&
                        (direction === 'asc' ? (
                          <CaretUpIcon size={12} weight="bold" aria-hidden="true" />
                        ) : (
                          <CaretDownIcon size={12} weight="bold" aria-hidden="true" />
                        ))}
                    </button>
                  ) : (
                    column.label
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
                  <td className="is-secondary">{dateFormat.format(new Date(row.updatedAt))}</td>
                </>
              )}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}
