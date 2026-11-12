// Shapes of the API's JSON responses. They mirror what the PHP controllers return.

/** A filter value: `slug` goes in URLs, `name` is shown to people. */
export type Option = { slug: string; name: string }

/** A filter value with how many products it would match. */
export type FacetOption = Option & { count: number }

export type ProductSummary = {
  id: number
  slug: string
  name: string
  imageUrl: string
  platform: Option
  region: Option
  listPriceCents: number
  /** Cheapest in-stock offer; null when no seller has stock. */
  lowestPriceCents: number | null
  discountPercent: number | null
  /** Sellers that currently have stock. */
  offerCount: number
}

/** GET /api/products */
export type ProductList = {
  items: ProductSummary[]
  total: number
  page: number
  pageSize: number
  pageCount: number
  facets: { platforms: FacetOption[]; regions: FacetOption[] }
}
