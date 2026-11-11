// Every request goes to /api on the page's own origin. In development Vite
// forwards it to the PHP server, in production Vercel forwards it to Render.

/** An HTTP error from the API, keeping the status so callers can react to 401, 404... */
export class ApiError extends Error {
  readonly status: number

  constructor(status: number, message: string) {
    super(message)
    this.status = status
  }
}

export async function apiGet<T>(path: string): Promise<T> {
  const response = await fetch(`/api${path}`, { headers: { Accept: 'application/json' } })

  // The body may not be JSON (e.g. a proxy error page), so parsing must not crash.
  const body = await response.json().catch(() => null)

  if (!response.ok) {
    throw new ApiError(response.status, body?.error ?? `Request failed (${response.status})`)
  }

  return body as T
}
