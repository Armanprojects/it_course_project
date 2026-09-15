import { WarningCircleIcon } from '@phosphor-icons/react'
import { useState } from 'react'
import { Link } from 'react-router-dom'
import { tokenStorage } from '../api/client'
import { useTranslation } from '../i18n/context'
import type { MessageKey } from '../i18n/messages'

/** Коды ошибок из App\Exception\AuthException и OAuthController. */
const ERROR_KEYS: Record<string, MessageKey> = {
  access_denied: 'oauth.accessDenied',
  missing_code: 'oauth.missingCode',
  provider_failed: 'oauth.providerFailed',
  provider_email_missing: 'oauth.noEmail',
  identity_taken: 'oauth.identityTaken',
  account_blocked: 'oauth.blocked',
  unknown_provider: 'oauth.unknownProvider',
}

/**
 * Разбирается один раз на модуле, а не в эффекте: фрагмент URL — это входные
 * данные навигации, они известны до первого рендера и не меняются.
 */
/**
 * Возвращает ключ ошибки, а не текст: функция вызывается вне React, языка
 * ещё не знает — переводит компонент.
 */
function consumeCallback(): { redirecting: boolean; error: MessageKey | null } {
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
    error: ERROR_KEYS[code] ?? 'oauth.generic',
  }
}

/**
 * Бэкенд возвращает браузер сюда с токеном во фрагменте URL.
 * Фрагмент не уходит на сервер — не попадает в логи nginx и в Referer.
 */
export function OAuthCallbackPage() {
  const t = useTranslation()
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
              {t('oauth.finishing')}
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
                <h1 className="h2">{t('oauth.failedTitle')}</h1>
                <p className="muted mt3" style={{ margin: 0 }}>
                  {t(error)}
                </p>
              </div>

              <Link
                to="/login"
                className="btn btn--primary btn--lg"
                style={{ alignSelf: 'flex-start' }}
              >
                {t('verify.backToLogin')}
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
