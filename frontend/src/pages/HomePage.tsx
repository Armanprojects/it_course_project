import { ArrowRightIcon } from '@phosphor-icons/react'
import { useEffect, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { catalogApi } from '../api/client'
import type { HomeData, PublicStats, TagCloudEntry } from '../api/types'
import { AppHeader } from '../components/AppHeader'
import { PositionsTable } from '../components/PositionsTable'
import { useTranslation } from '../i18n/context'
import type { MessageKey } from '../i18n/messages'
import { useErrorText } from '../i18n/useErrorText'

type State =
  | { kind: 'loading' }
  | { kind: 'ready'; data: HomeData }
  | { kind: 'error'; message: string }

const STAT_LABELS: { key: keyof PublicStats; label: MessageKey }[] = [
  { key: 'positions', label: 'home.statPositions' },
  { key: 'submittedCvs', label: 'home.statSubmitted' },
  { key: 'cvsLast24h', label: 'home.statLast24h' },
  { key: 'candidates', label: 'home.statCandidates' },
  { key: 'recruiters', label: 'home.statRecruiters' },
]

export function HomePage() {
  const t = useTranslation()
  const errorText = useErrorText()
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
            message: errorText(error, 'common.unexpectedError'),
          })
        }
      })

    return () => {
      active = false
    }
  }, [errorText])

  return (
    <>
      <AppHeader />

      <main className="page">
        <section className="hero">
          <h1 className="h1">{t('home.title')}</h1>

          <p className="hero__text muted">{t('home.lead')}</p>

          <div className="hero__actions">
            <Link to="/positions" className="btn btn--primary btn--lg">
              {t('home.browse')}
              <ArrowRightIcon size={16} aria-hidden="true" />
            </Link>

          </div>

        </section>

        {state.kind === 'loading' && (
          <p className="muted" role="status">
            {t('common.loading')}
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
  const t = useTranslation()
  return (
    <div className="col g6">
      <StatsBar stats={data.stats} />

      <Panel
        title={t('home.latest')}
        hint={t('home.latestHint')}
        action={<Link to="/positions">{t('home.allPositions')}</Link>}

      >
        <PositionsTable rows={data.latestPositions} />

      </Panel>

      <div className="home__split">
        <Panel title={t('home.popular')} hint={t('home.popularHint')}>
          <PositionsTable
            rows={data.topPositions}
            compact
            emptyMessage={t('home.popularEmpty')}
          />

        </Panel>

        <Panel title={t('home.tags')} hint={t('home.tagsHint')}>
          <TagCloud tags={data.tagCloud} />

        </Panel>

      </div>

    </div>

  )
}

function StatsBar({ stats }: { stats: PublicStats }) {
  const t = useTranslation()
  return (
    <dl className="stats">
      {STAT_LABELS.map(({ key, label }) => (
        <div key={key} className="stats__item">
          <dt className="stats__label">{t(label)}</dt>

          <dd className="stats__value">{stats[key]}</dd>

        </div>

      ))}
    </dl>

  )
}

function TagCloud({ tags }: { tags: TagCloudEntry[] }) {
  const t = useTranslation()
  const navigate = useNavigate()

  if (tags.length === 0) {
    return <p className="muted table__empty">{t('home.tagsEmpty')}</p>
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
