import { useEffect, useState } from 'react'
import { authApi, tokenStorage } from '../api/client'
import { UserRole, type User } from '../api/types'

/**
 * Текущий пользователь, чтобы UI знал, что показывать рекрутеру.
 *
 * Ответ кэшируется в модуле: шапка, страница позиции и таблица резюме
 * спрашивают его независимо, а /api/auth/me должен уйти один раз.
 */
let cached: User | null = null
let inFlight: Promise<User> | null = null

export function clearUserCache() {
  cached = null
  inFlight = null
}

function fetchUser(): Promise<User> {
  inFlight ??= authApi.me().then((user) => {
    cached = user
    inFlight = null

    return user
  })

  return inFlight
}

export interface CurrentUser {
  user: User | null
  loading: boolean
  isRecruiter: boolean
  isAdmin: boolean
  isCandidate: boolean
}

export function useCurrentUser(): CurrentUser {
  const [user, setUser] = useState<User | null>(cached)
  const [loading, setLoading] = useState(cached === null && tokenStorage.get() !== null)

  useEffect(() => {
    if (cached !== null || !tokenStorage.get()) {
      return
    }

    let active = true

    fetchUser()
      .then((loaded) => {
        if (active) {
          setUser(loaded)
        }
      })
      // Протухший токен — не повод ронять страницу: она просто отрисуется
      // как для гостя, а защищённые экраны сами уведут на вход.
      .catch(() => {
        if (active) {
          setUser(null)
        }
      })
      .finally(() => {
        if (active) {
          setLoading(false)
        }
      })

    return () => {
      active = false
    }
  }, [])

  const roles = user?.roles ?? []
  const isAdmin = roles.includes(UserRole.Admin)

  return {
    user,
    loading,
    // Админ наследует права рекрутера — так же, как в role_hierarchy на бэкенде.
    isRecruiter: isAdmin || roles.includes(UserRole.Recruiter),
    isAdmin,
    isCandidate: roles.includes(UserRole.Candidate),
  }
}
