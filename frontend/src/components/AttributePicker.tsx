import { MagnifyingGlassIcon, PlusIcon, XIcon } from '@phosphor-icons/react'
import { useEffect, useState } from 'react'
import { libraryApi, RequestError } from '../api/client'
import type { AttributeLibrary, LibraryAttribute } from '../api/types'
import { CATEGORY_LABELS, TYPE_LABELS } from '../lib/attributeLabels'

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
            requestError instanceof RequestError
              ? requestError.message
              : 'Не удалось загрузить библиотеку.',
          )
        }
      })

    return () => {
      active = false
    }
  }, [query, category])

  const available = (library?.items ?? []).filter((item) => !ownedIds.has(item.id))
  const recent = (library?.recent ?? []).filter((item) => !ownedIds.has(item.id))

  return (
    <div className="picker">
      <div className="picker__head">
        <h3 className="h2">Добавить атрибут</h3>
        <button type="button" className="attr__remove" onClick={onClose} aria-label="Закрыть">
          <XIcon size={16} aria-hidden="true" />
        </button>
      </div>

      <div className="picker__filters">
        <div className="apphead__search picker__search">
          <MagnifyingGlassIcon size={16} aria-hidden="true" />
          <input
            type="search"
            className="apphead__input"
            placeholder="Название атрибута…"
            aria-label="Поиск атрибута по началу названия"
            value={search}
            onChange={(event) => setSearch(event.target.value)}
          />
        </div>

        <select
          className="input picker__category"
          aria-label="Категория"
          value={category}
          onChange={(event) => setCategory(event.target.value)}
        >
          <option value="">Все категории</option>
          {(library?.categories ?? []).map((value) => (
            <option key={value} value={value}>
              {CATEGORY_LABELS[value] ?? value}
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
          <p className="section__title">Недавно использованные</p>
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
            {library === null ? 'Загружаем…' : 'Ничего не найдено.'}
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
                  {CATEGORY_LABELS[attribute.category] ?? attribute.category} ·{' '}
                  {TYPE_LABELS[attribute.type] ?? attribute.type}
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
