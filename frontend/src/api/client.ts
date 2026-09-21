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

/**
 * Срок действия JWT из его полезной нагрузки, в миллисекундах эпохи.
 *
 * Подпись здесь не проверяется и проверяться не может — это делает сервер.
 * Нас интересует только `exp`, чтобы не гнать заведомо мёртвый токен на
 * бэкенд и не пускать по нему на защищённые экраны.
 */
function expiryOf(token: string): number | null {
  const payload = token.split('.')[1]

  if (!payload) {
    return null
  }

  try {
    // base64url -> base64: JWT заменяет + и / на - и _, а хвостовые = убирает.
    const json = atob(payload.replace(/-/g, '+').replace(/_/g, '/'))
    const exp = (JSON.parse(json) as { exp?: number }).exp

    return typeof exp === 'number' ? exp * 1000 : null
  } catch {
    // Испорченный токен нельзя считать бессрочным — пусть его вычистят.
    return null
  }
}

export const tokenStorage = {
  get: (): string | null => localStorage.getItem(TOKEN_KEY),
  set: (token: string) => localStorage.setItem(TOKEN_KEY, token),
  clear: () => localStorage.removeItem(TOKEN_KEY),

  /**
   * Есть ли токен, который ещё имеет смысл отправлять.
   *
   * Экраны раньше смотрели только на наличие строки в localStorage, поэтому
   * с истёкшим токеном пускали внутрь, а страница входа, наоборот, считала
   * человека вошедшим. Токен без разбираемого `exp` считаем негодным:
   * бессрочных мы не выдаём.
   */
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

/**
 * Протухший JWT: срок жизни вышел, но токен всё ещё лежит в localStorage.
 * Страницы пускают по факту его наличия, поэтому без этой чистки человек
 * попадал на защищённый экран, который тут же падал в ошибку загрузки.
 *
 * Сервер на истёкший и на подделанный токен отвечает одинаково — 401, но
 * телом отдаёт {"code":401,...} без строкового error, поэтому опираемся на
 * статус, а не на тело. Переход делаем через location, а не через роутер:
 * перехватчик живёт вне React и навигацию из него не вызвать.
 */
api.interceptors.response.use(
  (response) => response,
  (error: unknown) => {
    const status = error instanceof AxiosError ? error.response?.status : undefined

    if (status === 401 && tokenStorage.get() !== null) {
      tokenStorage.clear()

      // /login сам по себе 401 не порождает, но неверный пароль на нём —
      // да: без этой проверки страница перезагружалась бы вместо показа
      // ошибки, стирая введённое.
      if (!window.location.pathname.startsWith('/login')) {
        window.location.href = '/login'
      }
    }

    return Promise.reject(error instanceof Error ? error : new Error(String(error)))
  },
)

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

  /**
   * Ошибки, которые придумал сам клиент (сеть, неизвестный сбой), несут в
   * message ключ словаря — их надо перевести. Сообщения бэкенда приходят
   * готовым текстом и отдаются как есть.
   */
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

    // Ответа нет вообще — сеть или упавший бэкенд. Здесь и ниже в message
    // кладётся ключ словаря: модуль не компонент, языка он не знает, а по
    // code вызывающий код всё равно понимает, что случилось.
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

/**
 * Сообщение об ошибке, когда ответ запрашивали блобом.
 *
 * При responseType: 'blob' тело ошибки тоже приходит блобом, и обычный разбор
 * увидел бы вместо JSON объект Blob. Читаем его текстом и возвращаемся к общему
 * формату ошибки — иначе вместо «резюме не найдено» пользователь получит
 * «непредвиденная ошибка».
 */
async function blobError(error: unknown): Promise<RequestError> {
  if (error instanceof AxiosError && error.response?.data instanceof Blob) {
    try {
      const parsed = JSON.parse(await error.response.data.text()) as ApiError

      if (parsed.message) {
        return new RequestError(parsed.message, parsed.error ?? 'request_failed')
      }
    } catch {
      // Не JSON — значит это не наш конверт ошибки, пусть решает общий разбор.
    }
  }

  return toRequestError(error)
}

/**
 * Скачивание файла.
 *
 * request<T> отдаёт только data и про заголовки ничего не знает, а имя файла
 * сервер присылает в Content-Disposition — поэтому для файлов отдельный путь.
 */
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
  // Ссылка должна быть в документе: Firefox игнорирует click() у элемента,
  // которого нет в дереве.
  document.body.append(link)
  link.click()
  link.remove()

  // Освобождаем сразу после клика: браузер к этому моменту уже забрал данные,
  // а без revoke блоб живёт до перезагрузки страницы.
  URL.revokeObjectURL(url)
}

