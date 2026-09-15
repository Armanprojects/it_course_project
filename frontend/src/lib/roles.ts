import { BriefcaseIcon, UsersThreeIcon } from '@phosphor-icons/react'
import { UserRole, type SelectableRole } from '../api/types'
import type { MessageKey } from '../i18n/messages'

export interface RoleOption {
  value: SelectableRole
  title: MessageKey
  text: MessageKey
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
    title: 'role.candidateTitle',
    text: 'role.candidateText',
    Icon: BriefcaseIcon,
    mod: 'c',
  },
  {
    value: UserRole.Recruiter,
    title: 'role.recruiterTitle',
    text: 'role.recruiterText',
    Icon: UsersThreeIcon,
    mod: 'r',
  },
]

/** Короткая подпись для плашки выбранной роли. */
export const ROLE_PILL: Record<SelectableRole, MessageKey> = {
  [UserRole.Candidate]: 'role.candidate',
  [UserRole.Recruiter]: 'role.recruiter',
}

export const ROLE_ICON: Record<SelectableRole, typeof BriefcaseIcon> = {
  [UserRole.Candidate]: BriefcaseIcon,
  [UserRole.Recruiter]: UsersThreeIcon,
}
