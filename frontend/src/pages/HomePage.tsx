import { useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { authApi, RequestError, tokenStorage } from '../api/client'
import type { User } from '../api/types'

type State =
  | { kind: 'loading' }
  | { kind: 'authenticated'; user: User }
  | { kind: 'error'; message: string }

/**
 * Временная главная страница: подтверждает, что токен работает.
 * Будет заменена реальной главной с позициями и статистикой.
 */
export function HomePage() {
  const [state, setState] = useState<State>({ kind: 'loading' })
  const navigate = useNavigate()

  useEffect(() => {
    if (!tokenStorage.get()) {
      navigate('/login', { replace: true })

      return
    }

    authApi
      .me()
      .then((user) => setState({ kind: 'authenticated', user }))
      .catch((error: unknown) => {
        // Протухший или невалидный токен — на вход, а не в тупик с ошибкой.
        if (error instanceof RequestError && error.code !== 'network_error') {
          tokenStorage.clear()
          navigate('/login', { replace: true })

          return
        }

        setState({
          kind: 'error',
          message: error instanceof RequestError ? error.message : 'Непредвиденная ошибка.',
        })
      })
  }, [navigate])

  const logout = () => {
    tokenStorage.clear()
    navigate('/login', { replace: true })
  }

  return (
    <div style={{ maxWidth: 640, margin: '0 auto', padding: 'var(--s10) var(--s5)' }}>
      {state.kind === 'loading' && (
        <p className="muted" style={{ margin: 0 }} role="status">
          Загружаем…
        </p>
      )}

      {state.kind === 'error' && (
        <div className="notice notice--error" role="alert">
          <span>{state.message}</span>
        </div>
      )}

      {state.kind === 'authenticated' && (
        <div className="col g4">
          <h1 className="h2">Вход выполнен</h1>

          <dl className="col g2 t-sm" style={{ margin: 0 }}>
            <div className="row g3">
              <dt className="muted" style={{ minWidth: 120 }}>
                Почта
              </dt>
              <dd style={{ margin: 0 }}>{state.user.email}</dd>
            </div>
            <div className="row g3">
              <dt className="muted" style={{ minWidth: 120 }}>
                Роли
              </dt>
              <dd style={{ margin: 0 }}>{state.user.roles.join(', ')}</dd>
            </div>
            <div className="row g3">
              <dt className="muted" style={{ minWidth: 120 }}>
                Профиль
              </dt>
              <dd style={{ margin: 0 }}>#{state.user.profileId}</dd>
            </div>
          </dl>

          <button
            type="button"
            className="btn btn--outline"
            style={{ alignSelf: 'flex-start' }}
            onClick={logout}
          >
            Выйти
          </button>
        </div>
      )}
    </div>
  )
}
