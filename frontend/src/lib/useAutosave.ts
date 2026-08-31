import { useCallback, useEffect, useRef, useState } from 'react'

export type SaveState = 'idle' | 'pending' | 'saving' | 'saved' | 'conflict' | 'error'

/** Интервал по заданию: сохранять раз в 5–10 секунд, а не на каждое нажатие. */
const SAVE_DELAY_MS = 5000

interface Options<T> {
  /** Отправка накопленных изменений. Возвращает true, если сохранение прошло. */
  onSave: (changes: T) => Promise<boolean>
  /** Пусты ли изменения — чтобы не слать тик впустую. */
  isEmpty: (changes: T) => boolean
  /** Пустой набор изменений: с него начинаем и к нему возвращаемся. */
  empty: T
}

/**
 * Автосохранение с накоплением изменений.
 *
 * Правки копятся в ref, а таймер отсчитывает от первой из них — то есть
 * непрерывный набор текста не откладывает сохранение бесконечно, но и не шлёт
 * запрос на каждую букву. Ref, а не state: он нужен обработчику таймера и
 * размонтированию, а лишний рендер на каждое нажатие тут ни к чему.
 */
export function useAutosave<T>({ onSave, isEmpty, empty }: Options<T>) {
  const [state, setState] = useState<SaveState>('idle')

  const pending = useRef<T>(empty)
  const timer = useRef<ReturnType<typeof setTimeout> | null>(null)
  // Сохранение уже летит: второй запрос параллельно отправлять нельзя, иначе
  // они гонятся за одну и ту же версию и второй гарантированно конфликтует.
  const inFlight = useRef(false)

  // Колбэк держим в ref: он пересоздаётся на каждый рендер вместе с данными
  // страницы, а таймер должен вызывать самую свежую версию, не перезапускаясь.
  // Присваиваем в эффекте, а не прямо в теле: запись в ref во время рендера
  // ломается при конкурентном рендеринге, когда рендер могут отменить.
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

    // Забираем изменения до запроса: правки, сделанные пока он летит, попадут
    // в следующий тик, а не потеряются при очистке после ответа.
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

  /** Зарегистрировать изменение и запустить отсчёт, если он ещё не идёт. */
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

  /** Сбросить накопленное — после конфликта, когда данные перечитаны. */
  const reset = useCallback(() => {
    if (timer.current) {
      clearTimeout(timer.current)
      timer.current = null
    }

    pending.current = empty
    setState('idle')
  }, [empty])

  // Несохранённые правки не должны утекать при уходе со страницы: браузер
  // покажет стандартное предупреждение, а таймер снимаем при размонтировании.
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
