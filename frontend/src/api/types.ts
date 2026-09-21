export const UserRole = {
  Candidate: 'ROLE_CANDIDATE',
  Recruiter: 'ROLE_RECRUITER',
  Admin: 'ROLE_ADMIN',
} as const

export type UserRole = (typeof UserRole)[keyof typeof UserRole]

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

export interface AdminUserPage {
  items: User[]
  total: number
  page: number
  perPage: number
}

export interface AuthResponse {
  token: string
  user: User
}

export interface RegistrationPending {
  status: 'verification_sent'
  email?: string
  message: string
}

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

export type PositionSort = 'title' | 'company' | 'level' | 'createdAt' | 'updatedAt'

export type SortDirection = 'asc' | 'desc'

export interface PositionPage {
  items: PositionRow[]
  total: number
  page: number
  pageSize: number
  pages: number
}

export interface CvPage {
  items: CvRow[]
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

export interface HomeData {
  stats: PublicStats
  latestPositions: PositionRow[]
  topPositions: PositionRow[]
  tagCloud: TagCloudEntry[]
}

export type AttributeType =
  | 'string'
  | 'text'
  | 'image'
  | 'numeric'
  | 'date'
  | 'period'
  | 'boolean'
  | 'select'

export interface PeriodValue {
  from: string | null
  to: string | null
}

export type AttributeValue = string | number | boolean | PeriodValue | null

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

export interface ProjectInput {
  name: string
  description: string | null
  periodFrom: string | null
  periodTo: string | null
  tags: string[]
}

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

export interface AccessRule {
  attributeId: number
  name?: string
  type?: AttributeType
  options?: string[]
  operator: FilterOperator
  value: unknown
}

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
  description: string | null
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

export interface CvDetail {
  id: number
  status: 'draft' | 'published'
  complete: boolean
  likesCount: number
  likedByMe: boolean
  canLike: boolean
  canEdit: boolean
  profileVersion: number
  createdAt: string
  updatedAt: string
  publishedAt: string | null
  candidate: { profileId: number; userId: number; email: string; name: string }
  position: { id: number; title: string; company: string | null; level: string | null }
  sections: CvSection[]
  projects: ProfileProject[]
  missing: string[]
}

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

export interface AttributeInput {
  name: string
  description: string | null
  category: string
  type: AttributeType
  options: string[]
  version?: number
}

export interface DiscussionMessage {
  id: number
  content: string
  createdAt: string
  mine: boolean
  author: { email: string; profileId: number | null }
}

export interface ApiError {
  error: string
  message: string
  violations?: Record<string, string>
  currentVersion?: number
}

export type OAuthProvider = 'google' | 'github'
