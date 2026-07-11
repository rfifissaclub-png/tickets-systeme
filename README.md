# Enterprise Ticket Management System (TMS)

A multi-tenant enterprise SaaS ticketing platform (Laravel 12 API + React 19 frontend), built for isolation, security, and auditability comparable to Jira/Zendesk/Freshdesk.

## Monorepo structure

- `backend/tms-backend/` — Laravel 12 API (PHP 8.4). Multi-tenant via shared schema + PostgreSQL Row-Level Security. Sanctum auth, Spatie RBAC, queues, events/listeners, policies, DTOs, repositories/services.
- `frontend/` — React 19 + TypeScript + Vite UI. **Not yet scaffolded** (Phase 2+).
- `infra/` — Docker, Docker Compose, Nginx, CI/CD.

## Status

**Phases 1–3 done, pending live verification: backend + database + Docker foundation, auth flows, RBAC, ticket domain + pool/pull workflow.** No prior code existed in this repository before Phase 1 — earlier revisions of this file overstated progress that was never actually committed. See [`TODO.md`](TODO.md) for the accurate, current checklist and the phase roadmap.

Every migration, controller, and test has been statically verified (`php -l`, `composer validate`, Laravel Pint) but **not yet run against a real PostgreSQL database** — local Docker access has been unavailable in the environment these phases were built in. Run the commands below at the first opportunity and fix anything that surfaces before trusting this as "working."

## Architecture decisions

- **Multi-tenancy**: shared PostgreSQL database, every tenant-owned table carries `tenant_id`, isolation enforced by PostgreSQL Row-Level Security policies plus a request-scoped tenant context middleware. Not database-per-tenant.
- **RBAC**: Spatie `laravel-permission`, tenant-scoped via the package's "teams" feature keyed on `tenant_id`. Roles: Super Admin, Tenant Admin, Manager, Support Agent, Client — seeded by `RoleAndPermissionSeeder`, enforced via permission-based policies (`UserPolicy`) plus a `Gate::before` Super Admin bypass.
- **Auth**: Laravel Sanctum bearer tokens. Login is tenant-slug-aware (a `tenant` field disambiguates users sharing an email across tenants); Super Admins log in with no tenant. Email verification (signed URL) and password reset (forgot/reset) are tenant-scoped and return generic responses to avoid account/tenant enumeration. `remember` on login issues a non-expiring token instead of the default TTL. 2FA-ready columns exist on `users` but verification logic isn't implemented yet.
- **Tickets**: `Ticket`/`TicketType`/`TicketComment`, all tenant-owned and RLS-protected. Clients and Agents are `User` records with the corresponding role, not separate tables. Status transitions (Pending → InProgress/OnHold → Resolved → Closed) are validated in `TicketStatusTransitions` and enforced a second time, independent of application code, by the `enforce_ticket_immutability` Postgres trigger — Resolved tickets are read-only, Closed tickets are fully immutable except a soft-delete. The ticket pool (`GET /ticket-pool`) lists unassigned Pending tickets; pulling one (`POST /ticket-pool/{id}/pull`) uses `SELECT ... FOR UPDATE` inside a transaction so two agents racing for the same ticket can't both win.
- **Realtime**: Laravel Reverb (planned, Phase 4+).
- **Storage**: S3-compatible (MinIO in dev, S3 in production).

## Local development

Requires Docker with the Compose v2 plugin. See `infra/docker-compose.yml`.

```
cd infra
docker compose up -d
docker compose exec backend php artisan migrate
docker compose exec backend php artisan db:seed
docker compose exec backend vendor/bin/pest
```

`db:seed` creates roles/permissions plus, outside production, two demo accounts (password `password` for both):
- `super-admin@tms.local` — platform Super Admin, no tenant.
- `admin@acme.tms.local` — Tenant Admin for the demo tenant `acme` (login requires `"tenant": "acme"`).

## Documentation

- [`TODO.md`](TODO.md) — phased build roadmap and current checklist.
