import { HeartIcon } from '@phosphor-icons/react'
import { useNavigate } from 'react-router-dom'
import type { CvRow } from '../api/types'

const dateFormat = new Intl.DateTimeFormat('ru-RU', { dateStyle: 'medium' })

interface Props {
  rows: CvRow[]
  /** В списке по позиции колонка позиции лишняя — она и так известна. */
  showPosition?: boolean
  emptyMessage?: string
}

/**
 * Таблица резюме: список по позиции и результаты поиска.
 *
 * Табличное представление обязательно по заданию, кнопок в строках нет —
 * кликается вся строка.
 */
export function CvTable({ rows, showPosition = true, emptyMessage = 'Резюме не найдены.' }: Props) {
  const navigate = useNavigate()

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
            <th scope="col">Кандидат</th>
            {showPosition && <th scope="col">Позиция</th>}
            <th scope="col">Статус</th>
            <th scope="col" className="is-secondary">
              Лайки
            </th>
            <th scope="col" className="is-secondary">
              Обновлено
            </th>
          </tr>
        </thead>

        <tbody>
          {rows.map((row) => (
            <tr key={row.id} className="table__row" onClick={() => navigate(`/cvs/${row.id}`)}>
              <td>
                <a
                  className="table__link"
                  href={`/cvs/${row.id}`}
                  onClick={(event) => {
                    event.preventDefault()
                    navigate(`/cvs/${row.id}`)
                  }}
                >
                  {row.candidate.name}
                </a>
                <span className="table__sub">{row.candidate.email}</span>
              </td>

              {showPosition && (
                <td>
                  {row.position.title}
                  {row.position.company && (
                    <span className="table__sub">{row.position.company}</span>
                  )}
                </td>
              )}

              <td>
                <span className={`chip${row.status === 'published' ? ' chip--ok' : ''}`}>
                  {row.status === 'published' ? 'Опубликовано' : 'Черновик'}
                </span>
              </td>

              <td className="is-secondary num">
                <span className={`likecount${row.likedByMe ? ' is-on' : ''}`}>
                  <HeartIcon
                    size={12}
                    weight={row.likedByMe ? 'fill' : 'regular'}
                    aria-hidden="true"
                  />
                  {row.likesCount}
                </span>
              </td>

              <td className="is-secondary">{dateFormat.format(new Date(row.updatedAt))}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}
