# Enterprise Ticket Management System (TMS)

A multi-tenant enterprise SaaS ticketing platform (Laravel 12 API + React 19 frontend), built for isolation, security, and auditability comparable to Jira/Zendesk/Freshdesk.

## Monorepo structure

- `backend/tms-backend/` — Laravel 12 API (PHP 8.4). Multi-tenant via shared schema + PostgreSQL Row-Level Security. Sanctum auth, Spatie RBAC, queues, events/listeners, policies, DTOs, repositories/services.
- `frontend/` — React 19 + TypeScript + Vite UI. **Not yet scaffolded** (Phase 2+).
- `infra/` — Docker, Docker Compose, Nginx, CI/CD.

## Status

**Phase 1 (in progress): backend + database + Docker foundation.** No prior code existed in this repository before this phase — earlier revisions of this file overstated progress that was never actually committed. See [`TODO.md`](TODO.md) for the accurate, current checklist and the phase roadmap.

## Architecture decisions

- **Multi-tenancy**: shared PostgreSQL database, every tenant-owned table carries `tenant_id`, isolation enforced by PostgreSQL Row-Level Security policies plus a request-scoped tenant context middleware. Not database-per-tenant.
- **RBAC**: Spatie `laravel-permission`, tenant-scoped via the package's "teams" feature keyed on `tenant_id`. Roles: Super Admin, Tenant Admin, Manager, Support Agent, Client.
- **Auth**: Laravel Sanctum, 2FA-ready data model, email verification, password reset (Phase 2).
- **Realtime**: Laravel Reverb (planned, Phase 3+).
- **Storage**: S3-compatible (MinIO in dev, S3 in production).

## Local development

Requires Docker with the Compose v2 plugin. See `infra/docker-compose.yml`.

```
cd infra
docker compose up -d
docker compose exec backend php artisan migrate
docker compose exec backend vendor/bin/pest
```

## Documentation

- [`TODO.md`](TODO.md) — phased build roadmap and current checklist.
