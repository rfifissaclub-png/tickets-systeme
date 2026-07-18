// Mirrors backend/tms-backend/database/seeders/DatabaseSeeder.php's
// seedLocalDevAccounts() exactly — kept in sync by hand, not generated.
// Requires `php artisan migrate:fresh --seed` to have been run against the
// stack this suite targets (see playwright.config.ts's own comment).
export const PASSWORD = 'password'

export const ACCOUNTS = {
  superAdmin: { tenant: '', email: 'super-admin@tms.local' },
  acmeTenantAdmin: { tenant: 'acme', email: 'admin@acme.tms.local' },
  acmeAgent: { tenant: 'acme', email: 'agent@acme.tms.local' },
  acmeClient: { tenant: 'acme', email: 'client@acme.tms.local' },
  globexTenantAdmin: { tenant: 'globex', email: 'admin@globex.tms.local' },
  globexClient: { tenant: 'globex', email: 'client@globex.tms.local' },
} as const
