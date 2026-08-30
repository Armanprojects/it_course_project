export type StrengthLevel = 'weak' | 'medium' | 'strong'

export interface PasswordStrength {
  /** Сколько из трёх сегментов индикатора зажечь. */
  score: 0 | 1 | 2 | 3
  level: StrengthLevel
  label: string
  /** Требование, которое ещё не выполнено, — что именно исправить. */
  hint: string
}

/** Минимальная длина; та же проверка продублирована на бэкенде. */
export const MIN_PASSWORD_LENGTH = 8

/**
 * Оценка надёжности пароля для индикатора под полем.
 *
 * Считаем выполненные требования, а не энтропию: пользователю нужно знать,
 * что именно добавить, а число вроде «46 бит» ему ни о чём не говорит.
 */
export function evaluatePassword(password: string): PasswordStrength {
  if (!password) {
    return { score: 0, level: 'weak', label: '', hint: `Минимум ${MIN_PASSWORD_LENGTH} символов, буквы и цифры` }
  }

  const longEnough = password.length >= MIN_PASSWORD_LENGTH
  const hasLetter = /\p{L}/u.test(password)
  const hasDigit = /\d/.test(password)
  const hasVariety = /[^\p{L}\d]/u.test(password) || (/\p{Lu}/u.test(password) && /\p{Ll}/u.test(password))

  if (!longEnough) {
    return {
      score: 1,
      level: 'weak',
      label: 'Слишком короткий',
      hint: `Нужно ещё ${MIN_PASSWORD_LENGTH - password.length} символов`,
    }
  }

  if (!hasLetter || !hasDigit) {
    return {
      score: 1,
      level: 'weak',
      label: 'Слабый',
      hint: hasLetter ? 'Добавьте цифру' : 'Добавьте букву',
    }
  }

  if (!hasVariety) {
    return {
      score: 2,
      level: 'medium',
      label: 'Средний',
      hint: 'Заглавная буква или символ сделают пароль надёжнее',
    }
  }

  return { score: 3, level: 'strong', label: 'Надёжный', hint: 'Такой пароль подойдёт' }
}
