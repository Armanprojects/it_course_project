/** Роли из App\Enum\UserRole. Значения должны совпадать с бэкендом. */
export const UserRole = {
  Candidate: 'ROLE_CANDIDATE',
  Recruiter: 'ROLE_RECRUITER',
  Admin: 'ROLE_ADMIN',
} as const

export type UserRole = (typeof UserRole)[keyof typeof UserRole]

/** Роль, которую пользователь выбирает на экране входа. */
export type SelectableRole = typeof UserRole.Candidate | typeof UserRole.Recruiter

export interface User {
  id: number
  email: string
  roles: string[]
  status: 'pending' | 'active' | 'blocked'
  locale: 'en' | 'ru'
  theme: 'light' | 'dark'
  createdAt: string
  lastLoginAt: string | null
  profileId: number | null
  hasPassword: boolean
  emailVerifiedAt: string | null
  identities: string[]
}

export interface AuthResponse {
  token: string
  user: User
}

/** Ответ на регистрацию и повторную отправку письма — токена здесь нет. */
export interface RegistrationPending {
  status: 'verification_sent'
  email?: string
  message: string
}

/** Конверт ошибок из ApiExceptionSubscriber. */
export interface ApiError {
  error: string
  message: string
  violations?: Record<string, string>
}

export type OAuthProvider = 'google' | 'github'
