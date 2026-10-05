import { LifebuoyIcon, XIcon } from '@phosphor-icons/react'
import { useId, useState, type FormEvent } from 'react'
import { useLocation } from 'react-router-dom'
import { catalogApi, cvApi, integrationApi, RequestError, tokenStorage } from '../api/client'
import type { TicketPriority } from '../api/types'
import { useTranslation } from '../i18n/context'
import { useErrorText } from '../i18n/useErrorText'

const PRIORITIES: TicketPriority[] = ['High', 'Average', 'Low']

/**
 * «Создать тикет поддержки» с любой страницы: плавающая кнопка и форма.
 *
 * Отправленный тикет приложение сохраняет JSON-файлом в Dropbox, где его
 * подхватывает флоу Power Automate и рассылает письма администраторам.
 */
export function SupportTicketWidget() {
  const [open, setOpen] = useState(false)

  // Тикет подписывается текущим пользователем — гостю форма не нужна.
  if (!tokenStorage.isValid()) {
    return null
  }

  return (
    <>
      <TicketButton onClick={() => setOpen(true)} />
      {open && <TicketDialog onClose={() => setOpen(false)} />}
    </>

  )
}

function TicketButton({ onClick }: { onClick: () => void }) {
  const t = useTranslation()

  return (
    <button type="button" className="helpfab" onClick={onClick}>
      <LifebuoyIcon size={16} aria-hidden="true" />
      {t('support.fab')}
    </button>

  )
}

/**
 * Название позиции берём из адреса страницы: на странице позиции (или её
 * редактирования) и на странице резюме тикет должен назвать позицию сам.
 */
async function resolvePositionTitle(pathname: string): Promise<string | undefined> {
  const position = /^\/positions\/(\d+)/.exec(pathname)

  if (position) {
    return (await catalogApi.position(Number(position[1]))).title
  }

  const cv = /^\/cvs\/(\d+)$/.exec(pathname)

  if (cv) {
    return (await cvApi.show(Number(cv[1]))).position.title
  }

  return undefined
}

function TicketDialog({ onClose }: { onClose: () => void }) {
  const t = useTranslation()
  const errorText = useErrorText()
  const location = useLocation()
  const titleId = useId()
  const summaryId = useId()
  const priorityId = useId()

  const [summary, setSummary] = useState('')
  const [priority, setPriority] = useState<TicketPriority>('Average')
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [summaryError, setSummaryError] = useState<string | null>(null)
  const [sent, setSent] = useState(false)

  const submit = async (event: FormEvent) => {
    event.preventDefault()

    setBusy(true)
    setError(null)
    setSummaryError(null)

    try {
      // Позиция — лучшее, что можно выяснить, но тикет важнее: если её
      // страница недоступна (черновик, чужое резюме), поле просто пустое.
      const position = await resolvePositionTitle(location.pathname).catch(() => undefined)

      await integrationApi.createSupportTicket({
        summary: summary.trim(),
        priority,
        link: window.location.href,
        position,
      })

      setSent(true)
    } catch (requestError: unknown) {
      if (requestError instanceof RequestError && requestError.violations.summary) {
        setSummaryError(requestError.violations.summary)
      } else {
        setError(errorText(requestError, 'support.failed'))
      }
    } finally {
      setBusy(false)
    }
  }

  return (
    <div className="modalback" role="presentation" onClick={onClose}>
      <div
        className="modal"
        role="dialog"
        aria-modal="true"
        aria-labelledby={titleId}
        onClick={(event) => event.stopPropagation()}
      >
        <div className="modal__head">
          <div>
            <h2 className="modal__title" id={titleId}>
              {t('support.title')}
            </h2>

            <p className="panel__hint muted-3">{t('support.hint')}</p>

          </div>

          <button type="button" className="iconbtn" onClick={onClose} aria-label={t('common.cancel')}>
            <XIcon size={16} aria-hidden="true" />
          </button>

        </div>

        {sent ? (
          <>
            <div className="notice notice--ok" role="status">
              <span>{t('support.success')}</span>

            </div>

            <div className="modal__actions">
              <button type="button" className="btn btn--primary" onClick={onClose}>
                {t('crm.done')}
              </button>

            </div>

          </>

        ) : (
          <form onSubmit={(event) => void submit(event)} className="col g4">
            {error && (
              <div className="notice notice--error" role="alert">
                <span>{error}</span>

              </div>

            )}

            <div className="field">
              <label htmlFor={summaryId} className="label">
                {t('support.summary')}
              </label>

              <textarea
                id={summaryId}
                className={`input${summaryError ? ' input--invalid' : ''}`}
                rows={4}
                value={summary}
                onChange={(event) => setSummary(event.target.value)}
                placeholder={t('support.summaryPlaceholder')}
                required
                aria-invalid={summaryError ? true : undefined}
              />

              {summaryError && <span className="field__error">{summaryError}</span>}
            </div>

            <div className="field">
              <label htmlFor={priorityId} className="label">
                {t('support.priority')}
              </label>

              <select
                id={priorityId}
                className="input"
                value={priority}
                onChange={(event) => setPriority(event.target.value as TicketPriority)}
              >
                {PRIORITIES.map((value) => (
                  <option key={value} value={value}>
                    {t(`support.priority${value}`)}
                  </option>

                ))}
              </select>

            </div>

            <div className="modal__actions">
              <button type="button" className="btn btn--ghost" onClick={onClose} disabled={busy}>
                {t('common.cancel')}
              </button>

              <button type="submit" className="btn btn--primary" disabled={busy}>
                {t(busy ? 'support.sending' : 'support.submit')}
              </button>

            </div>

          </form>

        )}
      </div>

    </div>

  )
}
