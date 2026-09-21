import { MagnifyingGlassIcon, ProhibitIcon, TrashIcon, UserGearIcon } from '@phosphor-icons/react'
import { useCallback, useEffect, useState, type FormEvent } from 'react'
import { Link, Navigate } from 'react-router-dom'
import { adminApi, tokenStorage } from '../api/client'
import { UserRole, type User } from '../api/types'
import { AppHeader } from '../components/AppHeader'
import { useTranslation } from '../i18n/context'
import { useDateFormat } from '../i18n/useDateFormat'
import { useErrorText } from '../i18n/useErrorText'
import { clearUserCache, useCurrentUser } from '../lib/useCurrentUser'

const ROLES = [UserRole.Candidate, UserRole.Recruiter, UserRole.Admin] as const

const ROLE_LABEL = {
  [UserRole.Candidate]: 'role.candidate',
  [UserRole.Recruiter]: 'role.recruiter',
  [UserRole.Admin]: 'role.admin',
} as const

export function UsersAdminPage() {
  if (!tokenStorage.isValid()) {
    return <Navigate to="/login" replace />
  }

  return <UsersGate />
}

function UsersGate() {
  const t = useTranslation()
  const { isAdmin, loading } = useCurrentUser()

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

  return isAdmin ? <UsersManager /> : <Navigate to="/" replace />
}

