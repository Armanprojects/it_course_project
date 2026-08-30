import axios, { AxiosError } from 'axios'
import type {
  ApiError,
  AuthResponse,
  OAuthProvider,
  RegistrationPending,
  SelectableRole,
  User,
} from './types'

/** Относительные пути: фронтенд и API за одним nginx, CORS не нужен. */
const api = axios.create({ baseURL: '/api' })

const TOKEN_KEY = 'cv_token'

export const tokenStorage = {
  get: (): string | null => localStorage.getItem(TOKEN_KEY),
  set: (token: string) => localStorage.setItem(TOKEN_KEY, token),
  clear: () => localStorage.removeItem(TOKEN_KEY),
}

api.interceptors.request.use((config) => {
  const token = tokenStorage.get()

  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }

  return config
})

/**
 * Ошибка с полями, понятными форме: код для логики, message для человека,
 * violations для подсветки конкретных полей.
 */
export class RequestError extends Error {
  // Поля объявлены отдельно, а не параметрами конструктора: сборка идёт с
  // erasableSyntaxOnly, где параметры-свойства запрещены — такой синтаксис
  // нельзя просто стереть при компиляции, он порождает код.
  readonly code: string
  readonly violations: Record<string, string>

  constructor(message: string, code: string, violations: Record<string, string> = {}) {
    super(message)
    this.name = 'RequestError'
    this.code = code
    this.violations = violations
  }
}

function toRequestError(error: unknown): RequestError {
  if (error instanceof AxiosError) {
    const data = error.response?.data as ApiError | undefined

    if (data?.message) {
      return new RequestError(data.message, data.error ?? 'request_failed', data.violations ?? {})
    }

    // Ответа нет вообще — сеть или упавший бэкенд.
    return new RequestError(
      'Сервер недоступен. Проверьте подключение и попробуйте снова.',
      'network_error',
    )
  }

  return new RequestError('Непредвиденная ошибка.', 'unexpected_error')
}

async function request<T>(run: () => Promise<{ data: T }>): Promise<T> {
  try {
    return (await run()).data
  } catch (error) {
    throw toRequestError(error)
  }
}

export const authApi = {
  login: (email: string, password: string) =>
    request<AuthResponse>(() => api.post('/auth/login', { email, password })),

  /**
   * Регистрация не выдаёт токен: адрес ещё не подтверждён, входить не с чем.
   */
  register: (email: string, password: string, passwordConfirmation: string, role: SelectableRole) =>
    request<RegistrationPending>(() =>
      api.post('/auth/register', { email, password, passwordConfirmation, role }),
    ),

  verifyEmail: (token: string) => request<AuthResponse>(() => api.post('/auth/verify', { token })),

  resendVerification: (email: string) =>
    request<RegistrationPending>(() => api.post('/auth/verify/resend', { email })),

  me: () => request<User>(() => api.get('/auth/me')),

  /**
   * OAuth уводит браузер на провайдера, поэтому это переход, а не запрос.
   * Роль уходит параметром: бэкенд кладёт её в сессию до редиректа и читает
   * на колбэке — состояние React к тому моменту уже потеряно.
   */
  startOAuth: (provider: OAuthProvider, role: SelectableRole) => {
    window.location.href = `/api/auth/oauth/${provider}?role=${encodeURIComponent(role)}`
  },
}

export default api
