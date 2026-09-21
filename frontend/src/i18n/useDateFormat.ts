import { useMemo } from 'react'
import { useSettings } from './context'

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
  }, [locale])
}
