import { Link } from 'react-router'
import type { Order } from '../api/types'
import { useApi } from '../api/useApi'
import { formatPrice } from '../lib/money'
import styles from './OrderHistory.module.css'

const STATUS_LABEL: Record<Order['status'], string> = {
  pending: 'Awaiting payment',
  paid: 'Paid',
  cancelled: 'Cancelled',
}
const orderDate = new Intl.DateTimeFormat('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })

/** The signed-in user's orders, newest first. Each one links to its page, where the keys are. */
export function OrderHistory() {
  const state = useApi<{ orders: Order[] }>('/orders')

  if (state.status === 'error') {
    return <p className={styles.note}>Could not load your orders: {state.error.message}</p>
  }
  if (state.status !== 'success') {
    return <p className={styles.note}>Loading your orders…</p>
  }
  if (state.data.orders.length === 0) {
    return (
      <p className={styles.note}>
        No orders yet. <Link to="/store">Find something in the store</Link>
      </p>
    )
  }

  return (
    <ul className={styles.list}>
      {state.data.orders.map((order) => (
        <li key={order.id}>
          <Link to={`/orders/${order.id}`} className={styles.order}>
            <span className={styles.id}>#{order.id}</span>
            <span className={styles.what}>
              {order.items[0].product.name}
              {order.items.length > 1 && ` + ${order.items.length - 1} more`}
            </span>
            <span className={styles.date}>{orderDate.format(new Date(order.createdAt))}</span>
            <span className={`${styles.status} ${styles[order.status]}`}>{STATUS_LABEL[order.status]}</span>
            <span className={styles.total}>{formatPrice(order.totalCents)}</span>
          </Link>
        </li>
      ))}
    </ul>
  )
}
