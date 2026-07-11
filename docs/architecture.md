# Architecture

## System overview

TMS is a multi-tenant enterprise ticketing SaaS: a Laravel 12 (PHP 8.4) JSON
API backend and a React 19 single-page frontend, talking over a Sanctum
bearer-token API plus a WebSocket channel for realtime notifications.

```
┌─────────────┐        HTTPS (bearer token)        ┌──────────────────────┐
│  React SPA  │ ──────────────────────────────────▶ │  Laravel API (v1)    │
│  (Vite)     │ ◀────────────────────────────────── │  behind Nginx        │
└─────────────┘        WSS (Reverb / Pusher proto)   └──────────┬───────────┘
       │                                                        │
       │                                              ┌─────────┴─────────┐
       │                                              │                   │
       ▼                                              ▼                   ▼
 Browser-side                                   PostgreSQL 16        Redis 7
 localStorage                                (RLS-isolated data)   (cache, queue,
 (auth token,                                                       Reverb pub/sub)
  theme, locale)                                       │
                                                         ▼
                                                 S3-compatible storage
                                                 (attachments; MinIO in dev)
```

Both apps are independently deployable (separate origins, separate Docker
images, separate CI workflows) and communicate only over the versioned JSON
API — there is no server-rendered coupling between them.

## Multi-tenancy: shared schema + PostgreSQL Row-Level Security

Every tenant's data lives in the same tables as every other tenant's — there
is one Postgres database, not one per tenant. Isolation is enforced by
**Row-Level Security (RLS)** policies at the database engine level, not just
by `WHERE tenant_id = ?` clauses in application code (see
`database/migrations/0001_01_01_000006_enable_row_level_security.php` and
`..._000014_enable_row_level_security_for_attachments_and_notifications.php`).

Two Postgres session variables, set once per request, drive every policy:

- `app.tenant_id` — the authenticated user's tenant. `NULL` for Super Admins
  and for requests before tenant resolution.
