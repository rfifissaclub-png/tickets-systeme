# TMS — Ticket Management System

A multi-tenant support ticketing platform built for organizations that need to manage customer support requests securely, with strict data isolation between tenants and skill-based routing of work to support staff.

## Overview

TMS centralizes support operations for one or many organizations ("tenants") on a single platform. Clients submit requests through a dedicated portal; support staff work from a shared, skill-filtered pool and their own personal queue. Every tenant's data is fully isolated at the database level, role-based access control governs every action, and a complete audit trail records who did what and when.

## Key features

- **Multi-tenant data isolation** — enforced at the PostgreSQL engine level via Row-Level Security, not just in application code. Tenants cannot access each other's data under any circumstance.
- **Role-based access control** — Super Admin, Tenant Admin, Manager, Support Agent, and Client roles, each with a distinct, permission-scoped view of the system.
- **Skill-based ticket routing** — unassigned tickets sit in a shared pool, filtered per agent by the ticket types they're qualified for. Agents pull tickets into their own queue; concurrent pull attempts on the same ticket are resolved atomically.
- **Ticket lifecycle management** — a defined status workflow (Pending, In Progress, On Hold, Resolved, Closed) with database-enforced immutability once a ticket is closed.
- **Attachments** — private, access-controlled file storage with content-based MIME validation, configurable size limits, antivirus scanning, and short-lived signed download URLs.
- **Notifications & realtime updates** — in-app, email, and WebSocket notifications for ticket assignment, comments, and status changes.
- **Reporting** — ticket volume, resolution time, and agent performance dashboards, including a cross-tenant view for platform administrators.
- **Full audit trail** — every state change and access-control decision is logged and immutable.
- **Internationalization** — English and French supported throughout the interface and notifications, with the architecture designed for additional languages.

## Technology stack

**Backend** — PHP 8.4, Laravel 12, PostgreSQL 16, Redis, Laravel Sanctum (authentication), Laravel Reverb (WebSockets), Pest (testing).

**Frontend** — React 19, TypeScript, Vite, Tailwind CSS, TanStack Query, React Router.

**Infrastructure** — Docker Compose (PostgreSQL, Redis, MinIO, Reverb, ClamAV, Mailpit), Nginx, GitHub Actions CI/CD.

## Project structure

```
backend/tms-backend/   Laravel API
frontend/               React application
infra/                  Docker images, Compose configuration, Nginx, CI/CD
docs/                   Architecture, API reference, deployment guide
```

## Getting started

### Prerequisites

- Docker with the Compose v2 plugin

### Running the stack

```bash
cd infra
docker compose up -d
docker compose exec backend php artisan migrate
docker compose exec backend php artisan db:seed
```

The backend API is served at `http://localhost:8080`, the frontend at `http://localhost:8081`.

### Frontend (standalone development)

```bash
cd frontend
cp .env.example .env
npm install
npm run dev
```

### Running tests

```bash
docker compose exec backend vendor/bin/pest
```

## Documentation

- [`docs/architecture.md`](docs/architecture.md) — system design and key technical decisions
- [`docs/openapi.yaml`](docs/openapi.yaml) — API reference (OpenAPI 3.0)
- [`docs/er-diagram.md`](docs/er-diagram.md) — entity-relationship diagram
- [`docs/deployment.md`](docs/deployment.md) — deployment and configuration guide

## License

Proprietary. All rights reserved.
