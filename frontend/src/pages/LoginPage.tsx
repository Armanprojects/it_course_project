import {
  ArrowLeftIcon,
  GithubLogoIcon,
  GoogleLogoIcon,
  WarningCircleIcon,
} from '@phosphor-icons/react'
import { useEffect, useRef, useState, type FormEvent } from 'react'
import { authApi, RequestError, tokenStorage } from '../api/client'
import { UserRole, type SelectableRole } from '../api/types'
import { FormField } from '../components/FormField'
import { RoleSelector } from '../components/RoleSelector'
import { VerificationNotice } from '../components/VerificationNotice'

type Mode = 'login' | 'register'

interface FieldErrors {
  email?: string
  password?: string
  passwordConfirmation?: string
}

const ROLE_LABEL: Record<SelectableRole, string> = {
  [UserRole.Candidate]: 'Я ищу работу',
  [UserRole.Recruiter]: 'Я ищу сотрудников',
}

export function LoginPage() {
  // Шаг 1 — выбор роли и способа входа, шаг 2 — учётные данные.
  // Разделение убирает форму с первого экрана: пользователь сначала
  // отвечает на один вопрос, а не смотрит сразу на всё сразу.
  // Шаг 3 — экран «проверьте почту» после успешной регистрации.
  const [step, setStep] = useState<1 | 2 | 3>(1)
  const [mode, setMode] = useState<Mode>('login')
  const [role, setRole] = useState<SelectableRole>(UserRole.Candidate)
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [passwordConfirmation, setPasswordConfirmation] = useState('')
  const [fieldErrors, setFieldErrors] = useState<FieldErrors>({})
  const [formError, setFormError] = useState<string | null>(null)
  const [submitting, setSubmitting] = useState(false)

  // После неудачной отправки фокус уходит на сводку ошибок: иначе
  // пользователь со скринридером не узнает, что submit провалился.
  const summaryRef = useRef<HTMLDivElement>(null)
  const stepTitleRef = useRef<HTMLHeadingElement>(null)
  // Счётчик, а не флаг: две неудачные отправки подряд должны снова увести
  // фокус на сводку, даже если её текст не изменился.
  const [failedSubmits, setFailedSubmits] = useState(0)

  // Смена шага — это смена содержимого экрана: фокус переносим на его
  // заголовок, иначе после нажатия кнопки он остался бы на исчезнувшем
  // элементе и скринридер не сообщил бы, что произошёл переход.
  useEffect(() => {
    // Шаг 3 уводит фокус сам — заголовок там внутри VerificationNotice.
    if (step === 2) {
      stepTitleRef.current?.focus()
    }
  }, [step])

  // Фокус переносим в эффекте, а не сразу в обработчике: в момент вызова
  // блок сводки ещё не отрисован и ref пустой.
  useEffect(() => {
    if (failedSubmits > 0) {
      summaryRef.current?.focus()
    }
  }, [failedSubmits])

  const validate = (): FieldErrors => {
    const errors: FieldErrors = {}

    if (!email.trim()) {
      errors.email = 'Укажите email.'
    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
      errors.email = 'Введите корректный email, например name@example.com.'
    }

    if (!password) {
      errors.password = 'Укажите пароль.'
    } else if (mode === 'register' && password.length < 8) {
      errors.password = 'Пароль должен быть не короче 8 символов.'
    }

    if (mode === 'register') {
      if (!passwordConfirmation) {
        errors.passwordConfirmation = 'Повторите пароль.'
      } else if (passwordConfirmation !== password) {
        errors.passwordConfirmation = 'Пароли не совпадают.'
      }
    }

    return errors
  }

  const goToCredentials = (next: Mode) => {
    setMode(next)
    setFieldErrors({})
    setFormError(null)
    // Повтор пароля не переносим между режимами: при переходе к входу поле
    // исчезает, и оставшееся значение всплыло бы при возврате к регистрации.
    setPasswordConfirmation('')
    setStep(2)
  }

  const goBack = () => {
    setStep(1)
    setFieldErrors({})
    setFormError(null)
  }

  /**
   * Ошибка снимается сразу, как только пользователь правит поле, а новая
   * показывается только на blur: иначе «Укажите пароль» висит поверх уже
   * исправленного ввода, а валидация на каждый символ ругается на
   * недописанный email.
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
      setFormError('Проверьте заполнение полей.')
      setFailedSubmits((n) => n + 1)

      return
    }

    setFormError(null)
    setSubmitting(true)

    try {
      if (mode === 'register') {
        await authApi.register(email, password, passwordConfirmation, role)
        setStep(3)

        return
      }

      const response = await authApi.login(email, password)

      tokenStorage.set(response.token)
      window.location.href = '/'
    } catch (error) {
      if (error instanceof RequestError) {
        // violations приходят с бэкенда — раскладываем их по полям, чтобы
        // ошибка была видна рядом с проблемным вводом, а не только сверху.
        setFieldErrors({
          email: error.violations.email,
          password: error.violations.password,
          passwordConfirmation: error.violations.passwordConfirmation,
        })
        setFormError(error.message)
      } else {
        setFormError('Непредвиденная ошибка. Попробуйте ещё раз.')
      }

      setFailedSubmits((n) => n + 1)
    } finally {
      setSubmitting(false)
    }
  }

  const isRegister = mode === 'register'

  return (
    <main className="min-vh-100 d-flex align-items-center py-4 py-md-5">
      <div className="container">
        <div className="row justify-content-center">
          <div className="col-12 col-sm-10 col-md-8 col-lg-5 col-xl-4">
            <div className="text-center mb-4">
              <h1 className="h4 mb-1">CVMatch</h1>
              <p className="text-body-secondary mb-0">
                {step === 3
                  ? 'Остался один шаг'
                  : step === 1
                    ? 'Выберите, зачем вы здесь'
                    : isRegister
                      ? 'Создайте аккаунт, чтобы начать'
                      : 'Введите данные для входа'}
              </p>
            </div>

            <div className="app-surface p-4">
              {step === 3 ? (
                <VerificationNotice
                  email={email}
                  onBack={() => {
                    setStep(1)
                    setPassword('')
                    setPasswordConfirmation('')
                  }}
                />
              ) : step === 1 ? (
                <>
                  <RoleSelector value={role} onChange={setRole} />

                  <div className="d-flex flex-column gap-2 mt-4">
                    <button
                      type="button"
                      className="btn btn-primary btn-lg w-100"
                      onClick={() => goToCredentials('login')}
                    >
                      Войти
                    </button>

                    <button
                      type="button"
                      className="btn btn-outline-primary btn-lg w-100"
                      onClick={() => goToCredentials('register')}
                    >
                      Зарегистрироваться
                    </button>
                  </div>

                  <div className="d-flex align-items-center gap-3 my-4">
                    <hr className="flex-grow-1 m-0" />
                    <span className="small text-body-secondary">или</span>
                    <hr className="flex-grow-1 m-0" />
                  </div>

                  <div className="d-flex flex-column gap-2">
                    <button
                      type="button"
                      className="btn btn-outline-secondary btn-lg d-flex align-items-center justify-content-center gap-2"
                      onClick={() => authApi.startOAuth('google', role)}
                    >
                      <GoogleLogoIcon size={20} weight="bold" aria-hidden="true" />
                      Продолжить с Google
                    </button>

                    <button
                      type="button"
                      className="btn btn-outline-secondary btn-lg d-flex align-items-center justify-content-center gap-2"
                      onClick={() => authApi.startOAuth('github', role)}
                    >
                      <GithubLogoIcon size={20} weight="fill" aria-hidden="true" />
                      Продолжить с GitHub
                    </button>
                  </div>
                </>
              ) : (
                <>
                  <div className="d-flex align-items-center gap-2 mb-3">
                    <button
                      type="button"
                      className="btn btn-link app-icon-action p-0 text-body-secondary"
                      onClick={goBack}
                      disabled={submitting}
                      aria-label="Назад к выбору роли"
                    >
                      <ArrowLeftIcon size={22} aria-hidden="true" />
                    </button>

                    <h2 ref={stepTitleRef} tabIndex={-1} className="h6 fw-semibold mb-0 app-step-title">
                      {isRegister ? 'Регистрация' : 'Вход'}
                    </h2>
                  </div>

                  {/* Выбранная роль остаётся видимой: иначе на втором шаге
                      пользователь теряет из виду, что он вообще выбрал. */}
                  <p className="small text-body-secondary border-start border-3 border-primary ps-2 mb-3">
                    {ROLE_LABEL[role]}
                  </p>

                  <form onSubmit={handleSubmit} noValidate>
                    {/* Сводка ошибок: role=alert объявляется сразу, tabIndex={-1}
                        позволяет увести на неё фокус после неудачной отправки. */}
                    {formError && (
                      <div
                        ref={summaryRef}
                        tabIndex={-1}
                        role="alert"
                        className="alert alert-danger d-flex gap-2 align-items-start py-2 px-3"
                      >
                        <WarningCircleIcon
                          size={20}
                          weight="fill"
                          aria-hidden="true"
                          className="flex-shrink-0 mt-1"
                        />
                        <span className="small">{formError}</span>
                      </div>
                    )}

                    <FormField
                      label="Email"
                      type="email"
                      value={email}
                      onChange={editField('email', setEmail)}
                      onBlur={() =>
                        setFieldErrors((prev) => ({ ...prev, email: validate().email }))
                      }
                      error={fieldErrors.email}
                      autoComplete="email"
                      placeholder="name@example.com"
                      disabled={submitting}
                    />

                    <FormField
                      label="Пароль"
                      type="password"
                      value={password}
                      onChange={editField('password', setPassword)}
                      onBlur={() =>
                        setFieldErrors((prev) => ({ ...prev, password: validate().password }))
                      }
                      error={fieldErrors.password}
                      autoComplete={isRegister ? 'new-password' : 'current-password'}
                      placeholder={isRegister ? 'Минимум 8 символов' : '••••••••'}
                      disabled={submitting}
                    />

                    {isRegister && (
                      <FormField
                        label="Повторите пароль"
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
                        placeholder="Ещё раз тот же пароль"
                        disabled={submitting}
                      />
                    )}

                    <button
                      type="submit"
                      className="btn btn-primary btn-lg w-100 mt-2"
                      disabled={submitting}
                    >
                      {submitting ? (
                        <>
                          <span className="spinner-border spinner-border-sm me-2" aria-hidden="true" />
                          {isRegister ? 'Создаём аккаунт…' : 'Входим…'}
                        </>
                      ) : isRegister ? (
                        'Зарегистрироваться'
                      ) : (
                        'Войти'
                      )}
                    </button>

                    <p className="text-center small text-body-secondary mt-2 mb-0 d-flex align-items-center justify-content-center gap-1 flex-wrap">
                      {isRegister ? 'Уже есть аккаунт?' : 'Нет аккаунта?'}
                      <button
                        type="button"
                        className="btn btn-link app-inline-action p-0 fw-semibold"
                        onClick={() => goToCredentials(isRegister ? 'login' : 'register')}
                        disabled={submitting}
                      >
                        {isRegister ? 'Войти' : 'Зарегистрироваться'}
                      </button>
                    </p>
                  </form>
                </>
              )}
            </div>
          </div>
        </div>
      </div>
    </main>
  )
}
