import { useState } from 'react'
import { useNavigate } from 'react-router'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'

import { TicketForm, type TicketFormValues } from '@/components/tickets/ticket-form'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { toApiError } from '@/lib/api-client'
import { useHasRole } from '@/hooks/use-auth'
import { useCreateTicket } from '@/hooks/use-tickets'

export function TicketCreatePage() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const { mutateAsync } = useCreateTicket()
  const [formError, setFormError] = useState<string | null>(null)
  // A Client can only ever raise a ticket for themselves (the backend
  // forces client_id to their own id regardless of what's sent) — the
  // picker only makes sense for staff creating a ticket on a client's
  // behalf.
  const isStaff = !useHasRole('Client')
  const isSuperAdmin = useHasRole('Super Admin')

  const onSubmit = async (values: TicketFormValues | FormData) => {
    setFormError(null)
    try {
      // mutateAsync accepts either a JSON payload or FormData (for attachments)
      const ticket = await mutateAsync(values as any)
      toast.success(t('tickets.create.createdToast'))
      // A Super Admin's own session has no tenant bound, so navigating
      // straight to another tenant's ticket detail page 404s (that page
      // isn't cross-tenant-aware) — back to the list, which is, instead.
      navigate(isSuperAdmin ? '/tickets' : `/tickets/${ticket.id}`)
    } catch (error) {
      setFormError(toApiError(error).message)
    }
  }

  return (
    <div className="mx-auto max-w-2xl space-y-6">
      <div>
        <h1 className="text-2xl font-semibold tracking-tight">{t('tickets.create.title')}</h1>
        <p className="text-muted-foreground text-sm">{t('tickets.create.subtitle')}</p>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>{t('tickets.create.cardTitle')}</CardTitle>
          <CardDescription>{t('tickets.create.cardDescription')}</CardDescription>
        </CardHeader>
        <CardContent>
          <TicketForm
            onSubmit={onSubmit}
            submitLabel={t('tickets.create.submit')}
            formError={formError}
            onCancel={() => navigate(-1)}
            showClientSelector={isStaff}
            showTenantSelector={isSuperAdmin}
          />
        </CardContent>
      </Card>
    </div>
  )
}
