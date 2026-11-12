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
- **Correction (Day 2):** the Day 1 note here said prepared statements and transactions were verified through the pooler. That check was wrong: it only confirmed that `commit()` did not throw, not that data was saved. In fact transactions were being silently rolled back. See #12 for the bug and the fix. `pg_trgm` is available (Postgres 18).
- **Honest note:** at demo traffic a direct connection would also work. Pooling is the correct default for PHP's request model and costs nothing here.

## 11. A deploy check reads the response body, not just the status (Day 1)

The service names `eneba-marketplace-api.onrender.com` and `eneba-marketplace.vercel.app` already belonged to another project, so our services got suffixed URLs. The first `vercel.json` pointed at the taken name, and production `/api/health` returned **200** from someone else's API. Only the body (`{"status":"ok",...}` instead of our `{"api":"up","database":"up"}`) gave it away. Deploy checks therefore compare the body, and the real URLs are recorded in the README.

## 12. PDO's named prepared statements are off: they silently broke transactions behind the pooler (Day 2)

**Symptom:** the first migration reported success, but running it again applied it again: no tables existed, and `schema_migrations` was empty.

**Narrowing down:** inside a transaction, the new table was visible *before* `COMMIT` and gone *after*. Postgres answers `COMMIT` on an aborted transaction with a silent `ROLLBACK`, so something was aborting the transaction without PDO reporting an error. The same test passed with prepared statements turned off, and passed on Neon's direct (non-pooled) endpoint. It only failed with pooler + named prepared statements.

