import { useCallback, useEffect, useMemo, useState, type ReactNode } from 'react'
import { authApi, tokenStorage } from '../api/client'
import { SettingsContext, type Settings } from './context'
import { catalogues } from './messages'
import {
  applyLocale,
  applyTheme,
  normalizeLocale,
  normalizeTheme,
  saveLocale,
  saveTheme,
  storedLocale,
  storedTheme,
  type Locale,
  type Theme,
} from './settings'

export function SettingsProvider({ children }: { children: ReactNode }) {
  const [locale, setLocaleState] = useState<Locale>(storedLocale)
  const [theme, setThemeState] = useState<Theme>(storedTheme)

  useEffect(() => {
    applyTheme(theme)
  }, [theme])

  useEffect(() => {
    applyLocale(locale)
  }, [locale])

  useEffect(() => {
    if (!tokenStorage.isValid()) {
      return
    }

    let active = true

    authApi
      .me()
      .then((user) => {
        if (!active) {
          return
        }

        const serverLocale = normalizeLocale(user.locale)
        const serverTheme = normalizeTheme(user.theme)

        if (serverLocale !== null) {
          setLocaleState(serverLocale)
          saveLocale(serverLocale)
        }

        if (serverTheme !== null) {
          setThemeState(serverTheme)
          saveTheme(serverTheme)
        }
      })
      .catch(() => undefined)

    return () => {
      active = false
    }
  }, [])

  const setLocale = useCallback((next: Locale) => {
    setLocaleState(next)
    saveLocale(next)

    if (tokenStorage.isValid()) {
      void authApi.updateSettings({ locale: next }).catch(() => undefined)
    }
  }, [])

  const setTheme = useCallback((next: Theme) => {
    setThemeState(next)
    saveTheme(next)

    if (tokenStorage.isValid()) {
      void authApi.updateSettings({ theme: next }).catch(() => undefined)
    }
  }, [])

  const t = useCallback<Settings['t']>(
    (key, params) => {
      const template = catalogues[locale][key]

      if (params === undefined) {
        return template
      }

      return template.replace(/\{(\w+)\}/g, (match, name: string) =>
        name in params ? String(params[name]) : match,
      )
    },
    [locale],
  )

  const value = useMemo<Settings>(
    () => ({ locale, theme, setLocale, setTheme, t }),
    [locale, theme, setLocale, setTheme, t],
  )

  return <SettingsContext.Provider value={value}>{children}</SettingsContext.Provider>
}
