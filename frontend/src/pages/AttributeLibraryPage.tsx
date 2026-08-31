import { ArrowCounterClockwiseIcon, MagnifyingGlassIcon, PencilSimpleIcon, PlusIcon, TrashIcon } from '@phosphor-icons/react'
import { useCallback, useEffect, useState, type FormEvent } from 'react'
import { Navigate } from 'react-router-dom'
import { attributeAdminApi, RequestError, tokenStorage } from '../api/client'
import type { AttributeInput, AttributeType, ManagedAttribute } from '../api/types'
import { AppHeader } from '../components/AppHeader'
import { CATEGORY_LABELS, TYPE_LABELS } from '../lib/attributeLabels'
import { useCurrentUser } from '../lib/useCurrentUser'

const BLANK: AttributeInput = {
  name: '',
  description: '',
  category: 'domain_knowledge',
  type: 'string',
  options: [],
}

/**
 * Библиотека атрибутов — общий пул, которым управляют все рекрутеры.
 *
 * Удаление мягкое: значения в профилях и ссылки из позиций остаются, атрибут
 * просто перестаёт предлагаться. Поэтому здесь же можно и восстановить.
 */
export function AttributeLibraryPage() {
  if (!tokenStorage.get()) {
    return <Navigate to="/login" replace />
  }

  return <LibraryGate />
}

function LibraryGate() {
  const { isRecruiter, loading } = useCurrentUser()

  if (loading) {
    return (
      <>
        <AppHeader />
        <main className="page">
          <p className="muted" role="status">
            Загружаем…
          </p>
        </main>
      </>
    )
  }

  return isRecruiter ? <LibraryManager /> : <Navigate to="/" replace />
}

