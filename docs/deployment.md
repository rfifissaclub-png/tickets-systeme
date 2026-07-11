# Deployment Guide

## Local development (Docker Compose)

```
cd infra
cp ../backend/tms-backend/.env.example ../backend/tms-backend/.env
docker compose up -d
docker compose exec backend php artisan key:generate
docker compose exec backend php artisan migrate
docker compose exec backend php artisan db:seed
```

Services and their host ports (see `infra/docker-compose.yml`):

| Service | Host port | Purpose |
|---|---|---|
| `nginx` | 8080 | Backend API entrypoint |
| `frontend` | 8081 | Production-style SPA build, served by its own nginx |
| `reverb` | 8085 | WebSocket server (browser-facing) |
| `postgres` | 5432 | Primary database |
| `redis` | 6379 | Cache, queue, Reverb pub/sub |
| `minio` | 9000 (API), 9001 (console) | S3-compatible object storage, dev only |
| `mailpit` | 8025 (UI), 1025 (SMTP) | Catches outgoing email in dev |

For day-to-day frontend work, running `npm run dev` directly on the host
(against the same `backend`/`nginx` stack) is faster than rebuilding the
`frontend` container on every change — see `frontend/README.md`. The
`frontend` Compose service exists so the production Docker image path is
exercised locally too, not just in CI.

`db:seed` creates the RBAC roles/permissions plus, outside production, two
demo accounts (password `password`): `super-admin@tms.local` (platform Super
Admin) and `admin@acme.tms.local` (Tenant Admin for demo tenant `acme`).

## Building production images

```
docker build -f infra/docker/php/Dockerfile --target production -t tms-backend .
docker build -f infra/docker/frontend/Dockerfile --target production \
  --build-arg VITE_API_URL=https://api.example.com/api/v1 \
  --build-arg VITE_APP_URL=https://api.example.com \
  --build-arg VITE_REVERB_APP_KEY=<your-reverb-key> \
  --build-arg VITE_REVERB_HOST=api.example.com \
  --build-arg VITE_REVERB_PORT=443 \
  --build-arg VITE_REVERB_SCHEME=https \
  -t tms-frontend .
```

`.github/workflows/docker-build.yml` does this automatically on every push
to `main`, publishing both images to `ghcr.io/<repo>/backend` and
`ghcr.io/<repo>/frontend` using the built-in `GITHUB_TOKEN` — no registry
secrets need to be configured. Pull requests only build (never push), so a
broken Dockerfile fails CI without publishing anything.

### Known limitation: frontend build-time configuration

Vite inlines every `VITE_*` variable into the static JS bundle at build
time — there is no server-side process in a static SPA deployment to read
runtime environment variables from later. This means:

- The image built by `docker-build.yml` is baked for **one** backend origin
  (the placeholder `api.tms.example.com` values in that workflow).
- Deploying to a real domain means rebuilding the frontend image with the
  real `--build-arg` values for that environment (or running a separate
  build per environment — staging, production — each with its own args).
- A future improvement, if multiple environments need to share one built
  image, is a small `env.js` written by a container entrypoint at
  *container* start (read via `<script src="/env.js">` before the app
  bundle loads) instead of `VITE_*` build args — deliberately not built now
  since a single environment doesn't need it yet, and it would be
  unverified, speculative infrastructure.

## Environment variables (backend)

See `backend/tms-backend/.env.example` for the full, current list. Grouped
here by concern:

