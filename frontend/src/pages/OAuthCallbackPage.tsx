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
    'Провайдер не передал подтверждённый email. Откройте доступ к email или войдите по паролю.',
  identity_taken: 'Этот аккаунт уже привязан к другому пользователю.',
  account_blocked: 'Аккаунт заблокирован. Обратитесь к администратору.',
  unknown_provider: 'Неизвестный провайдер входа.',
}

/**
 * Бэкенд возвращает браузер сюда с токеном во фрагменте URL.
 * Фрагмент не уходит на сервер — не попадает в логи nginx и в Referer.
 */
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

export function OAuthCallbackPage() {
  const [{ error }] = useState(consumeCallback)

  if (!error) {
    return (
      <main className="min-vh-100 d-flex align-items-center justify-content-center">
        <div className="text-center">
          <span className="spinner-border text-primary mb-3" role="status" aria-hidden="true" />
          <p className="text-body-secondary mb-0">Завершаем вход…</p>
        </div>
      </main>
    )
  }

  return (
    <main className="min-vh-100 d-flex align-items-center py-5">
      <div className="container">
        <div className="row justify-content-center">
          <div className="col-12 col-sm-10 col-md-6 col-lg-5">
            <div className="app-surface p-4 text-center">
              <WarningCircleIcon
                size={40}
                weight="fill"
                aria-hidden="true"
                className="text-danger mb-3"
              />
              <h1 className="h5 mb-2">Вход не выполнен</h1>
              <p className="text-body-secondary small">{error}</p>
              <Link to="/login" className="btn btn-primary mt-2">
                Вернуться ко входу
              </Link>
            </div>
          </div>
        </div>
      </div>
    </main>
  )
}
