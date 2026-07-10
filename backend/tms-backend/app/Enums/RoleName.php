<?php

namespace App\Enums;

/**
 * The five fixed platform roles. Full seeding lands in Phase 2; this enum
 * exists now so Phase 1 code (policies, tests) has a single source of truth
 * for role names instead of hardcoded strings.
 */
enum RoleName: string
{
    case SuperAdmin = 'Super Admin';
    case TenantAdmin = 'Tenant Admin';
    case Manager = 'Manager';
    case SupportAgent = 'Support Agent';
    case Client = 'Client';
}
