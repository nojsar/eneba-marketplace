import { useEffect, useState } from 'react'
import { apiGet } from './client'

export type ApiState<T> =
  /** `previous` is the last successful answer, so a page can keep showing it while the next one loads. */
  | { status: 'loading'; previous?: T }
  | { status: 'success'; data: T }
  | { status: 'error'; error: Error }

/** Loads `GET /api{path}` and reloads whenever the path changes. */
export function useApi<T>(path: string): ApiState<T> {
  // The latest answer, stored together with the path it answers.
  const [result, setResult] = useState<{ path: string; state: ApiState<T> } | null>(null)

  useEffect(() => {
    // If the path changes (or the page closes) before the answer arrives,
    // the old answer must not overwrite the new one.
    let cancelled = false

    apiGet<T>(path)
      .then((data) => {
        if (!cancelled) setResult({ path, state: { status: 'success', data } })
      })
      .catch((error: Error) => {
        if (!cancelled) setResult({ path, state: { status: 'error', error } })
      })

    return () => {
      cancelled = true
    }
  }, [path])

  if (result?.path === path) {
    return result.state
  }

  // "Loading" is not stored: if the answer we hold is for another path, the new one is still on its way.
  return { status: 'loading', previous: result?.state.status === 'success' ? result.state.data : undefined }
}
