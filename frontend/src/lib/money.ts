const euros = new Intl.NumberFormat('en-IE', { style: 'currency', currency: 'EUR' })

/** 2599 → "€25.99". Prices travel as whole cents and only become euros for display. */
export function formatPrice(cents: number): string {
  return euros.format(cents / 100)
}
