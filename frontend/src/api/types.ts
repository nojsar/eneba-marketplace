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

/** One entry of GET /api/home: which component to draw and where to fetch its content. */
export type HomeBlockInfo = {
  id: number
  title: string | null
  component: string
  /** Path under /api, e.g. "/blocks/best_sellers" */
  source: string
}

export type HomeLayout = { blocks: HomeBlockInfo[] }

export type Banner = { title: string; subtitle: string; imageUrl: string; ctaLabel: string; ctaUrl: string }

/** Content of a "hero" block */
export type HeroContent = { banners: Banner[] }

/** Content of a "product_rail" block */
export type RailContent = { items: ProductSummary[]; seeAllUrl: string | null }

/** The product details carried by cart and order lines. */
export type ProductRef = { slug: string; name: string; imageUrl: string; platform: string; region: string }

export type CartLine = {
  offerId: number
  quantity: number
  unitPriceCents: number
  lineTotalCents: number
  /** Keys this seller has available right now */
  stock: number
  /** e.g. "Only 2 left"; null when the line can be bought */
  problem: string | null
  seller: string
  product: ProductRef
}

/** GET /api/cart: current prices and stock, computed by the server */
export type CartQuote = { lines: CartLine[]; totalCents: number; canCheckout: boolean }

export type OrderItem = {
  offerId: number
  quantity: number
  unitPriceCents: number
  seller: string
  product: ProductRef
  /** Only on GET /api/orders/{id}, and only once the order is paid */
  keys?: string[]
}

export type Order = {
  id: number
  status: 'pending' | 'paid' | 'cancelled'
  totalCents: number
  createdAt: string
  items: OrderItem[]
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
