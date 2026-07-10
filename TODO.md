# Enterprise Ticket Management System (TMS) — Roadmap

Full target spec: multi-tenant enterprise ticketing SaaS on Laravel 12 + React 19, with RLS-based
tenancy, dynamic RBAC, ticket pool/pull workflow, audit trail, notifications, reporting, i18n,
Docker/CI infra — quality bar comparable to Jira/Zendesk/Freshdesk.

This is a multi-phase build. Each phase is scoped to land as a reviewable, tested increment.

## Phase 1 — Backend + Database + Docker foundation (current)

- [x] Git repository initialized.
- [x] Root documentation corrected to reflect actual repo state.
- [ ] Monorepo scaffold: `backend/`, `frontend/` (placeholder), `infra/`.
- [ ] Laravel 12 bootstrap (`backend/tms-backend/`, PHP 8.4) — Sanctum, Spatie RBAC, queues,
      events/listeners, policies, DTOs, repositories/services structure, Pest, L5-Swagger scaffold.
- [ ] Multi-tenancy + PostgreSQL RLS foundation: `tenants`/`users` migrations (UUID PKs), tenant
      context middleware, RLS policies on tenant-owned tables, audited Super Admin bypass path.
- [ ] Audit log foundation: `audit_logs` table, `AuditService`, `Auditable` trait/observer, auth
      event listeners (login/logout).
- [ ] API foundation: versioned routes (`/api/v1`), consistent JSON response envelope, centralized
      exception handling.
- [ ] Docker infra: PHP-FPM 8.4 + Nginx Dockerfiles, `docker-compose.yml` (Postgres, Redis, MinIO,
      Mailpit), `.env.example`.
- [ ] Pest baseline: tenant-isolation test, RBAC policy test.
- [ ] GitHub Actions CI skeleton (composer install, migrate, pest).

## Phase 2 — Auth flows + RBAC completeness

- [ ] Email verification, password reset, remember-me, rate limiting.
- [ ] 2FA-ready hooks.
- [ ] Full role/permission seeding (Super Admin, Tenant Admin, Manager, Support Agent, Client).

## Phase 3 — Ticket domain + pool/pull workflow

- [ ] Ticket state machine (Pending, In Progress, On Hold, Resolved, Closed) with transition
      validation; resolved = read-only, closed = immutable.
- [ ] Ticket pool + atomic pull-to-assign workflow (row locking, transaction-safe).
- [ ] Ticket types, clients, agents domain models.

## Phase 4 — Attachments, notifications, realtime

- [ ] Secure file uploads (MIME validation, signed URLs, private storage).
- [ ] Notifications: database + email; Laravel Reverb realtime channel.

## Phase 5 — Search + reporting

- [ ] Postgres full-text search, filters, sorting, pagination.
- [ ] KPI/report endpoints: tickets by tenant/agent, avg resolution, avg first response, SLA.

## Phase 6 — Frontend (React 19)

- [ ] Vite + TS + Tailwind + Shadcn UI scaffold.
- [ ] Router, TanStack Query, Zustand, React Hook Form + Zod, typed API client.
- [ ] Auth pages, protected routing, dashboard shell, dark mode, accessibility, skeleton/empty
      states, error boundaries, optimistic UI.

## Phase 7 — i18n, CI/CD hardening, documentation

- [ ] English + French locales, architecture allows unlimited languages.
- [ ] Full GitHub Actions pipeline (backend + frontend + deploy).
- [ ] Nginx hardening, security headers/CSP verification.
- [ ] Architecture docs, ER diagram, generated API docs, deployment guide.

## Future roadmap (post-MVP, architecture should not block these)

- [ ] AI ticket classification, SLA engine, Knowledge Base, CSAT, Live Chat, WhatsApp/Slack/Teams
      integrations, custom fields, macros, push notifications.
