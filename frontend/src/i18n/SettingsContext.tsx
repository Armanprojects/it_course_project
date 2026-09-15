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

/**
 * Язык и тема на всё приложение.
 *
 * Начальное состояние читается из localStorage синхронно, ещё до первого
 * рендера: если ждать ответа сервера, страница успеет мигнуть светлой темой
 * и английским. Профиль подтягивается следом и перекрывает локальный выбор —
 * он и есть источник правды между устройствами.
 */
export function SettingsProvider({ children }: { children: ReactNode }) {
  const [locale, setLocaleState] = useState<Locale>(storedLocale)
  const [theme, setThemeState] = useState<Theme>(storedTheme)

  // Атрибуты на <html> ставим в эффекте, а не при выборе: так они верны и
  // после подтягивания настроек с сервера, и при первой отрисовке.
  useEffect(() => {
    applyTheme(theme)
  }, [theme])

  useEffect(() => {
    applyLocale(locale)
  }, [locale])

  // Настройки вошедшего пользователя. Гостя это не касается — у него есть
  // только localStorage.
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
      // Протухший токен не должен ломать оформление: остаётся локальный выбор.
      .catch(() => undefined)

    return () => {
      active = false
    }
  }, [])

  const setLocale = useCallback((next: Locale) => {
    setLocaleState(next)
    saveLocale(next)

    // Сервер — фоновая синхронизация: интерфейс уже переключился, и ждать
    // ответа, чтобы показать новый язык, незачем.
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

  // Переводчик зависит только от языка. Отдельный useCallback, а не поле
  // внутри useMemo: иначе смена темы пересоздавала бы t, а он стоит в
  // зависимостях эффектов на многих страницах — они бы перезапускались зря.
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
