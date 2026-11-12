import { useNavigate } from 'react-router'
import { OrderHistory } from '../components/OrderHistory'
import { useAuth } from '../state/authContext'
import styles from './AccountPage.module.css'

const memberSince = new Intl.DateTimeFormat('en-GB', { day: 'numeric', month: 'long', year: 'numeric' })

/** Only reachable through <RequireAuth>, so there is always a user here. */
export function AccountPage() {
  const { user, logout } = useAuth()
  const navigate = useNavigate()

  if (user === null) {
    return null // cannot happen behind RequireAuth; this line just tells TypeScript
  }

  // Leave this protected page first. Logging out first would re-render RequireAuth with
  // no user, which redirects to /login before navigate('/') gets a chance to run.
  async function handleLogout() {
    navigate('/')
    await logout()
  }

  return (
    <section className={styles.account}>
      <h1 className={styles.title}>My account</h1>
      <dl className={styles.details}>
        <dt>Email</dt>
        <dd>{user.email}</dd>
        <dt>Member since</dt>
        <dd>{memberSince.format(new Date(user.createdAt))}</dd>
      </dl>
      <button type="button" className={styles.logout} onClick={handleLogout}>
        Log out
      </button>

      <h2 className={styles.subtitle}>Your orders</h2>
      <OrderHistory />
    </section>
  )
}
