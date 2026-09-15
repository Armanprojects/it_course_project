/**
 * Загрузка изображений во внешнее облако.
 *
 * Задание прямо запрещает класть картинки на свой сервер или в БД, поэтому
 * файл уходит из браузера напрямую в Cloudinary, а у нас хранится только
 * ссылка. Ключи для unsigned-загрузки публичны по своей природе — они и
 * рассчитаны на то, чтобы лежать в коде фронтенда.
 */
import type { MessageKey } from '../i18n/messages'

const CLOUD_NAME = import.meta.env.VITE_CLOUDINARY_CLOUD_NAME as string | undefined
const UPLOAD_PRESET = import.meta.env.VITE_CLOUDINARY_UPLOAD_PRESET as string | undefined

/** Пока ключи не заданы, поле работает как ввод ссылки вручную. */
export const cloudinaryConfigured = Boolean(CLOUD_NAME && UPLOAD_PRESET)

export const MAX_IMAGE_BYTES = 10 * 1024 * 1024

/**
 * Ошибка загрузки. В message лежит ключ словаря, а не готовый текст: модуль
 * не React-компонент и языка не знает — переводит тот, кто ловит.
 */
export class UploadError extends Error {
  // Поле объявлено отдельно, а не параметром конструктора: сборка идёт с
  // erasableSyntaxOnly, где параметры-свойства запрещены.
  readonly key: MessageKey

  constructor(key: MessageKey) {
    super(key)
    this.name = 'UploadError'
    this.key = key
  }
}

export async function uploadImage(file: File): Promise<string> {
  if (!cloudinaryConfigured) {
    throw new UploadError('upload.notConfigured')
  }

  if (!file.type.startsWith('image/')) {
    throw new UploadError('upload.notImage')
  }

  if (file.size > MAX_IMAGE_BYTES) {
    throw new UploadError('upload.tooBig')
  }

  const body = new FormData()
  body.append('file', file)
  body.append('upload_preset', UPLOAD_PRESET as string)

  // Голый fetch, а не общий axios-клиент: тот подставляет наш Authorization
  // и baseURL '/api', и то и другое чужому домену отправлять незачем.
  const response = await fetch(`https://api.cloudinary.com/v1_1/${CLOUD_NAME}/image/upload`, {
    method: 'POST',
    body,
  })

  if (!response.ok) {
    throw new UploadError('upload.rejected')
  }

  const data = (await response.json()) as { secure_url?: string }

  if (!data.secure_url) {
    throw new UploadError('upload.noUrl')
  }

  return data.secure_url
}
