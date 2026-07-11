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

### Phase 6.2 — Ticket domain UI (done, pending live verification)

- [x] TanStack Query hooks for tickets/ticket-types/ticket-pool/comments/attachments (`src/hooks/`),
      each with query-key-scoped cache invalidation on mutation.
- [x] Ticket list: debounced search (wired to Phase 5's Postgres full-text search), status/priority
      filters, pagination, skeleton loading, empty states for both "no results" and "load failed".
- [x] Ticket detail: read view + inline edit (shares one `TicketForm` component with create), status
      transitions via `TicketStatusSelect` — its allowed-transitions map is a hand-kept mirror of the
      backend's `TicketStatusTransitions` (only affects which options are *shown*; the backend is
      still the real authority and rejects anything outside its own graph regardless), comment
      thread with internal-note gating (checkbox only shown to staff roles — a UX nicety, the API
      enforces the real boundary), attachment upload/download/delete.
- [x] Ticket pool page with pull-to-assign; a 409 (another agent claimed it first) surfaces as a
      specific "someone else claimed this ticket" toast rather than a generic error, using the
      `ticket_already_assigned` error code from Phase 3.
- [x] Explicitly scoped out: a "reassign to a specific agent" picker (`PATCH /tickets/{id}/assign`
      exists on the backend, but a proper picker needs a users-list endpoint that doesn't exist yet —
      out of scope for a frontend-only pass) and a Ticket Types management page (the API hooks exist
      and back the create-ticket type dropdown already; a dedicated CRUD page for Tenant Admins is a
      small follow-up, not started).
- [ ] **Not verified** — same gap as Phase 6.1: typecheck/lint/build clean and the dev server serves
      every new route (confirmed via curl), but nothing has been opened in a real browser, and no
      live backend means the actual data flows (search results, pulling a ticket, posting a comment)
      have never executed end-to-end.

### Phase 6.3 — Notifications + reports UI, realtime (done, pending live verification)

- [x] `laravel-echo` + `pusher-js` wired to Reverb (`src/lib/echo.ts`), lazily connecting with the
      Sanctum bearer token attached to the `/broadcasting/auth` request. `useRealtimeNotifications`
      subscribes to this user's private channel once at the `AppShell` level (not per-page, so the
      subscription survives client-side navigation) and invalidates the notifications query +
      toasts on every broadcast; disconnected explicitly on logout so a stale connection doesn't
      keep authenticating as the previous user.
- [x] `NotificationBell`: unread badge, dropdown list, mark-one/mark-all read, links straight to the
      relevant ticket. A 60s poll backs up the realtime path for missed/dropped WebSocket events —
      belt and suspenders, not the primary update mechanism.
- [x] KPI/report dashboard (`ReportsPage`) consuming all four Phase 5 endpoints: stat tiles (total
      tickets, avg first response, avg resolution, open count), a status breakdown and a priority
      breakdown as horizontal bar charts, a 30-day created-vs-resolved trend line chart with a
      crosshair+tooltip, a tickets-by-agent table, and (Super Admin only) the platform-wide
      tickets-by-tenant table.
- [x] Charts were built by hand (SVG/CSS), not a charting library — invoked the **dataviz** skill
      before writing any chart code per its trigger rule. Categorical hues for status (identity, 5
      unordered values) and a sequential blue ramp for priority (ordinal: low→urgent) both ran
      through `scripts/validate_palette.js` for both light and dark surfaces before use; chart
      chrome (surface/ink/grid) reuses this app's own shadcn tokens rather than the skill's default
      chrome, so charts match the rest of the UI — only the categorical/sequential hue *values* are
      the skill's validated ones. Caught and fixed one mistake against the skill's own reference
      while building this: the dark-mode sequential ramp initially used palette step 650, one step
      darker than the palette's documented "no darker than step 600" floor for readable ordinal
      steps on a dark surface.
