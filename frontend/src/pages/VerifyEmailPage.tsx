import { CheckCircleIcon, WarningCircleIcon } from '@phosphor-icons/react'
import { useEffect, useRef, useState } from 'react'
import { useNavigate, useSearchParams } from 'react-router-dom'
import { authApi, RequestError, tokenStorage } from '../api/client'

type State =
  | { kind: 'verifying' }
  | { kind: 'done' }
  | { kind: 'failed'; code: string; message: string }

/** Тексты под коды ошибок из App\Exception\AuthException. */
const MESSAGES: Record<string, string> = {
  invalid_verification_token: 'Ссылка недействительна. Проверьте, что скопировали её полностью.',
  verification_token_expired: 'Срок действия ссылки истёк. Запросите новое письмо.',
  verification_token_used: 'Эта ссылка уже использована. Попробуйте войти.',
  account_blocked: 'Аккаунт заблокирован. Обратитесь к администратору.',
}

export function VerifyEmailPage() {
  const [params] = useSearchParams()
  const navigate = useNavigate()
  const headingRef = useRef<HTMLHeadingElement>(null)

  const token = params.get('token') ?? ''

  // Отсутствие токена видно из URL до первого рендера — это начальное
  // состояние, а не результат синхронизации, поэтому без эффекта.
  const [state, setState] = useState<State>(() =>
    token
      ? { kind: 'verifying' }
      : {
          kind: 'failed',
          code: 'invalid_verification_token',
          message: MESSAGES.invalid_verification_token,
        },
  )

  useEffect(() => {
    if (!token) {
      return
    }

    let cancelled = false

    authApi
      .verifyEmail(token)
      .then((response) => {
        if (cancelled) {
          return
        }

        // Подтверждение сразу логинит: заставлять вводить пароль после
        // перехода по ссылке — лишний шаг, данные мы уже проверили.
        tokenStorage.set(response.token)
        setState({ kind: 'done' })

        // Небольшая пауза, чтобы человек увидел подтверждение, а не мигание.
        setTimeout(() => navigate('/', { replace: true }), 1500)
      })
      .catch((error: unknown) => {
        if (cancelled) {
          return
        }

        const code = error instanceof RequestError ? error.code : 'unknown'

        setState({
          kind: 'failed',
          code,
          message:
            MESSAGES[code] ??
            (error instanceof RequestError ? error.message : 'Не удалось подтвердить адрес.'),
        })
      })

    // Клиент мог предзагрузить ссылку, а StrictMode в dev вызывает эффект
    // дважды — флаг не даёт обработать ответ уже размонтированного экрана.
    return () => {
      cancelled = true
    }
  }, [token, navigate])

  useEffect(() => {
    if (state.kind !== 'verifying') {
      headingRef.current?.focus()
    }
  }, [state.kind])

  return (
    <main className="min-vh-100 d-flex align-items-center py-5">
      <div className="container">
        <div className="row justify-content-center">
          <div className="col-12 col-sm-10 col-md-7 col-lg-5">
            <div className="app-surface p-4 text-center">
              {state.kind === 'verifying' && (
                <>
                  <span
                    className="spinner-border text-primary mb-3"
                    role="status"
                    aria-hidden="true"
                  />
                  <p className="text-body-secondary mb-0">Подтверждаем адрес…</p>
                </>
              )}

              {state.kind === 'done' && (
                <>
                  <CheckCircleIcon
                    size={44}
                    weight="fill"
                    aria-hidden="true"
                    className="text-success mb-3"
                  />
                  <h1 ref={headingRef} tabIndex={-1} className="h5 mb-2 app-step-title">
                    Адрес подтверждён
                  </h1>
                  <p className="text-body-secondary small mb-0">Открываем приложение…</p>
                </>
              )}

              {state.kind === 'failed' && (
                <>
                  <WarningCircleIcon
                    size={44}
                    weight="fill"
                    aria-hidden="true"
                    className="text-danger mb-3"
                  />
                  <h1 ref={headingRef} tabIndex={-1} className="h5 mb-2 app-step-title">
                    Не удалось подтвердить
                  </h1>
                  <p className="text-body-secondary small mb-4">{state.message}</p>

                  <button
                    type="button"
                    className="btn btn-primary"
                    onClick={() => navigate('/login', { replace: true })}
                  >
                    Перейти ко входу
                  </button>
                </>
              )}
            </div>
          </div>
        </div>
      </div>
    </main>
  )
}
