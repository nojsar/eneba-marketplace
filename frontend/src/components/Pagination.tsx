import styles from './Pagination.module.css'

type Props = {
  page: number
  pageCount: number
  onChange: (page: number) => void
}

export function Pagination({ page, pageCount, onChange }: Props) {
  if (pageCount <= 1) {
    return null
  }

  return (
    <nav className={styles.pagination} aria-label="Pages">
      <button type="button" className={styles.button} disabled={page <= 1} onClick={() => onChange(page - 1)}>
        Previous
      </button>
      <span className={styles.status}>
        Page {page} of {pageCount}
      </span>
      <button type="button" className={styles.button} disabled={page >= pageCount} onClick={() => onChange(page + 1)}>
        Next
      </button>
    </nav>
  )
}
