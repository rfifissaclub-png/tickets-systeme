import path from 'node:path'
// vitest/config's defineConfig is a strict superset of vite's own (adds the
// `test` key) — one config file, no duplicated plugins/aliases between a
// vite.config.ts and a separate vitest.config.ts.
import { defineConfig } from 'vitest/config'
import react from '@vitejs/plugin-react'
import tailwindcss from '@tailwindcss/vite'

// https://vite.dev/config/
export default defineConfig({
  plugins: [react(), tailwindcss()],
  resolve: {
    alias: {
      '@': path.resolve(__dirname, './src'),
    },
  },
  server: {
    port: 5173,
    host: true,
  },
  test: {
    environment: 'jsdom',
    globals: true,
    setupFiles: ['./vitest.setup.ts'],
    // e2e/ is Playwright's own suite (a different test runner, run via
    // `npm run test:e2e`) — vitest must never try to collect it.
    exclude: ['e2e/**', 'node_modules/**'],
    // The default `forks` pool (child_process.fork) hangs indefinitely in
    // at least one sandboxed dev environment this project has been
    // developed in ("Timeout waiting for worker to respond", every file,
    // every run) — worker_threads doesn't share that constraint and is
    // exercised the same way in every environment (no OS-level process
    // creation), so it's the safer default rather than something to
    // special-case per machine.
    pool: 'threads',
  },
})