function UsersManager() {
  const t = useTranslation()
  const errorText = useErrorText()
  const { user: me } = useCurrentUser()

  const [items, setItems] = useState<User[]>([])
  const [total, setTotal] = useState(0)
  const [page, setPage] = useState(1)
  const [perPage, setPerPage] = useState(25)
  const [search, setSearch] = useState('')
  const [query, setQuery] = useState('')
  const [roleFilter, setRoleFilter] = useState('')
  const [statusFilter, setStatusFilter] = useState('')
  const [loading, setLoading] = useState(true)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [selected, setSelected] = useState<Set<number>>(new Set())

  const load = useCallback(async () => {
    setLoading(true)

    try {
      const data = await adminApi.users({
        search: query || undefined,
        role: roleFilter || undefined,
        status: statusFilter || undefined,
        page,
      })

      setItems(data.items)
      setTotal(data.total)
      setPerPage(data.perPage)
      setError(null)
    } catch (requestError: unknown) {
      setError(errorText(requestError, 'common.unexpectedError'))
    } finally {
      setLoading(false)
    }
  }, [errorText, page, query, roleFilter, statusFilter])

  useEffect(() => {
    void load()
  }, [load])

  const submitSearch = (event: FormEvent) => {
    event.preventDefault()
    setPage(1)
    setQuery(search.trim())
  }

  const toggle = (id: number) => {
    setSelected((current) => {
      const next = new Set(current)
      next.has(id) ? next.delete(id) : next.add(id)

      return next
    })
  }

  const apply = async (action: (user: User) => Promise<unknown>) => {
    const chosen = items.filter((user) => selected.has(user.id))

    if (chosen.length === 0) {
      return
    }

    setBusy(true)
    setError(null)

    let touchedSelf = false

    try {
      for (const user of chosen) {
        await action(user)
        touchedSelf ||= user.id === me?.id
      }

      setSelected(new Set())

      if (touchedSelf) {
        clearUserCache()
        window.location.reload()

        return
      }

      await load()
    } catch (requestError: unknown) {
      setError(errorText(requestError, 'common.unexpectedError'))
      await load()
    } finally {
      setBusy(false)
    }
  }

  const pages = Math.max(1, Math.ceil(total / perPage))
  const chosen = items.filter((user) => selected.has(user.id))

  return (
    <>
      <AppHeader />

      <main className="page">
        <section className="panel">
          <div className="panel__head">
            <div>
              <h1 className="h2">{t('admin.usersTitle')}</h1>

              <p className="panel__hint muted-3">{t('admin.usersHint')}</p>

            </div>

            <span className="muted-3 t-sm">{t('admin.total', { count: total })}</span>

          </div>

          <div className="row g3 wrap">
            <form className="apphead__search" role="search" onSubmit={submitSearch}>
              <MagnifyingGlassIcon size={16} aria-hidden="true" />
              <input
                type="search"
                className="apphead__input"
                placeholder={t('admin.searchPlaceholder')}
                aria-label={t('admin.searchPlaceholder')}
                value={search}
                onChange={(event) => setSearch(event.target.value)}
              />

            </form>

            <select
              className="input input--auto"
              aria-label={t('admin.filterRole')}
              value={roleFilter}
              onChange={(event) => {
                setPage(1)
                setRoleFilter(event.target.value)
              }}
            >
              <option value="">{t('admin.allRoles')}</option>

              {ROLES.map((role) => (
                <option key={role} value={role}>
                  {t(ROLE_LABEL[role])}
                </option>

              ))}
            </select>

            <select
              className="input input--auto"
              aria-label={t('admin.filterStatus')}
              value={statusFilter}
              onChange={(event) => {
                setPage(1)
                setStatusFilter(event.target.value)
              }}
            >
              <option value="">{t('admin.allStatuses')}</option>

              <option value="active">{t('admin.statusActive')}</option>

              <option value="pending">{t('admin.statusPending')}</option>

              <option value="blocked">{t('admin.statusBlocked')}</option>

            </select>

          </div>

          {chosen.length > 0 && (
            <Toolbar
              chosen={chosen}
              busy={busy}
              onBlock={() => void apply((user) => adminApi.block(user.id))}
              onUnblock={() => void apply((user) => adminApi.unblock(user.id))}
              onGrant={(role) => void apply((user) => adminApi.grantRole(user.id, role))}
              onRevoke={(role) => void apply((user) => adminApi.revokeRole(user.id, role))}
              onDelete={() => void apply((user) => adminApi.deleteUser(user.id))}
              onClear={() => setSelected(new Set())}
            />

          )}

          {error && (
            <div className="notice notice--error" role="alert">
              <span>{error}</span>

            </div>

          )}

          {loading ? (
            <p className="muted table__empty" role="status">
              {t('common.loading')}
            </p>

          ) : items.length === 0 ? (
            <p className="muted table__empty">{t('admin.empty')}</p>

          ) : (
            <div className="table__scroll">
              <table className="table">
                <thead>
                  <tr>
                    <th scope="col" className="table__pick">
                      <input
                        type="checkbox"
                        aria-label={t('admin.selectAll')}
                        checked={selected.size > 0 && selected.size === items.length}
                        ref={(node) => {
                          if (node) {
                            node.indeterminate =
                              selected.size > 0 && selected.size < items.length
                          }
                        }}
                        onChange={(event) =>
                          setSelected(
                            event.target.checked
                              ? new Set(items.map((user) => user.id))
                              : new Set(),
                          )
                        }
                      />

                    </th>

                    <th scope="col">{t('admin.colUser')}</th>

                    <th scope="col">{t('admin.colStatus')}</th>

                    <th scope="col">{t('admin.colRoles')}</th>

                    <th scope="col" className="is-secondary">
                      {t('admin.colCreated')}
                    </th>

                  </tr>

                </thead>

                <tbody>
                  {items.map((user) => (
                    <UserRow
                      key={user.id}
                      user={user}
                      isMe={user.id === me?.id}
                      checked={selected.has(user.id)}
                      onToggle={() => toggle(user.id)}
                    />

                  ))}
                </tbody>

              </table>

            </div>

          )}

          {pages > 1 && (
            <div className="row g2">
              <button
                type="button"
                className="btn btn--ghost"
                disabled={page <= 1}
                onClick={() => setPage((current) => current - 1)}
              >
                {t('admin.prev')}
              </button>

              <span className="muted-3 t-sm">{t('admin.pageOf', { page, pages })}</span>

              <button
                type="button"
                className="btn btn--ghost"
                disabled={page >= pages}
                onClick={() => setPage((current) => current + 1)}
              >
                {t('admin.next')}
              </button>

            </div>

          )}
        </section>

      </main>

    </>

  )
}

