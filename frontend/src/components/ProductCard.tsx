import { Link } from 'react-router'
import type { ProductSummary } from '../api/types'
import { formatPrice } from '../lib/money'
import styles from './ProductCard.module.css'

/** A product tile as on eneba.com: cover, name, platform and region, cheapest price. */
export function ProductCard({ product }: { product: ProductSummary }) {
  return (
    <Link to={`/product/${product.slug}`} className={styles.card}>
      {/* Empty alt: the product name is right below, so screen readers would hear it twice */}
      <img className={styles.image} src={product.imageUrl} alt="" loading="lazy" width={600} height={900} />

      <div className={styles.body}>
        <h3 className={styles.name}>{product.name}</h3>
        <p className={styles.meta}>
          {product.platform.name} <span className={styles.region}>{product.region.name}</span>
        </p>

        {product.lowestPriceCents === null ? (
          <p className={styles.soldOut}>Out of stock</p>
        ) : (
          <div className={styles.priceBlock}>
            <span className={styles.from}>From</span>
            <span className={styles.price}>{formatPrice(product.lowestPriceCents)}</span>
            {product.discountPercent !== null && (
              <span className={styles.discount}>-{product.discountPercent}%</span>
            )}
          </div>
        )}
      </div>
    </Link>
  )
}
