import react from '@vitejs/plugin-react'
import { defineConfig } from 'vite'

export default defineConfig({
  plugins: [react()],
  server: {
    // The browser always calls /api/... on its own origin. In development Vite
    // forwards those requests to the PHP server; in production vercel.json
    // forwards them to Render. Same code path, no CORS, first-party cookies.
    proxy: { '/api': 'http://localhost:8000' },
  },
})
