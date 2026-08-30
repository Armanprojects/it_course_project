import { CheckCircleIcon, EnvelopeSimpleIcon, WarningCircleIcon } from '@phosphor-icons/react'
import { useEffect, useRef, useState } from 'react'
import { authApi, RequestError } from '../api/client'

interface Props {
  email: string
  /**
   * 'sent' — письмо только что отправлено при регистрации.
   * 'blocked' — попытка входа в неподтверждённый аккаунт: письмо старое,
   * и человеку нужно объяснить, почему его не пустили.
   */
  reason: 'sent' | 'blocked'
  onBack: () => void
}

/**
 * Экран «проверьте почту»: аккаунт создан, но неактивен до перехода по ссылке.
 * Здесь же живёт повторная отправка — единственное доступное действие.
 */
export function VerificationNotice({ email, reason, onBack }: Props) {
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
      await authApi.resendVerification(email)

      // Ответ бэкенда намеренно уклончив («если аккаунт существует…») —
      // он защищает от перебора адресов на публичном эндпоинте. Здесь же
      // человек только что ввёл пароль от этого аккаунта, так что
      // недосказанность выглядела бы странно.
      setNotice({ kind: 'ok', text: `Отправили новое письмо на ${email}. Проверьте входящие и папку «Спам».` })
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

  const isBlocked = 'blocked' === reason

  return (
    <div className="col g4">
      <EnvelopeSimpleIcon size={40} weight="light" aria-hidden="true" />

      <div>
        <h1 ref={headingRef} tabIndex={-1} className="h2 app-step-title">
          {isBlocked ? 'Подтвердите почту' : 'Проверьте почту'}
        </h1>
        <p className="muted mt3" style={{ margin: 0 }}>
          {isBlocked
            ? 'Аккаунт создан, но вход откроется после подтверждения адреса '
            : 'Мы отправили ссылку для подтверждения на адрес '}
          {/* Адрес выделен: по нему пользователь проверяет, не опечатался ли. */}
          <b style={{ color: 'var(--text-1)', wordBreak: 'break-all' }}>{email}</b>
        </p>
      </div>

      <p className="t-sm muted-3" style={{ margin: 0 }}>
        {isBlocked
          ? 'Найдите наше письмо и перейдите по ссылке из него. Если письмо не пришло или ссылка устарела, отправим новое.'
          : 'Перейдите по ссылке из письма, чтобы завершить регистрацию. Ссылка действует 24 часа.'}
      </p>

      {notice && (
        <div
          role={notice.kind === 'error' ? 'alert' : 'status'}
          className={`notice ${notice.kind === 'error' ? 'notice--error' : 'notice--ok'}`}
        >
          {notice.kind === 'error' ? (
            <WarningCircleIcon size={16} weight="fill" aria-hidden="true" />
          ) : (
            <CheckCircleIcon size={16} weight="fill" aria-hidden="true" />
          )}
          <span>{notice.text}</span>
        </div>
      )}

      <div className="col g2">
        <button
          type="button"
          className={`btn btn--lg ${isBlocked ? 'btn--primary' : 'btn--outline'}`}
          onClick={resend}
          disabled={sending}
        >
          {sending ? 'Отправляем…' : 'Отправить письмо ещё раз'}
        </button>

        <button type="button" className="btn btn--ghost" onClick={onBack} disabled={sending}>
          Вернуться ко входу
        </button>
      </div>
    </div>
  )
}
