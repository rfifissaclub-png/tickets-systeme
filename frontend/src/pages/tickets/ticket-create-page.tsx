import { useState } from 'react'
import { useNavigate } from 'react-router'
import { toast } from 'sonner'

import { TicketForm, type TicketFormValues } from '@/components/tickets/ticket-form'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { toApiError } from '@/lib/api-client'
import { useCreateTicket } from '@/hooks/use-tickets'

export function TicketCreatePage() {
  const navigate = useNavigate()
  const { mutateAsync } = useCreateTicket()
  const [formError, setFormError] = useState<string | null>(null)

  const onSubmit = async (values: TicketFormValues) => {
    setFormError(null)
    try {
      const ticket = await mutateAsync(values)
      toast.success('Ticket created.')
      navigate(`/tickets/${ticket.id}`)
    } catch (error) {
      setFormError(toApiError(error).message)
    }
  }

  return (
    <div className="mx-auto max-w-2xl space-y-6">
      <div>
        <h1 className="text-2xl font-semibold tracking-tight">New ticket</h1>
        <p className="text-muted-foreground text-sm">Describe the issue and we&apos;ll route it to the right team.</p>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Ticket details</CardTitle>
          <CardDescription>Fields marked required must be filled in before submitting.</CardDescription>
        </CardHeader>
        <CardContent>
          <TicketForm onSubmit={onSubmit} submitLabel="Create ticket" formError={formError} onCancel={() => navigate(-1)} />
        </CardContent>
      </Card>
    </div>
  )
}
