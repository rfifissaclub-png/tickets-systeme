import { zodResolver } from '@hookform/resolvers/zod'
import { useForm } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { z } from 'zod'

import { Alert, AlertDescription } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Skeleton } from '@/components/ui/skeleton'
import { Textarea } from '@/components/ui/textarea'
import { Input } from '@/components/ui/input'
import { useTicketTypes } from '@/hooks/use-ticket-types'
import { useUsers } from '@/hooks/use-users'
import type { TicketPriority } from '@/types/models'

/**
 * requireClient: true only when a staff member is *creating* a ticket
 * (showClientSelector on) — without picking a client explicitly, the
 * backend defaults client_id to the acting user's own id
 * (TicketService::create()), which would silently make the staff member
 * the ticket's client. Not required on the edit form: the backend has no
 * client_id field on ticket update at all, so there's nothing to submit.
 */
function useTicketFormSchema(requireClient: boolean) {
  const { t } = useTranslation()
  return z.object({
    ticket_type_id: z.string().min(1, t('tickets.form.chooseTypeError')),
    client_id: requireClient ? z.string().min(1, t('tickets.form.chooseClientError')) : z.string().optional(),
    subject: z.string().trim().min(1, t('tickets.form.subjectRequiredError')).max(255),
    description: z.string().trim().min(1, t('tickets.form.descriptionRequiredError')),
    priority: z.enum(['low', 'medium', 'high', 'urgent']),
  })
}

export type TicketFormValues = z.infer<ReturnType<typeof useTicketFormSchema>>

interface TicketFormProps {
  defaultValues?: Partial<TicketFormValues>
  onSubmit: (values: TicketFormValues) => Promise<void> | void
  submitLabel: string
  formError?: string | null
  onCancel?: () => void
  /** Shown (and required) only for a staff member creating a ticket on behalf of a client. */
  showClientSelector?: boolean
}

const PRIORITY_OPTIONS: TicketPriority[] = ['low', 'medium', 'high', 'urgent']

export function TicketForm({
  defaultValues,
  onSubmit,
  submitLabel,
  formError,
  onCancel,
  showClientSelector = false,
}: TicketFormProps) {
  const { t } = useTranslation()
  const schema = useTicketFormSchema(showClientSelector)
  const { data: ticketTypes, isLoading: typesLoading } = useTicketTypes()
  // Filtered client-side rather than a dedicated ?role= query param: this
  // app has no searchable/paginated user picker anywhere (the ticket type
  // Select above loads its full list the same way), so a 100-row page is
  // consistent with the rest of the UI rather than a one-off exception.
  const { data: userPage, isLoading: usersLoading } = useUsers(
    showClientSelector ? { per_page: 100 } : undefined,
    { enabled: showClientSelector },
  )
  const clients = userPage?.users.filter((candidate) => candidate.roles?.includes('Client')) ?? []

  const {
    register,
    handleSubmit,
    watch,
    setValue,
    formState: { errors, isSubmitting },
  } = useForm<TicketFormValues>({
    resolver: zodResolver(schema),
    defaultValues: {
      ticket_type_id: defaultValues?.ticket_type_id ?? '',
      client_id: defaultValues?.client_id ?? '',
      subject: defaultValues?.subject ?? '',
      description: defaultValues?.description ?? '',
      priority: defaultValues?.priority ?? 'medium',
    },
  })

  const ticketTypeId = watch('ticket_type_id')
  const clientId = watch('client_id')
  const priority = watch('priority')

  return (
    <form onSubmit={handleSubmit(onSubmit)} noValidate className="space-y-4">
      {formError && (
        <Alert variant="destructive" role="alert">
          <AlertDescription>{formError}</AlertDescription>
        </Alert>
      )}

      {showClientSelector && (
        <div className="space-y-2">
          <Label htmlFor="client_id">{t('tickets.form.client')}</Label>
          {usersLoading ? (
            <Skeleton className="h-9 w-full" />
          ) : (
            <Select value={clientId} onValueChange={(value) => setValue('client_id', value, { shouldValidate: true })}>
              <SelectTrigger id="client_id" className="w-full" aria-invalid={!!errors.client_id}>
                <SelectValue placeholder={t('tickets.form.chooseClient')} />
              </SelectTrigger>
              <SelectContent>
                {clients.map((client) => (
                  <SelectItem key={client.id} value={client.id}>
                    {client.name} ({client.email})
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          )}
          {errors.client_id && <p className="text-destructive text-sm">{errors.client_id.message}</p>}
        </div>
      )}

      <div className="space-y-2">
        <Label htmlFor="subject">{t('tickets.form.subject')}</Label>
        <Input id="subject" aria-invalid={!!errors.subject} {...register('subject')} />
        {errors.subject && <p className="text-destructive text-sm">{errors.subject.message}</p>}
      </div>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div className="space-y-2">
          <Label htmlFor="ticket_type_id">{t('tickets.form.ticketType')}</Label>
          {typesLoading ? (
            <Skeleton className="h-9 w-full" />
          ) : (
            <Select value={ticketTypeId} onValueChange={(value) => setValue('ticket_type_id', value, { shouldValidate: true })}>
              <SelectTrigger id="ticket_type_id" className="w-full" aria-invalid={!!errors.ticket_type_id}>
                <SelectValue placeholder={t('tickets.form.chooseType')} />
              </SelectTrigger>
              <SelectContent>
                {ticketTypes?.map((type) => (
                  <SelectItem key={type.id} value={type.id}>
                    {type.name}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          )}
          {errors.ticket_type_id && <p className="text-destructive text-sm">{errors.ticket_type_id.message}</p>}
        </div>

        <div className="space-y-2">
          <Label htmlFor="priority">{t('tickets.form.priority')}</Label>
          <Select value={priority} onValueChange={(value) => setValue('priority', value as TicketPriority)}>
            <SelectTrigger id="priority" className="w-full">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              {PRIORITY_OPTIONS.map((option) => (
                <SelectItem key={option} value={option}>
                  {t(`tickets.priority.${option}`)}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
      </div>

      <div className="space-y-2">
        <Label htmlFor="description">{t('tickets.form.description')}</Label>
        <Textarea
          id="description"
          rows={6}
          aria-invalid={!!errors.description}
          {...register('description')}
        />
        {errors.description && <p className="text-destructive text-sm">{errors.description.message}</p>}
      </div>

      <div className="flex justify-end gap-2">
        {onCancel && (
          <Button type="button" variant="outline" onClick={onCancel}>
            {t('common.cancel')}
          </Button>
        )}
        <Button type="submit" disabled={isSubmitting}>
          {isSubmitting ? t('common.saving') : submitLabel}
        </Button>
      </div>
    </form>
  )
}
