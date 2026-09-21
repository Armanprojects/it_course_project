import axios, { AxiosError } from 'axios'
import type { AxiosResponse } from 'axios'
import type {
  ApiError,
  AttributeInput,
  AttributeLibrary,
  AttributeLibraryAdmin,
  AttributeType,
  AttributeValue,
  AdminUserPage,
  AuthResponse,
  CvDetail,
  CvPage,
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

const api = axios.create({ baseURL: '/api' })

const TOKEN_KEY = 'cv_token'

function expiryOf(token: string): number | null {
  const payload = token.split('.')[1]

  if (!payload) {
    return null
  }

  try {
    const json = atob(payload.replace(/-/g, '+').replace(/_/g, '/'))
    const exp = (JSON.parse(json) as { exp?: number }).exp

    return typeof exp === 'number' ? exp * 1000 : null
  } catch {
    return null
  }
}

export const tokenStorage = {
  get: (): string | null => localStorage.getItem(TOKEN_KEY),
  set: (token: string) => localStorage.setItem(TOKEN_KEY, token),
  clear: () => localStorage.removeItem(TOKEN_KEY),

  isValid(): boolean {
    const token = this.get()

    if (token === null) {
      return false
    }

    const expiresAt = expiryOf(token)

    return expiresAt !== null && expiresAt > Date.now()
  },
}

api.interceptors.request.use((config) => {
  const token = tokenStorage.get()

  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }

  return config
})

api.interceptors.response.use(
  (response) => response,
  (error: unknown) => {
    const status = error instanceof AxiosError ? error.response?.status : undefined

    if (status === 401 && tokenStorage.get() !== null) {
      tokenStorage.clear()

      if (!window.location.pathname.startsWith('/login')) {
        window.location.href = '/login'
      }
    }

    return Promise.reject(error instanceof Error ? error : new Error(String(error)))
  },
)

export class RequestError extends Error {
  readonly code: string
  readonly violations: Record<string, string>
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

  get isLocalized(): boolean {
    return this.code === 'network_error' || this.code === 'unexpected_error'
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

    return new RequestError('error.network', 'network_error')
  }

  return new RequestError('common.unexpectedError', 'unexpected_error')
}

async function request<T>(run: () => Promise<{ data: T }>): Promise<T> {
  try {
    return (await run()).data
  } catch (error) {
    throw toRequestError(error)
  }
}

async function blobError(error: unknown): Promise<RequestError> {
  if (error instanceof AxiosError && error.response?.data instanceof Blob) {
    try {
      const parsed = JSON.parse(await error.response.data.text()) as ApiError

      if (parsed.message) {
        return new RequestError(parsed.message, parsed.error ?? 'request_failed')
      }
    } catch {
    }
  }

  return toRequestError(error)
}

async function download(
  run: () => Promise<AxiosResponse<Blob>>,
  fallbackName: string,
): Promise<void> {
  let response: AxiosResponse<Blob>

  try {
    response = await run()
  } catch (error) {
    throw await blobError(error)
  }

  const url = URL.createObjectURL(response.data)
  const link = document.createElement('a')

  link.href = url
  link.download = fileNameOf(response, fallbackName)
  document.body.append(link)
  link.click()
  link.remove()

  URL.revokeObjectURL(url)
}

function fileNameOf(response: AxiosResponse<Blob>, fallback: string): string {
  const disposition = response.headers['content-disposition'] as string | undefined

  if (disposition === undefined) {
    return fallback
  }

  const utf8 = /filename\*=UTF-8''([^;]+)/i.exec(disposition)

  if (utf8?.[1]) {
    try {
      return decodeURIComponent(utf8[1])
    } catch {
      return fallback
    }
  }

  const plain = /filename="?([^";]+)"?/i.exec(disposition)

  return plain?.[1] ?? fallback
}