- [x] Explicitly scoped out: a Ticket Types management page and a reassign-to-specific-agent picker
      (same reasons as Phase 6.2 — the latter needs a users-list endpoint that doesn't exist yet).
- [ ] **Not verified** — the deepest version of the same gap as every other Phase 6 sub-phase: no
      browser to actually see the charts render, and realtime specifically has *zero* coverage of
      any kind (not even the static-analysis-adjacent confidence the rest of the frontend has) — a
      WebSocket handshake, channel auth, and a live broadcast round-trip only exist as reviewed code
      right now, never executed once, against a backend that itself has never run either.

## Phase 7 — i18n, CI/CD hardening, documentation (done, pending live verification)

- [x] Backend i18n: `lang/en/*` + `lang/fr/*` (validation, passwords, auth, and a custom `messages`
      file for every domain string), `SetLocale` middleware negotiating `Accept-Language` (quality-
      ordered, falls back to `en`), every controller/exception/central handler message routed through
      `__()` — no hardcoded English string left in any API response. `ResetPassword`'s constant-keyed
      status strings (`Password::INVALID_USER` etc.) map directly to `lang/*/passwords.php` keys, so
      `__($status)` works without a translation table in application code.
- [x] Frontend i18n: `react-i18next`, `src/i18n/locales/{en,fr}.json` (182 keys, full parity verified
      programmatically), a Zustand-persisted `locale-store` (mirrors the existing `theme-store`
      pattern) driving both `t()` calls and an `Accept-Language` header attached to every API request
      (`api-client.ts`) — so the backend's Phase-7 locale negotiation actually gets used, not just the
      UI shell. `LanguageToggle` in the app header, mirroring `ThemeToggle`. Every page/component from
      Phases 6.1–6.3 re-translated; a from-source key-usage scan confirms every `t()` call resolves to
      a real key in both locale files.
- [x] Architecture allows unlimited languages: adding a third locale is "add `lang/xx/*.php` +
      `locales/xx.json` + one line in `SetLocale`'s and `i18n/index.ts`'s supported-locale lists" —
      no code path is hardcoded to exactly two.
- [x] CI/CD: `.github/workflows/frontend-ci.yml` (typecheck, lint, production build), added alongside
      the existing `backend-ci.yml`. `.github/workflows/docker-build.yml` builds both production
      Dockerfiles on every push/PR (validates them without pushing) and publishes to GHCR on `main`
      using the built-in `GITHUB_TOKEN` — no registry secrets to configure.
- [x] New `infra/docker/frontend/Dockerfile` (multi-stage: Node build → nginx serve) and
      `frontend.conf.template` — previously the frontend had no production Docker image at all, only
      `npm run dev`. Added a `frontend` service to `infra/docker-compose.yml` so the production image
      path is exercised locally too, not just in CI.
- [x] Nginx hardening: split the shared `security-headers.conf` so backend and frontend each own their
      own `Content-Security-Policy` (they were about to collide — the frontend's new CSP would have
      been sent as a second header alongside the backend's `default-src 'none'`, which browsers
      resolve by intersection, silently breaking the SPA). Added `server_tokens off`,
      `limit_req_status 429`. Frontend CSP's `connect-src` is templated per-deployment via nginx's
      built-in `envsubst`-over-templates entrypoint feature (`API_ORIGIN`/`WS_ORIGIN`), not hardcoded.
      All three nginx configs (`tms.conf`, `frontend.conf.template`, plus the shared headers file)
      syntax-checked with a real local `nginx -t`, not just eyeballed.
- [x] PHP production-image hardening: `opcache.validate_timestamps` now `0` in the production target
      only (`opcache-production.ini`, loaded after the dev default via conf.d filename ordering) —
      previously the same dev-friendly (and slower) setting shipped in the production image too. Added
      `infra/docker/php/entrypoint.sh` to actually run the `config:cache`/`route:cache`/`event:cache`
      step the Phase-1–6 Dockerfile comments had flagged as deferred to this phase.
- [x] Documentation: `docs/architecture.md` (system overview, tenancy/RLS mechanics, auth/RBAC, ticket
      workflow, notifications/realtime, attachments, audit, i18n, reporting), `docs/er-diagram.md` (a
      Mermaid ER diagram — rendered end-to-end with a real headless-Chromium `mmdc` run to confirm the
      syntax is actually valid, not just eyeballed), `docs/openapi.yaml` (hand-maintained OpenAPI 3.0
      spec covering all 26 route paths/32 operations — deliberately not L5-Swagger-annotation-
      generated, since annotating every controller under time pressure risked silent drift between
      "annotated" and "actual" endpoints; a hand-written spec checked against the real routes/
      FormRequests/API Resources was judged more trustworthy — validated with a real `@redocly/cli
      lint` pass, 0 errors), `docs/deployment.md` (env var reference, release/config-caching process,
      TLS/reverse-proxy expectations, CI/CD summary, and an explicitly documented known limitation:
      Vite bakes `VITE_*` vars at image build time, so the one GHCR frontend image published by CI is
      only valid for the origin it was built against — a real multi-environment deploy needs either a
      rebuild per environment or a future runtime-`env.js` mechanism, not built now since a single
      environment doesn't need it yet).
- [ ] **Live verification blocked** — same Docker/browser gap as every prior phase. Everything above
      was checked as deeply as static tooling allows (full list in the Phase 7 commit message) but
      none of it has run against a live stack: the locale-switching UI has never been clicked, the
      Docker images have never actually been built (no Docker daemon access here, only Dockerfile/
      compose-YAML static review), and the GHCR publish step has never executed.

## Phase 8 — Entity/user management, skill-based pool routing (done, pending live verification)

Closes three gaps found on re-reading the original cahier des charges functional spec against the
actual implementation: (1) no API/UI existed at all for admins to create tenants, create/manage
users, or assign roles — the app only ever worked with seeded demo data; (2) the ticket pool showed
every unassigned ticket to every eligible agent, not filtered by the "compétences" (qualified ticket
types) the spec's "Cas d'Usage Principal" describes as the actual point of the pool; (3) the Ticket
Types page had a nav entry and a fully-built backend CRUD API since Phase 6.2 but no frontend route.

- [x] `agent_skills` pivot table (new migration, RLS-protected like every other tenant-owned table) —
      `User::qualifiedTicketTypes()` / `TicketType::qualifiedAgents()`. A missing row means "not
      qualified", not "qualified for everything" — fails closed, same as the rest of this app.
- [x] Tenant management: `TenantController` (index/store/show/update/destroy), `TenantPolicy`,
      `TenantManagementService`. Platform-wide operations (list all, create, suspend) are hardcoded to
      the Super Admin role rather than a grantable permission — same reasoning as the existing
      tickets-by-tenant report, to close off a permission-grant privilege-escalation path. Caught and
      fixed a real bug while building this: an early draft of `TenantManagementService::find()`
      unconditionally bypassed RLS for every caller, which would have let a Tenant Admin's
      permission-only `tenants.view` check reach *any* tenant, not just their own — fixed to bypass
      only when the actor is actually a Super Admin.
- [x] User management: `UserController`, `UserRoleController`, `AgentSkillController`, `UserPolicy`
      extended with `manageRoles`/`manageSkills`, `UserManagementService`. Tenant-scoped email
      uniqueness (matches the DB's own `(tenant_id, email)` constraint), role assignment kept as a
      separate narrower permission from general user edits, Super-Admin-role grants restricted to
      users with no tenant, self-role-assignment blocked. Spatie's team-scoping is independent of
      RLS (permission pivot tables aren't RLS-protected) — `UserManagementService` explicitly sets and
      restores `PermissionsTeamId` around any cross-tenant role/skill write a Super Admin makes, or
      those writes would land scoped to no tenant at all.
- [x] Skill-based pool routing: `TicketPoolService::poolQuery()`/`pull()` now filter/reject by the
      calling agent's `agent_skills`, unless the agent can also assign tickets to others
      (Manager/Tenant Admin/Super Admin, who need full-pool oversight instead). Enforced twice —
      `TicketPolicy::pull()` blocks it before a transaction opens, `TicketPoolService::pull()`
      re-checks the row-locked ticket — the same belt-and-suspenders pattern as the read-only ticket
      checks. New `TicketNotQualifiedException` (403, `ticket_not_qualified`).
- [x] Frontend: `TicketTypesPage` (the missing route from Phase 6.2 — backend CRUD already existed),
      `UsersPage` (create users, assign roles via checkboxes, assign skills via checkboxes, deactivate),
      `TenantsPage` (Super Admin — create/list/suspend entities), new nav entries gated by role
      (`Manager`+ for Users, `Super Admin` only for Entities). All new UI text translated EN/FR, key
      parity verified (246/246 keys in both locale files).
- [x] Demo seed data extended: three ticket types matching the cahier des charges' own worked example
      (IT Support, Billing, Human Resources) and a demo Support Agent (`agent@acme.tms.local`)
      qualified only for IT Support, so the skill-filtering behavior is visible out of the box instead
      of only after an admin configures it by hand.
- [x] Backend tests: `TenantManagementTest`, `UserManagementTest`, `AgentSkillTest`, plus the existing
      `TicketPoolTest` updated for the new `poolQuery(User $agent)` signature and skill-gating (all
      previously-passing assertions preserved, three new cases added for skill filtering).
- [x] Docs: `docs/openapi.yaml` gained 6 new paths (tenants CRUD, users CRUD, role sync, skill sync;
      32 operations total now) plus `Tenant`/`TenantStatus` schemas and a `skills` field on `User` —
      re-validated with `@redocly/cli lint`, 0 errors. `docs/er-diagram.md` gained the `agent_skills`
      table and relationships, re-rendered with a real `mmdc` run. `docs/architecture.md` gained a
      "Tenant & user management" section and an expanded pool/pull section covering skill routing.
- [ ] **Live verification blocked** — same Docker/browser gap as every prior phase. Static verification
      only: `php -l` on every touched file, Pint, `composer validate`, a full `artisan list` boot
      check, `artisan route:list` confirming all 12 new routes registered, every new/updated Pest test
      correctly collected (fails only on "could not find driver" — no live Postgres here, not a code
      problem) — full list in the Phase 8 commit message. `tsc -b`, `oxlint`, and a production
      `vite build` all pass on the frontend. None of this has been exercised against a live stack or a
      real browser: the create-user/assign-roles/assign-skills flows and the pool's actual filtered
      contents have never been seen rendered, only reviewed as code.

## Future roadmap (post-MVP, architecture should not block these)

- [ ] AI ticket classification, SLA engine, Knowledge Base, CSAT, Live Chat, WhatsApp/Slack/Teams
      integrations, custom fields, macros, push notifications.
