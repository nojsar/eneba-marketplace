import type { ReactNode } from 'react'
import { Navigate, useLocation } from 'react-router'
import styles from '../pages/Message.module.css'
import { useAuth } from './authContext'

/**
 * Wraps pages that need an account (account, checkout, orders). Visitors are sent to
 * the login page, which brings them back here afterwards via ?next=.
 *
 * This only decides what the browser shows: the real protection is the API refusing
 * those requests with 401 for anyone without a valid session.
 */
export function RequireAuth({ children }: { children: ReactNode }) {
  const { user, loading } = useAuth()
  const location = useLocation()

  if (loading) {
    return (
      <section className={styles.message}>
        <p className={styles.text}>Checking your session…</p>
      </section>
    )
  }

  if (user === null) {
    const next = location.pathname + location.search
    return <Navigate to={`/login?next=${encodeURIComponent(next)}`} replace />
  }

  return children
}
