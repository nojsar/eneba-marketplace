/**
 * Where to go after logging in. Only paths inside this app are allowed, so a link
 * like /login?next=//evil.example cannot send people to another site afterwards.
 * ("//host" and "/\host" are read by browsers as links to another host.)
 */
export function safeNextPath(next: string | null): string {
  if (next !== null && next.startsWith('/') && !next.startsWith('//') && !next.startsWith('/\\')) {
    return next
  }
  return '/'
}
