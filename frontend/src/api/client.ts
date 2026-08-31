import axios, { AxiosError } from 'axios'
import type {
  ApiError,
  AttributeInput,
  AttributeLibrary,
  AttributeLibraryAdmin,
  AttributeType,
  AttributeValue,
  AuthResponse,
  CvDetail,
  CvRow,
  DiscussionMessage,
  FilterOperator,
  HomeData,
  ManagedAttribute,
  OAuthProvider,
  PositionDetail,
  PositionEditable,
  PositionInput,
  PositionPage,
  PositionSort,
  ProfileData,
  ProfileProject,
  ProjectInput,
  RegistrationPending,
  SelectableRole,
  SortDirection,
  TagSuggestion,
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
  /** Версия на сервере — только для конфликта оптимистичной блокировки. */
  readonly currentVersion?: number

  constructor(
    message: string,
    code: string,
    violations: Record<string, string> = {},
    currentVersion?: number,
  ) {
    super(message)
    this.name = 'RequestError'
    this.code = code
    this.violations = violations
    this.currentVersion = currentVersion
  }

  get isConflict(): boolean {
    return this.code === 'version_conflict'
  }
}

function toRequestError(error: unknown): RequestError {
  if (error instanceof AxiosError) {
    const data = error.response?.data as ApiError | undefined

    if (data?.message) {
      return new RequestError(
        data.message,
        data.error ?? 'request_failed',
        data.violations ?? {},
        data.currentVersion,
      )
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

export interface PositionQuery {
  search?: string
  sort?: PositionSort
  direction?: SortDirection
  page?: number
  pageSize?: number
}

/**
 * Каталог позиций и главная страница открыты без токена — их можно
 * запрашивать до входа. Интерцептор всё равно подставит токен, если он есть:
 * бэкенд узнаёт вошедшего и на публичных эндпоинтах.
 */
export const catalogApi = {
  home: () => request<HomeData>(() => api.get('/home')),

  positions: (query: PositionQuery = {}) =>
    request<PositionPage>(() => api.get('/positions', { params: query })),

  position: (id: number) => request<PositionDetail>(() => api.get(`/positions/${id}`)),
}

/**
 * Профиль: всё закрыто входом, читать и править может только владелец
 * (и администратор — чужой профиль по id).
 */
export const profileApi = {
  me: () => request<ProfileData>(() => api.get('/profile/me')),

  byId: (id: number) => request<ProfileData>(() => api.get(`/profile/${id}`)),

  /**
   * Тик автосохранения: уходит версия, которую клиент видел последней, и все
   * значения раздела. Ответ — профиль целиком с новой версией.
   */
  save: (version: number, values: Record<number, AttributeValue>) =>
    request<ProfileData>(() => api.patch('/profile/me', { version, values })),

  addAttribute: (attributeId: number, version: number) =>
    request<ProfileData>(() => api.post(`/profile/me/attributes/${attributeId}`, { version })),

  removeAttribute: (attributeId: number, version: number) =>
    request<ProfileData>(() =>
      api.delete(`/profile/me/attributes/${attributeId}`, { params: { version } }),
    ),

  createProject: (input: ProjectInput) =>
    request<ProfileProject>(() => api.post('/profile/me/projects', input)),

  updateProject: (id: number, input: ProjectInput) =>
    request<ProfileProject>(() => api.put(`/profile/me/projects/${id}`, input)),

  deleteProject: (id: number) =>
    request<void>(() => api.delete(`/profile/me/projects/${id}`)),
}

/** Библиотека атрибутов и теги — для выбора в профиле. */
export const libraryApi = {
  attributes: (params: { search?: string; category?: string } = {}) =>
    request<AttributeLibrary>(() => api.get('/attributes', { params })),

  tags: (q: string) =>
    request<{ items: TagSuggestion[] }>(() => api.get('/tags/suggest', { params: { q } })),
}

/**
 * Управление позициями — только для рекрутеров и админов.
 * Владения позицией нет: любой рекрутер правит любую.
 */
export const positionAdminApi = {
  edit: (id: number) => request<PositionEditable>(() => api.get(`/positions/${id}/edit`)),

  create: (input: PositionInput) =>
    request<PositionEditable>(() => api.post('/positions', input)),

  update: (id: number, input: PositionInput) =>
    request<PositionEditable>(() => api.put(`/positions/${id}`, input)),

  duplicate: (id: number) =>
    request<PositionEditable>(() => api.post(`/positions/${id}/duplicate`)),

  remove: (id: number) => request<void>(() => api.delete(`/positions/${id}`)),

  cvs: (id: number, drafts = false) =>
    request<{ items: CvRow[]; total: number }>(() =>
      api.get(`/positions/${id}/cvs`, { params: { drafts: drafts ? 1 : undefined } }),
    ),

  /** Какие операторы допускает каждый тип атрибута. */
  operators: () =>
    request<{ operators: Record<AttributeType, FilterOperator[]> }>(() =>
      api.get('/positions/meta/operators'),
    ),
}

/** Библиотека атрибутов: чтение всем, запись рекрутерам. */
export const attributeAdminApi = {
  manage: (params: { search?: string; category?: string } = {}) =>
    request<AttributeLibraryAdmin>(() => api.get('/attributes/manage', { params })),

  create: (input: AttributeInput) =>
    request<ManagedAttribute>(() => api.post('/attributes', input)),

  update: (id: number, input: AttributeInput) =>
    request<ManagedAttribute>(() => api.put(`/attributes/${id}`, input)),

  remove: (id: number) => request<void>(() => api.delete(`/attributes/${id}`)),

  restore: (id: number) => request<ManagedAttribute>(() => api.post(`/attributes/${id}/restore`)),
}

/** Резюме: создание кандидатом, чтение рекрутером, лайки. */
export const cvApi = {
  show: (id: number) => request<CvDetail>(() => api.get(`/cvs/${id}`)),

  start: (positionId: number) =>
    request<CvDetail>(() => api.post(`/cvs/positions/${positionId}`)),

  publish: (id: number) => request<CvDetail>(() => api.post(`/cvs/${id}/publish`)),

  unpublish: (id: number) => request<CvDetail>(() => api.delete(`/cvs/${id}/publish`)),

  remove: (id: number) => request<void>(() => api.delete(`/cvs/${id}`)),

  like: (id: number) =>
    request<{ likesCount: number; likedByMe: boolean }>(() => api.post(`/cvs/${id}/like`)),

  unlike: (id: number) =>
    request<{ likesCount: number; likedByMe: boolean }>(() => api.delete(`/cvs/${id}/like`)),

  search: (q: string) =>
    request<{ items: CvRow[]; total: number }>(() => api.get('/cvs/search', { params: { q } })),
}

/** Обсуждение позиции. Обновления — опросом: after отдаёт только новое. */
export const discussionApi = {
  list: (positionId: number, after?: number) =>
    request<{ items: DiscussionMessage[]; lastId: number | null }>(() =>
      api.get(`/positions/${positionId}/discussion`, { params: { after } }),
    ),

  post: (positionId: number, content: string) =>
    request<DiscussionMessage>(() =>
      api.post(`/positions/${positionId}/discussion`, { content }),
    ),
}

export default api
