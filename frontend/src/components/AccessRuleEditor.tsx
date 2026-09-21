import { TrashIcon } from '@phosphor-icons/react'
import type { AccessRule, AttributeType, FilterOperator, LibraryAttribute } from '../api/types'
import { useTranslation } from '../i18n/context'
import type { MessageKey } from '../i18n/messages'

const OPERATOR_LABELS: Record<FilterOperator, MessageKey> = {
  eq: 'rule.op.eq',
  neq: 'rule.op.neq',
  gt: 'rule.op.gt',
  gte: 'rule.op.gte',
  lt: 'rule.op.lt',
  lte: 'rule.op.lte',
  contains: 'rule.op.contains',
  in: 'rule.op.in',
  is_set: 'rule.op.isSet',
}

interface Props {
  rules: AccessRule[]
  attributes: LibraryAttribute[]
  operators: Record<AttributeType, FilterOperator[]>
  onChange: (rules: AccessRule[]) => void
}

export function AccessRuleEditor({ rules, attributes, operators, onChange }: Props) {
  const t = useTranslation()
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
          {t('rule.empty')}
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
                aria-label={t('rule.attribute')}
                value={rule.attributeId}
                onChange={(event) => {
                  const next = byId.get(Number(event.target.value))
                  const nextAllowed = next ? (operators[next.type] ?? ['is_set']) : ['is_set']

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
                aria-label={t('rule.operator')}
                value={rule.operator}
                onChange={(event) =>
                  update(index, { operator: event.target.value as FilterOperator })
                }
              >
                {allowed.map((operator) => (
                  <option key={operator} value={operator}>
                    {t(OPERATOR_LABELS[operator])}
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
                aria-label={t('rule.remove')}
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
        {t('rule.add')}
      </button>

    </div>

  )
}

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
  const t = useTranslation()

  if (operator === 'is_set') {
    return <span className="muted-3 t-sm rulerow__none">{t('rule.noValue')}</span>
  }

  if (type === 'boolean') {
    return (
      <select
        className="input"
        aria-label={t('rule.value')}
        value={value === true ? 'true' : 'false'}
        onChange={(event) => onChange(event.target.value === 'true')}
      >
        <option value="true">{t('rule.checked')}</option>

        <option value="false">{t('rule.unchecked')}</option>

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
        aria-label={t('rule.value')}
        value={typeof value === 'string' ? value : ''}
        onChange={(event) => onChange(event.target.value)}
      >
        <option value="">{t('rule.choose')}</option>

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
      aria-label={t('rule.value')}
      type={type === 'numeric' ? 'number' : type === 'date' ? 'date' : 'text'}
      step={type === 'numeric' ? 'any' : undefined}
      value={type === 'numeric' && text.includes('.') ? text.replace(/\.?0+$/, '') : text}
      onChange={(event) => onChange(event.target.value)}
    />

  )
}
