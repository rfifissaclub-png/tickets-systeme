# TMS Frontend

React 19 + TypeScript + Vite UI for the Enterprise Ticket Management System. See the [repo root README](../README.md) for architecture and the [roadmap](../TODO.md) for current status.

## Stack

- **Build**: Vite, TypeScript (`erasableSyntaxOnly` — no constructor parameter properties or enums that emit runtime code)
- **Styling**: Tailwind v4 (CSS-first theme, no `tailwind.config.ts`), shadcn/ui components hand-built from Radix primitives in `src/components/ui/`
- **Routing**: React Router v8 (`createBrowserRouter`)
- **Server state**: TanStack Query
- **Client state**: Zustand (`src/stores/`), persisted to localStorage
- **Forms**: React Hook Form + Zod
- **API**: `src/lib/api-client.ts` — axios instance that unwraps the backend's `ApiResponse` envelope and attaches the Sanctum bearer token automatically

## Commands

```
npm run dev      # dev server (http://localhost:5173)
npm run build    # typecheck (tsc -b) + production build
npm run lint     # oxlint
npm run preview  # preview a production build locally
```

Requires the backend running — see the root README's "Local development" section — and `.env` copied from `.env.example` (`VITE_API_URL` must point at it).
