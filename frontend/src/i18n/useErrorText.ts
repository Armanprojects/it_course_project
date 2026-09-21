import { useCallback } from 'react'
import { RequestError } from '../api/client'
import { useSettings } from './context'
import type { MessageKey } from './messages'

export function useErrorText() {
  const { t } = useSettings()

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
