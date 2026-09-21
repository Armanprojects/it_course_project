import { useCallback, useEffect, useRef, useState } from 'react'

export type SaveState = 'idle' | 'pending' | 'saving' | 'saved' | 'conflict' | 'error'

const SAVE_DELAY_MS = 5000

interface Options<T> {
  onSave: (changes: T) => Promise<boolean>
  isEmpty: (changes: T) => boolean
  empty: T
}

export function useAutosave<T>({ onSave, isEmpty, empty }: Options<T>) {
  const [state, setState] = useState<SaveState>('idle')

  const pending = useRef<T>(empty)
  const timer = useRef<ReturnType<typeof setTimeout> | null>(null)
  const inFlight = useRef(false)

  const saveRef = useRef(onSave)

  useEffect(() => {
    saveRef.current = onSave
  }, [onSave])

  const flush = useCallback(async () => {
    if (timer.current) {
      clearTimeout(timer.current)
      timer.current = null
    }

    if (inFlight.current || isEmpty(pending.current)) {
      return
    }

    const changes = pending.current
    pending.current = empty
    inFlight.current = true
    setState('saving')

    try {
      const ok = await saveRef.current(changes)
      setState(ok ? 'saved' : 'conflict')
    } catch {
      setState('error')
    } finally {
      inFlight.current = false
    }
  }, [empty, isEmpty])

  const schedule = useCallback(
    (merge: (current: T) => T) => {
      pending.current = merge(pending.current)
      setState('pending')

      timer.current ??= setTimeout(() => {
        timer.current = null
        void flush()
      }, SAVE_DELAY_MS)
    },
    [flush],
  )

  const reset = useCallback(() => {
    if (timer.current) {
      clearTimeout(timer.current)
      timer.current = null
    }

    pending.current = empty
    setState('idle')
  }, [empty])

  useEffect(() => {
    const warn = (event: BeforeUnloadEvent) => {
      if (!isEmpty(pending.current)) {
        event.preventDefault()
      }
    }

    window.addEventListener('beforeunload', warn)

    return () => {
      window.removeEventListener('beforeunload', warn)

      if (timer.current) {
        clearTimeout(timer.current)
      }
    }
  }, [isEmpty])

  return { state, schedule, flush, reset, hasPending: () => !isEmpty(pending.current) }
}
