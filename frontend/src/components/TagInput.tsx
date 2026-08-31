import { XIcon } from '@phosphor-icons/react'
import { useEffect, useId, useRef, useState, type KeyboardEvent } from 'react'
import { libraryApi } from '../api/client'
import type { TagSuggestion } from '../api/types'

interface Props {
  tags: string[]
  onChange: (tags: string[]) => void
}

/**
 * Ввод технологических тегов с автодополнением по уже введённым.
 *
 * Подсказки берём с сервера, отсортированные по частоте: набирая «re», человек
 * должен получить общий React, а не чей-то разовый тег.
 */
export function TagInput({ tags, onChange }: Props) {
  const [draft, setDraft] = useState('')
  const [suggestions, setSuggestions] = useState<TagSuggestion[]>([])
  const [open, setOpen] = useState(false)
  const listId = useId()
  const wrap = useRef<HTMLDivElement>(null)

  const query = draft.trim()

  useEffect(() => {
    if (query === '') {
      return
    }

    let active = true
    const timer = setTimeout(() => {
      libraryApi
        .tags(query)
        .then((result) => {
          if (active) {
            setSuggestions(result.items)
          }
        })
        // Подсказки — вспомогательная вещь: если сервер не ответил, ввод
        // руками всё равно работает, поэтому ошибку не показываем.
        .catch(() => {
          if (active) {
            setSuggestions([])
          }
        })
    }, 200)

    return () => {
      active = false
      clearTimeout(timer)
    }
  }, [query])

  // Отфильтровываем при рендере, а не в эффекте: пустой ввод и уже добавленные
  // теги — это производные от текущих пропсов, отдельное состояние им не нужно.
  const owned = new Set(tags.map((tag) => tag.toLowerCase()))
  const visible =
    query === '' ? [] : suggestions.filter((item) => !owned.has(item.name.toLowerCase()))

  // Клик мимо закрывает список подсказок.
  useEffect(() => {
    const onClickOutside = (event: MouseEvent) => {
      if (wrap.current && !wrap.current.contains(event.target as Node)) {
        setOpen(false)
      }
    }

    document.addEventListener('mousedown', onClickOutside)

    return () => document.removeEventListener('mousedown', onClickOutside)
  }, [])

  const add = (name: string) => {
    const clean = name.trim()

    if (clean === '') {
      return
    }

    // Регистр не должен плодить дубли — сервер их всё равно схлопнет.
    if (!tags.some((tag) => tag.toLowerCase() === clean.toLowerCase())) {
      onChange([...tags, clean])
    }

    setDraft('')
    setOpen(false)
  }

  const onKeyDown = (event: KeyboardEvent<HTMLInputElement>) => {
    if (event.key === 'Enter' || event.key === ',') {
      event.preventDefault()
      add(draft)

      return
    }

    // Backspace в пустом поле снимает последний тег — привычное поведение
    // для такого ввода.
    if (event.key === 'Backspace' && draft === '' && tags.length > 0) {
      onChange(tags.slice(0, -1))
    }
  }

  return (
    <div className="taginput" ref={wrap}>
      <div className="taginput__box">
        {tags.map((tag) => (
          <span key={tag} className="chip chip--tag">
            {tag}
            <button
              type="button"
              className="chip__x"
              onClick={() => onChange(tags.filter((item) => item !== tag))}
              aria-label={`Убрать тег ${tag}`}
            >
              <XIcon size={10} weight="bold" aria-hidden="true" />
            </button>
          </span>
        ))}

        <input
          type="text"
          className="taginput__field"
          placeholder={tags.length === 0 ? 'React, Docker…' : ''}
          aria-label="Добавить тег"
          aria-autocomplete="list"
          aria-controls={listId}
          value={draft}
          onChange={(event) => {
            setDraft(event.target.value)
            setOpen(true)
          }}
          onKeyDown={onKeyDown}
          onFocus={() => setOpen(true)}
        />
      </div>

      {open && visible.length > 0 && (
        <ul className="taginput__list" id={listId} role="listbox">
          {visible.map((item) => (
            <li key={item.id}>
              <button type="button" className="taginput__option" onClick={() => add(item.name)}>
                {item.name}
                <span className="cloud__count">{item.usageCount}</span>
              </button>
            </li>
          ))}
        </ul>
      )}
    </div>
  )
}
