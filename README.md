# Enterprise Ticket Management System (TMS)

A multi-tenant enterprise SaaS ticketing platform (Laravel 12 API + React 19 frontend), built for isolation, security, and auditability comparable to Jira/Zendesk/Freshdesk.

## Monorepo structure

- `backend/tms-backend/` — Laravel 12 API (PHP 8.4). Multi-tenant via shared schema + PostgreSQL Row-Level Security. Sanctum auth, Spatie RBAC, queues, events/listeners, policies, DTOs, repositories/services, English/French i18n.
- `frontend/` — React 19 + TypeScript + Vite UI. Foundation, ticket domain UI, notifications, reports, and English/French i18n all done.
- `infra/` — Docker (backend + frontend images), Docker Compose, Nginx, CI/CD.
- `docs/` — architecture, ER diagram, OpenAPI spec, deployment guide.

## Status

**All 8 phases done (backend, frontend, i18n, CI/CD, docs, entity/user management) — all pending live verification.** Backend: database + Docker foundation, auth flows, RBAC, ticket domain + pool/pull workflow, attachments + notifications + realtime, search + reporting, English/French i18n, tenant/user/role/agent-skill management. Frontend: Vite/Tailwind/shadcn scaffold, routing, state, typed API client, dark mode, auth pages, ticket list/detail/create/edit, comments, attachments, ticket pool, notification bell + realtime, KPI/report dashboard with charts, English/French i18n with a language switcher, ticket type/user/entity management pages. Infra: Docker images for both apps, three GitHub Actions workflows (backend CI, frontend CI, Docker image build), hardened Nginx configs. No prior code existed in this repository before Phase 1 — earlier revisions of this file overstated progress that was never actually committed. See [`TODO.md`](TODO.md) for the accurate, current checklist and the phase roadmap, and [`docs/`](docs/) for architecture, ER diagram, API spec, and deployment documentation.

Every backend migration/controller/test has been statically verified (`php -l`, `composer validate`, Laravel Pint) but **not yet run against a real PostgreSQL database** — local Docker access has been unavailable in the environment these phases were built in. The frontend typechecks, lints, and builds cleanly, but has never been opened in an actual browser (no browser automation tool available here) — run the commands below at the first opportunity and fix anything that surfaces before trusting either side as "working."

## Architecture decisions

