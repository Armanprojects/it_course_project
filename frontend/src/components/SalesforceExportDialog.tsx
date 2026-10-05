import { ArrowSquareOutIcon, XIcon } from '@phosphor-icons/react'
import { useId, useState, type FormEvent } from 'react'
import { integrationApi, RequestError } from '../api/client'
import type { SalesforceExportResult } from '../api/types'
import { useTranslation } from '../i18n/context'
import { useErrorText } from '../i18n/useErrorText'

interface Props {
  /** Предзаполнение из несъёмных полей профиля. */
  defaults: { firstName?: string; lastName?: string; phone?: string }
  /** id пользователя, когда админ экспортирует чужой профиль. */
  userId?: number
  onClose: () => void
}

/**
 * Форма «отправить в CRM»: собирает дополнительные сведения и создаёт
 * в Salesforce Account со связанным Contact.
 */
export function SalesforceExportDialog({ defaults, userId, onClose }: Props) {
  const t = useTranslation()
  const errorText = useErrorText()
  const titleId = useId()

  const [firstName, setFirstName] = useState(defaults.firstName ?? '')
  const [lastName, setLastName] = useState(defaults.lastName ?? '')
  const [phone, setPhone] = useState(defaults.phone ?? '')
  const [company, setCompany] = useState('')
  const [jobTitle, setJobTitle] = useState('')
  const [notes, setNotes] = useState('')

  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [violations, setViolations] = useState<Record<string, string>>({})
  const [result, setResult] = useState<SalesforceExportResult | null>(null)

  const submit = async (event: FormEvent) => {
    event.preventDefault()

    setBusy(true)
    setError(null)
    setViolations({})

    try {
      setResult(
        await integrationApi.salesforceExport({
          firstName: firstName.trim(),
          lastName: lastName.trim(),
          phone: phone.trim() || undefined,
          company: company.trim() || undefined,
          jobTitle: jobTitle.trim() || undefined,
          notes: notes.trim() || undefined,
          userId,
        }),
      )
    } catch (requestError: unknown) {
      if (requestError instanceof RequestError && Object.keys(requestError.violations).length > 0) {
        setViolations(requestError.violations)
      } else {
        setError(errorText(requestError, 'crm.failed'))
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
              {t('crm.title')}
            </h2>

            <p className="panel__hint muted-3">{t('crm.hint')}</p>

          </div>

          <button type="button" className="iconbtn" onClick={onClose} aria-label={t('common.cancel')}>
            <XIcon size={16} aria-hidden="true" />
          </button>

        </div>

        {result ? (
          <>
            <div className="notice notice--ok" role="status">
              <span>{t('crm.success')}</span>

            </div>

            <div className="modal__actions">
              <a className="btn btn--outline" href={result.accountUrl} target="_blank" rel="noreferrer">
                <ArrowSquareOutIcon size={14} aria-hidden="true" />
                {t('crm.openAccount')}
              </a>

              <a className="btn btn--outline" href={result.contactUrl} target="_blank" rel="noreferrer">
                <ArrowSquareOutIcon size={14} aria-hidden="true" />
                {t('crm.openContact')}
              </a>

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

            <div className="modal__grid">
              <Field
                label={t('crm.firstName')}
                value={firstName}
                onChange={setFirstName}
                required
                error={violations.firstName}
              />

              <Field
                label={t('crm.lastName')}
                value={lastName}
                onChange={setLastName}
                required
                error={violations.lastName}
              />

              <Field label={t('crm.phone')} value={phone} onChange={setPhone} error={violations.phone} />

              <Field
                label={t('crm.company')}
                value={company}
                onChange={setCompany}
                error={violations.company}
              />

              <Field
                label={t('crm.jobTitle')}
                value={jobTitle}
                onChange={setJobTitle}
                error={violations.jobTitle}
                wide
              />

              <NotesField
                label={t('crm.notes')}
                value={notes}
                onChange={setNotes}
                error={violations.notes}
              />

            </div>

            <div className="modal__actions">
              <button type="button" className="btn btn--ghost" onClick={onClose} disabled={busy}>
                {t('common.cancel')}
              </button>

              <button type="submit" className="btn btn--primary" disabled={busy}>
                {t(busy ? 'crm.sending' : 'crm.submit')}
              </button>

            </div>

          </form>

        )}
      </div>

    </div>

  )
}

interface FieldProps {
  label: string
  value: string
  onChange: (value: string) => void
  required?: boolean
  error?: string
  wide?: boolean
}

function Field({ label, value, onChange, required = false, error, wide = false }: FieldProps) {
  const id = useId()

  return (
    <div className={`field${wide ? ' field--wide' : ''}`}>
      <label htmlFor={id} className="label">
        {label}
      </label>

      <input
        id={id}
        type="text"
        className={`input${error ? ' input--invalid' : ''}`}
        value={value}
        onChange={(event) => onChange(event.target.value)}
        required={required}
        aria-invalid={error ? true : undefined}
      />

      {error && <span className="field__error">{error}</span>}
    </div>

  )
}

function NotesField({ label, value, onChange, error }: Omit<FieldProps, 'required' | 'wide'>) {
  const id = useId()

  return (
    <div className="field field--wide">
      <label htmlFor={id} className="label">
        {label}
      </label>

      <textarea
        id={id}
        className={`input${error ? ' input--invalid' : ''}`}
        rows={3}
        value={value}
        onChange={(event) => onChange(event.target.value)}
        aria-invalid={error ? true : undefined}
      />

      {error && <span className="field__error">{error}</span>}
    </div>

  )
}
