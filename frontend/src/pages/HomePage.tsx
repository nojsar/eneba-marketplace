import { ApiError } from '../api/client'
import { useApi, type ApiState } from '../api/useApi'
import styles from './Message.module.css'

type Health = { api: 'up'; database: 'up' | 'down' }

// Temporary Day 1 homepage: proves the chain browser → Vercel → Render → Neon works.
// Day 4 replaces it with the homepage built from backend blocks.
export function HomePage() {
  const health = useApi<Health>('/health')

  return (
    <section className={styles.message}>
      <h1 className={styles.title}>Marketplace under construction</h1>
      <p className={styles.text}>The store, search, product pages and checkout are on their way.</p>
      <p className={styles.note} role="status">
        {describeHealth(health)}
      </p>
    </section>
  )
}

function describeHealth(state: ApiState<Health>): string {
  if (state.status === 'loading' || state.status === 'idle') {
    return 'Checking the API… the free server can take up to a minute to wake up.'
  }
  if (state.status === 'success') {
    return 'API up · database up'
  }
  if (state.error instanceof ApiError && state.error.status === 503) {
    return 'API up · database unreachable'
  }
  return `API unreachable (${state.error.message})`
}