- `app.bypass_rls` — `'on'` only inside an explicitly authorized, audited
  `TenantContext::bypass()` call (e.g. a Super Admin's platform-wide report).
  Application code never sets this implicitly.

Because Postgres evaluates `NULL = NULL` as unknown rather than true, a
session with no tenant bound and no active bypass matches zero rows on every
RLS-protected table — isolation **fails closed** by default, not open.

`ALTER TABLE ... FORCE ROW LEVEL SECURITY` is applied to every tenant-owned
table. This is required because the app connects as the same Postgres role
that owns the tables (having run the migrations); without `FORCE`, Postgres
RLS silently does not apply to a table's owning role at all.

`App\Services\Tenancy\TenantContext` is the single source of truth for
binding: `App\Http\Middleware\ResolveTenantContext` calls it once per
authenticated request, right after Sanctum resolves the user, before any
tenant-scoped query can run.

## Authentication & authorization

- **AuthN**: Laravel Sanctum personal access tokens (not sessions/cookies —
  this is a pure API, consumed by a separate-origin SPA). `POST /auth/login`
  accepts an optional `tenant` slug; omitting it targets a platform Super
  Admin. The same generic "invalid credentials" error covers wrong tenant,
  wrong email, and wrong password, so the endpoint can't be used to enumerate
  accounts.
- **AuthZ (RBAC)**: `spatie/laravel-permission`, configured with its
  **teams** feature using `tenant_id` as the team foreign key. Role
  *assignments* are tenant-scoped (a user's "Manager" role only applies
  within their tenant); role *definitions* for the five fixed roles
  (`App\Enums\RoleName`: Super Admin, Tenant Admin, Manager, Support Agent,
  Client) are system-wide (`tenant_id IS NULL` on the role row), visible to
  every tenant rather than duplicated per-tenant.
- Every mutating endpoint additionally goes through a Laravel Policy
  (`app/Policies`) — permissions gate broad capability ("can view any
  ticket"), policies gate the specific instance ("can view *this* ticket").

## Tenant & user management

Entities (tenants) and their users are managed through a dedicated admin
surface (`TenantController`, `UserController`, `UserRoleController`,
`AgentSkillController`) rather than only ever existing via database seeding
— a Tenant Admin can provision agents/clients/other admins and configure
their roles and skills entirely through the API; a Super Admin can do the
same across any tenant, plus provision new tenants outright.

- **Platform-wide operations are hardcoded to the Super Admin role, not a
  grantable permission.** Listing every tenant, creating a new one, and
  suspending one are checked directly against `RoleName::SuperAdmin` in
  `TenantPolicy`, the same reasoning as `ReportController::byTenant()`: a
  `tenants.manage`-shaped permission could in principle be granted to a
  Tenant Admin role later, which would let a Tenant Admin see or create
  *other* tenants — a permission grant must never be able to cause that.
  Viewing/updating a single tenant stays permission-based (`tenants.view` /
  `tenants.update`) because PostgreSQL RLS already confines a Tenant Admin's
  own session to their own tenant row regardless of what the permission
  grants — there's no cross-tenant exposure to guard against there.
- **`TenantManagementService` / `UserManagementService`** hold this
  bypass-aware logic so controllers stay thin. A Super Admin's session is
  never bound to any tenant (see `ResolveTenantContext`), so every one of
  their cross-tenant reads/writes runs inside an explicit, audited
  `TenantContext::bypass()` call — but only when the actor actually *is* a
  Super Admin. Every service method takes the acting `User` and checks this
  per-call; bypassing unconditionally would silently defeat the RLS
  confinement a Tenant Admin's permission checks rely on (a real bug caught
  and fixed during Phase 8 — see `TenantManagementService`'s own docblock).
- **Role assignment is a separate, narrower permission from general user
  edits** (`users.manageRoles` vs. `users.update`) — granting someone the
  ability to fix a colleague's typo'd name shouldn't also grant them the
  ability to make that colleague a Tenant Admin. Self-role-assignment is
  blocked outright (`UserPolicy::manageRoles()`). Only a Super Admin may
  grant the Super Admin role, and only to a user with no tenant — enforced
  both in the FormRequest (which roles the *actor* may even mention) and in
  `UserManagementService` (which tenant the *target* ends up in), since the
  target's tenant isn't always known at validation time.
- **Spatie's team-scoping is independent of RLS** — the permission pivot
  tables (`model_has_roles`, etc.) aren't RLS-protected at all, they rely
  entirely on `PermissionRegistrar::setPermissionsTeamId()`. A Tenant
  Admin's own bound request already sets this correctly; a Super Admin's
  never is (no tenant of their own), so `UserManagementService` explicitly
  sets and restores it around any role/skill write made on a Super Admin's
  behalf — otherwise the pivot row would be written scoped to no tenant at
  all instead of the target user's actual tenant.
- **Email uniqueness is enforced per-tenant, not globally** (matching the
  `users` table's own `(tenant_id, email)` unique index plus a partial
  index for `tenant_id IS NULL`): two different tenants may each
  independently have a user with the same email address; two users in the
  *same* tenant may not.

## Ticket domain

### Status workflow

`App\Services\Tickets\TicketStatusTransitions` is the single source of truth
for the status graph:

```
pending ──▶ in_progress ──▶ on_hold
              │      ▲          │
              ▼      └──────────┘
           resolved ──▶ closed
              │
              └──▶ in_progress (reopen)
```

This is enforced twice, independently:

1. **Application layer** — `TicketStatusTransitions::assertAllowed()`,
   called from `TicketService::transitionStatus()`, throws
   `InvalidTicketTransitionException` (→ HTTP 422) on an illegal move.
2. **Database layer** — the `enforce_ticket_immutability` trigger function
   (`tickets_enforce_immutability` trigger, BEFORE UPDATE) independently
   rejects field changes on a `closed` ticket and content changes on a
   `resolved` ticket, regardless of which code path issued the `UPDATE`. A
   soft-delete (`deleted_at`) is exempted so closed tickets can still be
   archived.

The frontend's `TicketStatusSelect` keeps a hand-written mirror of this graph
purely to decide which options to *offer* in the UI — it is not authoritative
and the backend re-validates independently.

### Pool / pull assignment

Unassigned tickets (`agent_id IS NULL`) sit in a shared pool
(`GET /ticket-pool`). Any eligible agent can claim one
(`POST /ticket-pool/{ticket}/pull`); `TicketPoolService::pull()` runs the
claim inside a transaction using `SELECT ... FOR UPDATE` on the ticket row,
so under concurrent pulls exactly one caller succeeds and the rest receive
`409 ticket_already_assigned` — there is no larger window where two agents
could both believe they'd claimed the same ticket.

**Skill-based routing**: a front-line agent (someone who can't also assign
tickets to *other* people — i.e. lacks `tickets.assign`) only ever sees pool
tickets whose type they've been declared qualified for, via the
`agent_skills` pivot table (`User::qualifiedTicketTypes()`,
`PATCH /users/{user}/skills`). A Manager/Tenant Admin/Super Admin sees the
full, unfiltered pool for oversight instead. No skills configured means an
empty pool for that agent, not "everything" — the same fail-closed default
every other RLS/permission boundary in this app uses. Enforced twice:
`TicketPolicy::pull()` blocks an unqualified claim before a transaction even
opens, and `TicketPoolService::pull()` re-checks it again against the
row-locked ticket — the same belt-and-suspenders pattern as the read-only
status checks elsewhere in this file.

### Full-text search

`tickets.search_vector` is a Postgres `GENERATED ALWAYS AS (...) STORED`
`tsvector` column (subject weighted above description), backed by a GIN
index. `TicketSearchService` queries it with `websearch_to_tsquery`, which
tolerates user-typed syntax (quotes, `AND`/`OR`, `-exclude`) instead of
throwing on it the way `to_tsquery` would. When a search term is present and
no explicit `sort` is requested, results are ordered by `ts_rank` instead of
recency.

## Notifications & realtime

Notifications use a **custom** `notifications` table, not Laravel's stock
polymorphic one — the built-in table/channel has no `tenant_id` concept and
can't be RLS-protected the way every other table in this system is. See
`App\Notifications\Channels\TenantDatabaseChannel` and the
`0001_01_01_000013_create_notifications_table.php` /
`..._000014_enable_row_level_security_for_attachments_and_notifications.php`
migrations.

Realtime delivery is Laravel Reverb (self-hosted, Pusher-protocol-compatible)
over a private channel scoped to `tenant.{tenantId}.user.{userId}` — not
Laravel's default per-user channel naming, which would leak across tenants.
The frontend subscribes once, in `AppShell` (survives client-side
navigation), and falls back to a 60-second poll (`NotificationBell`) in case
a WebSocket event is missed.

## File attachments

Uploads go to S3-compatible object storage (MinIO in local Docker, any S3 API
in production) via `App\Services\Attachments\FileUploadService`. MIME type is
verified against the actual file content (`fileinfo`), not the client-
supplied `Content-Type` header or filename extension. The stored object key
is randomly generated, never derived from the user-supplied filename (no
path traversal surface). Downloads are served via short-lived presigned URLs
generated fresh per request (`GET /attachments/{id}/download`) — never a
long-lived or embedded link.

## Audit trail

`audit_logs` is an append-only table (no `updated_at`, no soft deletes)
capturing actor, action, subject (polymorphic `auditable_type`/`id`),
before/after values (`jsonb`), IP, and user agent. Model changes are captured
via an `Auditable` trait/observer; auth events (login/logout, password reset,
email verification) are captured via dedicated listeners on Laravel's auth
events, since those don't correspond to a model CRUD operation.

## Internationalization

- **Backend**: `App\Http\Middleware\SetLocale` negotiates the request's
  locale from the `Accept-Language` header (quality-value ordered,
  `en`/`fr` supported, falling back to `en`). Every validation message,
  domain exception, and controller-issued success/error message goes through
  Laravel's `__()` translator against `lang/{en,fr}/*.php` — nothing
  user-facing is a hardcoded English string.
- **Frontend**: `react-i18next`, with a Zustand-persisted locale preference
  (`useLocaleStore`, mirroring the existing theme-preference store) driving
  both the UI's `t()` calls and an `Accept-Language` header attached to every
  API request — so a French-speaking user gets French validation errors back
  from the API too, not just a French UI shell around English error text.

## Reporting

`App\Services\Reports\TicketReportService` computes tenant-scoped KPIs
(ticket counts by status/priority, average first-response/resolution time in
seconds via Postgres `EXTRACT(EPOCH FROM ...)`), per-agent breakdowns, and a
daily created-vs-resolved trend series. The platform-wide "tickets by tenant"
report is Super Admin only, checked directly against the role rather than a
grantable permission string, and always runs inside an audited
`TenantContext::bypass()` since it is inherently cross-tenant. These are raw
metrics an SLA system would sit on top of — SLA policies/targets/breach
tracking are explicitly not implemented (see the service's own doc comment).

## Data model

See [`er-diagram.md`](er-diagram.md) for the full entity-relationship diagram.

## API contract

See [`openapi.yaml`](openapi.yaml) for the complete OpenAPI 3.0 specification
of every `/api/v1/*` endpoint, hand-maintained against the actual routes,
form requests, and API resources (not generator-annotation-derived, so there
is no drift between "annotated" and "actual" endpoints).

## Deployment topology

See [`deployment.md`](deployment.md).
