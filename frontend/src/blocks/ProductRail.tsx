import { Link } from 'react-router'
import type { RailContent } from '../api/types'
import { ProductCard } from '../components/ProductCard'
import styles from './ProductRail.module.css'

/**
 * A titled row of product cards that scrolls sideways. The same component draws
 * "Best sellers" and "Biggest discounts": what differs is the backend provider.
 */
export function ProductRail({ title, content }: { title: string | null; content: RailContent }) {
  if (content.items.length === 0) {
    return null
  }

  return (
    <section className={styles.rail}>
      {title && <h2 className={styles.title}>{title}</h2>}
      <ul className={styles.items}>
        {content.items.map((product) => (
          <li key={product.id} className={styles.item}>
            <ProductCard product={product} />
          </li>
        ))}
      </ul>
      {content.seeAllUrl && (
        <Link to={content.seeAllUrl} className={styles.seeAll}>
          See all
        </Link>
      )}
    </section>
  )
}
