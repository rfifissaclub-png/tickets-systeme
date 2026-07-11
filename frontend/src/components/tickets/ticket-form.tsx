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
import type { TicketPriority } from '@/types/models'

function useTicketFormSchema() {
  const { t } = useTranslation()
  return z.object({
    ticket_type_id: z.string().min(1, t('tickets.form.chooseTypeError')),
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
}

const PRIORITY_OPTIONS: TicketPriority[] = ['low', 'medium', 'high', 'urgent']

export function TicketForm({ defaultValues, onSubmit, submitLabel, formError, onCancel }: TicketFormProps) {
  const { t } = useTranslation()
  const schema = useTicketFormSchema()
  const { data: ticketTypes, isLoading: typesLoading } = useTicketTypes()

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
      subject: defaultValues?.subject ?? '',
      description: defaultValues?.description ?? '',
      priority: defaultValues?.priority ?? 'medium',
    },
  })

  const ticketTypeId = watch('ticket_type_id')
  const priority = watch('priority')

  return (
    <form onSubmit={handleSubmit(onSubmit)} noValidate className="space-y-4">
      {formError && (
        <Alert variant="destructive" role="alert">
          <AlertDescription>{formError}</AlertDescription>
        </Alert>
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
