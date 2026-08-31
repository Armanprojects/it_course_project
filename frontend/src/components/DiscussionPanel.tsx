import { PaperPlaneRightIcon } from '@phosphor-icons/react'
import { lazy, Suspense, useEffect, useRef, useState, type FormEvent } from 'react'
import { Link } from 'react-router-dom'
import { discussionApi, RequestError } from '../api/client'
import type { DiscussionMessage } from '../api/types'

const Markdown = lazy(() => import('react-markdown'))

/** Задание требует, чтобы новые сообщения появлялись у всех за 2–5 секунд. */
const POLL_MS = 4000

const timeFormat = new Intl.DateTimeFormat('ru-RU', {
  dateStyle: 'short',
  timeStyle: 'short',
})

interface Props {
  positionId: number
}

/**
 * Вкладка «Обсуждение» позиции.
 *
 * Обновления — опросом: сервер отдаёт только сообщения новее последнего
 * известного id, поэтому опрос раз в 4 секунды почти ничего не стоит и не
 * требует поднимать websocket-сервер.
 */
export function DiscussionPanel({ positionId }: Props) {
  const [messages, setMessages] = useState<DiscussionMessage[]>([])
  const [draft, setDraft] = useState('')
  const [error, setError] = useState<string | null>(null)
  const [sending, setSending] = useState(false)

  // Курсор опроса в ref: он меняется в интервале, и обновлять из-за него
  // компонент незачем — перерисовку вызывает только новый список сообщений.
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
        // Только дописываем в конец: вставлять между существующими нельзя.
        setMessages((current) => [...current, ...result.items])
      } catch {
        // Молча: сорванный опрос не должен закрывать уже прочитанный тред.
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

      // Своё сообщение показываем сразу, а курсор двигаем, чтобы опрос не
      // принёс его второй раз.
      setMessages((current) => [...current, post])
      lastId.current = post.id
      setDraft('')
    } catch (requestError: unknown) {
      setError(
        requestError instanceof RequestError ? requestError.message : 'Не удалось отправить.',
      )
    } finally {
      setSending(false)
    }
  }

  return (
    <div className="col g4">
      {messages.length === 0 ? (
        <p className="muted table__empty">Сообщений пока нет. Начните обсуждение.</p>
      ) : (
        <ol className="thread">
          {messages.map((message) => (
            <li key={message.id} className={`thread__item${message.mine ? ' is-mine' : ''}`}>
              <div className="thread__meta">
                {/* Ссылка на профиль автора — только для рекрутеров: сервер
                    отдаёт profileId лишь им. */}
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
          placeholder="Ваше сообщение. Поддерживается Markdown."
          aria-label="Текст сообщения"
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
          {sending ? 'Отправляем…' : 'Отправить'}
        </button>
      </form>
    </div>
  )
}
