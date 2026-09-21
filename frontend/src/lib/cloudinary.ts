import type { MessageKey } from '../i18n/messages'

const CLOUD_NAME = import.meta.env.VITE_CLOUDINARY_CLOUD_NAME as string | undefined
const UPLOAD_PRESET = import.meta.env.VITE_CLOUDINARY_UPLOAD_PRESET as string | undefined

export const cloudinaryConfigured = Boolean(CLOUD_NAME && UPLOAD_PRESET)

export const MAX_IMAGE_BYTES = 10 * 1024 * 1024

export class UploadError extends Error {
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
