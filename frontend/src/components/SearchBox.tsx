import { useState, type FormEvent, type KeyboardEvent } from 'react'
import { useNavigate } from 'react-router'
import type { Suggestions } from '../api/types'
import { useApi } from '../api/useApi'
import { useDebouncedValue } from '../lib/useDebouncedValue'
import { formatPrice } from '../lib/money'
import { SearchIcon } from './icons'
import styles from './SearchBox.module.css'

/**
 * The header search, available on every page. While typing it suggests matching
 * products (typos allowed); Enter opens the full results in the store.
 *
 * Keyboard and screen readers follow the ARIA "combobox" pattern: the input stays
 * focused, arrow keys move a highlight through the list, Enter picks it, Escape closes.
 */
export function SearchBox({ initialQuery }: { initialQuery: string }) {
  const navigate = useNavigate()
  const [query, setQuery] = useState(initialQuery)
  const [open, setOpen] = useState(false)
  const [active, setActive] = useState(-1) // highlighted suggestion, -1 = none

  // Wait until typing pauses, and don't ask for suggestions for a single letter.
  const term = useDebouncedValue(query.trim(), 250)
  const state = useApi<Suggestions>(term.length >= 2 ? `/search/suggestions?q=${encodeURIComponent(term)}` : null)
  const items =
    state.status === 'success' ? state.data.items : state.status === 'loading' ? (state.previous?.items ?? []) : []
  const showList = open && query.trim().length >= 2 && items.length > 0

  // Picking a product is not a search, so the box is emptied for the next one.
  function openProduct(slug: string) {
    setOpen(false)
    setQuery('')
    navigate(`/product/${slug}`)
  }

  function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    if (showList && active >= 0 && items[active]) {
      openProduct(items[active].slug)
      return
    }
    setOpen(false)
    const q = query.trim()
    navigate(q ? `/store?q=${encodeURIComponent(q)}` : '/store')
  }

  function handleKeyDown(event: KeyboardEvent<HTMLInputElement>) {
    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
      event.preventDefault() // stop the cursor jumping to the start/end of the text
      setOpen(true)
      const step = event.key === 'ArrowDown' ? 1 : -1
      setActive((current) => Math.max(-1, Math.min(items.length - 1, current + step)))
    } else if (event.key === 'Escape') {
      setOpen(false)
    }
  }

  return (
    <form role="search" className={styles.search} onSubmit={handleSubmit}>
      <SearchIcon className={styles.icon} />
      <input
        type="search"
        className={styles.input}
        placeholder="Search for games"
        aria-label="Search for games"
        role="combobox"
        aria-autocomplete="list"
        aria-expanded={showList}
        aria-controls="search-suggestions"
        aria-activedescendant={showList && active >= 0 ? `suggestion-${active}` : undefined}
        autoComplete="off"
        value={query}
        onChange={(event) => {
          setQuery(event.target.value)
          setActive(-1)
          setOpen(true)
        }}
        onKeyDown={handleKeyDown}
        onFocus={() => setOpen(true)}
        onBlur={() => setOpen(false)}
      />

      {showList && (
        <ul id="search-suggestions" role="listbox" className={styles.list}>
          {items.map((product, index) => (
            <li
              key={product.id}
              id={`suggestion-${index}`}
              role="option"
              aria-selected={index === active}
              className={index === active ? `${styles.option} ${styles.active}` : styles.option}
              // mousedown, not click: preventDefault keeps focus in the input, so onBlur
              // does not close the list before the click lands.
              onMouseDown={(event) => {
                event.preventDefault()
                openProduct(product.slug)
              }}
              onMouseEnter={() => setActive(index)}
            >
              <img src={product.imageUrl} alt="" className={styles.thumb} width={40} height={53} />
              <span className={styles.text}>
                <span className={styles.name}>{product.name}</span>
                <span className={styles.meta}>
                  {product.platform.name} · <span className={styles.region}>{product.region.name}</span>
                </span>
              </span>
              {product.lowestPriceCents !== null && (
                <span className={styles.price}>{formatPrice(product.lowestPriceCents)}</span>
              )}
            </li>
          ))}
        </ul>
      )}
    </form>
  )
}