- **Multi-tenancy**: shared PostgreSQL database, every tenant-owned table carries `tenant_id`, isolation enforced by PostgreSQL Row-Level Security policies plus a request-scoped tenant context middleware. Not database-per-tenant.
- **RBAC**: Spatie `laravel-permission`, tenant-scoped via the package's "teams" feature keyed on `tenant_id`. Roles: Super Admin, Tenant Admin, Manager, Support Agent, Client — seeded by `RoleAndPermissionSeeder`, enforced via permission-based policies (`UserPolicy`) plus a `Gate::before` Super Admin bypass.
- **Auth**: Laravel Sanctum bearer tokens. Login is tenant-slug-aware (a `tenant` field disambiguates users sharing an email across tenants); Super Admins log in with no tenant. Email verification (signed URL) and password reset (forgot/reset) are tenant-scoped and return generic responses to avoid account/tenant enumeration. `remember` on login issues a non-expiring token instead of the default TTL. 2FA-ready columns exist on `users` but verification logic isn't implemented yet.
- **Tickets**: `Ticket`/`TicketType`/`TicketComment`, all tenant-owned and RLS-protected. Clients and Agents are `User` records with the corresponding role, not separate tables. Status transitions (Pending → InProgress/OnHold → Resolved → Closed) are validated in `TicketStatusTransitions` and enforced a second time, independent of application code, by the `enforce_ticket_immutability` Postgres trigger — Resolved tickets are read-only, Closed tickets are fully immutable except a soft-delete. The ticket pool (`GET /ticket-pool`) lists unassigned Pending tickets, filtered by the calling agent's `agent_skills` (qualified ticket types) unless they can also assign tickets to others; pulling one (`POST /ticket-pool/{id}/pull`) uses `SELECT ... FOR UPDATE` inside a transaction so two agents racing for the same ticket can't both win.
- **Tenant & user management**: `TenantController`/`UserController`/`UserRoleController`/`AgentSkillController` let a Tenant Admin provision users and assign roles/skills within their own tenant, and a Super Admin do the same across any tenant or provision new tenants outright. Platform-wide operations (list every tenant, create one, suspend one) are hardcoded to the Super Admin role rather than a grantable permission, to close off a permission-grant privilege-escalation path; single-tenant view/update stays permission-based since RLS already confines a Tenant Admin to their own tenant row regardless.
- **Attachments**: `TicketAttachment`, tenant-owned and RLS-protected. `FileUploadService` validates real file content (not just the extension) against an allowlist, stores every file under a randomly generated key — never the client-supplied filename — on the private `s3` disk. Downloads go through `GET /attachments/{id}/download`, which mints a fresh short-lived presigned URL per request rather than exposing a static link.
- **Notifications**: a custom tenant-owned `notifications` table/model (not Laravel's stock polymorphic-only one — this needed to be tenant-scoped like everything else), RLS-scoped to both the tenant *and* the specific recipient via a new `app.user_id` session variable, so notifications are a genuinely private inbox even within one tenant. Ticket assignment, new comments, and status changes each notify via database + email + realtime broadcast, skipping whoever just performed the action.
- **Realtime**: Laravel Reverb, broadcasting on a private `tenant.{tenantId}.user.{userId}` channel per user. Wiring this up surfaced two bugs in Laravel's own scaffolding for UUID-keyed apps: the default channel-auth closure casts ids to `(int)` (silently `0 === 0`, an auth bypass for any user), and the framework's automatic `/broadcasting/auth` route registration uses session-cookie `web` middleware, which can't authenticate this API's Sanctum bearer-token clients — both fixed (`routes/channels.php`, `bootstrap/app.php`).
- **Search + reporting**: `search` on `GET /tickets` runs Postgres full-text search against a `search_vector` STORED generated column (subject weighted above description, GIN-indexed, matched via `websearch_to_tsquery` so quotes/AND/OR/-exclusion in a user-typed query work) — kept in sync by Postgres itself, no trigger or job needed. `TicketReportService` provides a tenant KPI overview, tickets-by-agent, a Super-Admin-only cross-tenant tickets-by-tenant report, and a daily trend series shaped for a future chart. Deliberately out of scope here: actual SLA policies/targets/breach tracking — this reports the raw metrics (avg first response/resolution) an SLA engine would consume, not the engine itself.
- **Storage**: S3-compatible (MinIO in dev, S3 in production).
- **Frontend**: React 19 + TypeScript + Vite, Tailwind v4, shadcn/ui (hand-built from Radix primitives — see Phase 6.1 in `TODO.md` for why), React Router v8, TanStack Query, Zustand, React Hook Form + Zod. `src/lib/api-client.ts` is an axios instance that unwraps the backend's `ApiResponse` envelope and attaches the Sanctum bearer token from the persisted auth store automatically. Ticket list/detail/create/edit, the comment thread (internal-note gating mirrors the backend's `tickets.viewInternal` permission as a UX nicety only), attachments, and the ticket pool all live under `src/pages/tickets/`; `TicketStatusSelect` keeps a hand-written mirror of the backend's status transition graph so the UI only *offers* legal moves — the backend remains the actual authority.
- **Frontend realtime + notifications**: `src/lib/echo.ts` connects `laravel-echo`/`pusher-js` to Reverb with the Sanctum bearer token, subscribed once at the app shell level so it survives client-side navigation and is explicitly disconnected on logout. `NotificationBell` shows an unread badge and dropdown backed by a 60s poll as a fallback for missed WebSocket events, not the primary path.
- **Frontend charts**: hand-built SVG/CSS (no charting library) under `src/components/charts/`, following the repo's `dataviz` skill — categorical hues (ticket status) and a sequential ramp (ticket priority, genuinely ordinal low→urgent) are both run through the skill's `scripts/validate_palette.js` against light *and* dark surfaces before use; chart chrome reuses this app's own shadcn tokens so charts match the rest of the UI.
- **i18n**: English + French today, architecture supports unlimited languages. Backend: `lang/{en,fr}/*.php` + a locale-negotiating `SetLocale` middleware (`Accept-Language` header, quality-ordered, falls back to `en`) — every API response message goes through `__()`. Frontend: `react-i18next` + a Zustand-persisted locale store (mirrors the existing theme-store pattern) that also sets `Accept-Language` on every outgoing API request, so a French UI gets French validation errors back too, not just a French shell around English error text.

## Local development

### Backend

Requires Docker with the Compose v2 plugin. See `infra/docker-compose.yml`.

```
cd infra
docker compose up -d
docker compose exec backend php artisan migrate
docker compose exec backend php artisan db:seed
docker compose exec backend vendor/bin/pest
```

`db:seed` creates roles/permissions plus, outside production, three demo accounts (password `password` for all) and three demo ticket types (IT Support, Billing, Human Resources) on the `acme` tenant:
- `super-admin@tms.local` — platform Super Admin, no tenant.
- `admin@acme.tms.local` — Tenant Admin for the demo tenant `acme` (login requires `"tenant": "acme"`).
- `agent@acme.tms.local` — Support Agent for `acme`, qualified only for IT Support — demonstrates the ticket pool's skill-based filtering out of the box.

### Frontend

```
cd frontend
cp .env.example .env
npm install
npm run dev
```

Requires the backend running (see above) at the URL in `VITE_API_URL` (`.env.example` defaults to `http://localhost:8080/api/v1`, matching the Docker Compose nginx port). `npm run build` produces a production bundle; `npm run lint` runs oxlint; `npx tsc -b` typechecks.

## Documentation

- [`TODO.md`](TODO.md) — phased build roadmap and current checklist.
- [`docs/architecture.md`](docs/architecture.md) — system design: tenancy/RLS, auth/RBAC, ticket workflow, notifications/realtime, attachments, audit, i18n, reporting.
- [`docs/er-diagram.md`](docs/er-diagram.md) — entity-relationship diagram.
- [`docs/openapi.yaml`](docs/openapi.yaml) — OpenAPI 3.0 spec for every `/api/v1/*` endpoint.
- [`docs/deployment.md`](docs/deployment.md) — env vars, release process, TLS/reverse-proxy expectations, CI/CD.
