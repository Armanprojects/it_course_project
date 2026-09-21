import type { MessageKey } from '../i18n/messages'

export const TYPE_LABEL_KEYS: Record<string, MessageKey> = {
  string: 'type.string',
  text: 'type.text',
  image: 'type.image',
  numeric: 'type.numeric',
  date: 'type.date',
  period: 'type.period',
  boolean: 'type.boolean',
  select: 'type.select',
}

export const CATEGORY_LABEL_KEYS: Record<string, MessageKey> = {
  personal_information: 'category.personal_information',
  certification: 'category.certification',
  domain_knowledge: 'category.domain_knowledge',
  soft_skills: 'category.soft_skills',
}
