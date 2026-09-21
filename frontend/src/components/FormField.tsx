import { EyeIcon, EyeSlashIcon } from '@phosphor-icons/react'
import { useId, useState } from 'react'
import { evaluatePassword } from '../lib/passwordStrength'
import { useTranslation } from '../i18n/context'

interface Props {
  label: string
  type: 'email' | 'password' | 'text'
  value: string
  onChange: (value: string) => void
  onBlur?: () => void
  error?: string
  autoComplete: string
  placeholder?: string
  disabled?: boolean
  showStrength?: boolean
}

export function FormField({
  label,
  type,
  value,
  onChange,
  onBlur,
  error,
  autoComplete,
  placeholder,
  disabled = false,
  showStrength = false,
}: Props) {
  const t = useTranslation()
  const id = useId()
  const errorId = `${id}-error`
  const hintId = `${id}-hint`
  const [revealed, setRevealed] = useState(false)

  const isPassword = type === 'password'
  const inputType = isPassword && revealed ? 'text' : type
  const strength = showStrength ? evaluatePassword(value) : null

  const describedBy = error ? errorId : strength ? hintId : undefined

  const input = (
    <input
      id={id}
      type={inputType}
      className={`input${error ? ' input--invalid' : ''}`}
      value={value}
      onChange={(e) => onChange(e.target.value)}
      onBlur={onBlur}
      autoComplete={autoComplete}
      placeholder={placeholder}
      disabled={disabled}
      aria-invalid={error ? true : undefined}
      aria-describedby={describedBy}
    />

  )

  return (
    <div className="field">
      <label htmlFor={id} className="label">
        {label}
      </label>

      {isPassword ? (
        <div className="field__wrap">
          {input}
          <button
            type="button"
            className="field__reveal"
            onClick={() => setRevealed((v) => !v)}
            disabled={disabled}
            aria-label={t(revealed ? 'password.hide' : 'password.show')}
          >
            {revealed ? (
              <EyeSlashIcon size={17} aria-hidden="true" />
            ) : (
              <EyeIcon size={17} aria-hidden="true" />
            )}
          </button>

        </div>

      ) : (
        input
      )}

      {strength && (
        <>
          <div className="pwmeter" aria-hidden="true">
            {[1, 2, 3].map((segment) => (
              <span
                key={segment}
                className={segment <= strength.score ? `is-${strength.level}` : undefined}
              />

            ))}
          </div>

          <span id={hintId} className="t-xs muted-3">
            {strength.label
              ? `${t(strength.label)} · ${t(strength.hint, strength.hintParams)}`
              : t(strength.hint, strength.hintParams)}
          </span>

        </>

      )}

      {error && (
        <span id={errorId} className="field__error">
          {error}
        </span>

      )}
    </div>

  )
}
