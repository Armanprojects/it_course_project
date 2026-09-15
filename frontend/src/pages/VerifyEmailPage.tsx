import { CheckCircleIcon, WarningCircleIcon } from '@phosphor-icons/react'
import { useEffect, useRef, useState } from 'react'
import { useNavigate, useSearchParams } from 'react-router-dom'
import { authApi, RequestError, tokenStorage } from '../api/client'
import { useTranslation } from '../i18n/context'
import type { MessageKey } from '../i18n/messages'

type State =
  | { kind: 'verifying' }
  | { kind: 'done' }
  | { kind: 'failed'; code: string; message: string | null }

/** Ключи под коды ошибок из App\Exception\AuthException. */
const MESSAGE_KEYS: Record<string, MessageKey> = {
  invalid_verification_token: 'verify.invalidToken',
  verification_token_expired: 'verify.expiredToken',
  verification_token_used: 'verify.usedToken',
  account_blocked: 'verify.blocked',
}

export function VerifyEmailPage() {
  const t = useTranslation()
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
          // Текст берётся из MESSAGE_KEYS при отрисовке — здесь его нет.
          message: null,
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
          // Сообщение сервера — запасной вариант: свой перевод есть не под
          // каждый код, а показать что-то осмысленное надо всегда.
          message: error instanceof RequestError ? error.message : null,
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
    <div className="auth">
      <div className="auth__pane">
        <div className="auth__brand">
          <span className="auth__logo" aria-hidden="true">
            C
          </span>
          <span>CVMatch</span>
        </div>

        <div className="auth__box">
          {state.kind === 'verifying' && (
            <p className="muted" style={{ margin: 0 }} role="status">
              {t('verify.verifying')}
            </p>
          )}

          {state.kind === 'done' && (
            <div className="col g4">
              <CheckCircleIcon
                size={40}
                weight="fill"
                aria-hidden="true"
                style={{ color: 'var(--ok-fg)' }}
              />
              <div>
                <h1 ref={headingRef} tabIndex={-1} className="h2 app-step-title">
                  {t('verify.confirmed')}
                </h1>
                <p className="muted mt3" style={{ margin: 0 }}>
                  {t('verify.opening')}
                </p>
              </div>
            </div>
          )}

          {state.kind === 'failed' && (
            <div className="col g4">
              <WarningCircleIcon
                size={40}
                weight="fill"
                aria-hidden="true"
                style={{ color: 'var(--err-fg)' }}
              />
              <div>
                <h1 ref={headingRef} tabIndex={-1} className="h2 app-step-title">
                  {t('verify.failedTitle')}
                </h1>
                <p className="muted mt3" style={{ margin: 0 }}>
                  {MESSAGE_KEYS[state.code] !== undefined
                    ? t(MESSAGE_KEYS[state.code])
                    : (state.message ?? t('verify.failed'))}
                </p>
              </div>

              <button
                type="button"
                className="btn btn--primary btn--lg"
                style={{ alignSelf: 'flex-start' }}
                onClick={() => navigate('/login', { replace: true })}
              >
                {t('verify.goToLogin')}
              </button>
            </div>
          )}
        </div>

        <p className="auth__foot" style={{ margin: 0 }}>
          © {new Date().getFullYear()} CVMatch
        </p>
      </div>
    </div>
  )
}
