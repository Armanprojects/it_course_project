import { createContext, useContext } from 'react'
import type { MessageKey } from './messages'
import type { Locale, Theme } from './settings'

export interface Settings {
  locale: Locale
  theme: Theme
  setLocale: (locale: Locale) => void
  setTheme: (theme: Theme) => void
  t: (key: MessageKey, params?: Record<string, string | number>) => string
}

export const SettingsContext = createContext<Settings | null>(null)

export function useSettings(): Settings {
  const value = useContext(SettingsContext)

  if (value === null) {
    throw new Error('useSettings must be used inside SettingsProvider')
  }

  return value
}

export function useTranslation() {
  return useSettings().t
}
