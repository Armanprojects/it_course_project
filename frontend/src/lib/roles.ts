import { BriefcaseIcon, UsersThreeIcon } from '@phosphor-icons/react'
import { UserRole, type SelectableRole } from '../api/types'

export interface RoleOption {
  value: SelectableRole
  title: string
  text: string
  Icon: typeof BriefcaseIcon
  /** Модификатор цвета плитки: r — рекрутёр, c — кандидат. */
  mod: 'r' | 'c'
}

/**
 * Описания ролей вынесены из компонента: иначе экспорт констант рядом с
 * компонентом ломает hot reload — Vite перезагружает модуль целиком.
 */
export const ROLE_OPTIONS: RoleOption[] = [
  {
    value: UserRole.Candidate,
    title: 'Я ищу работу',
    text: 'Заполню профиль один раз и буду откликаться на позиции',
    Icon: BriefcaseIcon,
    mod: 'c',
  },
  {
    value: UserRole.Recruiter,
    title: 'Я ищу сотрудников',
    text: 'Создам позиции и буду просматривать резюме кандидатов',
    Icon: UsersThreeIcon,
    mod: 'r',
  },
]

/** Короткая подпись для плашки выбранной роли. */
export const ROLE_PILL: Record<SelectableRole, string> = {
  [UserRole.Candidate]: 'Соискатель',
  [UserRole.Recruiter]: 'Рекрутёр',
}

export const ROLE_ICON: Record<SelectableRole, typeof BriefcaseIcon> = {
  [UserRole.Candidate]: BriefcaseIcon,
  [UserRole.Recruiter]: UsersThreeIcon,
}