/** Имя из Content-Disposition; filename*= (RFC 5987) важнее обычного. */
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

  /**
   * Регистрация не выдаёт токен: адрес ещё не подтверждён, входить не с чем.
   */
  register: (email: string, password: string, passwordConfirmation: string, role: SelectableRole) =>
    request<RegistrationPending>(() =>
      api.post('/auth/register', { email, password, passwordConfirmation, role }),
    ),

  verifyEmail: (token: string) => request<AuthResponse>(() => api.post('/auth/verify', { token })),

  /**
   * Язык и тема. Поля необязательные: переключатель меняет что-то одно,
   * отсутствующее поле сервер оставляет как есть.
   */
  updateSettings: (settings: { locale?: string; theme?: string }) =>
    request<User>(() => api.patch('/auth/settings', settings)),

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
/**
 * Чей профиль правим: свой ('me') или конкретный — последнее доступно только
 * администратору, которому по заданию можно редактировать любой профиль.
 * Параметр необязателен, поэтому обычные вызовы остаются как были.
 */
export type ProfileTarget = 'me' | number

export const profileApi = {
  me: () => request<ProfileData>(() => api.get('/profile/me')),

  byId: (id: number) => request<ProfileData>(() => api.get(`/profile/${id}`)),

  /**
   * Тик автосохранения: уходит версия, которую клиент видел последней, и все
   * значения раздела. Ответ — профиль целиком с новой версией.
   */
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

/**
 * Управление пользователями — единственная часть админки без аналога для
 * обычных ролей. Остальные права администратора реализованы как послабления
 * внутри обычных endpoint'ов, отдельного API им не нужно.
 */
export const adminApi = {
  users: (params: { search?: string; role?: string; status?: string; page?: number } = {}) =>
    request<AdminUserPage>(() => api.get('/admin/users', { params })),

  block: (id: number) => request<User>(() => api.post(`/admin/users/${id}/block`)),

  unblock: (id: number) => request<User>(() => api.delete(`/admin/users/${id}/block`)),

  grantRole: (id: number, role: string) =>
    request<User>(() => api.post(`/admin/users/${id}/roles`, { role })),

  // DELETE с телом: роль здесь — значение для проверки по enum, а не сегмент
  // пути, поэтому она едет в body так же, как при выдаче.
  revokeRole: (id: number, role: string) =>
    request<User>(() => api.delete(`/admin/users/${id}/roles`, { data: { role } })),

  deleteUser: (id: number) => request<void>(() => api.delete(`/admin/users/${id}`)),
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

  /**
   * Сводная таблица резюме по позиции — для анализа в Excel.
   *
   * Формат xls — это SpreadsheetML, а не zip-архив xlsx: в рантайм-образе нет
   * ext-zip, а Excel открывает оба одинаково.
   */
  exportCvs: (id: number, format: 'csv' | 'xls', drafts = false) =>
    download(
      () =>
        api.get<Blob>(`/positions/${id}/cvs/export`, {
          params: { format, drafts: drafts ? 1 : undefined },
          responseType: 'blob',
        }),
      `position-${id}-cvs.${format}`,
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

  /**
   * Правка одного атрибута прямо в резюме. Значение уходит в профиль — резюме
   * своих значений не хранит, — поэтому и версия здесь профильная.
   */
  editAttribute: (id: number, attributeId: number, value: AttributeValue, version: number) =>
    request<CvDetail>(() => api.patch(`/cvs/${id}/attributes`, { attributeId, value, version })),

  publish: (id: number) => request<CvDetail>(() => api.post(`/cvs/${id}/publish`)),

  unpublish: (id: number) => request<CvDetail>(() => api.delete(`/cvs/${id}/publish`)),

  remove: (id: number) => request<void>(() => api.delete(`/cvs/${id}`)),

  like: (id: number) =>
    request<{ likesCount: number; likedByMe: boolean }>(() => api.post(`/cvs/${id}/like`)),

  unlike: (id: number) =>
    request<{ likesCount: number; likedByMe: boolean }>(() => api.delete(`/cvs/${id}/like`)),

  search: (q: string) =>
    request<{ items: CvRow[]; total: number }>(() => api.get('/cvs/search', { params: { q } })),

  /** Печатный вариант резюме с QR-кодом обратно на эту страницу. */
  pdf: (id: number) =>
    download(() => api.get<Blob>(`/cvs/${id}/pdf`, { responseType: 'blob' }), `cv-${id}.pdf`),
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
