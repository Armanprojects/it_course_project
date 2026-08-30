import { EyeIcon, EyeSlashIcon } from '@phosphor-icons/react'
import { useId, useState } from 'react'

interface Props {
  label: string
  type: 'email' | 'password'
  value: string
  onChange: (value: string) => void
  onBlur?: () => void
  error?: string
  autoComplete: string
  placeholder?: string
  disabled?: boolean
  required?: boolean
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
  required = true,
}: Props) {
  const id = useId()
  const errorId = `${id}-error`
  const [revealed, setRevealed] = useState(false)

  const isPassword = type === 'password'
  const inputType = isPassword && revealed ? 'text' : type

  return (
    <div className="mb-3">
      <label htmlFor={id} className="form-label fw-semibold">
        {label}
        {required && (
          <span className="text-danger ms-1" aria-hidden="true">
            *
          </span>
        )}
      </label>

      <div className={isPassword ? 'position-relative' : undefined}>
        <input
          id={id}
          type={inputType}
          className={`form-control form-control-lg${error ? ' is-invalid' : ''}`}
          style={isPassword ? { paddingRight: '3rem' } : undefined}
          value={value}
          onChange={(e) => onChange(e.target.value)}
          onBlur={onBlur}
          // Менеджеры паролей должны работать, вставка не блокируется —
          // требование WCAG 2.2 к доступной аутентификации.
          autoComplete={autoComplete}
          placeholder={placeholder}
          disabled={disabled}
          required={required}
          aria-invalid={error ? true : undefined}
          aria-describedby={error ? errorId : undefined}
        />

        {isPassword && (
          <button
            type="button"
            className="btn btn-link position-absolute top-0 end-0 h-100 px-3 text-body-secondary text-decoration-none"
            style={{ zIndex: 5 }}
            onClick={() => setRevealed((v) => !v)}
            disabled={disabled}
            aria-label={revealed ? 'Скрыть пароль' : 'Показать пароль'}
          >
            {revealed ? (
              <EyeSlashIcon size={20} aria-hidden="true" />
            ) : (
              <EyeIcon size={20} aria-hidden="true" />
            )}
          </button>
        )}
      </div>

      {error && (
        <div id={errorId} className="invalid-feedback d-block">
          {error}
        </div>
      )}
    </div>
  )
}
