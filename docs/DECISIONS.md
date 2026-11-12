# Decisions

Every scope and design choice, why it was made, and what was rejected.
Newest entries at the bottom. "Day N" says when the decision is (or will be) implemented.

---

## 1. Scope: all seven parts, depth in two of them (Day 0)

The brief asks for seven parts and says "a few features done extensively beat many fitting minimal requirements". Every part is built; **Store + Search** and **Checkout** get the depth.

| Part | Depth | What "done" means |
|---|---|---|
| Store | Deep | Filters (platform, region, price), sort, pagination, filter counts, all state in the URL |
| Search | Deep | Header search on every page, suggestions while typing, typo and partial-name matching via `pg_trgm` |
| Product page | Solid | Details + offers from several sellers, cheapest first |
| Checkout | Deep | Server re-prices the cart, reserves keys, Stripe test-mode Checkout, webhook fulfilment, keys on the order page |
| Auth | Solid | Register, log in, log out, database sessions in an httpOnly cookie, protected routes |
| Homepage | Solid | Layout and every block's content come from the backend |
| Styling | Solid | Tokens sampled from eneba.com, responsive down to phone width |

**Cut, and why**

- *A separately deployed service per homepage block*: each block gets its own endpoint, PHP class and data source instead. Same contract (the frontend renders only what it receives, one failing block does not break the page) without extra free-tier servers that each have their own cold start.
- *Seller accounts, admin panel, CMS for the homepage*: sellers, offers, keys and banners are seed data. The brief is about the buyer.
- *Server-side cart*: the cart lives in localStorage. The server never trusts its prices; it recalculates them at checkout.
- *Emailing keys, password reset, email verification, OAuth*: the key is shown on the order page and in order history.
- *A search engine (Meilisearch, Elastic, Algolia)*: `pg_trgm` handles typos and partial names inside the database we already have, which is enough for a catalogue of ~40 products.
- *Reviews, wishlist, multiple currencies (EUR only), translations, VAT, refunds, rate limiting, SSR/SEO.*

Size target: ~1,000 lines of PHP and ~2,000 lines of TypeScript/CSS, so every line can be explained in a 90-minute interview.

## 2. One repository, two apps (Day 1)

`frontend/` (React) deploys to Vercel and `backend/` (PHP) deploys to Render; each service is pointed at its own folder. One repo keeps API changes and the UI that uses them in the same commit.

## 3. The browser only ever talks to one origin (Day 1)

The frontend calls `/api/...` on its own domain. `frontend/vercel.json` forwards those requests to Render in production; `frontend/vite.config.ts` forwards them to `localhost:8000` in development.

- **Why:** the login cookie (Day 4) is then a first-party cookie, which works in every browser. `vercel.app` and `onrender.com` are both on the Public Suffix List, so the page and a directly-called API would be different *sites*. A cookie set by the API would then be a third-party cookie:
  - it is only sent at all if marked `SameSite=None; Secure`, the fetch uses `credentials: 'include'`, and the API answers with `Access-Control-Allow-Origin: <exact origin>` + `Access-Control-Allow-Credentials: true`;
  - even then Safari (and Brave, and Chrome Incognito) block third-party cookies by default: login returns 200, but the next request arrives without the cookie, so the user looks logged out;
  - `SameSite=None` also means any other website can make requests that carry the cookie, so we would need CSRF tokens. With one origin, `SameSite=Lax` blocks that by default.
