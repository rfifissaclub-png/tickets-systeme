import {
  BarChart3Icon,
  BuildingIcon,
  InboxIcon,
  LayoutDashboardIcon,
  ScrollTextIcon,
  TagsIcon,
  TicketIcon,
  UsersIcon,
  type LucideIcon,
} from 'lucide-react'

import type { RoleName } from '@/types/models'

export interface NavItem {
  labelKey: string
  to: string
  icon: LucideIcon
  /** Omit to show for every authenticated role. */
  roles?: RoleName[]
}

export const NAV_ITEMS: NavItem[] = [
  { labelKey: 'nav.dashboard', to: '/', icon: LayoutDashboardIcon },
  { labelKey: 'nav.tickets', to: '/tickets', icon: TicketIcon },
  {
    labelKey: 'nav.ticketPool',
    to: '/ticket-pool',
    icon: InboxIcon,
    roles: ['Support Agent', 'Manager', 'Tenant Admin', 'Super Admin'],
  },
  {
    labelKey: 'nav.reports',
    to: '/reports',
    icon: BarChart3Icon,
    roles: ['Manager', 'Tenant Admin', 'Super Admin'],
  },
  {
    labelKey: 'nav.ticketTypes',
    to: '/ticket-types',
    icon: TagsIcon,
    roles: ['Super Admin'],
  },
  {
    labelKey: 'nav.users',
    to: '/users',
    icon: UsersIcon,
    roles: ['Manager', 'Tenant Admin', 'Super Admin'],
  },
  {
    labelKey: 'nav.tenants',
    to: '/tenants',
    icon: BuildingIcon,
    roles: ['Super Admin'],
  },
  {
    labelKey: 'nav.auditLogs',
    to: '/audit-logs',
    icon: ScrollTextIcon,
    roles: ['Tenant Admin', 'Super Admin'],
  },
]
