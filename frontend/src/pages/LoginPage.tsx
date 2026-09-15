import { CaretLeftIcon, GithubLogoIcon, WarningCircleIcon } from '@phosphor-icons/react'
import { useEffect, useRef, useState, type FormEvent } from 'react'
import { authApi, RequestError, tokenStorage } from '../api/client'
import { UserRole, type SelectableRole } from '../api/types'
import { FormField } from '../components/FormField'
import { GoogleMark } from '../components/GoogleMark'
import { RoleSelector } from '../components/RoleSelector'
import { VerificationNotice } from '../components/VerificationNotice'
import { MIN_PASSWORD_LENGTH } from '../lib/passwordStrength'
import { ROLE_ICON, ROLE_PILL } from '../lib/roles'
import { useTranslation } from '../i18n/context'

type Step = 'pick' | 'form' | 'sent'
type Mode = 'login' | 'signup'

interface FieldErrors {
  email?: string
  password?: string
  passwordConfirmation?: string
}

export function LoginPage() {
  const t = useTranslation()
  // Шаг 1 — выбор роли, шаг 2 — форма, шаг 3 — «проверьте почту».
  // Роль спрашиваем первой: от неё зависит, что человек увидит после входа.
  const [step, setStep] = useState<Step>('pick')
  const [mode, setMode] = useState<Mode>('login')
  const [role, setRole] = useState<SelectableRole>(UserRole.Candidate)
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [passwordConfirmation, setPasswordConfirmation] = useState('')
  const [fieldErrors, setFieldErrors] = useState<FieldErrors>({})
  const [formError, setFormError] = useState<string | null>(null)
  const [submitting, setSubmitting] = useState(false)
  // Почему показан экран подтверждения: после регистрации или после
  // попытки входа в неподтверждённый аккаунт — тексты там разные.
  const [sentReason, setSentReason] = useState<'sent' | 'blocked'>('sent')

  const summaryRef = useRef<HTMLDivElement>(null)
  const headingRef = useRef<HTMLHeadingElement>(null)
  // Счётчик, а не флаг: две неудачные отправки подряд должны снова увести
  // фокус на сводку, даже если её текст не изменился.
  const [failedSubmits, setFailedSubmits] = useState(0)

  const isSignup = mode === 'signup'

  // Смена шага — это смена содержимого экрана: фокус переносим на заголовок,
  // иначе он остался бы на исчезнувшей кнопке и скринридер промолчал бы.
  useEffect(() => {
    if (step === 'form') {
      headingRef.current?.focus()
    }
  }, [step])

  // Фокус переносим в эффекте, а не в обработчике: в момент вызова блок
  // сводки ещё не отрисован и ref пустой.
  useEffect(() => {
    if (failedSubmits > 0) {
      summaryRef.current?.focus()
    }
  }, [failedSubmits])

  const validate = (): FieldErrors => {
    const errors: FieldErrors = {}

    if (!email.trim()) {
      errors.email = t('auth.emailRequired')
    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
      errors.email = t('auth.emailInvalid')
    }

    if (!password) {
      errors.password = t('auth.passwordRequired')
    } else if (isSignup && password.length < MIN_PASSWORD_LENGTH) {
      errors.password = t('auth.passwordShort', { min: MIN_PASSWORD_LENGTH })
    }

    if (isSignup) {
      if (!passwordConfirmation) {
        errors.passwordConfirmation = t('auth.repeatPassword')
      } else if (passwordConfirmation !== password) {
        errors.passwordConfirmation = t('auth.passwordsDiffer')
      }
    }

    return errors
  }

  const pickRole = (next: SelectableRole) => {
    setRole(next)
    setStep('form')
  }

  const switchMode = (next: Mode) => {
    if (next === mode) {
      return
    }

    setMode(next)
    setFieldErrors({})
    setFormError(null)
    // Повтор пароля не переносим между режимами: при входе поле исчезает,
    // и оставшееся значение всплыло бы при возврате к регистрации.
    setPasswordConfirmation('')
  }

  /**
   * Ошибка снимается сразу, как только пользователь правит поле, а новая
   * показывается только на blur: иначе «Укажите пароль» висит поверх уже
   * исправленного ввода, а проверка на каждый символ ругается на
   * недописанный адрес.
   */
  const editField =
    (field: keyof FieldErrors, setValue: (v: string) => void) => (value: string) => {
      setValue(value)

      if (fieldErrors[field]) {
        setFieldErrors((prev) => ({ ...prev, [field]: undefined }))
        setFormError(null)
      }
    }

  const handleSubmit = async (event: FormEvent) => {
    event.preventDefault()

    const errors = validate()
    setFieldErrors(errors)

    if (Object.keys(errors).length > 0) {
      setFormError(t('auth.checkFields'))
      setFailedSubmits((n) => n + 1)

      return
    }

    setFormError(null)
    setSubmitting(true)

    try {
      if (isSignup) {
        await authApi.register(email, password, passwordConfirmation, role)
        setSentReason('sent')
        setStep('sent')

        return
      }

      const response = await authApi.login(email, password)

      tokenStorage.set(response.token)
      window.location.href = '/'
    } catch (error) {
      if (error instanceof RequestError) {
        // Пароль верен, но адрес не подтверждён — это не ошибка ввода, а
        // незавершённая регистрация. Ведём на экран с повторной отправкой,
        // иначе человек упирается в сообщение без единого действия.
        if ('email_not_verified' === error.code) {
          setSentReason('blocked')
          setStep('sent')

          return
        }

        // violations приходят с бэкенда — раскладываем их по полям, чтобы
        // ошибка была видна рядом с проблемным вводом, а не только сверху.
        setFieldErrors({
          email: error.violations.email,
          password: error.violations.password,
          passwordConfirmation: error.violations.passwordConfirmation,
        })
        setFormError(error.message)
      } else {
        setFormError(t('auth.retry'))
      }

      setFailedSubmits((n) => n + 1)
    } finally {
      setSubmitting(false)
    }
  }

  const RoleIcon = ROLE_ICON[role]

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
          {step === 'pick' && (
            <>
              <h1 className="h1">{t('auth.welcome')}</h1>
              <p className="muted mt3" style={{ margin: 0 }}>
                {t('auth.pickRole')}
              </p>

              <RoleSelector onPick={pickRole} />

              <p className="t-sm muted-3 mt6" style={{ margin: 0 }}>
                {t('auth.terms')}
              </p>
            </>
          )}

          {step === 'form' && (
            <>
              <button type="button" className="auth__back" onClick={() => setStep('pick')}>
                <CaretLeftIcon size={14} aria-hidden="true" />
                {t('auth.otherRole')}
              </button>

              <div className="row row--between g3">
                <h1 ref={headingRef} tabIndex={-1} className="h2 app-step-title">
                  {t(isSignup ? 'auth.signupTitle' : 'auth.loginTitle')}
                </h1>

                <span className="rolepill">
                  <RoleIcon size={13} aria-hidden="true" />
                  {t(ROLE_PILL[role])}
                  <button type="button" className="rolepill__change" onClick={() => setStep('pick')}>
                    {t('auth.change')}
                  </button>
                </span>
              </div>

              <div className="authtabs mt5" role="group" aria-label={t('auth.tabsLabel')}>
                <button
                  type="button"
                  className={`authtabs__btn${!isSignup ? ' is-on' : ''}`}
                  aria-pressed={!isSignup}
                  onClick={() => switchMode('login')}
                >
                  {t('auth.signin')}
                </button>
                <button
                  type="button"
                  className={`authtabs__btn${isSignup ? ' is-on' : ''}`}
                  aria-pressed={isSignup}
                  onClick={() => switchMode('signup')}
                >
                  {t('auth.signup')}
                </button>
              </div>

              <form className="col g4 mt5" onSubmit={handleSubmit} noValidate>
                {formError && (
                  <div ref={summaryRef} tabIndex={-1} role="alert" className="notice notice--error">
                    <WarningCircleIcon size={16} weight="fill" aria-hidden="true" />
                    <span>{formError}</span>
                  </div>
                )}

                <FormField
                  label={t('auth.workEmail')}
                  type="email"
                  value={email}
                  onChange={editField('email', setEmail)}
                  onBlur={() => setFieldErrors((prev) => ({ ...prev, email: validate().email }))}
                  error={fieldErrors.email}
                  autoComplete="email"
                  placeholder="name@example.kz"
                  disabled={submitting}
                />

                <FormField
                  label={t('auth.password')}
                  type="password"
                  value={password}
                  onChange={editField('password', setPassword)}
                  onBlur={() =>
                    setFieldErrors((prev) => ({ ...prev, password: validate().password }))
                  }
                  error={fieldErrors.password}
                  autoComplete={isSignup ? 'new-password' : 'current-password'}
                  placeholder="••••••••"
                  disabled={submitting}
                  showStrength={isSignup}
                />

                {isSignup && (
                  <FormField
                    label={t('auth.repeatLabel')}
                    type="password"
                    value={passwordConfirmation}
                    onChange={editField('passwordConfirmation', setPasswordConfirmation)}
                    onBlur={() =>
                      setFieldErrors((prev) => ({
                        ...prev,
                        passwordConfirmation: validate().passwordConfirmation,
                      }))
                    }
                    error={fieldErrors.passwordConfirmation}
                    autoComplete="new-password"
                    placeholder={t('auth.repeatPlaceholder')}
                    disabled={submitting}
                  />
                )}

                <button
                  type="submit"
                  className="btn btn--primary btn--lg btn--block"
                  disabled={submitting}
                >
                  {submitting
                    ? isSignup
                      ? t('auth.creating')
                      : t('auth.signingIn')
                    : isSignup
                      ? t('auth.createAccount')
                      : t('auth.signin')}
                </button>
              </form>

              <div className="auth__or mt5">{t('auth.or')}</div>

              <div className="col g2 mt5">
                <button
                  type="button"
                  className="ssobtn"
                  onClick={() => authApi.startOAuth('google', role)}
                  disabled={submitting}
                >
                  <GoogleMark />
                  {t('auth.withGoogle')}
                </button>

                <button
                  type="button"
                  className="ssobtn"
                  onClick={() => authApi.startOAuth('github', role)}
                  disabled={submitting}
                >
                  <GithubLogoIcon size={17} weight="fill" aria-hidden="true" />
                  {t('auth.withGithub')}
                </button>
              </div>
            </>
          )}

          {step === 'sent' && (
            <VerificationNotice
              email={email}
              reason={sentReason}
              onBack={() => {
                setStep('form')
                setMode('login')
                setPassword('')
                setPasswordConfirmation('')
              }}
            />
          )}
        </div>

        <p className="auth__foot" style={{ margin: 0 }}>
          © {new Date().getFullYear()} CVMatch
        </p>
      </div>
    </div>
  )
}
