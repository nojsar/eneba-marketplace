import { useState, type FormEvent } from 'react'
import { Link, useNavigate } from 'react-router'
import styles from './Header.module.css'
import { CartIcon, SearchIcon, UserIcon } from './icons'

export function Header() {
  const navigate = useNavigate()
  const [query, setQuery] = useState('')

  // Searching just opens the store with ?q=..., so search and the catalog share one page.
  function handleSearch(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    const q = query.trim()
    navigate(q ? `/store?q=${encodeURIComponent(q)}` : '/store')
  }

  return (
    <header>
      <div className={styles.top}>
        <Link to="/" className={styles.logo}>
          <span className={styles.logoMark} aria-hidden="true" />
          eneba
          <span className={styles.demoTag}>demo</span>
        </Link>

        <form role="search" className={styles.search} onSubmit={handleSearch}>
          <SearchIcon className={styles.searchIcon} />
          <input
            type="search"
            className={styles.searchInput}
            placeholder="Search for games"
            aria-label="Search for games"
            value={query}
            onChange={(event) => setQuery(event.target.value)}
          />
        </form>

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
