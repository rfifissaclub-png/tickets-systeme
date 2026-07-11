# Entity-Relationship Diagram

Every table below except `tenants` itself carries a `tenant_id` and is
protected by PostgreSQL Row-Level Security (see
[`architecture.md`](architecture.md#multi-tenancy-shared-schema--postgresql-row-level-security)).
Spatie `laravel-permission`'s pivot tables are omitted for readability — see
`database/migrations/0001_01_01_000003_create_permission_tables.php` for
their exact shape (`teams`-mode, `tenant_id` as the team foreign key).

```mermaid
erDiagram
    TENANTS ||--o{ USERS : "owns"
    TENANTS ||--o{ TICKET_TYPES : "owns"
    TENANTS ||--o{ TICKETS : "owns"
    TENANTS ||--o{ TICKET_COMMENTS : "owns"
    TENANTS ||--o{ TICKET_ATTACHMENTS : "owns"
    TENANTS ||--o{ NOTIFICATIONS : "owns"
    TENANTS ||--o{ AUDIT_LOGS : "owns (nullable)"

    USERS ||--o{ TICKETS : "client_id"
    USERS ||--o{ TICKETS : "agent_id (nullable)"
    USERS ||--o{ TICKET_COMMENTS : "author_id"
    USERS ||--o{ TICKET_ATTACHMENTS : "uploader_id"
    USERS ||--o{ AUDIT_LOGS : "actor_id (nullable)"
    USERS ||--o{ AGENT_SKILLS : "user_id"

    TICKET_TYPES ||--o{ TICKETS : "ticket_type_id"
    TICKET_TYPES ||--o{ AGENT_SKILLS : "ticket_type_id"
    TENANTS ||--o{ AGENT_SKILLS : "owns"

    TICKETS ||--o{ TICKET_COMMENTS : "ticket_id"
    TICKETS ||--o{ TICKET_ATTACHMENTS : "ticket_id"

    TENANTS {
        uuid id PK
        string name
        string slug UK
        string status "active | suspended"
        jsonb settings
        timestamps ts
        timestamp deleted_at
    }

    USERS {
        uuid id PK
        uuid tenant_id FK "nullable — null = platform Super Admin"
        string name
        string email "unique per tenant; partial-unique when tenant_id IS NULL"
        timestamp email_verified_at
        string password
        text two_factor_secret "nullable, storage-ready"
        timestamps ts
        timestamp deleted_at
    }

    TICKET_TYPES {
        uuid id PK
        uuid tenant_id FK
        string name "unique per tenant"
        text description
        boolean is_active
        timestamps ts
        timestamp deleted_at
    }

    TICKETS {
        uuid id PK
        uuid tenant_id FK
        uuid ticket_type_id FK
        uuid client_id FK "users, restrict delete"
        uuid agent_id FK "nullable — null while in the pool"
        string subject
        text description
        string status "pending|in_progress|on_hold|resolved|closed"
        string priority "low|medium|high|urgent"
        tsvector search_vector "generated, GIN-indexed"
        timestamp first_response_at
        timestamp resolved_at
        timestamp closed_at
        timestamps ts
        timestamp deleted_at
    }

    TICKET_COMMENTS {
        uuid id PK
        uuid tenant_id FK
        uuid ticket_id FK
        uuid author_id FK "users, restrict delete"
        text body
        boolean is_internal "hidden from the client when true"
        timestamps ts
        timestamp deleted_at
    }

    TICKET_ATTACHMENTS {
        uuid id PK
        uuid tenant_id FK
        uuid ticket_id FK
        uuid uploader_id FK "users, restrict delete"
        string original_filename "display only, never a storage path"
        string disk "s3"
        string stored_path "random key, not derived from filename"
        string mime_type
        bigint size_bytes
        timestamps ts
        timestamp deleted_at
    }

    NOTIFICATIONS {
        uuid id PK
        uuid tenant_id FK
        string notifiable_type
        uuid notifiable_id "polymorphic — the recipient user"
        string type "notification class name"
        jsonb data
        timestamp read_at "nullable"
        timestamps ts
    }

    AUDIT_LOGS {
        uuid id PK
        uuid tenant_id FK "nullable — platform-level actions"
        uuid actor_id FK "nullable — users, set null on delete"
        string action
        string auditable_type "nullable, polymorphic"
        uuid auditable_id "nullable"
        jsonb old_values
        jsonb new_values
        string ip_address
        text user_agent
        timestamp created_at "append-only, no updated_at"
    }

    AGENT_SKILLS {
        uuid id PK
        uuid tenant_id FK
        uuid user_id FK "cascade delete"
        uuid ticket_type_id FK "cascade delete"
        timestamps ts
    }
```

## Notes on deliberate design choices

- **UUID primary keys everywhere** — no auto-increment integer IDs, so
  resource identifiers never leak cross-tenant row counts and are safe to
  expose directly in URLs/API responses.
- **`agent_id` nullable, `client_id` not** — a ticket always has a client
  (who reported it) but may sit unassigned in the pool with no agent yet.
- **`restrictOnDelete()` on `client_id`/`author_id`/`uploader_id`** — a user
  who has ever created a ticket, posted a comment, or uploaded a file cannot
  be hard-deleted while that history exists (soft-delete the user instead);
  `agent_id` is `nullOnDelete()` since unassigning a ticket when its agent
  leaves is a valid, non-destructive outcome.
- **`tickets.search_vector`** is a stored generated column, not a
  separately-maintained field — it can never drift out of sync with
  `subject`/`description` because Postgres recomputes it on every write.
- **`notifications.tenant_id` is `NOT NULL`** (unlike `audit_logs`) —
  notifications are always about something tenant-scoped (a ticket event);
  there is no platform-level notification concept yet.
- **`agent_skills` is a plain many-to-many pivot with its own `tenant_id`**
  (not derivable from either side alone in a way RLS could use directly) —
  it exists solely to drive the ticket pool's skill-based filtering (see
  `architecture.md`); a missing row means "not qualified", not "qualified
  for everything" — the pool fails closed for a fresh agent with no rows
  here yet, same as every other RLS/permission boundary in this app.