function LibraryManager() {
  const [items, setItems] = useState<ManagedAttribute[]>([])
  const [categories, setCategories] = useState<string[]>([])
  const [types, setTypes] = useState<AttributeType[]>([])
  const [search, setSearch] = useState('')
  const [query, setQuery] = useState('')
  const [category, setCategory] = useState('')
  const [editing, setEditing] = useState<number | 'new' | null>(null)
  const [selected, setSelected] = useState<Set<number>>(new Set())
  const [error, setError] = useState<string | null>(null)
  const [loaded, setLoaded] = useState(false)

  useEffect(() => {
    const timer = setTimeout(() => setQuery(search), 250)

    return () => clearTimeout(timer)
  }, [search])

  const load = useCallback(async () => {
    try {
      const data = await attributeAdminApi.manage({
        search: query || undefined,
        category: category || undefined,
      })

      setItems(data.items)
      setCategories(data.categories)
      setTypes(data.types)
      setError(null)
    } catch (requestError: unknown) {
      setError(
        requestError instanceof RequestError ? requestError.message : 'Не удалось загрузить.',
      )
    } finally {
      setLoaded(true)
    }
  }, [query, category])

  useEffect(() => {
    void load()
  }, [load])

  const act = async (run: () => Promise<unknown>) => {
    setError(null)

    try {
      await run()
      await load()
    } catch (requestError: unknown) {
      setError(
        requestError instanceof RequestError ? requestError.message : 'Не удалось выполнить.',
      )
    }
  }

  const toggle = (id: number) => {
    setSelected((current) => {
      const next = new Set(current)

      // delete возвращает, был ли элемент — так снятие и добавление
      // укладываются в одну проверку.
      if (!next.delete(id)) {
        next.add(id)
      }

      return next
    })
  }

  /**
   * Действие тулбара над всем выделением. Запросы идут последовательно:
   * у атрибутов версионирование, и параллельные записи гонялись бы за него.
   */
  const bulk = async (run: (id: number) => Promise<unknown>) => {
    setError(null)

    try {
      for (const id of selected) {
        await run(id)
      }

      setSelected(new Set())
      await load()
    } catch (requestError: unknown) {
      setError(
        requestError instanceof RequestError ? requestError.message : 'Не удалось выполнить.',
      )
      await load()
    }
  }

  return (
    <>
      <AppHeader />

      <main className="page">
        <div className="panel__head">
          <div className="col g1">
            <h1 className="h1">Библиотека атрибутов</h1>
            <p className="muted" style={{ margin: 0 }}>
              Общий пул — им управляют все рекрутеры
            </p>
          </div>

          {editing === null && (
            <button type="button" className="btn btn--primary" onClick={() => setEditing('new')}>
              <PlusIcon size={14} aria-hidden="true" />
              Новый атрибут
            </button>
          )}
        </div>

        {error && (
          <div className="notice notice--error" role="alert">
            <span>{error}</span>
          </div>
        )}

        {/* Форма — над таблицей, а не внутри строки: строка остаётся записью,
            которую открывают, и не превращается в редактор. */}
        {editing === 'new' && (
          <AttributeForm
            initial={BLANK}
            categories={categories}
            types={types}
            onCancel={() => setEditing(null)}
            onSave={async (input) => {
              await act(() => attributeAdminApi.create(input))
              setEditing(null)
            }}
          />
        )}

        {typeof editing === 'number' && (() => {
          const target = items.find((item) => item.id === editing)

          if (!target) {
            return null
          }

          return (
            <AttributeForm
              key={target.id}
              initial={{
                name: target.name,
                description: target.description ?? '',
                category: target.category,
                type: target.type,
                options: target.options,
                version: target.version,
              }}
              categories={categories}
              types={types}
              lockType
              onCancel={() => setEditing(null)}
              onSave={async (input) => {
                await act(() => attributeAdminApi.update(target.id, input))
                setEditing(null)
              }}
            />
          )
        })()}

        <section className="panel">
          <div className="picker__filters">
            <div className="apphead__search picker__search">
              <MagnifyingGlassIcon size={16} aria-hidden="true" />
              <input
                type="search"
                className="apphead__input"
                placeholder="Название атрибута…"
                aria-label="Поиск по началу названия"
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
              {categories.map((value) => (
                <option key={value} value={value}>
                  {CATEGORY_LABELS[value] ?? value}
                </option>
              ))}
            </select>
          </div>

          {/* Действия — в панели над таблицей и работают над выделенным.
              Кнопки в каждой строке задание прямо запрещает. */}
          <SelectionToolbar
            selected={selected}
            items={items}
            onEdit={() => setEditing([...selected][0] ?? null)}
            onRemove={() => void bulk((id) => attributeAdminApi.remove(id))}
            onRestore={() => void bulk((id) => attributeAdminApi.restore(id))}
            onClear={() => setSelected(new Set())}
          />

          {!loaded ? (
            <p className="muted table__empty">Загружаем…</p>
          ) : items.length === 0 ? (
            <p className="muted table__empty">Ничего не найдено.</p>
          ) : (
            <div className="table__scroll">
              <table className="table">
                <thead>
                  <tr>
                    <th scope="col" className="table__pick">
                      <input
                        type="checkbox"
                        aria-label="Выделить все"
                        checked={selected.size > 0 && selected.size === items.length}
                        ref={(node) => {
                          if (node) {
                            node.indeterminate =
                              selected.size > 0 && selected.size < items.length
                          }
                        }}
                        onChange={(event) =>
                          setSelected(
                            event.target.checked
                              ? new Set(items.map((item) => item.id))
                              : new Set(),
                          )
                        }
                      />
                    </th>
                    <th scope="col">Название</th>
                    <th scope="col">Категория</th>
                    <th scope="col">Тип</th>
                    <th scope="col" className="is-secondary">
                      Используется
                    </th>
                  </tr>
                </thead>

                <tbody>
                  {items.map((attribute) => (
                    <AttributeRow
                      key={attribute.id}
                      attribute={attribute}
                      checked={selected.has(attribute.id)}
                      onToggle={() => toggle(attribute.id)}
                      onOpen={() => setEditing(attribute.id)}
                    />
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </section>
      </main>
    </>
  )
}

function AttributeRow({
  attribute,
  checked,
  onToggle,
  onOpen,
}: {
  attribute: ManagedAttribute
  checked: boolean
  onToggle: () => void
  onOpen: () => void
}) {
  const used = attribute.usage.profiles + attribute.usage.positions + attribute.usage.rules

  return (
    <tr
      className={`table__row${attribute.removed ? ' is-removed' : ''}`}
      onClick={onOpen}
      aria-selected={checked}
    >
      {/* Клик по ячейке с флажком не должен открывать запись — иначе
          выделить строку мышью будет невозможно. */}
      <td className="table__pick" onClick={(event) => event.stopPropagation()}>
        <input
          type="checkbox"
          checked={checked}
          onChange={onToggle}
          aria-label={`Выделить «${attribute.name}»`}
        />
      </td>

      <td>
        <span className="table__link">{attribute.name}</span>
        {attribute.system && <span className="chip chip--muted">встроенный</span>}
        {attribute.removed && <span className="chip chip--muted">удалён</span>}
        {attribute.description && <span className="table__sub">{attribute.description}</span>}
      </td>

      <td className="muted">{CATEGORY_LABELS[attribute.category] ?? attribute.category}</td>

      <td className="muted">
        {TYPE_LABELS[attribute.type] ?? attribute.type}
        {attribute.options.length > 0 && (
          <span className="table__sub">{attribute.options.join(' · ')}</span>
        )}
      </td>

      <td className="is-secondary muted t-xs">
        {used === 0
          ? '—'
          : `профилей ${attribute.usage.profiles} · позиций ${attribute.usage.positions} · правил ${attribute.usage.rules}`}
      </td>
    </tr>
  )
}

/**
 * Панель действий над выделенными записями — то, что задание предлагает
 * взамен кнопок в каждой строке.
 *
 * Редактирование включается только для одной записи: форма правит один
 * атрибут. Удаление и восстановление работают над всем выделением.
 */
function SelectionToolbar({
  selected,
  items,
  onEdit,
  onRemove,
  onRestore,
  onClear,
}: {
  selected: Set<number>
  items: ManagedAttribute[]
  onEdit: () => void
  onRemove: () => void
  onRestore: () => void
  onClear: () => void
}) {
  const [confirming, setConfirming] = useState(false)
  const chosen = items.filter((item) => selected.has(item.id))

  if (chosen.length === 0) {
    return (
      <p className="muted t-sm toolbar__hint">
        Отметьте атрибуты, чтобы отредактировать, удалить или восстановить их.
      </p>
    )
  }

  // Встроенные атрибуты удалять нельзя — сервер откажет, поэтому и кнопку
  // не предлагаем, если в выделении есть хоть один такой.
  const deletable = chosen.filter((item) => !item.system && !item.removed)
  const restorable = chosen.filter((item) => item.removed)
  const used = deletable.reduce(
    (sum, item) => sum + item.usage.profiles + item.usage.positions + item.usage.rules,
    0,
  )

  return (
    <div className="toolbar">
      <span className="t-sm">Выделено: {chosen.length}</span>

      <div className="row g2">
        <button
          type="button"
          className="btn btn--outline"
          disabled={chosen.length !== 1}
          onClick={onEdit}
          title={chosen.length === 1 ? undefined : 'Редактировать можно один атрибут'}
        >
          <PencilSimpleIcon size={14} aria-hidden="true" />
          Редактировать
        </button>

        {restorable.length > 0 && (
          <button type="button" className="btn btn--outline" onClick={onRestore}>
            <ArrowCounterClockwiseIcon size={14} aria-hidden="true" />
            Восстановить ({restorable.length})
          </button>
        )}

        {deletable.length > 0 && (
          <button
            type="button"
            className="btn btn--outline"
            onClick={() => setConfirming(true)}
          >
            <TrashIcon size={14} aria-hidden="true" />
            Удалить ({deletable.length})
          </button>
        )}

        <button type="button" className="btn btn--ghost" onClick={onClear}>
          Снять выделение
        </button>
      </div>

      {confirming && (
        <div className="notice notice--error toolbar__confirm" role="alert">
          <span>
            {used > 0
              ? `Атрибуты используются (${used} связей). Значения и ссылки сохранятся, но из библиотеки они исчезнут.`
              : `Удалить ${deletable.length} атр. из библиотеки?`}
          </span>
          <div className="row g2">
            <button type="button" className="btn btn--ghost" onClick={() => setConfirming(false)}>
              Отмена
            </button>
            <button
              type="button"
              className="btn btn--primary"
              onClick={() => {
                setConfirming(false)
                onRemove()
              }}
            >
              Удалить
            </button>
          </div>
        </div>
      )}
    </div>
  )
}
function AttributeForm({
  initial,
  categories,
  types,
  lockType = false,
  onCancel,
  onSave,
}: {
  initial: AttributeInput
  categories: string[]
  types: AttributeType[]
  lockType?: boolean
  onCancel: () => void
  onSave: (input: AttributeInput) => Promise<void>
}) {
  const [form, setForm] = useState<AttributeInput>(initial)
  const [busy, setBusy] = useState(false)

  const submit = async (event: FormEvent) => {
    event.preventDefault()
    setBusy(true)

    await onSave({
      ...form,
      description: form.description?.trim() || null,
      options: form.type === 'select' ? form.options.filter((o) => o.trim() !== '') : [],
    })

    setBusy(false)
  }

  return (
    <form className="panel project--form" onSubmit={submit}>
      <div className="field">
        <label className="label" htmlFor="attr-name">
          Название
        </label>
        <input
          id="attr-name"
          className="input"
          required
          maxLength={120}
          value={form.name}
          onChange={(event) => setForm({ ...form, name: event.target.value })}
        />
        <span className="t-xs muted-3">Должно быть уникальным во всей системе</span>
      </div>

      <div className="period">
        <div className="field">
          <label className="label" htmlFor="attr-category">
            Категория
          </label>
          <select
            id="attr-category"
            className="input"
            value={form.category}
            onChange={(event) => setForm({ ...form, category: event.target.value })}
          >
            {categories.map((value) => (
              <option key={value} value={value}>
                {CATEGORY_LABELS[value] ?? value}
              </option>
            ))}
          </select>
        </div>

        <div className="field">
          <label className="label" htmlFor="attr-type">
            Тип
          </label>
          <select
            id="attr-type"
            className="input"
            value={form.type}
            disabled={lockType}
            onChange={(event) =>
              setForm({ ...form, type: event.target.value as AttributeType })
            }
          >
            {types.map((value) => (
              <option key={value} value={value}>
                {TYPE_LABELS[value] ?? value}
              </option>
            ))}
          </select>
          {lockType && (
            <span className="t-xs muted-3">
              Тип нельзя менять: уже сохранённые значения станут недействительны
            </span>
          )}
        </div>
      </div>

      <div className="field">
        <label className="label" htmlFor="attr-description">
          Описание
        </label>
        <textarea
          id="attr-description"
          className="input input--area"
          rows={2}
          value={form.description ?? ''}
          onChange={(event) => setForm({ ...form, description: event.target.value })}
        />
      </div>

      {form.type === 'select' && (
        <div className="field">
          <label className="label" htmlFor="attr-options">
            Варианты — по одному в строке
          </label>
          <textarea
            id="attr-options"
            className="input input--area"
            rows={4}
            required
            value={form.options.join('\n')}
            onChange={(event) =>
              setForm({ ...form, options: event.target.value.split('\n') })
            }
          />
        </div>
      )}

      <div className="row g2">
        <button type="submit" className="btn btn--primary" disabled={busy}>
          {busy ? 'Сохраняем…' : 'Сохранить'}
        </button>
        <button type="button" className="btn btn--ghost" onClick={onCancel} disabled={busy}>
          Отмена
        </button>
      </div>
    </form>
  )
}
