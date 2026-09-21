import { MagnifyingGlassIcon, MoonIcon, SunIcon } from '@phosphor-icons/react'
import { useState, type FormEvent } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router-dom'
import { tokenStorage } from '../api/client'
import { useSettings } from '../i18n/context'
import { LOCALES } from '../i18n/settings'
import { clearUserCache, useCurrentUser } from '../lib/useCurrentUser'

export function AppHeader() {
  const [params] = useSearchParams()
  const searchParam = params.get('search') ?? ''

  return <HeaderBar key={searchParam} initialQuery={searchParam} />
}

function HeaderBar({ initialQuery }: { initialQuery: string }) {
  const navigate = useNavigate()
  const [query, setQuery] = useState(initialQuery)
  const { isRecruiter, isAdmin, hasProfile } = useCurrentUser()
  const { t, locale, setLocale, theme, setTheme } = useSettings()

  const authenticated = tokenStorage.isValid()

  const submit = (event: FormEvent) => {
    event.preventDefault()

    const trimmed = query.trim()
    navigate(trimmed ? `/positions?search=${encodeURIComponent(trimmed)}` : '/positions')
  }

  const logout = () => {
    tokenStorage.clear()
    clearUserCache()
    navigate('/', { replace: true })
  }

  return (
    <header className="apphead">
      <div className="apphead__inner">
        <Link to="/" className="apphead__brand">
          <span className="apphead__logo" aria-hidden="true">
            CV
          </span>

          <span>{t('app.name')}</span>

        </Link>

        <form className="apphead__search" role="search" onSubmit={submit}>
          <MagnifyingGlassIcon size={16} aria-hidden="true" />
          <input
            type="search"
            className="apphead__input"
            placeholder={t('header.searchPlaceholder')}
            aria-label={t('header.searchLabel')}
            value={query}
            onChange={(event) => setQuery(event.target.value)}
          />

        </form>

        <nav className="apphead__nav">
          <Link to="/positions" className="btn btn--ghost">
            {t('header.positions')}
          </Link>

          {authenticated ? (
            <>
              {isRecruiter && (
                <>
                  <Link to="/cvs/search" className="btn btn--ghost">
                    {t('header.cvs')}
                  </Link>

                  <Link to="/attributes" className="btn btn--ghost">
                    {t('header.attributes')}
                  </Link>

                </>

              )}

              {isAdmin && (
                <Link to="/admin/users" className="btn btn--ghost">
                  {t('header.users')}
                </Link>

              )}

              {/* Профиль заводится не каждому: у части рекрутёрских учёток
                  его нет, и ссылка вела бы на 404. */}
              {hasProfile && (
                <Link to="/profile" className="btn btn--ghost">
                  {t('header.profile')}
                </Link>
              )}

              <button type="button" className="btn btn--outline" onClick={logout}>
                {t('header.logout')}
              </button>

            </>

          ) : (
            <Link to="/login" className="btn btn--primary">
              {t('header.login')}
            </Link>

          )}

          <div className="apphead__prefs">
            <button
              type="button"
              className="iconbtn"
              onClick={() => setTheme(theme === 'dark' ? 'light' : 'dark')}
              aria-label={t(theme === 'dark' ? 'header.themeLight' : 'header.themeDark')}
              title={t(theme === 'dark' ? 'header.themeLight' : 'header.themeDark')}
            >
              {theme === 'dark' ? (
                <SunIcon size={16} aria-hidden="true" />
              ) : (
                <MoonIcon size={16} aria-hidden="true" />
              )}
            </button>

            <select
              className="langpick"
              value={locale}
              onChange={(event) => setLocale(event.target.value as typeof locale)}
              aria-label={t('header.language')}
              title={t('header.language')}
            >
              {LOCALES.map((code) => (
                <option key={code} value={code}>
                  {code.toUpperCase()}
                </option>

              ))}
            </select>

          </div>

        </nav>

      </div>

    </header>

  )
}
