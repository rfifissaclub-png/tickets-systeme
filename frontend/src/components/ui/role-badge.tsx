import * as React from 'react'
import { Badge } from './badge'

const ROLE_VARIANTS: Record<string, React.ComponentProps<typeof Badge>['variant']> = {
  'Super Admin': 'destructive',
  'Tenant Admin': 'warning',
  Manager: 'success',
  'Support Agent': 'secondary',
  Client: 'secondary',
}

export function RoleBadge({ role }: { role: string }) {
  const variant = ROLE_VARIANTS[role] ?? 'secondary'
  return <Badge variant={variant}>{role}</Badge>
}

export default RoleBadge
