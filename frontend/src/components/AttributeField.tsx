import { TrashIcon } from '@phosphor-icons/react'
import type { AttributeValue, PeriodValue, ProfileAttribute } from '../api/types'
import { ImageField } from './ImageField'

interface Props {
  attribute: ProfileAttribute
  value: AttributeValue
  onChange: (value: AttributeValue) => void
  /** Встроенные атрибуты удалять нельзя — для них кнопки не будет. */
  onRemove?: () => void
}

function asText(value: AttributeValue): string {
  if (value === null || value === undefined || typeof value === 'object') {
    return ''
  }

  return String(value)
}

/**
 * Числа приходят строкой из decimal(20,6) — «7.500000». Показывать хвост нулей
 * в поле ввода незачем, а float-ом их гонять нельзя: точность decimal тогда
 * теряется. Поэтому обрезаем незначащие нули строкой.
 */
function asNumberText(value: AttributeValue): string {
  const text = asText(value)

  if (text === '' || !text.includes('.')) {
    return text
  }

  return text.replace(/\.?0+$/, '')
}

function asPeriod(value: AttributeValue): PeriodValue {
  return value !== null && typeof value === 'object' ? value : { from: null, to: null }
}

/**
 * Редактор одного атрибута профиля. Восемь типов из библиотеки — восемь
 * элементов ввода, но снаружи у всех один интерфейс: value/onChange.
 *
 * Пустое значение подсвечиваем — по заданию незаполненные атрибуты должны
 * быть видны сразу, и та же подсветка понадобится в резюме.
 */
export function AttributeField({ attribute, value, onChange, onRemove }: Props) {
  const inputId = `attr-${attribute.attributeId}`
  const isEmpty = value === null || value === '' || value === undefined

  return (
    <div className={`attr${isEmpty ? ' attr--empty' : ''}`}>
      <div className="attr__head">
        <label className="label" htmlFor={inputId}>
          {attribute.name}
          {isEmpty && <span className="attr__flag">не заполнено</span>}
        </label>

        {onRemove && (
          <button
            type="button"
            className="attr__remove"
            onClick={onRemove}
            aria-label={`Убрать атрибут «${attribute.name}» из профиля`}
            title="Убрать из профиля"
          >
            <TrashIcon size={14} aria-hidden="true" />
          </button>
        )}
      </div>

      {attribute.description && <p className="attr__hint muted-3">{attribute.description}</p>}

      <AttributeInput id={inputId} attribute={attribute} value={value} onChange={onChange} />
    </div>
  )
}

function AttributeInput({
  id,
  attribute,
  value,
  onChange,
}: {
  id: string
  attribute: ProfileAttribute
  value: AttributeValue
  onChange: (value: AttributeValue) => void
}) {
  switch (attribute.type) {
    case 'text':
      return (
        <textarea
          id={id}
          className="input input--area"
          rows={4}
          placeholder="Поддерживается Markdown"
          value={asText(value)}
          onChange={(event) => onChange(event.target.value)}
        />
      )

    case 'numeric':
      return (
        <input
          id={id}
          type="number"
          step="any"
          className="input"
          value={asNumberText(value)}
          // Пустое поле — это очищенное значение, а не ноль.
          onChange={(event) => onChange(event.target.value === '' ? null : event.target.value)}
        />
      )

    case 'date':
      return (
        <input
          id={id}
          type="date"
          className="input"
          value={asText(value)}
          onChange={(event) => onChange(event.target.value || null)}
        />
      )

    case 'boolean':
      return (
        <label className="checkline" htmlFor={id}>
          <input
            id={id}
            type="checkbox"
            checked={value === true}
            onChange={(event) => onChange(event.target.checked)}
          />
          <span className="t-sm">{value === true ? 'Да' : 'Нет'}</span>
        </label>
      )

    case 'select':
      return (
        <select
          id={id}
          className="input"
          value={asText(value)}
          onChange={(event) => onChange(event.target.value || null)}
        >
          <option value="">— не выбрано —</option>
          {attribute.options.map((option) => (
            <option key={option} value={option}>
              {option}
            </option>
          ))}
        </select>
      )

    case 'period': {
      const period = asPeriod(value)

      return (
        <div className="period">
          <input
            id={id}
            type="date"
            className="input"
            aria-label="Начало периода"
            value={period.from ?? ''}
            onChange={(event) => onChange({ ...period, from: event.target.value || null })}
          />
          <span className="muted-3" aria-hidden="true">
            —
          </span>
          <input
            type="date"
            className="input"
            aria-label="Конец периода, пусто — по настоящее время"
            value={period.to ?? ''}
            onChange={(event) => onChange({ ...period, to: event.target.value || null })}
          />
        </div>
      )
    }

    case 'image':
      return <ImageField id={id} value={asText(value)} onChange={onChange} />

    default:
      return (
        <input
          id={id}
          type="text"
          className="input"
          value={asText(value)}
          onChange={(event) => onChange(event.target.value)}
        />
      )
  }
}
