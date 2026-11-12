import { useState } from 'react'
import { Link, useNavigate } from 'react-router'
import { apiPost } from '../api/client'
import type { CartQuote } from '../api/types'
import { useApi } from '../api/useApi'
import { formatPrice } from '../lib/money'
import { useAuth } from '../state/authContext'
import { MAX_QUANTITY, useCart } from '../state/cartContext'
import styles from './CartPage.module.css'

/**
 * The cart. What is in it comes from localStorage (offer ids and quantities);
 * everything shown about it comes from GET /api/cart: names, current prices,
 * stock and the total. Checkout needs an account.
 */
export function CartPage() {
  const cart = useCart()
  const { user } = useAuth()
  const navigate = useNavigate()
  const [error, setError] = useState<string | null>(null)
  const [redirecting, setRedirecting] = useState(false)

  // "/cart?items=12:1,15:2"; null (no request) when the cart is empty.
  const path = cart.items.length > 0 ? `/cart?items=${cart.items.map((i) => `${i.offerId}:${i.quantity}`).join(',')}` : null
  const quote = useApi<CartQuote>(path)
  const data = quote.status === 'success' ? quote.data : quote.status === 'loading' ? quote.previous : undefined

  async function handleCheckout() {
    if (user === null) {
      navigate('/login?next=/cart') // the login page brings the buyer back here
      return
    }
    setError(null)
    setRedirecting(true)
    try {
      // The server creates the order, reserves the keys and answers with Stripe's payment page.
      const { url } = await apiPost<{ url: string }>('/checkout', { items: cart.items })
      window.location.assign(url) // leave the app: the buyer pays on Stripe's own page
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Checkout failed')
      setRedirecting(false)
    }
  }

  if (cart.items.length === 0) {
    return (
      <section className={styles.empty}>
        <h1 className={styles.title}>Your cart is empty</h1>
        <Link to="/store" className={styles.primary}>
          Browse the store
        </Link>
      </section>
    )
  }

  if (quote.status === 'error') {
    return (
      <p className={styles.empty} role="alert">
        Could not load your cart: {quote.error.message}
      </p>
    )
  }

  return (
    <div>
      <h1 className={styles.title}>Cart</h1>

      {!data ? (
        <p className={styles.empty}>Loading your cart…</p>
      ) : (
        <div className={styles.layout}>
          <ul className={quote.status === 'loading' ? `${styles.lines} ${styles.updating}` : styles.lines}>
            {data.lines.map((line) => (
              <li key={line.offerId} className={styles.line}>
                <img src={line.product.imageUrl} alt="" className={styles.thumb} width={60} height={80} />
                <div className={styles.info}>
                  <Link to={`/product/${line.product.slug}`} className={styles.name}>
                    {line.product.name}
                  </Link>
                  <span className={styles.meta}>
                    {line.product.platform} · <span className={styles.region}>{line.product.region}</span> · sold by{' '}
                    {line.seller}
                  </span>
                  {line.problem && <span className={styles.problem}>{line.problem}</span>}
                </div>

                <label className={styles.quantity}>
                  <span className={styles.visuallyHidden}>Quantity</span>
                  <select
                    value={line.quantity}
                    onChange={(event) => cart.setQuantity(line.offerId, Number(event.target.value))}
                  >
                    {/* Up to what is in stock (but always the current amount, even if stock dropped) */}
                    {Array.from({ length: Math.min(MAX_QUANTITY, Math.max(line.stock, line.quantity)) }, (_, i) => i + 1).map(
                      (n) => (
                        <option key={n} value={n}>
                          {n}
                        </option>
                      ),
                    )}
                  </select>
                </label>

                <span className={styles.price}>{formatPrice(line.lineTotalCents)}</span>
                <button type="button" className={styles.remove} onClick={() => cart.remove([line.offerId])}>
                  Remove
                </button>
              </li>
            ))}
          </ul>

          <aside className={styles.summary}>
            <div className={styles.totalRow}>
              <span>Total</span>
              <span className={styles.total}>{formatPrice(data.totalCents)}</span>
            </div>
            <button
              type="button"
              className={styles.primary}
              onClick={handleCheckout}
              disabled={!data.canCheckout || redirecting || quote.status === 'loading'}
            >
              {redirecting ? 'Opening payment…' : user ? 'Checkout' : 'Log in to check out'}
            </button>
            {error && (
              <p className={styles.error} role="alert">
                {error}
              </p>
            )}
            {!data.canCheckout && <p className={styles.note}>Fix the items marked in yellow to continue.</p>}
            <p className={styles.note}>
              Keys are held for you for 30 minutes while you pay. Payments run in Stripe test mode: use card 4242 4242
              4242 4242, any future date and any CVC.
            </p>
          </aside>
        </div>
      )}
    </div>
  )
}
