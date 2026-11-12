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

/** GET /api/search/suggestions */
export type Suggestions = { items: ProductSummary[] }

export type Offer = {
  id: number
  priceCents: number
  /** Keys this seller has available right now. */
  stock: number
  seller: { name: string; ratingPercent: number; ratingCount: number }
}

/** GET /api/products/{slug} */
export type ProductDetails = {
  product: ProductSummary & { description: string; releaseDate: string }
  /** Sellers with stock first, cheapest first. */
  offers: Offer[]
  /** The same game on other platforms or in other regions. */
  otherListings: { slug: string; platform: string; region: string; lowestPriceCents: number | null }[]
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
