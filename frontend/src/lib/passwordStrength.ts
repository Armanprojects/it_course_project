import type { MessageKey } from '../i18n/messages'

export type StrengthLevel = 'weak' | 'medium' | 'strong'

export interface PasswordStrength {
  /** Сколько из трёх сегментов индикатора зажечь. */
  score: 0 | 1 | 2 | 3
  level: StrengthLevel
  /** Ключи словаря, а не готовые строки: модуль ничего не знает о языке. */
  label: MessageKey | null
  /** Требование, которое ещё не выполнено, — что именно исправить. */
  hint: MessageKey
  /** Подстановки для hint, если он с числом. */
  hintParams?: Record<string, number>
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
    return {
      score: 0,
      level: 'weak',
      label: null,
      hint: 'password.hintMin',
      hintParams: { min: MIN_PASSWORD_LENGTH },
    }
  }

  const longEnough = password.length >= MIN_PASSWORD_LENGTH
  const hasLetter = /\p{L}/u.test(password)
  const hasDigit = /\d/.test(password)
  const hasVariety = /[^\p{L}\d]/u.test(password) || (/\p{Lu}/u.test(password) && /\p{Ll}/u.test(password))

  if (!longEnough) {
    return {
      score: 1,
      level: 'weak',
      label: 'password.tooShort',
      hint: 'password.needMore',
      hintParams: { count: MIN_PASSWORD_LENGTH - password.length },
    }
  }

  if (!hasLetter || !hasDigit) {
    return {
      score: 1,
      level: 'weak',
      label: 'password.weak',
      hint: hasLetter ? 'password.addDigit' : 'password.addLetter',
    }
  }

  if (!hasVariety) {
    return {
      score: 2,
      level: 'medium',
      label: 'password.medium',
      hint: 'password.mediumHint',
    }
  }

  return { score: 3, level: 'strong', label: 'password.strong', hint: 'password.strongHint' }
}
