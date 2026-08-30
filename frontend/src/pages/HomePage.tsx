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

  if (state.kind === 'loading') {
    return (
      <main className="min-vh-100 d-flex align-items-center justify-content-center">
        <span className="spinner-border text-primary" role="status" aria-label="Загрузка" />
      </main>
    )
  }

  if (state.kind === 'error') {
    return (
      <main className="container py-5">
        <div className="alert alert-danger" role="alert">
          {state.message}
        </div>
      </main>
    )
  }

  return (
    <main className="container py-5">
      <div className="row justify-content-center">
        <div className="col-12 col-lg-7">
          <div className="app-surface p-4">
            <h1 className="h5 mb-3">Вход выполнен</h1>

            <dl className="row small mb-4">
              <dt className="col-4">Email</dt>
              <dd className="col-8">{state.user.email}</dd>

              <dt className="col-4">Роли</dt>
              <dd className="col-8">{state.user.roles.join(', ')}</dd>

              <dt className="col-4">Профиль</dt>
              <dd className="col-8">#{state.user.profileId}</dd>
            </dl>

            <button type="button" className="btn btn-outline-secondary" onClick={logout}>
              Выйти
            </button>
          </div>
        </div>
      </div>
    </main>
  )
}
