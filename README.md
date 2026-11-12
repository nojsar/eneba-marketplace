# Eneba-style marketplace

A digital game-key marketplace in the style of [eneba.com](https://www.eneba.com), built for the Eneba internship assignment.

**Live:** <https://eneba-marketplace-taupe.vercel.app> · API: <https://eneba-marketplace-api-3r9s.onrender.com/api/health>

**Status:** Day 4 of 7: catalogue, store, typo-tolerant search, product pages, accounts and the block-based homepage. The free API server sleeps after 15 idle minutes, so the first request can take up to a minute.

## Architecture

```
Browser ──► Vercel (React app + /api forwarding) ──► Render (PHP API, Docker) ──► Neon (PostgreSQL)
                                                          ▲
                                         Stripe webhook ──┘   (test mode)
```

The browser only talks to the Vercel domain; `/api/*` is forwarded to Render. Why: [docs/DECISIONS.md](docs/DECISIONS.md#3-the-browser-only-ever-talks-to-one-origin-day-1).

| Folder | What | Stack |
|---|---|---|
| [frontend/](frontend) | Single-page app | React 19, TypeScript, Vite, React Router, CSS Modules |
| [backend/](backend) | REST API | PHP 8.3, no framework, PDO |
| [docs/](docs) | Decisions log, walkthrough, AI chat logs | Markdown |

## Run locally

Requirements: PHP 8.3 with `pdo_pgsql`, Composer, Node 24. No Docker or local Postgres; development uses a Neon `dev` branch.

```bash
cd backend
composer install
cp .env.example .env     # then paste your Neon dev-branch URL into .env
composer migrate         # create or update the tables
composer seed            # demo data (composer seed -- --fresh wipes and reseeds)
composer serve           # API on http://localhost:8000
```

```bash
cd frontend
npm install
npm run dev              # app on http://localhost:5173, /api forwarded to :8000
```

Check: <http://localhost:5173> shows "API up · database up".

## API

| Method | Path | Description |
|---|---|---|
| GET | `/api/health` | API status and database reachability (503 if the database is down) |
| GET | `/api/products` | Store: one page of products plus filter counts. Query: `q` (typos allowed), `platform` and `region` (comma-separated slugs), `min_price` / `max_price` (euros), `sort` (`relevance` when searching, `bestselling`, `price_asc`, `price_desc`, `newest`), `page` |
| GET | `/api/products/{slug}` | Product page: details, every seller's offer with stock, other platforms/regions (404 if unknown) |
| GET | `/api/search/suggestions?q=` | Up to 6 best matches for the header search (needs 2+ letters) |
| GET | `/api/home` | Homepage layout: blocks in order, each with its component and content endpoint |
| GET | `/api/blocks/{name}` | One block's content: `hero_banners`, `best_sellers`, `deals` |
| POST | `/api/auth/register` | `{email, password}` → creates the account and logs in (sets the session cookie) |
| POST | `/api/auth/login` | `{email, password}` → sets the session cookie; 401 on wrong credentials |
| POST | `/api/auth/logout` | Deletes the session and the cookie |
| GET | `/api/auth/me` | The logged-in user, or `{"user": null}` |

POST bodies must be JSON (`Content-Type: application/json`); anything else gets a 415.

In production, the container runs migrations and seeds an empty database on start ([backend/docker/start.sh](backend/docker/start.sh)).