**Cause:** PDO prepares every query as a *named* statement (`pdo_stmt_00000001`) and, when the PHP statement object is destroyed, sends `DEALLOCATE pdo_stmt_00000001` as plain SQL. PgBouncer (Neon's pooler) supports prepared statements by renaming them on the server, so that `DEALLOCATE` names a statement that does not exist and fails. PDO ignores the failure, but it has already aborted the open transaction.

**Fix:** `PDO::PGSQL_ATTR_DISABLE_PREPARES` in `Database.php`. PDO then sends each query together with its parameters in one call, using an unnamed statement, so there is nothing to deallocate. Parameters still travel separately from the SQL text, so SQL injection protection is unchanged. The only cost is that a statement executed many times is planned each time, which does not matter for a PHP request that runs each query once.

**Rejected:** switching to the direct endpoint (it works, but gives up pooling, #10); emulated prepares (PDO would paste escaped values into the SQL text).

**Lesson:** the Day 1 check asked "did `commit()` throw?" instead of "is the data there?". Like #11: verify the effect, not the absence of an error.

## 13. Data model (Day 2)

- **A product is one game on one platform in one region**, like Eneba's "Elden Ring (PC) Steam Key GLOBAL". So the platform and region filters are plain columns, and each listing has its own sellers and prices.
- **Platforms and regions are lookup tables** (`slug` + `name`). The store's filter options and their display names come from the database, not from frontend code.
- **Money is integer cents** everywhere (`price_cents`, `list_price_cents`). Floats cannot represent 0.10 exactly. Euros appear only in the URL (`?min_price=9.99`, converted on the way in) and on screen.
- **Stock is real key rows** (`product_keys`), each `available → reserved → sold`. "In stock" means "has an available key", never a counter that can drift. A `CHECK` makes "not available" and "belongs to an order item" the same thing.
- **The order line copies the price** (`order_items.unit_price_cents`), so later price changes do not rewrite what someone paid.
- **One view, `product_listing`,** adds the display names, cheapest in-stock price, sellers with stock and units sold. Store, search and the homepage blocks all read from it, so "cheapest price" is defined in exactly one place. It uses correlated subqueries (easy to read) rather than joins with `GROUP BY`. With 48 products the query takes a few milliseconds.
- **Orders and users exist from Day 2**, because "best-selling" is counted from paid orders. The seed creates three demo buyers whose password hash is `!`, which no password can match.
- **Cut:** key codes are stored in plain text. A real marketplace would encrypt them at rest. Cover images are loaded from Steam's public CDN by app id, not stored here.

## 14. Migrations and seed data run when the container starts (Day 2)

`docker/start.sh` runs `bin/migrate.php`, then `bin/seed.php --if-empty`, then starts Apache.

- **Migrations** are numbered `.sql` files applied in order and recorded in `schema_migrations`. Each runs in a transaction, and Postgres can roll back `CREATE TABLE` too, so a failing migration leaves nothing half-applied. If a step fails, the container stops and Render keeps the previous version live.
- **Seeding** only happens when there are no products, so redeploys never wipe real orders. `--fresh` (development only) truncates and reseeds. A fixed random seed (`mt_srand(2027)`) gives every environment the same sellers, prices and stock.
- **Why at start-up:** Render's free plan has no pre-deploy hook or shell, and running migrations by hand against production is easy to forget. The cost is a couple of quick queries on each cold start.
- Multi-row `INSERT`s for keys: one round trip per offer instead of one per key.

## 15. The store: the page URL is the API request (Day 2)

`/store?platform=steam,gog&sort=price_asc&page=2` sends the same query string to `GET /api/products`. Filters, sort and page live only in the URL, so the back button, reloads and shared links all work, and no React state can drift from what is shown.

- **Filter counts ignore their own group.** The platform counts apply every filter *except* the platform one, so ticking "Steam" still shows how many "GOG" would add. Options with 0 matches are greyed out instead of leading to an empty page.
- **Ticking several platforms widens** the results (Steam OR GOG). Different groups narrow them (platform AND region AND price).
- **Validation in one place** (`ProductFilters::fromQuery`). Bad input gets a 400 with a readable message (`?page=0`, `?sort=cheap`, `?min_price=abc`, `min > max`, `?platform[]=x`). Testing caught `?page=0` silently becoming page 1, because PHP treats `"0"` as false in `?:`.
- **No user input is ever pasted into SQL.** Values are bound parameters. `IN (...)` gets one placeholder per value, because PDO cannot bind a list. `ORDER BY` comes from a fixed map of four sort keys. In searches, `%` and `_` are escaped so they match literally.
- **Stable paging:** every sort ends with `id`, so two products with the same price cannot swap places between page 1 and page 2.
- **While new results load, the old ones stay on screen, dimmed** (`useApi` returns the previous data with `loading`), instead of flashing an empty grid on every click.
- Search is a plain `ILIKE` partial match for now. Day 3 adds typo tolerance.

## 16. Search: exact text first, typo matching as the fallback (Day 3)

Postgres `pg_trgm` splits text into trigrams ("witcher" → `wit`, `itc`, `tch`, ...) and measures how many two strings share. A GIN trigram index on `products.name` (migration 002) serves all three ways we match:

| Operator | Matches | Example |
|---|---|---|
| `name ILIKE '%…%'` | the typed text appears in the name | `hogwart` → Hogwarts Legacy |
| `q <% name` | a typo inside a longer name (word similarity ≥ 0.6) | `witchr` → The Witcher 3: Wild Hunt |
| `q % name` | a typo across a short name (whole-name similarity ≥ 0.3) | `palwrld` → Palworld |

- **Why both typo operators:** word similarity alone missed one-letter typos in short names (`palwrld`, `factrio`, `terarria` all scored 0.50–0.55). Whole-name similarity alone misses typos in long names (`witchr` vs. "The Witcher 3: Wild Hunt" scores only 0.21). Probing 19 queries showed `%` added exactly those three games and no unwanted matches.
- **Exact text first:** the typo operators only apply when **no** product name contains the typed text (`NOT EXISTS (… ILIKE …)`). Without this, `elden` also matched "The **Elder** Scrolls" (one letter apart) and, sorted by price, Skyrim came first. Now `elden` finds only Elden Ring, `elder` only Skyrim, and `eldn ring` falls back to typo matching.
- **Ranking:** when searching, the default sort is `relevance`: `word_similarity` (0–1), then best-selling. Any other sort is still allowed; `relevance` without a search term is a 400.
- **Default thresholds, no `SET`:** the thresholds are session settings, and #10 explains why session settings are unreliable through the pooler. The defaults happened to fit.
- **Tested queries:** `witchr`, `cyberpnk`, `eldn ring`, `baldurs gate`, `red ded`, `stardew valey`, `palwrld`, `god of war ragnarok` (no ö) all find their game; `zelda`, `mario`, `gta`, `fifa` find nothing.
- **Known limits:** abbreviations (`gta`, `cod`) would need a keywords/aliases column. `witcher wild hunt` (missing "3:") also matches Monster Hunter **Wild**s, ranked below the Witcher. Search covers names only, not descriptions.
- **Rejected:** a search service (Meilisearch, Algolia): better ranking and synonyms, but another free-tier service and a sync job, for a 48-product catalogue (#1).

## 17. Header suggestions and the product page (Day 3)

- **Suggestions** (`GET /api/search/suggestions?q=`) reuse the store query with `relevance` and a limit of 6, so the dropdown and the results page can never disagree. They start at two letters, and the request waits until typing pauses for 250 ms (`useDebouncedValue`), so typing "elden" costs one request, not five.
- **Accessible combobox:** the input keeps focus while arrow keys move a highlight (`aria-activedescendant`), Enter opens the highlighted product or, with nothing highlighted, the full results, and Escape closes the list. Options react on `mousedown` with `preventDefault`, so the input's blur does not close the list before the click lands.
- **The search box follows the URL:** it is keyed on `?q=`, so after a reload, a shared link or the back button it shows the search on screen. Picking a product empties it.
- **`useApi(null)`** means "don't request anything" (status `idle`), used before two letters are typed.
- **Product page** (`GET /api/products/{slug}`): details, every seller's offer with stock and rating, sorted with stock first and then cheapest, so the first offer is the "Best price". It also links the same game on other platforms and regions. Sold-out offers stay visible, dimmed, so the comparison is honest. Unknown slugs return 404 and show the not-found page.
- **Scroll to top** when the path changes (not the query, so store filters do not jump).
- In development every product request appears twice: React's StrictMode mounts components twice on purpose. `useApi`'s `cancelled` flag ignores the first answer; production builds request once.
- **"Add to cart"** buttons on offers come with the cart on Day 5, rather than shipping buttons that do nothing.

## 18. Accounts: database sessions in an httpOnly cookie (Day 4)

- **Passwords:** `password_hash()` with `PASSWORD_DEFAULT` (bcrypt, random salt, cost 10) and `password_verify()`. Length 8–72 bytes: bcrypt ignores everything after byte 72, so longer passwords are refused rather than silently cut.
- **Sessions are rows, not JWTs.** Login creates a random 32-byte token (`random_bytes`). The cookie holds the token, and the `sessions` table holds only its **SHA-256 hash**, so a leaked table cannot log anyone in. Logout deletes the row, so it takes effect immediately, which a JWT cannot do without a blocklist. Sessions expire after 30 days. A new token is issued on every login, so a planted or old token is never reused.
- **Why SHA-256 and not bcrypt for tokens:** the token is 256 random bits, so it cannot be guessed and does not need a slow hash. A fast hash also allows the indexed lookup `WHERE token_hash = …`.
- **Cookie flags:** `HttpOnly` (JavaScript cannot read it, so an XSS bug cannot steal the session; verified `document.cookie` is empty), `Secure` (HTTPS only; browsers allow it on localhost), `SameSite=Lax` (not sent on requests other sites start, apart from plain link clicks), `Path=/`.
- **CSRF, two layers:** `SameSite=Lax`, and the API only accepts `application/json` bodies (415 otherwise). An HTML form on another site cannot send JSON, and `fetch` from another origin would need a CORS preflight that we never answer.
- **No account fishing:** login gives the same message for an unknown email and a wrong password, and runs `password_verify` against a dummy hash for unknown emails so both take the same time (measured locally: ~660–736 ms vs ~665–695 ms). Registration does reveal that an email is taken (409). Most shops accept that trade-off for a clear sign-up error.
- **One account per email is the database's job:** a `UNIQUE` constraint. The service turns SQLSTATE `23505` into a 409, so two sign-ups racing with the same email cannot both succeed (a "check, then insert" could).
- **`GET /api/auth/me` returns `{"user": null}` (200) for visitors.** Asking "who am I?" is not an error. Protected endpoints (checkout and orders, Day 5) answer 401 via `AuthService::requireUser()`.
- **Frontend:** `AuthProvider` asks `/auth/me` once per page load (the cookie is invisible to JavaScript) and shares the user via context. The header shows nothing until that answer arrives, so "Log in" never flashes for a logged-in user. `RequireAuth` sends visitors to `/login?next=…` and back afterwards. It only decides what the browser shows; the API's 401 is the real protection.
- **Safe redirects:** `next` must be a path inside the app (starts with `/`, not `//` or `/\`), so `/login?next=//evil.example` cannot bounce people to another site after logging in.
- **Bug found in testing:** "Log out" on the account page landed on the login page. Clearing the user re-rendered `RequireAuth` (still guarding `/account`), which redirected to login before `navigate('/')` ran. Fix: navigate away first, then log out.
- **Cut:** email verification, password reset, rate limiting on login, "log out everywhere", and a clean-up job for expired session rows (they are ignored when they expire).

## 19. Homepage: the backend decides the layout and each block's content (Day 4)

`GET /api/home` returns the blocks to draw, in order: `{ id, title, component, source }`. The frontend then fetches each block's `source` (`GET /api/blocks/{name}`) and draws it with the named component. Nothing about the blocks, their order, titles, banners or products is in the frontend.

- **Three blocks, two components.** `hero_banners` reads the `banners` table and draws as `hero`. `best_sellers` (most units sold, in stock) and `deals` (smallest price ÷ list price) both draw as `product_rail`. Component (how it looks) and provider (where the data comes from) are separate.
- **"Its own backend service"** (the brief) is implemented as one PHP class per block behind its own endpoint, each running its own query, rather than one deployment per block (#1). The boundary is the same: the frontend only knows a URL per block, so any block could move to a separate service without a frontend change.
- **Each block loads and fails on its own.** All blocks request in parallel (checked in the browser's network timeline). An error shows a small "Could not load …" for that block only. An unknown provider in `home_blocks` is skipped and logged, and an unknown `component` is skipped by the frontend, so a newer backend cannot crash an older frontend.
- **Layout as data:** `home_blocks` (provider, title, position, is_active) and `banners` are tables. Reordering, renaming or switching off a block is an `UPDATE`, not a deploy. Their starting rows ship with migration 004, because every environment needs them (the demo seed only runs on an empty catalogue).
- **Placeholders** of roughly the final height while blocks load, so the page does not jump.
- **Rails scroll sideways** with CSS scroll-snap: a carousel without JavaScript.
- **Banner images** are Steam's public `library_hero.jpg` images (checked to exist).
- **Cut:** an admin screen to edit banners and blocks, personalised blocks ("Recommended for you"), and caching block responses.

## Planned (recorded now, implemented later)

- **Keys are reserved at checkout** (Day 5) with `SELECT ... FOR UPDATE SKIP LOCKED`, so two buyers cannot pay for the last key.
- **Fulfilment is idempotent** (Day 5): the same function is called by the Stripe webhook and by the order page (which asks Stripe if the order is still pending), so a missed or repeated webhook is harmless.
