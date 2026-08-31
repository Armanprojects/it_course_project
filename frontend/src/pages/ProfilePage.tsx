import { CheckCircleIcon, CloudArrowUpIcon, PlusIcon, WarningCircleIcon } from '@phosphor-icons/react'
import { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import { Link, Navigate, useNavigate } from 'react-router-dom'
import { profileApi, RequestError, tokenStorage } from '../api/client'
import type { AttributeValue, ProfileAttribute, ProfileData } from '../api/types'
import { AppHeader } from '../components/AppHeader'
import { AttributeField } from '../components/AttributeField'
import { AttributePicker } from '../components/AttributePicker'
import { ProjectsSection } from '../components/ProjectsSection'
import { useAutosave, type SaveState } from '../lib/useAutosave'

type Draft = Record<number, AttributeValue>

const EMPTY_DRAFT: Draft = {}
const isDraftEmpty = (draft: Draft) => Object.keys(draft).length === 0

/**
 * Персональный профиль: четыре раздела задания — «Обо мне», «Информация»,
 * «Проекты» и «Резюме».
 *
 * Значения атрибутов сохраняются автоматически раз в несколько секунд с
 * оптимистичной блокировкой; проекты — своей формой.
 */
export function ProfilePage() {
  // Без токена на страницу заходить незачем — уводим на вход, не дожидаясь
  // 401 от сервера. Проверка при рендере: она не зависит от загрузки данных.
  if (!tokenStorage.get()) {
    return <Navigate to="/login" replace />
  }

  return <ProfileView />
}

function ProfileView() {
  const navigate = useNavigate()
  const [profile, setProfile] = useState<ProfileData | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [picking, setPicking] = useState(false)

  // Локальные правки поверх серверных данных: пока тик не ушёл, показываем
  // то, что человек набрал, а не то, что лежит на сервере.
  const [draft, setDraft] = useState<Draft>({})

  // Версия нужна обработчику сохранения, который живёт в таймере и не должен
  // пересоздаваться на каждое нажатие клавиши.
  const versionRef = useRef(0)

  const load = useCallback(async () => {
    try {
      const data = await profileApi.me()
      versionRef.current = data.version
      setProfile(data)
      setError(null)
    } catch (requestError: unknown) {
      if (requestError instanceof RequestError && requestError.code === 'unauthorized') {
        tokenStorage.clear()
        navigate('/login', { replace: true })

        return
      }

      setError(
        requestError instanceof RequestError ? requestError.message : 'Непредвиденная ошибка.',
      )
    }
  }, [navigate])

  // Загрузка профиля — синхронизация с сервером, это ровно то, для чего
  // эффект и нужен.
  useEffect(() => {
    void load()
  }, [load])

  const onSave = useCallback(async (changes: Draft) => {
    const data = await profileApi.save(versionRef.current, changes)
    versionRef.current = data.version
    setProfile(data)
    // Правки уехали на сервер и вернулись в его ответе — локальную копию
    // чистим, иначе она перекроет то, что сервер мог поправить.
    setDraft({})

    return true
  }, [])

  const autosave = useAutosave<Draft>({
    onSave: async (changes) => {
      try {
        return await onSave(changes)
      } catch (requestError: unknown) {
        if (requestError instanceof RequestError && requestError.isConflict) {
          // Кто-то сохранил профиль раньше нас. Перечитываем и показываем
          // предупреждение: молча затирать чужие правки нельзя.
          await load()
          setDraft({})

          return false
        }

        throw requestError
      }
    },
    isEmpty: isDraftEmpty,
    empty: EMPTY_DRAFT,
  })

  const { schedule, flush, reset } = autosave

  const change = useCallback(
    (attributeId: number, value: AttributeValue) => {
      setDraft((current) => ({ ...current, [attributeId]: value }))
      schedule((pending) => ({ ...pending, [attributeId]: value }))
    },
    [schedule],
  )

  /** Добавление и удаление атрибута — сразу, не по таймеру: это структурная
      правка, и ждать её пять секунд было бы странно. */
  const mutateAttributes = useCallback(
    async (run: (version: number) => Promise<ProfileData>) => {
      // Незасохранённые значения сначала отправляем, иначе структурная правка
      // поднимет версию и они улетят в конфликт.
      await flush()
      reset()

      try {
        const data = await run(versionRef.current)
        versionRef.current = data.version
        setProfile(data)
        setDraft({})
        setError(null)
      } catch (requestError: unknown) {
        if (requestError instanceof RequestError && requestError.isConflict) {
          await load()

          return
        }

        setError(
          requestError instanceof RequestError ? requestError.message : 'Непредвиденная ошибка.',
        )
      }
    },
    [flush, load, reset],
  )

  const ownedIds = useMemo(
    () =>
      new Set([
        ...(profile?.me ?? []).map((attribute) => attribute.attributeId),
        ...(profile?.info ?? []).map((attribute) => attribute.attributeId),
      ]),
    [profile],
  )

  const valueOf = (attribute: ProfileAttribute): AttributeValue =>
    attribute.attributeId in draft ? draft[attribute.attributeId] : attribute.value

  if (error && profile === null) {
    return (
      <>
        <AppHeader />
        <main className="page">
          <div className="notice notice--error" role="alert">
            <span>{error}</span>
          </div>
        </main>
      </>
    )
  }

  if (profile === null) {
    return (
      <>
        <AppHeader />
        <main className="page">
          <p className="muted" role="status">
            Загружаем…
          </p>
        </main>
      </>
    )
  }

  return (
    <>
      <AppHeader />

      <main className="page">
        <div className="panel__head">
          <div className="col g1">
            <h1 className="h1">Мой профиль</h1>
            <p className="muted" style={{ margin: 0 }}>
              {profile.user.email}
            </p>
          </div>

          <SaveBadge state={autosave.state} />
        </div>

        {error && (
          <div className="notice notice--error" role="alert">
            <span>{error}</span>
          </div>
        )}

        <section className="panel">
          <div className="panel__head">
            <div>
              <h2 className="h2">Обо мне</h2>
              <p className="panel__hint muted-3">
                Встроенные поля — их нельзя убрать из профиля
              </p>
            </div>
          </div>

          <div className="attrgrid">
            {profile.me.map((attribute) => (
              <AttributeField
                key={attribute.attributeId}
                attribute={attribute}
                value={valueOf(attribute)}
                onChange={(value) => change(attribute.attributeId, value)}
              />
            ))}
          </div>
        </section>

        <section className="panel">
          <div className="panel__head">
            <div>
              <h2 className="h2">Информация</h2>
              <p className="panel__hint muted-3">Атрибуты, выбранные из библиотеки</p>
            </div>

            {!picking && (
              <button type="button" className="btn btn--outline" onClick={() => setPicking(true)}>
                <PlusIcon size={14} aria-hidden="true" />
                Добавить атрибут
              </button>
            )}
          </div>

          {picking && (
            <AttributePicker
              ownedIds={ownedIds}
              onClose={() => setPicking(false)}
              onPick={(attribute) => {
                setPicking(false)
                void mutateAttributes((version) =>
                  profileApi.addAttribute(attribute.id, version),
                )
              }}
            />
          )}

          {profile.info.length === 0 ? (
            <p className="muted table__empty">
              Пока ничего не добавлено. Выберите атрибуты из библиотеки.
            </p>
          ) : (
            <div className="attrgrid">
              {profile.info.map((attribute) => (
                <AttributeField
                  key={attribute.attributeId}
                  attribute={attribute}
                  value={valueOf(attribute)}
                  onChange={(value) => change(attribute.attributeId, value)}
                  onRemove={() =>
                    void mutateAttributes((version) =>
                      profileApi.removeAttribute(attribute.attributeId, version),
                    )
                  }
                />
              ))}
            </div>
          )}
        </section>

        <ProjectsSection projects={profile.projects} onChanged={() => void load()} />

        <CvSection cvs={profile.cvs} />
      </main>
    </>
  )
}

/** Индикатор автосохранения: человек должен видеть, что правки не потеряны. */
function SaveBadge({ state }: { state: SaveState }) {
  if (state === 'idle') {
    return null
  }

  const text: Record<Exclude<SaveState, 'idle'>, string> = {
    pending: 'Есть несохранённые изменения',
    saving: 'Сохраняем…',
    saved: 'Сохранено',
    conflict: 'Профиль изменился в другой вкладке — данные перезагружены',
    error: 'Не удалось сохранить',
  }

  const tone = state === 'conflict' || state === 'error' ? 'is-warn' : ''

  return (
    <span className={`savebadge ${tone}`} role="status">
      {state === 'saved' && <CheckCircleIcon size={14} aria-hidden="true" />}
      {(state === 'conflict' || state === 'error') && (
        <WarningCircleIcon size={14} aria-hidden="true" />
      )}
      {(state === 'saving' || state === 'pending') && (
        <CloudArrowUpIcon size={14} aria-hidden="true" />
      )}
      {text[state]}
    </span>
  )
}

const dateFormat = new Intl.DateTimeFormat('ru-RU', { dateStyle: 'medium' })

/**
 * Раздел «Резюме»: таблица резюме кандидата — по одному на позицию.
 * Табличное представление здесь обязательно по заданию.
 */
function CvSection({ cvs }: { cvs: ProfileData['cvs'] }) {
  return (
    <section className="panel">
      <div className="panel__head">
        <div>
          <h2 className="h2">Резюме</h2>
          <p className="panel__hint muted-3">Не больше одного резюме на позицию</p>
        </div>

        <Link to="/positions">Найти позицию</Link>
      </div>

      {cvs.length === 0 ? (
        <p className="muted table__empty">
          Резюме пока нет. Выберите позицию в каталоге, чтобы создать первое.
        </p>
      ) : (
        <div className="table__scroll">
          <table className="table">
            <thead>
              <tr>
                <th scope="col">Позиция</th>
                <th scope="col">Компания</th>
                <th scope="col">Статус</th>
                <th scope="col" className="is-secondary">
                  Лайки
                </th>
                <th scope="col" className="is-secondary">
                  Обновлено
                </th>
              </tr>
            </thead>
            <tbody>
              {cvs.map((cv) => (
                <tr key={cv.id}>
                  <td>
                    <Link className="table__link" to={`/positions/${cv.position.id}`}>
                      {cv.position.title}
                    </Link>
                  </td>
                  <td>{cv.position.company ?? <span className="muted-3">—</span>}</td>
                  <td>
                    <span className={`chip${cv.status === 'published' ? ' chip--ok' : ''}`}>
                      {cv.status === 'published' ? 'Опубликовано' : 'Черновик'}
                    </span>
                  </td>
                  <td className="is-secondary num">{cv.likesCount}</td>
                  <td className="is-secondary">{dateFormat.format(new Date(cv.updatedAt))}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </section>
  )
}
