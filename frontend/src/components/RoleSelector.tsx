import { BriefcaseIcon, CheckCircleIcon, UsersThreeIcon } from '@phosphor-icons/react'
import { UserRole, type SelectableRole } from '../api/types'

interface RoleOption {
  value: SelectableRole
  title: string
  description: string
  Icon: typeof BriefcaseIcon
}

const OPTIONS: RoleOption[] = [
  {
    value: UserRole.Candidate,
    title: 'Я ищу работу',
    description: 'Заполню профиль и создам резюме',
    Icon: BriefcaseIcon,
  },
  {
    value: UserRole.Recruiter,
    title: 'Я ищу сотрудников',
    description: 'Создам позиции и найду кандидатов',
    Icon: UsersThreeIcon,
  },
]

interface Props {
  value: SelectableRole
  onChange: (role: SelectableRole) => void
  disabled?: boolean
}

/**
 * Группа radio, а не набор кнопок: нативные input дают стрелки клавиатуры,
 * объявление «выбрано 1 из 2» скринридером и корректную семантику формы.
 * Визуально они скрыты, оформление несёт label.
 */
export function RoleSelector({ value, onChange, disabled = false }: Props) {
  return (
    <fieldset className="mb-4" disabled={disabled}>
      {/* float:none снимает превращение legend в блок на всю ширину, из-за
          которого Bootstrap ломает его семантику группы. */}
      <legend className="fw-semibold fs-6 mb-2" style={{ float: 'none', width: 'auto' }}>
        Кто вы?
      </legend>

      <div className="d-flex flex-column gap-2">
        {OPTIONS.map(({ value: role, title, description, Icon }) => (
          <div key={role}>
            <input
              type="radio"
              className="role-option__input"
              name="role"
              id={`role-${role}`}
              value={role}
              checked={value === role}
              onChange={() => onChange(role)}
              // Без явного имени скринридер зачитал бы value («ROLE_CANDIDATE»):
              // текст лежит в label, а он оформляет карточку, а не подписывает input.
              aria-label={`${title}. ${description}`}
            />
            <label className="role-option__label" htmlFor={`role-${role}`}>
              <span className="role-option__icon">
                {/* Иконка дублирует текст рядом, поэтому скрыта от скринридера. */}
                <Icon size={22} weight="regular" aria-hidden="true" />
              </span>

              <span className="d-flex flex-column">
                <span className="fw-semibold lh-sm">{title}</span>
                <span className="small text-body-secondary lh-sm">{description}</span>
              </span>

              {/* Второй, не цветовой признак выбора — для дальтоников. */}
              <span className="role-option__check">
                <CheckCircleIcon size={22} weight="fill" aria-hidden="true" />
              </span>
            </label>
          </div>
        ))}
      </div>
    </fieldset>
  )
}
