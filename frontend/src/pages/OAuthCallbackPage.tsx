import { WarningCircleIcon } from '@phosphor-icons/react'
import { useState } from 'react'
import { Link } from 'react-router-dom'
import { tokenStorage } from '../api/client'

/** Коды ошибок из App\Exception\AuthException и OAuthController. */
const ERROR_MESSAGES: Record<string, string> = {
  access_denied: 'Вы отменили вход через провайдера.',
  missing_code: 'Провайдер не передал код авторизации. Попробуйте ещё раз.',
  provider_failed: 'Провайдер отклонил запрос. Попробуйте ещё раз.',
  provider_email_missing:
    'Провайдер не передал подтверждённый адрес. Откройте доступ к почте или войдите по паролю.',
  identity_taken: 'Этот аккаунт уже привязан к другому пользователю.',
  account_blocked: 'Аккаунт заблокирован. Обратитесь к администратору.',
  unknown_provider: 'Неизвестный провайдер входа.',
}

/**
 * Разбирается один раз на модуле, а не в эффекте: фрагмент URL — это входные
 * данные навигации, они известны до первого рендера и не меняются.
 */
function consumeCallback(): { redirecting: boolean; error: string | null } {
  const params = new URLSearchParams(window.location.hash.slice(1))
  const token = params.get('token')

  if (token) {
    tokenStorage.set(token)

    // replace, а не push: возврат «назад» не должен вести на колбэк
    // с уже израсходованным токеном в адресе.
    window.location.replace('/')

    return { redirecting: true, error: null }
  }

  const code = params.get('error') ?? ''

  return {
    redirecting: false,
    error: ERROR_MESSAGES[code] ?? 'Не удалось войти через провайдера.',
  }
}

/**
 * Бэкенд возвращает браузер сюда с токеном во фрагменте URL.
 * Фрагмент не уходит на сервер — не попадает в логи nginx и в Referer.
 */
export function OAuthCallbackPage() {
  const [{ error }] = useState(consumeCallback)

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
          {!error ? (
            <p className="muted" style={{ margin: 0 }} role="status">
              Завершаем вход…
            </p>
          ) : (
            <div className="col g4">
              <WarningCircleIcon
                size={40}
                weight="fill"
                aria-hidden="true"
                style={{ color: 'var(--err-fg)' }}
              />
              <div>
                <h1 className="h2">Вход не выполнен</h1>
                <p className="muted mt3" style={{ margin: 0 }}>
                  {error}
                </p>
              </div>

              <Link
                to="/login"
                className="btn btn--primary btn--lg"
                style={{ alignSelf: 'flex-start' }}
              >
                Вернуться ко входу
              </Link>
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
