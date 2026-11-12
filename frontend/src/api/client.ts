// Every request goes to /api on the page's own origin. In development Vite
// forwards it to the PHP server, in production Vercel forwards it to Render.
// Same origin also means fetch sends the session cookie automatically.

/** An HTTP error from the API, keeping the status so callers can react to 401, 404... */
export class ApiError extends Error {
  readonly status: number

  constructor(status: number, message: string) {
    super(message)
    this.status = status
  }
}

export function apiGet<T>(path: string): Promise<T> {
  return request<T>(path, { method: 'GET' })
}

/** POSTs a JSON body. The API only accepts JSON, which is part of its CSRF protection. */
export function apiPost<T>(path: string, body: unknown = {}): Promise<T> {
  return request<T>(path, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body),
  })
}

async function request<T>(path: string, init: RequestInit): Promise<T> {
  const response = await fetch(`/api${path}`, { ...init, headers: { Accept: 'application/json', ...init.headers } })

  // The body may not be JSON (e.g. a proxy error page), so parsing must not crash.
  const body = await response.json().catch(() => null)

  if (!response.ok) {
    throw new ApiError(response.status, body?.error ?? `Request failed (${response.status})`)
  }

  return body as T
}
