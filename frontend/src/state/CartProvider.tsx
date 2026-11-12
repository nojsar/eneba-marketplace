import { useEffect, useState, type ReactNode } from 'react'
import { CartContext, MAX_QUANTITY, type Cart, type CartItem } from './cartContext'

const STORAGE_KEY = 'cart'

/**
 * The cart lives in localStorage, so it survives reloads and works for visitors
 * who are not logged in yet. The server re-prices it at checkout anyway.
 */
export function CartProvider({ children }: { children: ReactNode }) {
  // Passing the function (not its result) makes React read localStorage only on the first render.
  const [items, setItems] = useState<CartItem[]>(loadCart)

  // localStorage is outside React, so keeping it in step is a job for an effect.
  useEffect(() => {
    try {
      localStorage.setItem(STORAGE_KEY, JSON.stringify(items))
    } catch {
      // Storage full or blocked (e.g. some private modes): the cart still works until the page closes.
    }
  }, [items])

  const cart: Cart = {
    items,
    count: items.reduce((sum, item) => sum + item.quantity, 0),
    add: (offerId) =>
      setItems((current) =>
        current.some((item) => item.offerId === offerId)
          ? current.map((item) =>
              item.offerId === offerId ? { ...item, quantity: Math.min(MAX_QUANTITY, item.quantity + 1) } : item,
            )
          : [...current, { offerId, quantity: 1 }],
      ),
    setQuantity: (offerId, quantity) =>
      setItems((current) => current.map((item) => (item.offerId === offerId ? { ...item, quantity } : item))),
    remove: (offerIds) =>
      // Returning the same array when nothing matches tells React nothing changed, so nothing re-renders.
      setItems((current) =>
        current.some((item) => offerIds.includes(item.offerId))
          ? current.filter((item) => !offerIds.includes(item.offerId))
          : current,
      ),
  }

  return <CartContext value={cart}>{children}</CartContext>
}

/** Reads the saved cart, ignoring anything malformed (it is user-editable storage). */
function loadCart(): CartItem[] {
  try {
    const saved: unknown = JSON.parse(localStorage.getItem(STORAGE_KEY) ?? '[]')
    return Array.isArray(saved) ? saved.filter(isCartItem) : []
  } catch {
    return []
  }
}

function isCartItem(value: unknown): value is CartItem {
  if (typeof value !== 'object' || value === null) return false
  const { offerId, quantity } = value as Record<string, unknown>
  return (
    Number.isInteger(offerId) &&
    Number.isInteger(quantity) &&
    (quantity as number) >= 1 &&
    (quantity as number) <= MAX_QUANTITY
  )
}
