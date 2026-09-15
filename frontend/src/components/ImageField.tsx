import { ImageIcon, SpinnerGapIcon } from '@phosphor-icons/react'
import { useId, useRef, useState, type DragEvent } from 'react'
import { cloudinaryConfigured, uploadImage, UploadError } from '../lib/cloudinary'
import { useTranslation } from '../i18n/context'

interface Props {
  id: string
  value: string
  onChange: (value: string | null) => void
}

/**
 * Поле для атрибута-изображения: перетаскивание или выбор файла, картинка
 * уходит в облако, у нас остаётся ссылка.
 *
 * Если ключи Cloudinary не заданы, остаётся ручной ввод ссылки — поле не
 * ломается, просто теряет загрузку.
 */
export function ImageField({ id, value, onChange }: Props) {
  const t = useTranslation()
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [dragging, setDragging] = useState(false)
  const fileInput = useRef<HTMLInputElement>(null)
  const urlFieldId = useId()

  const upload = async (file: File | undefined) => {
    if (!file) {
      return
    }

    setBusy(true)
    setError(null)

    try {
      onChange(await uploadImage(file))
    } catch (uploadError) {
      setError(
        uploadError instanceof UploadError ? t(uploadError.key) : t('image.uploadFailed'),
      )
    } finally {
      setBusy(false)
    }
  }

  const onDrop = (event: DragEvent<HTMLDivElement>) => {
    event.preventDefault()
    setDragging(false)

    if (cloudinaryConfigured) {
      void upload(event.dataTransfer.files[0])
    }
  }

  return (
    <div className="col g2">
      {value && (
        <img className="attr__preview" src={value} alt="" onError={() => setError(t('image.brokenLink'))} />
      )}

      {cloudinaryConfigured && (
        <div
          className={`dropzone${dragging ? ' is-over' : ''}`}
          onDragOver={(event) => {
            event.preventDefault()
            setDragging(true)
          }}
          onDragLeave={() => setDragging(false)}
          onDrop={onDrop}
        >
          {busy ? (
            <SpinnerGapIcon size={18} className="spin" aria-hidden="true" />
          ) : (
            <ImageIcon size={18} aria-hidden="true" />
          )}

          <span className="t-sm muted">
            {t(busy ? 'image.uploading' : 'image.dropHere')}
          </span>

          {!busy && (
            <button
              type="button"
              className="linkbtn"
              onClick={() => fileInput.current?.click()}
            >
              {t('image.choose')}
            </button>
          )}

          <input
            ref={fileInput}
            type="file"
            accept="image/*"
            className="sr-only"
            onChange={(event) => {
              void upload(event.target.files?.[0])
              // Сбрасываем, иначе повторный выбор того же файла не сработает.
              event.target.value = ''
            }}
          />
        </div>
      )}

      <label className="label" htmlFor={cloudinaryConfigured ? urlFieldId : id}>
        {t(cloudinaryConfigured ? 'image.orPasteLink' : 'image.linkLabel')}
      </label>

      <input
        id={cloudinaryConfigured ? urlFieldId : id}
        type="url"
        className="input"
        placeholder="https://…"
        value={value}
        onChange={(event) => {
          setError(null)
          onChange(event.target.value || null)
        }}
      />

      {error && <p className="field__error">{error}</p>}
    </div>
  )
}
