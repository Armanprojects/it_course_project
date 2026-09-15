import { MagnifyingGlassIcon, PlusIcon, XIcon } from '@phosphor-icons/react'
import { useEffect, useState } from 'react'
import { libraryApi } from '../api/client'
import type { AttributeLibrary, LibraryAttribute } from '../api/types'
import { useAttributeLabels } from '../i18n/useAttributeLabels'
import { useTranslation } from '../i18n/context'
import { useErrorText } from '../i18n/useErrorText'

interface Props {
  /** Уже добавленные — их прячем из выдачи. */
  ownedIds: Set<number>
  onPick: (attribute: LibraryAttribute) => void
  onClose: () => void
}

/**
 * Выбор атрибута из библиотеки.
 *
 * Задание требует три вещи, потому что библиотека может стать большой: поиск
 * по префиксу, недавно использованные и фильтр по категории. Всё три делает
 * сервер — фильтровать полную выдачу на клиенте значило бы сначала её выкачать.
 */
export function AttributePicker({ ownedIds, onPick, onClose }: Props) {
  const t = useTranslation()
  const errorText = useErrorText()
  const { categoryLabel, typeLabel } = useAttributeLabels()
  const [search, setSearch] = useState('')
  const [category, setCategory] = useState('')
  const [library, setLibrary] = useState<AttributeLibrary | null>(null)
  const [error, setError] = useState<string | null>(null)

  // Ввод не дёргает сервер на каждую букву: ждём паузу в наборе.
  const [query, setQuery] = useState('')

  useEffect(() => {
    const timer = setTimeout(() => setQuery(search), 250)

    return () => clearTimeout(timer)
  }, [search])

  useEffect(() => {
    let active = true

    libraryApi
      .attributes({ search: query || undefined, category: category || undefined })
      .then((result) => {
        if (active) {
          setLibrary(result)
          setError(null)
        }
      })
      .catch((requestError: unknown) => {
        if (active) {
          setError(
            errorText(requestError, 'picker.loadFailed')
          )
        }
      })

    return () => {
      active = false
    }
  }, [query, category, errorText])

  const available = (library?.items ?? []).filter((item) => !ownedIds.has(item.id))
  const recent = (library?.recent ?? []).filter((item) => !ownedIds.has(item.id))

  return (
    <div className="picker">
      <div className="picker__head">
        <h3 className="h2">{t('picker.title')}</h3>
        <button type="button" className="attr__remove" onClick={onClose} aria-label={t('picker.close')}>
          <XIcon size={16} aria-hidden="true" />
        </button>
      </div>

      <div className="picker__filters">
        <div className="apphead__search picker__search">
          <MagnifyingGlassIcon size={16} aria-hidden="true" />
          <input
            type="search"
            className="apphead__input"
            placeholder={t('picker.searchPlaceholder')}
            aria-label={t('picker.searchLabel')}
            value={search}
            onChange={(event) => setSearch(event.target.value)}
          />
        </div>

        <select
          className="input picker__category"
          aria-label={t('picker.categoryLabel')}
          value={category}
          onChange={(event) => setCategory(event.target.value)}
        >
          <option value="">{t('picker.allCategories')}</option>
          {(library?.categories ?? []).map((value) => (
            <option key={value} value={value}>
              {categoryLabel(value)}
            </option>
          ))}
        </select>
      </div>

      {error && (
        <div className="notice notice--error" role="alert">
          <span>{error}</span>
        </div>
      )}

      {/* Недавние показываем только когда человек ещё не начал искать —
          иначе они спорят с выдачей по запросу. */}
      {!query && !category && recent.length > 0 && (
        <div className="col g2">
          <p className="section__title">{t('picker.recent')}</p>
          <div className="cloud">
            {recent.map((attribute) => (
              <button
                key={attribute.id}
                type="button"
                className="cloud__tag"
                onClick={() => onPick(attribute)}
              >
                <PlusIcon size={12} aria-hidden="true" />
                {attribute.name}
              </button>
            ))}
          </div>
        </div>
      )}

      <div className="picker__list">
        {available.length === 0 ? (
          <p className="muted table__empty">
            {t(library === null ? 'common.loading' : 'picker.nothing')}
          </p>
        ) : (
          available.map((attribute) => (
            <button
              key={attribute.id}
              type="button"
              className="picker__item"
              onClick={() => onPick(attribute)}
            >
              <span className="col g1">
                <span className="picker__name">{attribute.name}</span>
                <span className="t-xs muted-3">
                  {categoryLabel(attribute.category)} ·{' '}
                  {typeLabel(attribute.type)}
                </span>
              </span>
              <PlusIcon size={14} aria-hidden="true" />
            </button>
          ))
        )}
      </div>
    </div>
  )
}
