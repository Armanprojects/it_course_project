import { TrashIcon } from '@phosphor-icons/react'
import type { AttributeValue, PeriodValue, ProfileAttribute } from '../api/types'
import { ImageField } from './ImageField'
import { useTranslation } from '../i18n/context'

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
  const t = useTranslation()
  const inputId = `attr-${attribute.attributeId}`
  const isEmpty = value === null || value === '' || value === undefined

  return (
    <div className={`attr${isEmpty ? ' attr--empty' : ''}`}>
      <div className="attr__head">
        <label className="label" htmlFor={inputId}>
          {attribute.name}
          {isEmpty && <span className="attr__flag">{t('common.notFilled')}</span>}
        </label>

        {onRemove && (
          <button
            type="button"
            className="attr__remove"
            onClick={onRemove}
            aria-label={t('attr.removeFromProfile', { name: attribute.name })}
            title={t('attr.removeTitle')}
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

/**
 * Сам элемент ввода, без обвязки с подписью и кнопкой удаления. Экспортируется
 * ради резюме: там атрибут правится по месту, и повторять восемь типов ввода
 * второй раз означало бы гарантированно их рассинхронизировать.
 */
export function AttributeInput({
  id,
  attribute,
  value,
  onChange,
  autoFocus,
  onBlur,
}: {
  id: string
  attribute: Pick<ProfileAttribute, 'attributeId' | 'type' | 'options'>
  value: AttributeValue
  onChange: (value: AttributeValue) => void
  autoFocus?: boolean
  onBlur?: () => void
}) {
  const t = useTranslation()
  switch (attribute.type) {
    case 'text':
      return (
        <textarea
          id={id}
          className="input input--area"
          rows={4}
          autoFocus={autoFocus}
          onBlur={onBlur}
          placeholder={t('attr.markdownHint')}
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
          autoFocus={autoFocus}
          onBlur={onBlur}
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
          autoFocus={autoFocus}
          onBlur={onBlur}
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
            autoFocus={autoFocus}
            onBlur={onBlur}
            checked={value === true}
            onChange={(event) => onChange(event.target.checked)}
          />
          <span className="t-sm">{t(value === true ? 'common.yes' : 'common.no')}</span>
        </label>
      )

    case 'select':
      return (
        <select
          id={id}
          className="input"
          autoFocus={autoFocus}
          onBlur={onBlur}
          value={asText(value)}
          onChange={(event) => onChange(event.target.value || null)}
        >
          <option value="">{t('attr.notSelected')}</option>
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
        <div className="period" onBlur={onBlur}>
          <input
            id={id}
            type="date"
            className="input"
            autoFocus={autoFocus}
            aria-label={t('attr.periodStart')}
            value={period.from ?? ''}
            onChange={(event) => onChange({ ...period, from: event.target.value || null })}
          />
          <span className="muted-3" aria-hidden="true">
            —
          </span>
          <input
            type="date"
            className="input"
            aria-label={t('attr.periodEnd')}
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
          autoFocus={autoFocus}
          onBlur={onBlur}
          value={asText(value)}
          onChange={(event) => onChange(event.target.value)}
        />
      )
  }
}
