import { CheckCircleIcon, EnvelopeSimpleIcon, WarningCircleIcon } from '@phosphor-icons/react'
import { useEffect, useRef, useState } from 'react'
import { authApi, RequestError } from '../api/client'

interface Props {
  email: string
  onBack: () => void
}

/**
 * Экран после регистрации: аккаунт создан, но неактивен до перехода по ссылке.
 */
export function VerificationNotice({ email, onBack }: Props) {
  const [sending, setSending] = useState(false)
  const [notice, setNotice] = useState<{ kind: 'ok' | 'error'; text: string } | null>(null)
  const headingRef = useRef<HTMLHeadingElement>(null)

  // Экран сменился — уводим фокус на заголовок, иначе он остался бы на
  // исчезнувшей кнопке отправки и скринридер промолчал бы о переходе.
  useEffect(() => {
    headingRef.current?.focus()
  }, [])

  const resend = async () => {
    setSending(true)
    setNotice(null)

    try {
      const response = await authApi.resendVerification(email)
      setNotice({ kind: 'ok', text: response.message })
    } catch (error) {
      setNotice({
        kind: 'error',
        text:
          error instanceof RequestError
            ? error.message
            : 'Не удалось отправить письмо. Попробуйте позже.',
      })
    } finally {
      setSending(false)
    }
  }

  return (
    <div className="text-center">
      <EnvelopeSimpleIcon
        size={44}
        weight="light"
        aria-hidden="true"
        className="text-primary mb-3"
      />

      <h2 ref={headingRef} tabIndex={-1} className="h5 mb-2 app-step-title">
        Проверьте почту
      </h2>

      <p className="text-body-secondary small mb-1">
        Мы отправили ссылку для подтверждения на адрес
      </p>
      {/* Адрес отдельной строкой и не переносится посреди слова: по нему
          пользователь проверяет, не опечатался ли он при вводе. */}
      <p className="fw-semibold mb-3 text-break">{email}</p>

      <p className="text-body-secondary small mb-4">
        Перейдите по ссылке из письма, чтобы завершить регистрацию. Ссылка действует 24 часа.
      </p>

      {notice && (
        <div
          role={notice.kind === 'error' ? 'alert' : 'status'}
          className={`alert ${notice.kind === 'error' ? 'alert-danger' : 'alert-success'} d-flex gap-2 align-items-start py-2 px-3 text-start`}
        >
          {notice.kind === 'error' ? (
            <WarningCircleIcon size={20} weight="fill" aria-hidden="true" className="flex-shrink-0 mt-1" />
          ) : (
            <CheckCircleIcon size={20} weight="fill" aria-hidden="true" className="flex-shrink-0 mt-1" />
          )}
          <span className="small">{notice.text}</span>
        </div>
      )}

      <div className="d-flex flex-column gap-2">
        <button
          type="button"
          className="btn btn-outline-primary"
          onClick={resend}
          disabled={sending}
        >
          {sending ? (
            <>
              <span className="spinner-border spinner-border-sm me-2" aria-hidden="true" />
              Отправляем…
            </>
          ) : (
            'Отправить письмо ещё раз'
          )}
        </button>

        <button type="button" className="btn btn-link" onClick={onBack} disabled={sending}>
          Вернуться ко входу
        </button>
      </div>
    </div>
  )
}
