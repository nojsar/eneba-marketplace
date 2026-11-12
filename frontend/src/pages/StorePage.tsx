import { Link, useSearchParams } from 'react-router'
import type { ProductList } from '../api/types'
import { useApi } from '../api/useApi'
import { FilterPanel, type ParamChanges } from '../components/FilterPanel'
import { Pagination } from '../components/Pagination'
import { ProductCard } from '../components/ProductCard'
import styles from './StorePage.module.css'

const SORT_OPTIONS = [
  { value: 'relevance', label: 'Best match' },
  { value: 'bestselling', label: 'Best-selling' },
  { value: 'price_asc', label: 'Price: low to high' },
  { value: 'price_desc', label: 'Price: high to low' },
  { value: 'newest', label: 'Newest' },
]

/**
 * The catalogue. All state lives in the URL (/store?platform=steam&sort=price_asc&page=2),
 * so the back button, reloads and shared links all work. The same query string is
 * sent to the API unchanged: the page URL *is* the API request.
 */
export function StorePage() {
  const [params, setParams] = useSearchParams()
  const state = useApi<ProductList>(`/products?${params}`)

  // While a new page loads, keep showing the previous results (dimmed) instead of a blank grid.
  const data = state.status === 'success' ? state.data : state.status === 'loading' ? state.previous : undefined
  const search = params.get('q')
  // Same defaults as the API: best match when searching, best-selling otherwise.
  const defaultSort = search ? 'relevance' : 'bestselling'
  const sortOptions = search ? SORT_OPTIONS : SORT_OPTIONS.filter((option) => option.value !== 'relevance')

  function update(changes: ParamChanges) {
    const next = new URLSearchParams(params)
    for (const [key, value] of Object.entries(changes)) {
      if (value === null) {
        next.delete(key)
      } else {
        next.set(key, value)
      }
    }
    // Changing a filter or the sort starts again from page 1.
    if (!('page' in changes)) {
      next.delete('page')
    }
    setParams(next)
  }

  function goToPage(page: number) {
    update({ page: page === 1 ? null : String(page) })
    window.scrollTo({ top: 0 })
  }

  return (
    <div className={styles.store}>
      <div className={styles.heading}>
        <div>
          <h1 className={styles.title}>{search ? `Results for “${search}”` : 'Store'}</h1>
          {data && <p className={styles.total}>{data.total} results</p>}
        </div>
        <label className={styles.sort}>
          Sort by
          <select
            value={params.get('sort') ?? defaultSort}
            onChange={(event) => update({ sort: event.target.value === defaultSort ? null : event.target.value })}
          >
            {sortOptions.map((option) => (
              <option key={option.value} value={option.value}>
                {option.label}
              </option>
            ))}
          </select>
        </label>
      </div>

      <div className={styles.layout}>
        <FilterPanel params={params} facets={data?.facets} onChange={update} />

        <section aria-busy={state.status === 'loading'} className={state.status === 'loading' ? styles.loading : undefined}>
          {state.status === 'error' && (
            <p className={styles.message} role="alert">
              Could not load products: {state.error.message}
            </p>
          )}

          {!data && state.status === 'loading' && <p className={styles.message}>Loading products…</p>}

          {data && data.items.length === 0 && (
            <div className={styles.message}>
              <p>{data.total === 0 ? 'No products match your search and filters.' : 'This page is empty.'}</p>
              <Link to="/store" className={styles.reset}>
                Show all products
              </Link>
            </div>
          )}

          {data && data.items.length > 0 && (
            <>
              <ul className={styles.grid}>
                {data.items.map((product) => (
                  <li key={product.id}>
                    <ProductCard product={product} />
                  </li>
                ))}
              </ul>
              <Pagination page={data.page} pageCount={data.pageCount} onChange={goToPage} />
            </>
          )}
        </section>
      </div>
    </div>
  )
}
