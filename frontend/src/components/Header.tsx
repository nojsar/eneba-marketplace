import { Link, useSearchParams } from 'react-router'
import { useAuth } from '../state/authContext'
import { useCart } from '../state/cartContext'
import styles from './Header.module.css'
import { CartIcon, UserIcon } from './icons'
import { SearchBox } from './SearchBox'

export function Header() {
  const [params] = useSearchParams()
  const urlQuery = params.get('q') ?? ''
  const { user, loading } = useAuth()
  const { count } = useCart()

  return (
    <header>
      <div className={styles.top}>
        <Link to="/" className={styles.logo}>
          <span className={styles.logoMark} aria-hidden="true" />
          eneba
          <span className={styles.demoTag}>demo</span>
        </Link>

        {/* The key recreates the box whenever the URL's search changes (a reload, a shared
            link, the back button), so the box always shows the search being displayed. */}
        <SearchBox key={urlQuery} initialQuery={urlQuery} />

        <nav className={styles.actions} aria-label="Account">
          <Link to="/cart" className={styles.iconLink} aria-label={count > 0 ? `Cart, ${count} items` : 'Cart'}>
            <CartIcon />
            {count > 0 && (
              <span className={styles.badge} aria-hidden="true">
                {count}
              </span>
            )}
          </Link>
          {/* Nothing until the session check answers, so "Log in" never flashes for a logged-in user */}
          {loading ? null : user ? (
            <Link to="/account" className={styles.account} title={user.email}>
              <UserIcon />
              <span className={styles.email}>{user.email}</span>
            </Link>
          ) : (
            <span className={styles.account}>
              <UserIcon />
              <Link to="/login">Log in</Link>
              <span className={styles.divider} aria-hidden="true">|</span>
              <Link to="/register">Register</Link>
            </span>
          )}
        </nav>
      </div>

      <nav className={styles.categories} aria-label="Main">
        <div className={styles.categoriesInner}>
          <Link to="/store">Store</Link>
        </div>
      </nav>
    </header>
  )
}
