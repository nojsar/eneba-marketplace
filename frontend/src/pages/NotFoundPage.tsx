import { Link } from 'react-router'
import styles from './Message.module.css'

export function NotFoundPage() {
  return (
    <section className={styles.message}>
      <h1 className={styles.title}>Page not found</h1>
      <p className={styles.text}>This page does not exist, or it has not been built yet.</p>
      <Link to="/" className={styles.link}>
        Back to the homepage
      </Link>
    </section>
  )
}
