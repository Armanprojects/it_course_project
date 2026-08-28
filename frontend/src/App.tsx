import { useEffect, useState } from 'react'
import axios from 'axios'

/** Ответ GET /api/health. */
interface HealthResponse {
  status: string
  message: string
  php: string
  database: {
    connected: boolean
    server?: string | null
    error?: string
  }
  timestamp: string
}

type State =
  | { kind: 'loading' }
  | { kind: 'ok'; data: HealthResponse }
  | { kind: 'error'; error: string }

function App() {
  const [state, setState] = useState<State>({ kind: 'loading' })

  const load = () => {
    setState({ kind: 'loading' })

    // Относительный путь, а не http://localhost:8000 — фронтенд и API
    // за одним nginx, поэтому origin общий и CORS не нужен.
    axios
      .get<HealthResponse>('/api/health')
      .then((response) => setState({ kind: 'ok', data: response.data }))
      .catch((error: unknown) =>
        setState({
          kind: 'error',
          error: axios.isAxiosError(error)
            ? (error.message ?? 'Request failed')
            : 'Unexpected error',
        }),
      )
  }

  useEffect(load, [])

  return (
    <div className="container py-5">
      <div className="row justify-content-center">
        <div className="col-12 col-lg-8">
          <h1 className="h3 mb-1">CV Management System</h1>
          <p className="text-body-secondary mb-4">
            Этап 1 · проверка связки Docker → nginx → Symfony → PostgreSQL
          </p>

          <div className="card shadow-sm">
            <div className="card-header d-flex align-items-center justify-content-between">
              <span className="fw-semibold">GET /api/health</span>
              <button
                type="button"
                className="btn btn-sm btn-outline-secondary"
                onClick={load}
                disabled={state.kind === 'loading'}
              >
                Обновить
              </button>
            </div>

            <div className="card-body">
              {state.kind === 'loading' && (
                <div className="d-flex align-items-center gap-2 text-body-secondary">
                  <span className="spinner-border spinner-border-sm" role="status" />
                  <span>Запрос к API…</span>
                </div>
              )}

              {state.kind === 'error' && (
                <div className="alert alert-danger mb-0" role="alert">
                  <div className="fw-semibold">Бэкенд недоступен</div>
                  <div className="small">{state.error}</div>
                </div>
              )}

              {state.kind === 'ok' && (
                <>
                  <div className="alert alert-success" role="alert">
                    <span className="fw-semibold">{state.data.message}</span>
                  </div>

                  <dl className="row mb-0 small">
                    <dt className="col-sm-4">Статус API</dt>
                    <dd className="col-sm-8">{state.data.status}</dd>

                    <dt className="col-sm-4">Версия PHP</dt>
                    <dd className="col-sm-8">{state.data.php}</dd>

                    <dt className="col-sm-4">PostgreSQL</dt>
                    <dd className="col-sm-8">
                      {state.data.database.connected ? (
                        <span className="badge text-bg-success">подключена</span>
                      ) : (
                        <>
                          <span className="badge text-bg-danger">недоступна</span>
                          <div className="text-danger mt-1">{state.data.database.error}</div>
                        </>
                      )}
                    </dd>

                    {state.data.database.server && (
                      <>
                        <dt className="col-sm-4">Версия сервера</dt>
                        <dd className="col-sm-8 text-break">{state.data.database.server}</dd>
                      </>
                    )}

                    <dt className="col-sm-4">Время ответа</dt>
                    <dd className="col-sm-8">{state.data.timestamp}</dd>
                  </dl>
                </>
              )}
            </div>
          </div>
        </div>
      </div>
    </div>
  )
}

export default App
