import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'

import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Skeleton } from '@/components/ui/skeleton'
import { toApiError } from '@/lib/api-client'
import { useAssignTicket } from '@/hooks/use-tickets'
import { useUsers } from '@/hooks/use-users'
import type { RoleName, User } from '@/types/models'

// Mirrors AssignTicketRequest::ASSIGNABLE_ROLES on the backend — kept in
// sync by hand, not generated. Only used to decide which candidates to
// *offer*; the backend is the actual authority and rejects anything else.
const ASSIGNABLE_ROLES: RoleName[] = ['Support Agent', 'Manager', 'Tenant Admin']

export function TicketAssignSelect({
  ticketId,
  currentAgent,
}: {
  ticketId: string
  currentAgent: User | null
}) {
  const { t } = useTranslation()
  const { mutate, isPending } = useAssignTicket(ticketId)
  // Filtered client-side, same reasoning as TicketForm's client picker: no
  // searchable/paginated user selector exists anywhere else in this app.
  const { data: userPage, isLoading } = useUsers({ per_page: 100 })
  const agents = userPage?.users.filter((candidate) => candidate.roles?.some((role) => ASSIGNABLE_ROLES.includes(role))) ?? []

  if (isLoading) {
    return <Skeleton className="h-9 w-[220px]" />
  }

  return (
    <Select
      value={currentAgent?.id ?? ''}
      disabled={isPending}
      onValueChange={(agentId) =>
        mutate(agentId, {
          onSuccess: () => toast.success(t('tickets.detail.assignedToast')),
          // firstError(), not the generic top-level message: the backend's
          // validation_failed message is always the same generic string
          // (see bootstrap/app.php's ValidationException render callback),
          // while the specific reason — not this tenant's agent, wrong
          // role, or (see TicketService::assign()) missing the ticket
          // type's skill — lives in errors.agent_id and is what actually
          // tells the admin what to fix.
          onError: (error) => {
            const apiError = toApiError(error)
            toast.error(apiError.firstError() ?? apiError.message)
          },
        })
      }
    >
      <SelectTrigger className="w-[220px]" aria-label={t('tickets.detail.agent')}>
        <SelectValue placeholder={t('tickets.detail.chooseAgent')} />
      </SelectTrigger>
      <SelectContent>
        {agents.map((agent) => (
          <SelectItem key={agent.id} value={agent.id}>
            {agent.name}
          </SelectItem>
        ))}
      </SelectContent>
    </Select>
  )
}
