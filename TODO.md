# Enterprise Ticket Management System (TMS) — Roadmap

Full target spec: multi-tenant enterprise ticketing SaaS on Laravel 12 + React 19, with RLS-based
tenancy, dynamic RBAC, ticket pool/pull workflow, audit trail, notifications, reporting, i18n,
Docker/CI infra — quality bar comparable to Jira/Zendesk/Freshdesk.

This is a multi-phase build. Each phase is scoped to land as a reviewable, tested increment.

## Phase 1 — Backend + Database + Docker foundation (done, pending live verification)

- [x] Git repository initialized.
- [x] Root documentation corrected to reflect actual repo state.
- [x] Monorepo scaffold: `backend/`, `frontend/` (placeholder), `infra/`.
- [x] Laravel 12 bootstrap (`backend/tms-backend/`, PHP 8.4) — Sanctum, Spatie RBAC, queues,
      events/listeners, policies, DTOs, repositories/services structure, Pest, L5-Swagger scaffold.
- [x] Multi-tenancy + PostgreSQL RLS foundation: `tenants`/`users` migrations (UUID PKs), tenant
      context middleware, RLS policies on tenant-owned tables, audited Super Admin bypass path.
- [x] Audit log foundation: `audit_logs` table, `AuditService`, `Auditable` trait/observer, auth
      event listeners (login/logout).
- [x] API foundation: versioned routes (`/api/v1`), consistent JSON response envelope, centralized
      exception handling.
- [x] Docker infra: PHP-FPM 8.4 + Nginx Dockerfiles, `docker-compose.yml` (Postgres, Redis, MinIO,
      Mailpit), `.env.example`.
- [x] Pest baseline: tenant-isolation test, RBAC policy test.
- [x] GitHub Actions CI skeleton (composer install, migrate, pest).
- [ ] **Live verification blocked**: local Docker socket access/Compose plugin unavailable in this
      environment — migrations and the Pest suite have only been statically verified (`php -l`,
      `composer validate`, Pint), never actually run against real PostgreSQL. Run
      `docker compose up -d && docker compose exec backend php artisan migrate && docker compose exec backend vendor/bin/pest`
      (or via CI) at the first opportunity and fix anything that surfaces.

## Phase 2 — Auth flows + RBAC completeness (done, pending live verification)

- [x] Login (tenant-slug-aware pre-auth lookup via an audited RLS bypass, generic error message to
      prevent account/tenant enumeration), logout (explicit Sanctum token revocation + audit).
- [x] Email verification (signed-URL flow, custom route naming to fit `/api/v1`), password reset
      (forgot/reset, tenant-scoped, generic response to prevent enumeration).
- [x] Remember-me (non-expiring token) vs default Sanctum token expiration.
- [x] Rate limiting on login/password-reset/verification (keyed by email+IP, not IP alone).
- [x] 2FA-ready columns on `users` (encrypted secret/recovery codes, `two_factor_confirmed_at`) — no
      verification logic yet, just the storage.
- [x] Full role/permission seeding (`RoleAndPermissionSeeder`): 5 system-wide roles, a
      user/tenant-management permission catalog, `UserPolicy` refactored to permission-based checks,
      `Gate::before` Super Admin bypass.
- [x] Role/permission change audit logging (Spatie attach/detach events → audit trail).
- [ ] **Live verification blocked** — same Docker gap as Phase 1; the full auth + RBAC Pest suite has
      not actually run against Postgres yet.

## Phase 3 — Ticket domain + pool/pull workflow (done, pending live verification)

- [x] Ticket state machine (Pending, InProgress, OnHold, Resolved, Closed):
      `TicketStatusTransitions` enforces the graph at the application layer; the
      `enforce_ticket_immutability` Postgres trigger enforces Resolved read-only /
      Closed fully-immutable a second time at the database layer regardless of code path
      (soft-delete of a Closed ticket is the one allowed exception).
- [x] Ticket pool + atomic pull-to-assign workflow: `TicketPoolService::pull()` uses
      `SELECT ... FOR UPDATE` inside a transaction so two agents racing for the same ticket
      can't both win — the loser gets a clean 409, not a silent double-assignment.
- [x] Domain models: `TicketType`, `Ticket`, `TicketComment` (internal vs. client-visible),
      all tenant-owned and RLS-protected. Clients/Agents are Users with the corresponding
      role, not separate tables (matches how Users/roles are already modeled).
- [x] Permission catalog extended (`tickets.*`, `ticket_types.manage`); an assigned agent can
      always edit their own ticket via ownership, never a blanket permission — one agent
      cannot edit another's ticket by default.
