<?php

namespace App\Enums;

/**
 * The five fixed platform roles — a single source of truth for role names
 * used by policies, seeders, and tests instead of hardcoded strings.
 */
enum RoleName: string
{
    case SuperAdmin = 'Super Admin';
    case TenantAdmin = 'Tenant Admin';
    case Manager = 'Manager';
    case SupportAgent = 'Support Agent';
    case Client = 'Client';
}
