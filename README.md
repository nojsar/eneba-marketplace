# Eneba-style marketplace

A digital game-key marketplace in the style of [eneba.com](https://www.eneba.com), built for the Eneba internship assignment.

**Live:** <https://eneba-marketplace-taupe.vercel.app> · API: <https://eneba-marketplace-api-3r9s.onrender.com/api/health>

**Status:** Day 3 of 7: catalogue, store, typo-tolerant search and product pages. The free API server sleeps after 15 idle minutes, so the first request can take up to a minute.

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

In production, the container runs migrations and seeds an empty database on start ([backend/docker/start.sh](backend/docker/start.sh)).
