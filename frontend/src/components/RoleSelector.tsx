import { CaretRightIcon } from '@phosphor-icons/react'
import type { SelectableRole } from '../api/types'
import { ROLE_OPTIONS } from '../lib/roles'

interface Props {
  onPick: (role: SelectableRole) => void
}

/**
 * Первый шаг: выбор роли сразу ведёт дальше, поэтому это кнопки, а не radio.
 * Промежуточного «подтвердить» здесь нет — выбор и есть действие.
 */
export function RoleSelector({ onPick }: Props) {
  return (
    <div className="rolepick mt6">
      {ROLE_OPTIONS.map(({ value, title, text, Icon, mod }) => (
        <button key={value} type="button" className="rolecard" onClick={() => onPick(value)}>
          <span className={`rolecard__icon rolecard__icon--${mod}`}>
            {/* Иконка дублирует заголовок рядом, поэтому скрыта от скринридера. */}
            <Icon size={19} aria-hidden="true" />
          </span>

          <span className="rolecard__body">
            <span className="rolecard__title">{title}</span>
            <span className="rolecard__text">{text}</span>
          </span>

          <span className="rolecard__go">
            <CaretRightIcon size={16} aria-hidden="true" />
          </span>
        </button>
      ))}
    </div>
  )
}
