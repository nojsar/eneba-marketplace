import { useEffect, useState } from 'react'
import { Link, useParams } from 'react-router'
import { ApiError } from '../api/client'
import type { Order } from '../api/types'
import { useApi } from '../api/useApi'
import { formatPrice } from '../lib/money'
import { useCart } from '../state/cartContext'
import { NotFoundPage } from './NotFoundPage'
import styles from './OrderPage.module.css'

const MAX_CHECKS = 15 // × 2 s = about 30 seconds of waiting for the payment to be confirmed
const orderDate = new Intl.DateTimeFormat('en-GB', { day: 'numeric', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit' })

/** Where Stripe sends the buyer back after paying (/orders/:id). Shows the keys once the order is paid. */
export function OrderPage() {
  const { id = '' } = useParams()
  const cart = useCart()

  // Changing `check` changes the path, which makes useApi fetch the order again.
  // The API ignores ?check; it only exists to ask a second time.
  const [check, setCheck] = useState(0)
  const state = useApi<Order>(`/orders/${encodeURIComponent(id)}?check=${check}`)
  const order = state.status === 'success' ? state.data : state.status === 'loading' ? state.previous : undefined

  // Still pending: the payment is being confirmed. Ask again in 2 seconds, a limited number of times.
  useEffect(() => {
    if (state.status !== 'success' || state.data.status !== 'pending' || check >= MAX_CHECKS) return
    const timer = setTimeout(() => setCheck((n) => n + 1), 2000)
    return () => clearTimeout(timer)
  }, [state, check])

  // Paid: what was bought leaves the cart. (Not earlier: if payment is cancelled, the cart must still be there.)
  const { remove } = cart
  useEffect(() => {
    if (order?.status === 'paid') {
      remove(order.items.map((item) => item.offerId))
    }
  }, [order, remove])

  if (state.status === 'error') {
    return state.error instanceof ApiError && state.error.status === 404 ? (
      <NotFoundPage />
    ) : (
      <p className={styles.message} role="alert">
        Could not load this order: {state.error.message}
      </p>
    )
  }
  if (!order) {
    return <p className={styles.message}>Loading your order…</p>
  }

  return (
    <article className={styles.order}>
      <header className={styles.header}>
        <h1 className={styles.title}>Order #{order.id}</h1>
        <p className={styles.meta}>
          {orderDate.format(new Date(order.createdAt))} · {formatPrice(order.totalCents)}
        </p>
      </header>

      <StatusMessage order={order} gaveUp={check >= MAX_CHECKS} />

      <ul className={styles.items}>
        {order.items.map((item) => (
          <li key={item.offerId} className={styles.item}>
            <img src={item.product.imageUrl} alt="" className={styles.thumb} width={60} height={80} />
            <div className={styles.info}>
              <Link to={`/product/${item.product.slug}`} className={styles.name}>
                {item.product.name}
              </Link>
              <span className={styles.itemMeta}>
                {item.product.platform} · {item.product.region} · sold by {item.seller} · {item.quantity} ×{' '}
                {formatPrice(item.unitPriceCents)}
              </span>
              {item.keys && item.keys.length > 0 && (
                <ul className={styles.keys} aria-label={`Keys for ${item.product.name}`}>
                  {item.keys.map((key) => (
                    <li key={key}>
                      <KeyCode code={key} />
                    </li>
                  ))}
                </ul>
              )}
            </div>
          </li>
        ))}
      </ul>
    </article>
  )
}

function StatusMessage({ order, gaveUp }: { order: Order; gaveUp: boolean }) {
  if (order.status === 'paid') {
    return <p className={`${styles.status} ${styles.paid}`}>Payment received. Your keys are below.</p>
  }
  if (order.status === 'cancelled') {
    return (
      <p className={`${styles.status} ${styles.cancelled}`}>
        This order was cancelled because the payment was not completed. Nothing was charged.{' '}
        <Link to="/cart">Back to your cart</Link>
      </p>
    )
  }
  return (
    <p className={styles.status} role="status">
      {gaveUp
        ? 'We have not received your payment yet. If you left the payment page, this order is cancelled automatically within 30 minutes and its keys go back on sale.'
        : 'Confirming your payment…'}
    </p>
  )
}

/** A key with a copy button. The keys are what the buyer paid for, so copying must be one click. */
function KeyCode({ code }: { code: string }) {
  const [copied, setCopied] = useState(false)

  async function copy() {
    await navigator.clipboard.writeText(code)
    setCopied(true)
    setTimeout(() => setCopied(false), 1500)
  }

  return (
    <span className={styles.key}>
      <code className={styles.code}>{code}</code>
      <button type="button" className={styles.copy} onClick={copy}>
        {copied ? 'Copied' : 'Copy'}
      </button>
    </span>
  )
}
