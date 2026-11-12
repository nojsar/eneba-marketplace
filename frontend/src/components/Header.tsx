import { Link, useSearchParams } from 'react-router'
import styles from './Header.module.css'
import { CartIcon, UserIcon } from './icons'
import { SearchBox } from './SearchBox'

export function Header() {
  const [params] = useSearchParams()
  const urlQuery = params.get('q') ?? ''

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
          <Link to="/cart" className={styles.iconLink} aria-label="Cart">
            <CartIcon />
          </Link>
          <span className={styles.account}>
            <UserIcon />
            <Link to="/login">Log in</Link>
            <span className={styles.divider} aria-hidden="true">|</span>
            <Link to="/register">Register</Link>
          </span>
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
