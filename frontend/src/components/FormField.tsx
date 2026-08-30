import { EyeIcon, EyeSlashIcon } from '@phosphor-icons/react'
import { useId, useState } from 'react'
import { evaluatePassword } from '../lib/passwordStrength'

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
  /** Показывать индикатор надёжности — только при создании пароля. */
  showStrength?: boolean
}

/**
 * Поле с видимой меткой (не placeholder-only) и ошибкой под ним, связанной
 * через aria-describedby: скринридер читает её вместе с полем, а не отдельно.
 */
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
  const id = useId()
  const errorId = `${id}-error`
  const hintId = `${id}-hint`
  const [revealed, setRevealed] = useState(false)

  const isPassword = type === 'password'
  const inputType = isPassword && revealed ? 'text' : type
  const strength = showStrength ? evaluatePassword(value) : null

  // Подсказку про требования показываем всегда, ошибку — поверх неё:
  // две записи в describedby читались бы подряд и путали.
  const describedBy = error ? errorId : strength ? hintId : undefined

  const input = (
    <input
      id={id}
      type={inputType}
      className={`input${error ? ' input--invalid' : ''}`}
      value={value}
      onChange={(e) => onChange(e.target.value)}
      onBlur={onBlur}
      // Менеджеры паролей должны работать, вставка не блокируется —
      // требование WCAG 2.2 к доступной аутентификации.
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
            aria-label={revealed ? 'Скрыть пароль' : 'Показать пароль'}
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
          {/* Полоски — только визуальная подсказка, смысл несёт текст ниже:
              по одному цвету состояние не определить при дальтонизме. */}
          <div className="pwmeter" aria-hidden="true">
            {[1, 2, 3].map((segment) => (
              <span
                key={segment}
                className={segment <= strength.score ? `is-${strength.level}` : undefined}
              />
            ))}
          </div>

          {/* aria-live не ставим: индикатор меняется на каждый символ, и
              озвучивание каждого шага мешало бы вводу. */}
          <span id={hintId} className="t-xs muted-3">
            {strength.label ? `${strength.label} · ${strength.hint}` : strength.hint}
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