- [x] `first_response_at` tracked (first non-internal staff reply) for Phase 5 SLA reporting.
- [ ] **Live verification blocked** — same Docker gap as Phases 1–2. The state-machine/trigger
      tests, pool-pull tests, and access-control tests have not run against real Postgres yet.
      True concurrent-transaction race testing for pull() also needs a live, multi-connection
      test against the real stack — a single-process Pest run can only prove the logical guard,
      not the actual lock contention.

## Phase 4 — Attachments, notifications, realtime (done, pending live verification)

- [x] Secure file uploads: `FileUploadService` validates real file content (fileinfo `mimetypes:`,
      not just the client extension/header) against an allowlist, generates a random storage key
      per file (never derived from the client filename — sidesteps path traversal / double-extension
      tricks entirely), stores on the private `s3` disk (MinIO in dev). Downloads go through a
      dedicated endpoint that mints a fresh short-lived presigned URL on demand, not a static link
      embedded in list responses.
- [x] `ticket_attachments` is tenant-owned and RLS-protected like every other domain table; its
      policy reuses `TicketPolicy`/`TicketCommentPolicy` rules (viewing an attachment = viewing the
      ticket, uploading = same rule as commenting, including the Closed-ticket block).
- [x] Notifications: a **custom** tenant-owned `notifications` table/model/channel
      (`TenantDatabaseChannel`), not Laravel's stock polymorphic-only notifications table — the spec
      requires notifications to be tenant-owned like everything else, which the stock table has no
      concept of. RLS on this table is scoped to **both** `tenant_id` and `notifiable_id` (via a new
      `app.user_id` session variable set alongside `app.tenant_id`) — a personal inbox, so one user
      in a tenant can never read another's notifications even with full tenant access.
