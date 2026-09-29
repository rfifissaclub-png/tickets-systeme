export type TicketStatus = 'pending' | 'in_progress' | 'on_hold' | 'resolved' | 'closed'
export type TicketPriority = 'low' | 'medium' | 'high' | 'urgent'
export type TenantStatus = 'active' | 'suspended'

export const ROLE_NAMES = [
  'Super Admin',
  'Tenant Admin',
  'Manager',
  'Support Agent',
  'Client',
] as const

export type RoleName = (typeof ROLE_NAMES)[number]

export interface Tenant {
  id: string
  name: string
  slug: string
  status: TenantStatus
  settings?: Record<string, unknown>
  created_at?: string
  updated_at?: string
}

export interface User {
  id: string
  tenant_id: string | null
  name: string
  email: string
  email_verified_at: string | null
  roles?: RoleName[]
  skills?: TicketType[]
}

export interface Client {
  id: string
  tenant_id: string
  name: string
  email: string
  is_linked?: boolean | null
  parent_tenant_id?: string | null
}

export interface TicketType {
  id: string
  name: string
  description: string | null
  is_active: boolean
  created_at: string
  updated_at: string
}

export interface Ticket {
  id: string
  ticket_type: TicketType | null
  client: User | null
  agent: User | null
  subject: string
  description: string
  status: TicketStatus
  priority: TicketPriority
  first_response_at: string | null
  resolved_at: string | null
  closed_at: string | null
  created_at: string
  updated_at: string
}

export interface TicketComment {
  id: string
  author: User | null
  body: string
  is_internal: boolean
  created_at: string
  updated_at: string
}

export interface TicketAttachment {
  id: string
  uploader: User | null
  original_filename: string
  mime_type: string
  size_bytes: number
  created_at: string
}

export interface AppNotification {
  id: string
  type: string
  data: Record<string, unknown>
  read_at: string | null
  created_at: string
}

export interface AuditLog {
  id: string
  tenant_id: string | null
  actor: { id: string; name: string; email: string } | null
  action: string
  auditable_type: string | null
  auditable_id: string | null
  old_values: Record<string, unknown> | null
  new_values: Record<string, unknown> | null
  ip_address: string | null
  created_at: string
}
