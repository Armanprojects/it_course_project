import { useMemo } from 'react'
import { useSettings } from './context'

/**
 * Форматирование дат по выбранному языку.
 *
 * Intl-форматтер дорого создавать на каждый рендер таблицы, поэтому он
 * запоминается на язык: строк в таблице сотни, языков два.
 */
export function useDateFormat(options: Intl.DateTimeFormatOptions = { dateStyle: 'medium' }) {
  const { locale } = useSettings()

  return useMemo(() => {
    const tag = locale === 'ru' ? 'ru-RU' : 'en-GB'
    const format = new Intl.DateTimeFormat(tag, options)

    return (value: string | Date | null | undefined): string => {
      if (value === null || value === undefined || value === '') {
        return ''
      }

      const date = value instanceof Date ? value : new Date(value)

      return Number.isNaN(date.getTime()) ? '' : format.format(date)
    }
    // options — литерал на месте вызова, поэтому в зависимостях только язык:
    // иначе форматтер пересоздавался бы каждый рендер и кеш терял смысл.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [locale])
}
