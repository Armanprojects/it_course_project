import { PaperPlaneRightIcon } from '@phosphor-icons/react'
import { lazy, Suspense, useEffect, useRef, useState, type FormEvent } from 'react'
import { Link } from 'react-router-dom'
import { discussionApi } from '../api/client'
import type { DiscussionMessage } from '../api/types'
import { useTranslation } from '../i18n/context'
import { useErrorText } from '../i18n/useErrorText'

const Markdown = lazy(() => import('react-markdown'))

const POLL_MS = 4000

const timeFormat = new Intl.DateTimeFormat('ru-RU', {
  dateStyle: 'short',
  timeStyle: 'short',
})

interface Props {
  positionId: number
}

export function DiscussionPanel({ positionId }: Props) {
  const t = useTranslation()
  const errorText = useErrorText()
  const [messages, setMessages] = useState<DiscussionMessage[]>([])
  const [draft, setDraft] = useState('')
  const [error, setError] = useState<string | null>(null)
  const [sending, setSending] = useState(false)

  const lastId = useRef<number | null>(null)

  useEffect(() => {
    let active = true

    const poll = async () => {
      try {
        const result = await discussionApi.list(positionId, lastId.current ?? undefined)

        if (!active || result.items.length === 0) {
          return
        }

        lastId.current = result.lastId
        setMessages((current) => [...current, ...result.items])
      } catch {
      }
    }

    void poll()
    const timer = setInterval(() => void poll(), POLL_MS)

    return () => {
      active = false
      clearInterval(timer)
    }
  }, [positionId])

  const send = async (event: FormEvent) => {
    event.preventDefault()

    const content = draft.trim()

    if (content === '') {
      return
    }

    setSending(true)
    setError(null)

    try {
      const post = await discussionApi.post(positionId, content)

      setMessages((current) => [...current, post])
      lastId.current = post.id
      setDraft('')
    } catch (requestError: unknown) {
      setError(
        errorText(requestError, 'discussion.sendFailed'),
      )
    } finally {
      setSending(false)
    }
  }

  return (
    <div className="col g4">
      {messages.length === 0 ? (
        <p className="muted table__empty">{t('discussion.empty')}</p>

      ) : (
        <ol className="thread">
          {messages.map((message) => (
            <li key={message.id} className={`thread__item${message.mine ? ' is-mine' : ''}`}>
              <div className="thread__meta">
                {message.author.profileId !== null ? (
                  <Link to={`/profiles/${message.author.profileId}`} className="thread__author">
                    {message.author.email}
                  </Link>

                ) : (
                  <span className="thread__author">{message.author.email}</span>

                )}
                <time className="t-xs muted-3" dateTime={message.createdAt}>
                  {timeFormat.format(new Date(message.createdAt))}
                </time>

              </div>

              <div className="prose prose--md t-sm">
                <Suspense fallback={<p>{message.content}</p>}>

                  <Markdown>{message.content}</Markdown>

                </Suspense>

              </div>

            </li>

          ))}
        </ol>

      )}

      <form className="col g2" onSubmit={send}>
        <textarea
          className="input input--area"
          rows={3}
          placeholder={t('discussion.placeholder')}
          aria-label={t('discussion.label')}
          value={draft}
          onChange={(event) => setDraft(event.target.value)}
        />

        {error && (
          <div className="notice notice--error" role="alert">
            <span>{error}</span>

          </div>

        )}

        <button
          type="submit"
          className="btn btn--primary"
          style={{ alignSelf: 'flex-start' }}
          disabled={sending || draft.trim() === ''}
        >
          <PaperPlaneRightIcon size={14} aria-hidden="true" />
          {t(sending ? 'discussion.sending' : 'discussion.send')}
        </button>

      </form>

    </div>

  )
}
