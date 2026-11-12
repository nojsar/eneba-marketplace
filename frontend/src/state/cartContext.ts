import { createContext, useContext } from 'react'

/** The same limit the API enforces per cart line. */
export const MAX_QUANTITY = 10

/**
 * A cart line is only an offer id and a quantity. Names, prices and stock are always
 * fetched from the API (GET /api/cart), so the browser never decides what anything costs.
 */
export type CartItem = { offerId: number; quantity: number }

export type Cart = {
  items: CartItem[]
  /** Number of keys in the cart, for the header badge */
  count: number
  add: (offerId: number) => void
  setQuantity: (offerId: number, quantity: number) => void
  remove: (offerIds: number[]) => void
}

export const CartContext = createContext<Cart | null>(null)

export function useCart(): Cart {
  const cart = useContext(CartContext)
  if (cart === null) {
    throw new Error('useCart() must be used inside <CartProvider>')
  }
  return cart
}
