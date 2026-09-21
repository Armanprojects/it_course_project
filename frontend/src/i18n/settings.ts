export const LOCALES = ['en', 'ru'] as const
export type Locale = (typeof LOCALES)[number]

export const THEMES = ['light', 'dark'] as const
export type Theme = (typeof THEMES)[number]

const LOCALE_KEY = 'ui.locale'
const THEME_KEY = 'ui.theme'

export const DEFAULT_LOCALE: Locale = 'en'
export const DEFAULT_THEME: Theme = 'light'

function isLocale(value: unknown): value is Locale {
  return typeof value === 'string' && (LOCALES as readonly string[]).includes(value)
}

function isTheme(value: unknown): value is Theme {
  return typeof value === 'string' && (THEMES as readonly string[]).includes(value)
}

function read(key: string): string | null {
  try {
    return localStorage.getItem(key)
  } catch {
    return null
  }
}

function write(key: string, value: string): void {
  try {
    localStorage.setItem(key, value)
  } catch {
  }
}

function browserLocale(): Locale {
  const preferred = typeof navigator === 'undefined' ? [] : navigator.languages ?? [navigator.language]

  for (const tag of preferred) {
    const base = tag.toLowerCase().split('-')[0]

    if (isLocale(base)) {
      return base
    }
  }

  return DEFAULT_LOCALE
}

export function storedLocale(): Locale {
  const saved = read(LOCALE_KEY)

  return isLocale(saved) ? saved : browserLocale()
}

export function storedTheme(): Theme {
  const saved = read(THEME_KEY)

  if (isTheme(saved)) {
    return saved
  }

  const dark =
    typeof matchMedia === 'function' && matchMedia('(prefers-color-scheme: dark)').matches

  return dark ? 'dark' : DEFAULT_THEME
}

export function saveLocale(locale: Locale): void {
  write(LOCALE_KEY, locale)
}

export function saveTheme(theme: Theme): void {
  write(THEME_KEY, theme)
}

export function applyTheme(theme: Theme): void {
  document.documentElement.dataset.theme = theme
}

export function applyLocale(locale: Locale): void {
  document.documentElement.lang = locale
}

export function normalizeLocale(value: unknown): Locale | null {
  return isLocale(value) ? value : null
}

export function normalizeTheme(value: unknown): Theme | null {
  return isTheme(value) ? value : null
}
