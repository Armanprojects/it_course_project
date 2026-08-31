import { ImageIcon, SpinnerGapIcon } from '@phosphor-icons/react'
import { useId, useRef, useState, type DragEvent } from 'react'
import { cloudinaryConfigured, uploadImage, UploadError } from '../lib/cloudinary'

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
        uploadError instanceof UploadError ? uploadError.message : 'Не удалось загрузить файл.',
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
        <img className="attr__preview" src={value} alt="" onError={() => setError('Ссылка не открывается как изображение.')} />
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
            {busy ? 'Загружаем…' : 'Перетащите файл сюда или'}
          </span>

          {!busy && (
            <button
              type="button"
              className="linkbtn"
              onClick={() => fileInput.current?.click()}
            >
              выберите
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
        {cloudinaryConfigured ? 'или вставьте ссылку' : 'Ссылка на изображение'}
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
