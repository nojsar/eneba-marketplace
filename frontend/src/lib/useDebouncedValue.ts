import { useEffect, useState } from 'react'

/**
 * Returns `value`, but only after it has stopped changing for `delayMs`.
 * Typing "elden" quickly then sends one request instead of five.
 */
export function useDebouncedValue<T>(value: T, delayMs: number): T {
  const [debounced, setDebounced] = useState(value)

  useEffect(() => {
    const timer = setTimeout(() => setDebounced(value), delayMs)
    // A new keystroke before the delay is over cancels the pending update.
    return () => clearTimeout(timer)
  }, [value, delayMs])

  return debounced
}
