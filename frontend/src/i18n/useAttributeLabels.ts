import { useCallback } from 'react'
import { CATEGORY_LABEL_KEYS, TYPE_LABEL_KEYS } from '../lib/attributeLabels'
import { useTranslation } from './context'

/**
 * Подписи категорий и типов атрибутов.
 *
 * Неизвестный код возвращаем как есть: библиотека может получить новую
 * категорию раньше, чем словарь, и пустая ячейка хуже сырого значения.
 */
export function useAttributeLabels() {
  const t = useTranslation()

  const categoryLabel = useCallback(
    (code: string): string => {
      const key = CATEGORY_LABEL_KEYS[code]

      return key === undefined ? code : t(key)
    },
    [t],
  )

  const typeLabel = useCallback(
    (code: string): string => {
      const key = TYPE_LABEL_KEYS[code]

      return key === undefined ? code : t(key)
    },
    [t],
  )

  return { categoryLabel, typeLabel }
}
