import { createContext, useContext } from 'react'
import type { MessageKey } from './messages'
import type { Locale, Theme } from './settings'

export interface Settings {
  locale: Locale
  theme: Theme
  setLocale: (locale: Locale) => void
  setTheme: (theme: Theme) => void
  /** Перевод по ключу; {placeholder} подставляется из params. */
  t: (key: MessageKey, params?: Record<string, string | number>) => string
}

/**
 * Контекст и хуки живут отдельно от провайдера: файл с компонентом должен
 * экспортировать только компонент, иначе Vite не умеет обновлять его на лету.
 */
export const SettingsContext = createContext<Settings | null>(null)

export function useSettings(): Settings {
  const value = useContext(SettingsContext)

  if (value === null) {
    throw new Error('useSettings must be used inside SettingsProvider')
  }

  return value
}

/** Короткий доступ к переводчику — то, что нужно большинству компонентов. */
export function useTranslation() {
  return useSettings().t
}
