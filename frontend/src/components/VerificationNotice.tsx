import { CheckCircleIcon, EnvelopeSimpleIcon, WarningCircleIcon } from '@phosphor-icons/react'
import { useEffect, useRef, useState } from 'react'
import { authApi } from '../api/client'
import { useTranslation } from '../i18n/context'
import { useErrorText } from '../i18n/useErrorText'

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
  const t = useTranslation()
  const errorText = useErrorText()
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
      setNotice({ kind: 'ok', text: t('verify.resent', { email }) })
    } catch (error) {
      setNotice({
        kind: 'error',
        text:
          errorText(error, 'verify.resendFailed')
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
          {t(isBlocked ? 'verify.confirmEmail' : 'verify.checkEmail')}
        </h1>
        <p className="muted mt3" style={{ margin: 0 }}>
          {isBlocked
            ? t('verify.blockedLead')
            : t('verify.sentLead')}
          {/* Адрес выделен: по нему пользователь проверяет, не опечатался ли. */}
          <b style={{ color: 'var(--text-1)', wordBreak: 'break-all' }}>{email}</b>
        </p>
      </div>

      <p className="t-sm muted-3" style={{ margin: 0 }}>
        {isBlocked
          ? t('verify.blockedHint')
          : t('verify.sentHint')}
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
          {t(sending ? 'verify.sending' : 'verify.resend')}
        </button>

        <button type="button" className="btn btn--ghost" onClick={onBack} disabled={sending}>
          {t('verify.backToLogin')}
        </button>
      </div>
    </div>
  )
}