function Toolbar({
  chosen,
  busy,
  onBlock,
  onUnblock,
  onGrant,
  onRevoke,
  onDelete,
  onClear,
}: {
  chosen: User[]
  busy: boolean
  onBlock: () => void
  onUnblock: () => void
  onGrant: (role: string) => void
  onRevoke: (role: string) => void
  onDelete: () => void
  onClear: () => void
}) {
  const t = useTranslation()
  const [confirming, setConfirming] = useState(false)

  const blockable = chosen.filter((user) => user.status !== 'blocked')
  const unblockable = chosen.filter((user) => user.status === 'blocked')

  return (
    <div className="toolbar">
      <span className="t-sm">{t('admin.selectedCount', { count: chosen.length })}</span>

      <div className="row g2 wrap">
        {ROLES.map((role) => {
          const toGrant = chosen.filter((user) => !user.roles.includes(role))
          const toRevoke = chosen.filter((user) => user.roles.includes(role))

          return (
            <span key={role} className="row g2">
              {toGrant.length > 0 && (
                <button
                  type="button"
                  className="btn btn--outline"
                  disabled={busy}
                  onClick={() => onGrant(role)}
                >
                  <UserGearIcon size={14} aria-hidden="true" />
                  {t('admin.grantRole', { role: t(ROLE_LABEL[role]), count: toGrant.length })}
                </button>

              )}

              {toRevoke.length > 0 && (
                <button
                  type="button"
                  className="btn btn--outline"
                  disabled={busy}
                  onClick={() => onRevoke(role)}
                >
                  <UserGearIcon size={14} aria-hidden="true" />
                  {t('admin.revokeRole', { role: t(ROLE_LABEL[role]), count: toRevoke.length })}
                </button>

              )}
            </span>

          )
        })}

        {blockable.length > 0 && (
          <button type="button" className="btn btn--outline" disabled={busy} onClick={onBlock}>
            <ProhibitIcon size={14} aria-hidden="true" />
            {t('admin.blockCount', { count: blockable.length })}
          </button>

        )}

        {unblockable.length > 0 && (
          <button type="button" className="btn btn--outline" disabled={busy} onClick={onUnblock}>
            <ProhibitIcon size={14} aria-hidden="true" />
            {t('admin.unblockCount', { count: unblockable.length })}
          </button>

        )}

        <button
          type="button"
          className="btn btn--outline"
          disabled={busy}
          onClick={() => setConfirming(true)}
        >
          <TrashIcon size={14} aria-hidden="true" />
          {t('admin.deleteCount', { count: chosen.length })}
        </button>

        <button type="button" className="btn btn--ghost" disabled={busy} onClick={onClear}>
          {t('admin.clearSelection')}
        </button>

      </div>

      {confirming && (
        <div className="notice notice--error toolbar__confirm" role="alert">
          <span>{t('admin.confirmDelete', { count: chosen.length })}</span>

          <div className="row g2">
            <button
              type="button"
              className="btn btn--primary"
              disabled={busy}
              onClick={() => {
                setConfirming(false)
                onDelete()
              }}
            >
              {t('admin.confirmDeleteYes')}
            </button>

            <button
              type="button"
              className="btn btn--ghost"
              onClick={() => setConfirming(false)}
            >
              {t('common.cancel')}
            </button>

          </div>

        </div>

      )}
    </div>

  )
}

function UserRow({
  user,
  isMe,
  checked,
  onToggle,
}: {
  user: User
  isMe: boolean
  checked: boolean
  onToggle: () => void
}) {
  const t = useTranslation()
  const formatDate = useDateFormat()
  const blocked = user.status === 'blocked'

  return (
    <tr className="table__row" aria-selected={checked}>
      <td className="table__pick">
        <input
          type="checkbox"
          checked={checked}
          onChange={onToggle}
          aria-label={t('admin.selectOne', { email: user.email })}
        />

      </td>

      <td>
        {user.profileId === null ? (
          user.email
        ) : (
          <Link className="table__link" to={`/profiles/${user.profileId}`}>
            {user.email}
          </Link>

        )}
        {isMe && <span className="table__sub">{t('admin.you')}</span>}

      </td>

      <td>
        <span className={`chip${user.status === 'active' ? ' chip--ok' : ''}`}>
          {t(
            blocked
              ? 'admin.statusBlocked'
              : user.status === 'pending'
                ? 'admin.statusPending'
                : 'admin.statusActive',
          )}
        </span>

      </td>

      <td>
        <div className="cloud">
          {ROLES.filter((role) => user.roles.includes(role)).map((role) => (
            <span key={role} className="chip">
              {t(ROLE_LABEL[role])}
            </span>

          ))}
        </div>

      </td>

      <td className="is-secondary">{formatDate(user.createdAt)}</td>

    </tr>

  )
}