export const authApi = {
  login: (email: string, password: string) =>
    request<AuthResponse>(() => api.post('/auth/login', { email, password })),

  register: (email: string, password: string, passwordConfirmation: string, role: SelectableRole) =>
    request<RegistrationPending>(() =>
      api.post('/auth/register', { email, password, passwordConfirmation, role }),
    ),

  verifyEmail: (token: string) => request<AuthResponse>(() => api.post('/auth/verify', { token })),

  updateSettings: (settings: { locale?: string; theme?: string }) =>
    request<User>(() => api.patch('/auth/settings', settings)),

  resendVerification: (email: string) =>
    request<RegistrationPending>(() => api.post('/auth/verify/resend', { email })),

  me: () => request<User>(() => api.get('/auth/me')),

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

export const catalogApi = {
  home: () => request<HomeData>(() => api.get('/home')),

  positions: (query: PositionQuery = {}) =>
    request<PositionPage>(() => api.get('/positions', { params: query })),

  position: (id: number) => request<PositionDetail>(() => api.get(`/positions/${id}`)),
}

export type ProfileTarget = 'me' | number

export const profileApi = {
  me: () => request<ProfileData>(() => api.get('/profile/me')),

  byId: (id: number) => request<ProfileData>(() => api.get(`/profile/${id}`)),

  save: (version: number, values: Record<number, AttributeValue>, target: ProfileTarget = 'me') =>
    request<ProfileData>(() => api.patch(`/profile/${target}`, { version, values })),

  addAttribute: (attributeId: number, version: number, target: ProfileTarget = 'me') =>
    request<ProfileData>(() =>
      api.post(`/profile/${target}/attributes/${attributeId}`, { version }),
    ),

  removeAttribute: (attributeId: number, version: number, target: ProfileTarget = 'me') =>
    request<ProfileData>(() =>
      api.delete(`/profile/${target}/attributes/${attributeId}`, { params: { version } }),
    ),

  createProject: (input: ProjectInput, target: ProfileTarget = 'me') =>
    request<ProfileProject>(() => api.post(`/profile/${target}/projects`, input)),

  updateProject: (id: number, input: ProjectInput, target: ProfileTarget = 'me') =>
    request<ProfileProject>(() => api.put(`/profile/${target}/projects/${id}`, input)),

  deleteProject: (id: number, target: ProfileTarget = 'me') =>
    request<void>(() => api.delete(`/profile/${target}/projects/${id}`)),
}

export const adminApi = {
  users: (params: { search?: string; role?: string; status?: string; page?: number } = {}) =>
    request<AdminUserPage>(() => api.get('/admin/users', { params })),

  block: (id: number) => request<User>(() => api.post(`/admin/users/${id}/block`)),

  unblock: (id: number) => request<User>(() => api.delete(`/admin/users/${id}/block`)),

  grantRole: (id: number, role: string) =>
    request<User>(() => api.post(`/admin/users/${id}/roles`, { role })),

  revokeRole: (id: number, role: string) =>
    request<User>(() => api.delete(`/admin/users/${id}/roles`, { data: { role } })),

  deleteUser: (id: number) => request<void>(() => api.delete(`/admin/users/${id}`)),
}

export const libraryApi = {
  attributes: (params: { search?: string; category?: string } = {}) =>
    request<AttributeLibrary>(() => api.get('/attributes', { params })),

  tags: (q: string) =>
    request<{ items: TagSuggestion[] }>(() => api.get('/tags/suggest', { params: { q } })),
}

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

  exportCvs: (id: number, format: 'csv' | 'xls', drafts = false) =>
    download(
      () =>
        api.get<Blob>(`/positions/${id}/cvs/export`, {
          params: { format, drafts: drafts ? 1 : undefined },
          responseType: 'blob',
        }),
      `position-${id}-cvs.${format}`,
    ),

  operators: () =>
    request<{ operators: Record<AttributeType, FilterOperator[]> }>(() =>
      api.get('/positions/meta/operators'),
    ),
}

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

export const cvApi = {
  show: (id: number) => request<CvDetail>(() => api.get(`/cvs/${id}`)),

  start: (positionId: number) =>
    request<CvDetail>(() => api.post(`/cvs/positions/${positionId}`)),

  editAttribute: (id: number, attributeId: number, value: AttributeValue, version: number) =>
    request<CvDetail>(() => api.patch(`/cvs/${id}/attributes`, { attributeId, value, version })),

  publish: (id: number) => request<CvDetail>(() => api.post(`/cvs/${id}/publish`)),

  unpublish: (id: number) => request<CvDetail>(() => api.delete(`/cvs/${id}/publish`)),

  remove: (id: number) => request<void>(() => api.delete(`/cvs/${id}`)),

  like: (id: number) =>
    request<{ likesCount: number; likedByMe: boolean }>(() => api.post(`/cvs/${id}/like`)),

  unlike: (id: number) =>
    request<{ likesCount: number; likedByMe: boolean }>(() => api.delete(`/cvs/${id}/like`)),

  /**
   * Каталог опубликованных резюме. Пустой запрос — не «ничего не найдено»,
   * а «фильтра нет»: сервер отдаёт всех кандидатов постранично.
   */
  search: (q: string, page = 1) =>
    request<CvPage>(() =>
      api.get('/cvs/search', { params: { q: q || undefined, page: page > 1 ? page : undefined } }),
    ),

  pdf: (id: number) =>
    download(() => api.get<Blob>(`/cvs/${id}/pdf`, { responseType: 'blob' }), `cv-${id}.pdf`),
}

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
