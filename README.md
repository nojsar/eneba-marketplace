# Eneba-style marketplace

A digital game-key marketplace in the style of [eneba.com](https://www.eneba.com), built for the Eneba internship assignment.

**Live:** <https://eneba-marketplace-taupe.vercel.app> · API: <https://eneba-marketplace-api-3r9s.onrender.com/api/health>

**Status:** Day 1 of 7, hosting skeleton. The free API server sleeps after 15 idle minutes, so the first request can take up to a minute.

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
