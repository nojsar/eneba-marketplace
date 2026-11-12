import { useEffect } from 'react'
import { Outlet, useLocation } from 'react-router'
import { Header } from './Header'
import styles from './Layout.module.css'

/** The frame around every page: header on top, the current page in the middle, footer below. */
export function Layout() {
  const { pathname } = useLocation()

  // A new page starts at the top. Only the path counts, so changing a store filter
  // (which only changes ?query) does not make the page jump.
  useEffect(() => {
    window.scrollTo(0, 0)
  }, [pathname])

  return (
    <div className={styles.page}>
      <Header />
      <main className={styles.main}>
        <Outlet />
      </main>
      <footer className={styles.footer}>
        Demo project for the Eneba internship assignment, not affiliated with Eneba.
        Payments run in Stripe test mode; no real money is involved.
      </footer>
    </div>
  )
}
