import { ArrowRightIcon } from '@phosphor-icons/react'
import { useEffect, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { catalogApi, RequestError } from '../api/client'
import type { HomeData, PublicStats, TagCloudEntry } from '../api/types'
import { AppHeader } from '../components/AppHeader'
import { PositionsTable } from '../components/PositionsTable'

type State =
  | { kind: 'loading' }
  | { kind: 'ready'; data: HomeData }
  | { kind: 'error'; message: string }

const STAT_LABELS: { key: keyof PublicStats; label: string }[] = [
  { key: 'positions', label: 'Позиций' },
  { key: 'submittedCvs', label: 'Резюме подано' },
  { key: 'cvsLast24h', label: 'Создано за сутки' },
  { key: 'candidates', label: 'Кандидатов' },
  { key: 'recruiters', label: 'Рекрутеров' },
]

/**
 * Главная страница. Открыта без авторизации: по заданию гость должен видеть
 * каталог позиций и публичную статистику, и только резюме, профили и
 * обсуждения закрыты входом.
 */
export function HomePage() {
  const [state, setState] = useState<State>({ kind: 'loading' })

  useEffect(() => {
    let active = true

    catalogApi
      .home()
      .then((data) => {
        if (active) {
          setState({ kind: 'ready', data })
        }
      })
      .catch((error: unknown) => {
        if (active) {
          setState({
            kind: 'error',
            message: error instanceof RequestError ? error.message : 'Непредвиденная ошибка.',
          })
        }
      })

    // Ответ может прийти после размонтирования — например, если гость сразу
    // ушёл на вход. setState в этот момент ничего не чинит, только шумит.
    return () => {
      active = false
    }
  }, [])

  return (
    <>
      <AppHeader />

      <main className="page">
        <section className="hero">
          <h1 className="h1">Позиции и резюме в одном месте</h1>
          <p className="hero__text muted">
            Рекрутеры собирают шаблоны позиций из общей библиотеки атрибутов, кандидаты
            заполняют профиль один раз, а резюме под каждую позицию система собирает сама.
            Каталог открыт всем — вход нужен только чтобы подать резюме.
          </p>

          <div className="hero__actions">
            <Link to="/positions" className="btn btn--primary btn--lg">
              Смотреть все позиции
              <ArrowRightIcon size={16} aria-hidden="true" />
            </Link>
            <Link to="/login" className="btn btn--outline btn--lg">
              Создать аккаунт
            </Link>
          </div>
        </section>

        {state.kind === 'loading' && (
          <p className="muted" role="status">
            Загружаем…
          </p>
        )}

        {state.kind === 'error' && (
          <div className="notice notice--error" role="alert">
            <span>{state.message}</span>
          </div>
        )}

        {state.kind === 'ready' && <HomeContent data={state.data} />}
      </main>
    </>
  )
}

function HomeContent({ data }: { data: HomeData }) {
  return (
    <div className="col g6">
      <StatsBar stats={data.stats} />

      <Panel
        title="Последние позиции"
        hint="Недавно созданные и обновлённые"
        action={<Link to="/positions">Все позиции</Link>}
      >
        <PositionsTable rows={data.latestPositions} />
      </Panel>

      <div className="home__split">
        <Panel title="Самые популярные" hint="Топ-5 по числу поданных резюме">
          <PositionsTable
            rows={data.topPositions}
            compact
            emptyMessage="Пока никто не подал резюме."
          />
        </Panel>

        <Panel title="Технологии" hint="Теги проектов и позиций">
          <TagCloud tags={data.tagCloud} />
        </Panel>
      </div>
    </div>
  )
}

function StatsBar({ stats }: { stats: PublicStats }) {
  return (
    <dl className="stats">
      {STAT_LABELS.map(({ key, label }) => (
        <div key={key} className="stats__item">
          <dt className="stats__label">{label}</dt>
          <dd className="stats__value">{stats[key]}</dd>
        </div>
      ))}
    </dl>
  )
}

/**
 * Облако тегов. Вес показываем размером и насыщенностью, но рядом всегда
 * стоит число — размер шрифта сам по себе слишком неточная шкала.
 */
function TagCloud({ tags }: { tags: TagCloudEntry[] }) {
  const navigate = useNavigate()

  if (tags.length === 0) {
    return <p className="muted table__empty">Тегов пока нет.</p>
  }

  const max = Math.max(...tags.map((tag) => tag.usageCount))

  return (
    <div className="cloud">
      {tags.map((tag) => {
        const weight = max > 0 ? tag.usageCount / max : 0

        return (
          <button
            key={tag.id}
            type="button"
            className="cloud__tag"
            style={{
              fontSize: `${12 + Math.round(weight * 6)}px`,
              opacity: 0.55 + weight * 0.45,
            }}
            onClick={() => navigate(`/positions?search=${encodeURIComponent(tag.name)}`)}
          >
            {tag.name}
            <span className="cloud__count">{tag.usageCount}</span>
          </button>
        )
      })}
    </div>
  )
}

interface PanelProps {
  title: string
  hint?: string
  action?: React.ReactNode
  children: React.ReactNode
}

function Panel({ title, hint, action, children }: PanelProps) {
  return (
    <section className="panel">
      <div className="panel__head">
        <div>
          <h2 className="h2">{title}</h2>
          {hint && <p className="panel__hint muted-3">{hint}</p>}
        </div>
        {action}
      </div>

      {children}
    </section>
  )
}