| Concern | Variables |
|---|---|
| App identity | `APP_NAME`, `APP_ENV`, `APP_KEY` (generate via `artisan key:generate`, never commit), `APP_DEBUG` (**must** be `false` outside local/staging — leaks stack traces), `APP_URL` |
| Locale | `APP_LOCALE`, `APP_FALLBACK_LOCALE` — `en`/`fr` supported today (see `docs/architecture.md#internationalization`) |
| Database | `DB_CONNECTION=pgsql`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` |
| Cache / queue / session | `CACHE_STORE=redis`, `QUEUE_CONNECTION=redis`, `SESSION_DRIVER=redis` (session store is unused by the API itself — Sanctum uses bearer tokens, not cookies — but Laravel still needs a configured driver), `REDIS_HOST`, `REDIS_PORT`, `REDIS_PASSWORD`, `REDIS_CLIENT=predis` (no `phpredis` PECL extension in the image; predis avoids that build step) |
| Realtime | `BROADCAST_CONNECTION=reverb`, `REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET` (generate real values in production — the `.env.example` values are dev-only), `REVERB_HOST`/`REVERB_PORT`/`REVERB_SCHEME` (server-to-server, internal), `REVERB_SERVER_HOST`/`REVERB_SERVER_PORT` (what the Reverb process itself binds) |
| File storage | `FILESYSTEM_DISK=s3`, `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION`, `AWS_BUCKET`, `AWS_ENDPOINT` (point at MinIO in dev, a real S3-compatible endpoint in production), `AWS_USE_PATH_STYLE_ENDPOINT` |
| Mail | `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` — points at Mailpit in dev; use a real SMTP provider or `MAIL_MAILER=ses` in production |
| API docs | `L5_SWAGGER_CONST_HOST` — should match the real API base URL in each environment |

**Secrets** (`APP_KEY`, `DB_PASSWORD`, `REVERB_APP_SECRET`,
`AWS_SECRET_ACCESS_KEY`, `MAIL_PASSWORD`) must come from your platform's
secret store (e.g. environment injection from a secrets manager) — never
committed, and never baked into a Docker image layer.

## Release process (config/route/event caching)

The production PHP image (`infra/docker/php/Dockerfile`, `production`
target) deliberately does **not** run `artisan config:cache` /
`route:cache` / `event:cache` at build time: that stage has no real
`APP_KEY`/DB credentials available, so anything cached then would bake in
wrong values. Instead, `infra/docker/php/entrypoint.sh` runs those three
commands every time the container starts, after the platform has injected
real environment variables, then execs the original command (`php-fpm`, or
`php artisan queue:work` / `php artisan reverb:start` for the worker
containers).

This is simple and correct for the current single-image-multiple-container
shape, but it does mean multiple replicas of the same image starting
simultaneously each redundantly rebuild the same cache files (harmless —
Laravel's cache commands are idempotent, just slightly wasteful). If/when
this deploys at a scale where that matters, split it into a dedicated
one-shot "release" job (migrate + cache) that runs once before the app
containers start, separate from the app containers' own entrypoint —
standard on platforms like ECS/Kubernetes/Heroku. Not built now since
premature for the current scale.

`opcache.validate_timestamps` is `0` in the production image only (see
`infra/docker/php/opcache-production.ini`) — code is baked into the image
there, not bind-mounted, so the per-request mtime check PHP would otherwise
do on every file is pure overhead. The `development` target
keeps `validate_timestamps=1` so bind-mounted source edits are picked up
without a container restart.

## Migrations

Run `php artisan migrate --force` against the target database before (or as
part of) a deploy. `--force` is required outside `local`/`testing`
environments — Laravel refuses to run migrations in production without it,
as a guard against accidental destructive schema changes.

## Reverse proxy / TLS

Both `infra/docker/nginx/Dockerfile` (backend) and
`infra/docker/frontend/Dockerfile` (SPA) listen on plain HTTP internally and
expect a TLS-terminating load balancer or ingress in front in any real
deployment — they are not designed to hold TLS certificates themselves. The
`Strict-Transport-Security` header they send is safe to send unconditionally
(see `infra/docker/nginx/security-headers.conf`): browsers only start
enforcing it once they've actually seen it over HTTPS, so it does nothing
over the internal plain-HTTP hop and correctly protects the public HTTPS
endpoint.

The frontend's nginx CSP (`infra/docker/frontend/frontend.conf.template`) is
templated: `API_ORIGIN`/`WS_ORIGIN` environment variables are substituted at
*container* start (nginx's own `envsubst`-over-templates entrypoint feature,
not custom scripting) so the same image's CSP can point at the right backend
per environment without a rebuild — unlike the `VITE_*` build args above,
which do require one.

## CI/CD

- **`.github/workflows/backend-ci.yml`** — Pest test suite + Pint style
  check + `composer validate`, against a real Postgres/Redis service
  container, on every push/PR touching `backend/tms-backend/**`.
- **`.github/workflows/frontend-ci.yml`** — `tsc -b`, `oxlint`, and a
  production `vite build`, on every push/PR touching `frontend/**`.
- **`.github/workflows/docker-build.yml`** — builds both production
  Dockerfiles on every push/PR (validates them even without pushing);
  publishes to GHCR on push to `main` only.

None of these currently deploy anywhere — there is no hosting target
configured yet. Wiring a real deploy step (e.g. pulling the newly-pushed
GHCR image and rolling a Kubernetes/ECS/Fly/Render service) is
infrastructure-specific and deliberately left for whoever picks the actual
hosting platform, rather than guessed at here.

## What's verified vs. not

Every command and config file referenced in this guide has been statically
checked (`docker compose config`-equivalent YAML parsing, `nginx -t`
syntax-checked configs, a real Mermaid render of the ER diagram, a real
OpenAPI 3.0 lint of `openapi.yaml`, full backend/frontend static
verification per their own CI workflows) — see the Phase 7 commit message
for the exact list. **None of it has been run against a live Docker daemon**
in this environment (no Docker socket access here — see `README.md`). Before
trusting a production deploy, actually run `docker compose up -d` end to end
first.
