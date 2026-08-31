import { MagnifyingGlassIcon } from '@phosphor-icons/react'
import { useState, type FormEvent } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router-dom'
import { tokenStorage } from '../api/client'
import { clearUserCache, useCurrentUser } from '../lib/useCurrentUser'

/**
 * Шапка есть на каждой странице, и поиск в ней — тоже: требование задания.
 * Отправка всегда уводит на каталог, потому что результат — это таблица
 * позиций, а не отдельный экран выдачи.
 */
export function AppHeader() {
  const [params] = useSearchParams()
  const searchParam = params.get('search') ?? ''

  // key сбрасывает состояние поля при смене запроса в URL — переход «назад»
  // должен вернуть и текст в поле, иначе шапка покажет одно, а таблица
  // отфильтрует по другому. Через эффект это стоило бы лишнего рендера.
  return <HeaderBar key={searchParam} initialQuery={searchParam} />
}

function HeaderBar({ initialQuery }: { initialQuery: string }) {
  const navigate = useNavigate()
  const [query, setQuery] = useState(initialQuery)
  const { isRecruiter } = useCurrentUser()

  const authenticated = tokenStorage.get() !== null

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
          <span>Hiring Platform</span>
        </Link>

        <form className="apphead__search" role="search" onSubmit={submit}>
          <MagnifyingGlassIcon size={16} aria-hidden="true" />
          <input
            type="search"
            className="apphead__input"
            placeholder="Поиск позиций…"
            aria-label="Поиск по позициям"
            value={query}
            onChange={(event) => setQuery(event.target.value)}
          />
        </form>

        <nav className="apphead__nav">
          <Link to="/positions" className="btn btn--ghost">
            Позиции
          </Link>

          {authenticated ? (
            <>
              {/* Поиск по резюме и библиотека — инструменты рекрутера, в
                  навигации кандидата им делать нечего. */}
              {isRecruiter && (
                <>
                  <Link to="/cvs/search" className="btn btn--ghost">
                    Резюме
                  </Link>
                  <Link to="/attributes" className="btn btn--ghost">
                    Атрибуты
                  </Link>
                </>
              )}

              <Link to="/profile" className="btn btn--ghost">
                Профиль
              </Link>
              <button type="button" className="btn btn--outline" onClick={logout}>
                Выйти
              </button>
            </>
          ) : (
            <Link to="/login" className="btn btn--primary">
              Войти
            </Link>
          )}
        </nav>
      </div>
    </header>
  )
}
