#!/usr/bin/env bash
set -euo pipefail

# Runs the minimal set of services required for backend tests, waits
# for Postgres to be ready, then runs composer test inside the backend container.
# Usage: ./infra/scripts/run-backend-tests.sh
#
# Run from the repository root. If infra/.env exists (see infra/.env.example),
# its overrides are applied; otherwise Docker Compose uses the built-in
# defaults from infra/docker-compose.yml.

dc_up() {
  if [ -f "infra/.env" ]; then
    docker compose -f infra/docker-compose.yml --env-file infra/.env up "$@"
  else
    docker compose -f infra/docker-compose.yml up "$@"
  fi
}

dc_exec() {
  if [ -f "infra/.env" ]; then
    docker compose -f infra/docker-compose.yml --env-file infra/.env exec "$@"
  else
    docker compose -f infra/docker-compose.yml exec "$@"
  fi
}

echo "Starting dependent services (postgres, redis, clamav, minio)..."
dc_up -d postgres redis clamav minio

echo "Waiting for Postgres to become ready..."
# Wait until pg_isready returns success inside the postgres container
until dc_exec -T postgres pg_isready -U tms -d tms >/dev/null 2>&1; do
  echo -n '.'; sleep 1
done

echo "Bringing up backend container..."
dc_up -d backend

echo "Running backend test suite inside backend container..."
# Run tests non-interactively
dc_exec -T backend bash -lc "composer test --no-interaction"

EXIT_CODE=$?

if [ $EXIT_CODE -ne 0 ]; then
  echo "Backend tests failed (exit code $EXIT_CODE). See output above for failures."
else
  echo "All backend tests passed."
fi

exit $EXIT_CODE
