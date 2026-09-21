import { HeartIcon } from '@phosphor-icons/react'
import { useNavigate } from 'react-router-dom'
import type { CvRow } from '../api/types'
import { useTranslation } from '../i18n/context'
import { useDateFormat } from '../i18n/useDateFormat'

interface Props {
  rows: CvRow[]
  showPosition?: boolean
  emptyMessage?: string
}

export function CvTable({ rows, showPosition = true, emptyMessage }: Props) {
  const navigate = useNavigate()
  const t = useTranslation()
  const formatDate = useDateFormat()

  if (rows.length === 0) {
    return (
      <p className="muted table__empty" role="status">
        {emptyMessage ?? t('cvTable.empty')}
      </p>

    )
  }

  return (
    <div className="table__scroll">
      <table className="table">
        <thead>
          <tr>
            <th scope="col">{t('cvTable.candidate')}</th>

            {showPosition && <th scope="col">{t('cvTable.position')}</th>}

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
                  {t(row.status === 'published' ? 'cv.published' : 'cv.draft')}
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

              <td className="is-secondary">{formatDate(row.updatedAt)}</td>

            </tr>

          ))}
        </tbody>

      </table>

    </div>

  )
}
