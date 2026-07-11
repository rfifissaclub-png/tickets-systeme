import {
  BarChart3Icon,
  InboxIcon,
  LayoutDashboardIcon,
  TagsIcon,
  TicketIcon,
  type LucideIcon,
} from 'lucide-react'

import type { RoleName } from '@/types/models'

export interface NavItem {
  label: string
  to: string
  icon: LucideIcon
  /** Omit to show for every authenticated role. */
  roles?: RoleName[]
}

export const NAV_ITEMS: NavItem[] = [
  { label: 'Dashboard', to: '/', icon: LayoutDashboardIcon },
  { label: 'Tickets', to: '/tickets', icon: TicketIcon },
  {
    label: 'Ticket Pool',
    to: '/ticket-pool',
    icon: InboxIcon,
    roles: ['Support Agent', 'Manager', 'Tenant Admin', 'Super Admin'],
  },
  {
    label: 'Reports',
    to: '/reports',
    icon: BarChart3Icon,
    roles: ['Manager', 'Tenant Admin', 'Super Admin'],
  },
  {
    label: 'Ticket Types',
    to: '/ticket-types',
    icon: TagsIcon,
    roles: ['Tenant Admin', 'Super Admin'],
  },
]
