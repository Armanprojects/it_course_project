/**
 * Подписи для типов и категорий атрибутов.
 *
 * Лежат отдельно, потому что нужны и профилю, и странице позиции: значения
 * приходят с сервера как коды из App\Enum, переводить их в двух местах —
 * гарантированно разойтись.
 */
export const TYPE_LABELS: Record<string, string> = {
  string: 'Строка',
  text: 'Текст',
  image: 'Изображение',
  numeric: 'Число',
  date: 'Дата',
  period: 'Период',
  boolean: 'Флажок',
  select: 'Выбор из списка',
}

export const CATEGORY_LABELS: Record<string, string> = {
  personal_information: 'Личные данные',
  certification: 'Сертификаты',
  domain_knowledge: 'Профессиональные знания',
  soft_skills: 'Гибкие навыки',
}
