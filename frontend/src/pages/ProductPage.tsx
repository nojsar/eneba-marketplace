import { Link, useParams } from 'react-router'
import { ApiError } from '../api/client'
import type { ProductDetails } from '../api/types'
import { useApi } from '../api/useApi'
import { formatPrice } from '../lib/money'
import { NotFoundPage } from './NotFoundPage'
import styles from './ProductPage.module.css'

// timeZone UTC: "2022-02-25" is midnight UTC, and in a timezone west of UTC it would otherwise print as 24 February.
const releaseDate = new Intl.DateTimeFormat('en-GB', { day: 'numeric', month: 'long', year: 'numeric', timeZone: 'UTC' })

/** One product: details, best price, and every seller's offer side by side so the buyer can compare. */
export function ProductPage() {
  const { slug = '' } = useParams()
  const state = useApi<ProductDetails>(`/products/${encodeURIComponent(slug)}`)

  if (state.status === 'error') {
    if (state.error instanceof ApiError && state.error.status === 404) {
      return <NotFoundPage />
    }
    return (
      <p className={styles.message} role="alert">
        Could not load this product: {state.error.message}
      </p>
    )
  }
  // Unlike the store, never keep showing the previous product while the next one loads.
  if (state.status !== 'success') {
    return <p className={styles.message}>Loading…</p>
  }

  const { product, offers, otherListings } = state.data
  const sellersWithStock = offers.filter((offer) => offer.stock > 0).length

  return (
    <article>
      <nav aria-label="Breadcrumb" className={styles.breadcrumb}>
        <Link to="/store">Store</Link> / <Link to={`/store?platform=${product.platform.slug}`}>{product.platform.name}</Link>{' '}
        / <span aria-current="page">{product.name}</span>
      </nav>

      <div className={styles.top}>
        <img className={styles.cover} src={product.imageUrl} alt={`${product.name} cover art`} width={600} height={900} />

        <div className={styles.summary}>
          <h1 className={styles.title}>{product.name}</h1>
          <p className={styles.tags}>
            <span className={styles.tag}>{product.platform.name}</span>
            <span className={`${styles.tag} ${styles.region}`}>{product.region.name}</span>
          </p>
          <p className={styles.release}>Released {releaseDate.format(new Date(product.releaseDate))}</p>

          {product.lowestPriceCents === null ? (
            <p className={styles.soldOut}>Out of stock at every seller</p>
          ) : (
            <div className={styles.bestPrice}>
              <span className={styles.label}>
                Best price from {sellersWithStock} {sellersWithStock === 1 ? 'seller' : 'sellers'}
              </span>
              <span className={styles.price}>{formatPrice(product.lowestPriceCents)}</span>
              {product.discountPercent !== null && (
                <span className={styles.saving}>
                  <s>{formatPrice(product.listPriceCents)}</s> -{product.discountPercent}%
                </span>
              )}
              <a href="#offers" className={styles.cta}>
                Compare offers
              </a>
            </div>
          )}

          {otherListings.length > 0 && (
            <div>
              <h2 className={styles.smallHeading}>Also available</h2>
              <ul className={styles.otherList}>
                {otherListings.map((listing) => (
                  <li key={listing.slug}>
                    <Link to={`/product/${listing.slug}`} className={styles.otherLink}>
                      {listing.platform} · {listing.region}
                      {listing.lowestPriceCents !== null && <strong>{formatPrice(listing.lowestPriceCents)}</strong>}
                    </Link>
                  </li>
                ))}
              </ul>
            </div>
          )}
        </div>
      </div>

      <section id="offers" className={styles.section}>
        <h2 className={styles.heading}>Offers from {offers.length} sellers</h2>
        <ul className={styles.offers}>
          {offers.map((offer, index) => (
            <li key={offer.id} className={offer.stock === 0 ? `${styles.offer} ${styles.offerSoldOut}` : styles.offer}>
              <div className={styles.seller}>
                <span className={styles.sellerName}>{offer.seller.name}</span>
                <span className={styles.rating}>
                  {offer.seller.ratingPercent}% positive · {offer.seller.ratingCount.toLocaleString('en')} ratings
                </span>
              </div>
              {/* Offers arrive sorted (in stock first, cheapest first), so the first one is the best deal. */}
              {index === 0 && offer.stock > 0 && <span className={styles.badge}>Best price</span>}
              <span className={styles.stock}>{stockLabel(offer.stock)}</span>
              <span className={styles.offerPrice}>{formatPrice(offer.priceCents)}</span>
            </li>
          ))}
        </ul>
      </section>

      <section className={styles.section}>
        <h2 className={styles.heading}>About the game</h2>
        <p className={styles.description}>{product.description}</p>
      </section>
    </article>
  )
}

function stockLabel(stock: number): string {
  if (stock === 0) return 'Out of stock'
  if (stock <= 3) return `Only ${stock} left`
  return 'In stock'
}