- [x] Three notification classes (assigned, comment added, status changed) dispatched from the
      ticket services/controller, each firing database + mail + broadcast, and each careful not to
      notify whoever just performed the action themselves (e.g. pulling your own ticket from the
      pool doesn't notify you).
- [x] Laravel Reverb installed and wired for realtime: private per-user channel
      (`tenant.{tenantId}.user.{userId}`, see `User::receivesBroadcastNotificationsOn()`), channel
      authorization added to `routes/channels.php`. Found and fixed two bugs in Laravel's own
      broadcasting scaffolding while wiring this up: the default channel-auth closure casts UUIDs to
      `(int)` (always evaluates true — an auth bypass), and `withRouting(channels: ...)` registers
      `/broadcasting/auth` under session-cookie `web` middleware, which can never authenticate this
      API's Sanctum bearer-token clients — replaced with an explicit `withBroadcasting(...,
      ['middleware' => ['api', 'auth:sanctum']])` call.
- [x] `infra/docker-compose.yml` gained a `reverb` service (internal port 8080, host-mapped to 8085
      for a future browser client) alongside the existing MinIO/Mailpit from Phase 1.
- [ ] **Live verification blocked** — same Docker gap as Phases 1–3. Additionally: actual WebSocket
      connectivity to Reverb and S3 presigned URL generation against real MinIO have *no* test
      coverage at all yet (`Storage::fake()` covers the attachment storage logic, but not real S3
      signing; there is no practical way to test a live WebSocket handshake in Pest) — both need
      manual verification against the running stack, not just `vendor/bin/pest`.

## Phase 5 — Search + reporting (done, pending live verification)

- [x] Postgres full-text search: a `search_vector` STORED generated column on `tickets` (subject
      weighted above description), GIN-indexed, matched via `websearch_to_tsquery` (forgiving of
      user-typed quotes/AND/OR/-exclusion, unlike `plainto_tsquery`/`to_tsquery`). Kept in sync by
      Postgres itself on every write — no trigger or application code has to remember to update it.
- [x] Query-building logic extracted into `TicketSearchService` (ownership scoping, filters, search,
      sorting) rather than living in the controller — advanced filters: comma-separated multi-value
      status/priority, `ticket_type_id`, `client_id`, `agent_id`, `unassigned`, `created_from`/
      `created_to` date range, on top of the pagination/sorting already in Phase 3. Relevance
      (`ts_rank`) is the default sort while searching, unless a caller passes an explicit `sort`.
- [x] `TicketReportService`: tenant overview (counts by status/priority, avg resolution time, avg
      first-response time via `EXTRACT(EPOCH FROM ...)` + `AVG(...) FILTER (WHERE ...)`), tickets by
      agent, a Super-Admin-only platform-wide tickets-by-tenant report (via the audited RLS bypass —
      the one deliberately *not* gated by a permission string, since no tenant-level role should ever
      be grantable this), and a daily created-vs-resolved trend series shaped for a future frontend
      chart (Phase 6) — this phase returns chart-ready data, not a rendered chart.
- [x] Explicitly scoped out: actual SLA policies/targets/breach tracking. The spec lists "SLA" both
      as a Phase 5 KPI and as a future-roadmap "SLA engine" — this phase reports the raw metrics
      (avg first response / resolution) an SLA system would be built on top of; the policy/target
      model itself doesn't exist yet and stays future-roadmap scope.
- [ ] **Live verification blocked** — same Docker gap as Phases 1–4. The `avg_resolution_seconds`
      test asserts an exact value from a single controlled fixture (proving the EXTRACT/AVG math is
      right, not just "some number came back") but has never actually run against Postgres.

## Phase 6 — Frontend (React 19)

Split into sub-phases, same reasoning as the backend: the full scope (routing, state, auth UI,
ticket domain UI, notifications, reports, dark mode, accessibility, realtime) is too large for one
reviewable increment.

### Phase 6.1 — Foundation (done)

- [x] Vite + React 19 + TypeScript scaffold in `frontend/`, Tailwind v4 (`@tailwindcss/vite`, CSS-first
      theme via `@theme inline` — no `tailwind.config.ts` needed at this version), path alias `@/*`.
- [x] shadcn/ui foundation built by hand, not via the CLI: the shadcn CLI's own registry (separate
      from npm) timed out in this sandbox, so `components/ui/*` (button, input, label, card, avatar,
      dropdown-menu, separator, dialog, tooltip, skeleton, alert, sonner) were written directly —
      they're just Radix primitives + `cva` + `cn()`, the CLI only ever copies this same source in.
      **Environment note**: `registry.npmjs.org` was unreachable from this sandbox (timed out); all
      `npm install` calls used `registry.npmmirror.com` for this session only (not committed to any
      `.npmrc` — a real dev machine should have normal registry access and doesn't need this).
- [x] React Router v8 (`createBrowserRouter`), TanStack Query, Zustand (`auth-store` persisted to
      localStorage, `theme-store`), React Hook Form + Zod, an axios-based typed API client
      (`src/lib/api-client.ts`) that unwraps the backend's `ApiResponse` envelope and normalizes
      errors into `ApiRequestError`.
- [x] Dark mode (`ThemeProvider`, light/dark/system, persisted), `AppShell` (responsive sidebar —
      desktop persistent panel, mobile `Dialog`-based drawer — header, user menu, theme toggle),
      `ErrorBoundary`, `EmptyState`, `Toaster` (sonner) wired at the app root.
- [x] Auth pages: login (tenant-slug-aware, matching the backend's login contract), forgot-password,
      reset-password (reads `token`/`email`/`tenant` from the URL the backend's reset email now
      links to — see the `ResetPassword::createUrlUsing()` fix above). `ProtectedRoute`/`GuestRoute`
      gate access by auth-store state; nav items are role-filtered client-side as a UX nicety only
      (the API is the real authorization boundary regardless).
- [x] Verified: `tsc -b` clean, `oxlint` clean (one benign shadcn Fast-Refresh warning), production
      `vite build` succeeds, dev server serves the real app (confirmed via curl — see below).
- [ ] **Not verified**: no browser automation tool is available in this environment, so the login
      flow, dark mode toggle, and responsive layout were never visually/interactively exercised —
      only confirmed to typecheck, lint, build, and serve valid HTML/JS. Full E2E verification
      additionally needs the live backend, which is blocked by the same Docker gap as Phases 1–5.
      **Open the app in a real browser and click through login/dark-mode/mobile-nav before trusting
      this beyond "it compiles."**

### Phase 6.2 — Ticket domain UI (next)

- [ ] Ticket list (search/filters/sort from Phase 5, skeleton loading, empty states), detail view,
      create/edit forms, comment thread (internal-note gating), attachment upload/download.
- [ ] Ticket pool UI + pull-to-assign, optimistic UI where it's safe (not on pull — that one has to
      wait for the server's atomic-assignment result, see TicketPoolService).

### Phase 6.3 — Notifications + reports UI, realtime

- [ ] Notification bell/dropdown, mark-as-read, Laravel Echo + Reverb client wiring for live updates.
- [ ] KPI/report dashboards (Phase 5's endpoints) with charts.

## Phase 7 — i18n, CI/CD hardening, documentation

- [ ] English + French locales, architecture allows unlimited languages.
- [ ] Full GitHub Actions pipeline (backend + frontend + deploy).
- [ ] Nginx hardening, security headers/CSP verification.
- [ ] Architecture docs, ER diagram, generated API docs, deployment guide.

## Future roadmap (post-MVP, architecture should not block these)

- [ ] AI ticket classification, SLA engine, Knowledge Base, CSAT, Live Chat, WhatsApp/Slack/Teams
      integrations, custom fields, macros, push notifications.
