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

/** Строка таблицы позиций. Совпадает с PositionRepository::toRow. */
export interface PositionRow {
  id: number
  title: string
  shortDescription: string | null
  company: string | null
  level: string | null
  public: boolean
  attributeCount: number
  cvCount: number
  createdAt: string
  updatedAt: string
}

/** Колонки, по которым бэкенд разрешает сортировать. */
export type PositionSort = 'title' | 'company' | 'level' | 'createdAt' | 'updatedAt'

export type SortDirection = 'asc' | 'desc'

export interface PositionPage {
  items: PositionRow[]
  total: number
  page: number
  pageSize: number
  pages: number
}

export interface PositionAttribute {
  id: number
  name: string
  description: string | null
  category: string
  type: string
  options: string[]
  section: string | null
  required: boolean
  sortOrder: number
}

export interface PositionDetail {
  id: number
  title: string
  shortDescription: string | null
  company: string | null
  level: string | null
  public: boolean
  maxProjects: number
  createdAt: string
  updatedAt: string
  attributes: PositionAttribute[]
  projectTags: { id: number; name: string }[]
}

/** Публичная статистика: только агрегаты, ничьих персональных данных. */
export interface PublicStats {
  positions: number
  cvs: number
  submittedCvs: number
  cvsLast24h: number
  candidates: number
  recruiters: number
}

export interface TagCloudEntry {
  id: number
  name: string
  usageCount: number
}

/** Всё, что рисует главная страница, одним ответом. */
export interface HomeData {
  stats: PublicStats
  latestPositions: PositionRow[]
  topPositions: PositionRow[]
  tagCloud: TagCloudEntry[]
}

/** Типы атрибутов из App\Enum\AttributeType. */
export type AttributeType =
  | 'string'
  | 'text'
  | 'image'
  | 'numeric'
  | 'date'
  | 'period'
  | 'boolean'
  | 'select'

/** Значение периода — единственный не-скалярный тип. */
export interface PeriodValue {
  from: string | null
  to: string | null
}

export type AttributeValue = string | number | boolean | PeriodValue | null

/** Атрибут вместе со значением в профиле. */
export interface ProfileAttribute {
  attributeId: number
  name: string
  description: string | null
  category: string
  type: AttributeType
  options: string[]
  system: boolean
  value: AttributeValue
  empty: boolean
  version: number | null
}

/** Атрибут в библиотеке — без значения. */
export interface LibraryAttribute {
  id: number
  name: string
  description: string | null
  category: string
  type: AttributeType
  options: string[]
  system: boolean
}

export interface AttributeLibrary {
  items: LibraryAttribute[]
  recent: LibraryAttribute[]
  categories: string[]
}

export interface ProjectTag {
  id: number
  name: string
}

export interface ProfileProject {
  id: number
  name: string
  description: string | null
  periodFrom: string | null
  periodTo: string | null
  ongoing: boolean
  sortOrder: number
  tags: ProjectTag[]
}

export interface ProfileCv {
  id: number
  status: 'draft' | 'published'
  likesCount: number
  createdAt: string
  updatedAt: string
  publishedAt: string | null
  position: {
    id: number
    title: string
    company: string | null
    level: string | null
  }
}

/** Профиль целиком: четыре раздела задания плюс версия для автосохранения. */
export interface ProfileData {
  id: number
  version: number
  updatedAt: string
  user: {
    id: number
    email: string
    roles: string[]
  }
  me: ProfileAttribute[]
  info: ProfileAttribute[]
  projects: ProfileProject[]
  cvs: ProfileCv[]
}

export interface TagSuggestion {
  id: number
  name: string
  usageCount: number
}

/** Тело запроса на сохранение проекта. */
export interface ProjectInput {
  name: string
  description: string | null
  periodFrom: string | null
  periodTo: string | null
  tags: string[]
}

/** Операторы фильтрации из App\Enum\FilterOperator. */
export type FilterOperator =
  | 'eq'
  | 'neq'
  | 'gt'
  | 'gte'
  | 'lt'
  | 'lte'
  | 'contains'
  | 'in'
  | 'is_set'

/** Атрибут в шаблоне позиции. */
export interface TemplateAttribute {
  attributeId: number
  name: string
  category: string
  type: AttributeType
  options: string[]
  required: boolean
  section: string | null
  sortOrder: number
}

/** Правило доступа: атрибут + оператор + операнд. */
export interface AccessRule {
  attributeId: number
  name?: string
  type?: AttributeType
  options?: string[]
  operator: FilterOperator
  value: unknown
}

/** Позиция в режиме редактирования — с правилами и версией. */
export interface PositionEditable {
  id: number
  title: string
  shortDescription: string | null
  company: string | null
  level: string | null
  public: boolean
  maxProjects: number
  version: number
  createdAt: string
  updatedAt: string
  attributes: TemplateAttribute[]
  accessRules: AccessRule[]
  projectTags: string[]
}

/** Тело запроса на сохранение позиции. */
export interface PositionInput {
  title: string
  shortDescription: string | null
  company: string | null
  level: string | null
  public: boolean
  maxProjects: number
  attributes: { attributeId: number; required: boolean; section: string | null; sortOrder: number }[]
  accessRules: { attributeId: number; operator: FilterOperator; value: unknown }[]
  projectTags: string[]
  version?: number
}

/** Строка в таблице резюме. */
export interface CvRow {
  id: number
  status: 'draft' | 'published'
  likesCount: number
  likedByMe: boolean
  updatedAt: string
  candidate: { profileId: number; email: string; name: string }
  position: { id: number; title: string; company: string | null }
}

export interface CvSectionAttribute {
  attributeId: number
  name: string
  type: AttributeType
  options: string[]
  required: boolean
  value: AttributeValue
  empty: boolean
}

export interface CvSection {
  section: string
  attributes: CvSectionAttribute[]
}

/** Сгенерированное резюме целиком. */
export interface CvDetail {
  id: number
  status: 'draft' | 'published'
  complete: boolean
  likesCount: number
  likedByMe: boolean
  /** Лайкать может рекрутер; публиковать — владелец. У админа есть оба. */
  canLike: boolean
  canEdit: boolean
  createdAt: string
  updatedAt: string
  publishedAt: string | null
  candidate: { profileId: number; userId: number; email: string; name: string }
  position: { id: number; title: string; company: string | null; level: string | null }
  sections: CvSection[]
  projects: ProfileProject[]
  missing: string[]
}

/** Атрибут в экране управления библиотекой. */
export interface ManagedAttribute extends LibraryAttribute {
  version: number
  removed: boolean
  usage: { profiles: number; positions: number; rules: number }
}

export interface AttributeLibraryAdmin {
  items: ManagedAttribute[]
  categories: string[]
  types: AttributeType[]
}

/** Тело запроса на сохранение атрибута. */
export interface AttributeInput {
  name: string
  description: string | null
  category: string
  type: AttributeType
  options: string[]
  version?: number
}

/** Сообщение в обсуждении позиции. */
export interface DiscussionMessage {
  id: number
  content: string
  createdAt: string
  mine: boolean
  author: { email: string; profileId: number | null }
}

/** Конверт ошибок из ApiExceptionSubscriber. */
export interface ApiError {
  error: string
  message: string
  violations?: Record<string, string>
  /** Приходит только с version_conflict: версия, которая сейчас на сервере. */
  currentVersion?: number
}

export type OAuthProvider = 'google' | 'github'