- There is also no CORS to configure and no extra preflight `OPTIONS` request before every JSON `POST`.
- **Cost:** one extra hop through Vercel. Stripe's webhook (Day 5) calls Render directly because it is server-to-server.
- **Rejected:** calling Render directly with a token in `localStorage`. Simpler to set up, but any XSS bug could read the token.
- **Verified (12 Nov):** after 17 idle minutes (Render's free plan sleeps after 15), a request through Vercel waited for Render to wake and returned 200 after **13.7 s**, with no timeout. Warm requests: **0.50 s** through Vercel vs **0.32 s** straight to Render, so the extra hop costs roughly 0.2 s (one sample each, both servers in Frankfurt). Render says a wake-up can take up to about a minute; a wake-up that slow was not observed. Before a demo, open `/api/health` once to wake the server.

## 4. Plain PHP, no framework (Day 1)

A front controller (`public/index.php`), a ~60-line router and small classes. Composer is used only for autoloading (and later the Stripe SDK).

- **Why:** every line in the request path is ours and can be explained; nothing happens by convention or "magic".
- **Rejected:** Laravel/Symfony (huge surface to explain for ~10 endpoints); Slim (small, but adds PSR-7/middleware concepts we do not need).

## 5. Errors: client errors vs. everything else (Day 1)

Code throws `HttpException(404, 'Not found')` for errors the client should see. `index.php` turns those into `{"error": "..."}` with that status. Any other exception becomes a generic `500 Internal server error`, and the details go only to the server log, so stack traces and SQL never leak to the browser. The production `php.ini` also stops PHP from printing warnings into responses.

## 6. Database connection from one URL, opened lazily (Day 1)

`Database` takes the `DATABASE_URL` Neon provides and opens the PDO connection on the first query, so requests that never touch the database do not pay for a connection. PDO is set to throw on SQL errors and return rows as associative arrays.

Locally we use a separate Neon **branch** (`dev`), so no local Postgres or Docker is needed and the `production` branch's data stays clean.

## 7. Render runs the API from a Dockerfile (Day 1)

Render has no built-in PHP runtime. The image is the official `php:8.3-apache` plus `pdo_pgsql`. Apache serves only `public/`, sends every unknown URL to `index.php` (`FallbackResource`), and listens on the `$PORT` Render provides. Composer dependencies are installed in their own layer so code-only changes rebuild faster.

## 8. Frontend: React Router + CSS Modules, nothing else (Day 1)

- **Routing:** React Router's `BrowserRouter`/`Routes`; a `Layout` route wraps every page with the header and footer.
- **Styling:** `styles/tokens.css` holds colours, font sizes and spacing sampled from eneba.com's computed styles (page `#4618ac`, cards `#1f0a4d`, yellow buttons `#fad318`, Metropolis font, square corners, 5px spacing steps). Components use CSS Modules, so class names cannot clash.
- **Rejected:** Tailwind (long class strings to explain), UI kits (would not look like Eneba), Redux/TanStack Query (one small `useApi` hook covers our loading needs).
- **Branding:** the logo is a text wordmark, not Eneba's logo file, and the footer says it is a demo with test payments only.

## 9. Loading state is derived, not stored (Day 1)

`useApi` stores the latest answer together with the path it belongs to. If the stored answer is for a different path, the hook reports `loading`. This avoids calling `setState` synchronously inside an effect (flagged by the linter as an extra render) and ignores answers that arrive after the user has moved on.

## 10. Connect through Neon's pooler, not directly (Day 1)

Both `DATABASE_URL`s use Neon's pooled endpoint (host contains `-pooler`), which runs PgBouncer in front of Postgres.

- **Why:** PHP shares nothing between requests, so every request opens a new connection and closes it at the end. Without a pooler, each of those is a real Postgres connection: a new server process with its own memory, limited by `max_connections` (about 100 on Neon's smallest compute). Apache can run up to 150 workers by default, so a burst of traffic could hit `too many clients` errors. PgBouncer accepts up to 10,000 client connections and lends a small set of real Postgres connections to them, one transaction at a time.
- **What it does not save:** the TLS handshake and login to the pooler still happen per request. What disappears is starting a new Postgres process per request and the risk of running out of connections.
- **Trade-off (transaction mode):** anything tied to a session, not a transaction, is unreliable: `SET` variables, `LISTEN/NOTIFY`, session-level advisory locks, temporary tables. We use none of them. Row locks (`SELECT ... FOR UPDATE`) live inside a transaction, so key reservation is unaffected.
- **Verified (Day 1):** PDO's server-side prepared statements (prepare once, execute many) and transactions work through Neon's pooler, and `pg_trgm` is available (Postgres 18).
- **Honest note:** at demo traffic a direct connection would also work. Pooling is the correct default for PHP's request model and costs nothing here.

## 11. A deploy check reads the response body, not just the status (Day 1)

The service names `eneba-marketplace-api.onrender.com` and `eneba-marketplace.vercel.app` already belonged to another project, so our services got suffixed URLs. The first `vercel.json` pointed at the taken name, and production `/api/health` returned **200** from someone else's API. Only the body (`{"status":"ok",...}` instead of our `{"api":"up","database":"up"}`) gave it away. Deploy checks therefore compare the body, and the real URLs are recorded in the README.

## Planned (recorded now, implemented later)

- **Money is stored as integer cents** (Day 2). Floats cannot represent 0.10 exactly.
- **Stock is real key rows**, each `available → reserved → sold` (Day 2/5). Keys are reserved at checkout with `SELECT ... FOR UPDATE SKIP LOCKED`, so two buyers cannot pay for the last key.
- **Fulfilment is idempotent** (Day 5): the same function is called by the Stripe webhook and by the order page (which asks Stripe if the order is still pending), so a missed or repeated webhook is harmless.
- **Session tokens are random, and only their SHA-256 hash is stored** (Day 4). Logout deletes the row; a database leak does not leak working sessions.
