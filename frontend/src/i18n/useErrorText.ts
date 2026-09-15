import { useCallback } from 'react'
import { RequestError } from '../api/client'
import { useSettings } from './context'
import type { MessageKey } from './messages'

/**
 * Текст ошибки запроса на языке интерфейса.
 *
 * Бэкенд присылает готовое сообщение — его показываем как есть. Ошибки,
 * которые клиент придумал сам (сеть, неизвестный сбой), несут ключ словаря
 * и переводятся здесь.
 */
export function useErrorText() {
  const { t } = useSettings()

  // Стабильная ссылка: функция стоит в зависимостях эффектов, и новый объект
  // на каждый рендер заставлял бы их перезапускаться.
  return useCallback(
    (error: unknown, fallback: MessageKey = 'common.unexpectedError'): string => {
      if (error instanceof RequestError) {
        return error.isLocalized ? t(error.message as MessageKey) : error.message
      }

      return t(fallback)
    },
    [t],
  )
}
