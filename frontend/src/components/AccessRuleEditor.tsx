import { TrashIcon } from '@phosphor-icons/react'
import type { AccessRule, AttributeType, FilterOperator, LibraryAttribute } from '../api/types'

/** Подписи операторов из App\Enum\FilterOperator. */
const OPERATOR_LABELS: Record<FilterOperator, string> = {
  eq: 'равно',
  neq: 'не равно',
  gt: 'больше',
  gte: 'больше или равно',
  lt: 'меньше',
  lte: 'меньше или равно',
  contains: 'содержит',
  in: 'один из',
  is_set: 'заполнено',
}

interface Props {
  rules: AccessRule[]
  /** Атрибуты, по которым можно строить правило. */
  attributes: LibraryAttribute[]
  /** Какие операторы допускает каждый тип — приходит с сервера. */
  operators: Record<AttributeType, FilterOperator[]>
  onChange: (rules: AccessRule[]) => void
}

/**
 * Редактор правил доступа к позиции.
 *
 * Правила складываются по И: кандидат видит позицию, только если проходит
 * все. Набор операторов зависит от типа атрибута — это ограничение приходит
 * с сервера, чтобы клиент не мог предложить «содержит» для флажка.
 */
export function AccessRuleEditor({ rules, attributes, operators, onChange }: Props) {
  const byId = new Map(attributes.map((attribute) => [attribute.id, attribute]))

  const update = (index: number, patch: Partial<AccessRule>) => {
    onChange(rules.map((rule, i) => (i === index ? { ...rule, ...patch } : rule)))
  }

  const add = () => {
    const first = attributes[0]

    if (!first) {
      return
    }

    const allowed = operators[first.type] ?? ['is_set']
    onChange([...rules, { attributeId: first.id, operator: allowed[0], value: null }])
  }

  return (
    <div className="col g3">
      {rules.length === 0 ? (
        <p className="muted t-sm" style={{ margin: 0 }}>
          Правил нет — позиция закрыта для всех, пока не станет публичной или не
          появится хотя бы одно правило.
        </p>
      ) : (
        rules.map((rule, index) => {
          const attribute = byId.get(rule.attributeId)
          const type = attribute?.type ?? 'string'
          const allowed = operators[type] ?? ['is_set']

          return (
            <div className="rulerow" key={`${rule.attributeId}-${index}`}>
              <select
                className="input"
                aria-label="Атрибут"
                value={rule.attributeId}
                onChange={(event) => {
                  const next = byId.get(Number(event.target.value))
                  const nextAllowed = next ? (operators[next.type] ?? ['is_set']) : ['is_set']

                  // Тип сменился — старый оператор и операнд могут быть
                  // невалидны, поэтому сбрасываем их вместе с атрибутом.
                  update(index, {
                    attributeId: Number(event.target.value),
                    operator: nextAllowed[0] as FilterOperator,
                    value: null,
                  })
                }}
              >
                {attributes.map((option) => (
                  <option key={option.id} value={option.id}>
                    {option.name}
                  </option>
                ))}
              </select>

              <select
                className="input"
                aria-label="Оператор"
                value={rule.operator}
                onChange={(event) =>
                  update(index, { operator: event.target.value as FilterOperator })
                }
              >
                {allowed.map((operator) => (
                  <option key={operator} value={operator}>
                    {OPERATOR_LABELS[operator]}
                  </option>
                ))}
              </select>

              <OperandInput
                type={type}
                operator={rule.operator}
                options={attribute?.options ?? []}
                value={rule.value}
                onChange={(value) => update(index, { value })}
              />

              <button
                type="button"
                className="attr__remove"
                onClick={() => onChange(rules.filter((_, i) => i !== index))}
                aria-label="Убрать правило"
              >
                <TrashIcon size={14} aria-hidden="true" />
              </button>
            </div>
          )
        })
      )}

      <button
        type="button"
        className="btn btn--outline"
        style={{ alignSelf: 'flex-start' }}
        onClick={add}
        disabled={attributes.length === 0}
      >
        Добавить правило
      </button>
    </div>
  )
}

/** Поле операнда: его вид определяет тип атрибута, а не оператор. */
function OperandInput({
  type,
  operator,
  options,
  value,
  onChange,
}: {
  type: AttributeType
  operator: FilterOperator
  options: string[]
  value: unknown
  onChange: (value: unknown) => void
}) {
  // «Заполнено» проверяет наличие значения, сравнивать не с чем.
  if (operator === 'is_set') {
    return <span className="muted-3 t-sm rulerow__none">значение не нужно</span>
  }

  if (type === 'boolean') {
    return (
      <select
        className="input"
        aria-label="Значение"
        value={value === true ? 'true' : 'false'}
        onChange={(event) => onChange(event.target.value === 'true')}
      >
        <option value="true">отмечено</option>
        <option value="false">не отмечено</option>
      </select>
    )
  }

  if (type === 'select' && operator === 'in') {
    const selected = Array.isArray(value) ? (value as string[]) : []

    return (
      <div className="rulerow__options">
        {options.map((option) => (
          <label key={option} className="checkline t-sm">
            <input
              type="checkbox"
              checked={selected.includes(option)}
              onChange={(event) =>
                onChange(
                  event.target.checked
                    ? [...selected, option]
                    : selected.filter((item) => item !== option),
                )
              }
            />
            {option}
          </label>
        ))}
      </div>
    )
  }

  if (type === 'select') {
    return (
      <select
        className="input"
        aria-label="Значение"
        value={typeof value === 'string' ? value : ''}
        onChange={(event) => onChange(event.target.value)}
      >
        <option value="">— выберите —</option>
        {options.map((option) => (
          <option key={option} value={option}>
            {option}
          </option>
        ))}
      </select>
    )
  }

  const text = typeof value === 'string' || typeof value === 'number' ? String(value) : ''

  return (
    <input
      className="input"
      aria-label="Значение"
      type={type === 'numeric' ? 'number' : type === 'date' ? 'date' : 'text'}
      step={type === 'numeric' ? 'any' : undefined}
      // Операнд приходит из decimal(20,6) строкой «5.000000»: показывать
      // хвост нулей незачем, а числом его гонять нельзя — потеряется точность.
      value={type === 'numeric' && text.includes('.') ? text.replace(/\.?0+$/, '') : text}
      onChange={(event) => onChange(event.target.value)}
    />
  )
}
