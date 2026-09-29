import * as React from 'react'
import { zodResolver } from '@hookform/resolvers/zod'
import { useForm } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { z } from 'zod'

import { Alert, AlertDescription } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Label } from '@/components/ui/label'
import { XIcon } from 'lucide-react'
import AttachmentPreview from '@/components/ui/attachment-preview'
import AttachmentLightbox from '@/components/ui/attachment-lightbox'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Skeleton } from '@/components/ui/skeleton'
import { Textarea } from '@/components/ui/textarea'
import { Input } from '@/components/ui/input'
import { useTenants } from '@/hooks/use-tenants'
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
 *
 * requireTenant: true only for a Super Admin creating a ticket
 * (showTenantSelector on) — they have no tenant of their own for
 * TicketService::create() to default to (see StoreTicketRequest).
 */
function useTicketFormSchema(requireClient: boolean, requireTenant: boolean) {
  const { t } = useTranslation()
  return z.object({
    ticket_type_id: z.string().min(1, t('tickets.form.chooseTypeError')),
    tenant_id: requireTenant ? z.string().min(1, t('tickets.form.chooseTenantError')) : z.string().optional(),
    client_id: requireClient ? z.string().min(1, t('tickets.form.chooseClientError')) : z.string().optional(),
    subject: z.string().trim().min(1, t('tickets.form.subjectRequiredError')).max(255),
    description: z.string().trim().min(1, t('tickets.form.descriptionRequiredError')),
    priority: z.enum(['low', 'medium', 'high', 'urgent']),
  })
}

export type TicketFormValues = z.infer<ReturnType<typeof useTicketFormSchema>>

interface TicketFormProps {
  defaultValues?: Partial<TicketFormValues>
  onSubmit: (values: TicketFormValues | FormData) => Promise<void> | void
  submitLabel: string
  formError?: string | null
  onCancel?: () => void
  /** Shown (and required) only for a staff member creating a ticket on behalf of a client. */
  showClientSelector?: boolean
  /** Shown (and required) only for a Super Admin, who has no tenant of their own. */
  showTenantSelector?: boolean
  /** Fields that should be locked/disabled in the form (e.g. when editing cross-tenant tickets). */
  lockedFields?: ('subject' | 'description')[]
}

const PRIORITY_OPTIONS: TicketPriority[] = ['low', 'medium', 'high', 'urgent']

export function TicketForm({
  defaultValues,
  onSubmit,
  submitLabel,
  formError,
  onCancel,
  showClientSelector = false,
  showTenantSelector = false,
  lockedFields = [],
}: TicketFormProps) {
  const { t } = useTranslation()
  const schema = useTicketFormSchema(showClientSelector, showTenantSelector)
  const { data: ticketTypes, isLoading: typesLoading } = useTicketTypes()
  const { data: tenantPage, isLoading: tenantsLoading } = useTenants(
    { per_page: 100 },
    { enabled: showTenantSelector },
  )
  const tenants = tenantPage?.tenants ?? []

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
      tenant_id: defaultValues?.tenant_id ?? '',
      client_id: defaultValues?.client_id ?? '',
      subject: defaultValues?.subject ?? '',
      description: defaultValues?.description ?? '',
      priority: defaultValues?.priority ?? 'medium',
    },
  })

  const ticketTypeId = watch('ticket_type_id')
  const tenantId = watch('tenant_id')
  const clientId = watch('client_id')
  const priority = watch('priority')

  const locked = new Set(lockedFields ?? [])

  const [files, setFiles] = React.useState<File[]>([])

  const onFilesChange = (fileList: FileList | null) => {
    if (!fileList) return
    setFiles((prev) => [...prev, ...Array.from(fileList)])
  }

  const removeFileAt = (index: number) => setFiles((prev) => prev.filter((_, i) => i !== index))

  // Filtered client-side rather than a dedicated ?role= query param: this
  // app has no searchable/paginated user picker anywhere (the ticket type
  // Select above loads its full list the same way), so a 100-row page is
  // consistent with the rest of the UI rather than a one-off exception.
  // For a Super Admin (showTenantSelector), the client list is scoped to
  // whichever tenant they've picked — held back until they have, since
  // "every client across every tenant" isn't a usable dropdown.
  const clientsEnabled = showClientSelector && (!showTenantSelector || !!tenantId)
  const { data: userPage, isLoading: usersLoading } = useUsers(
    clientsEnabled ? { per_page: 100, tenant_id: showTenantSelector ? tenantId : undefined } : undefined,
    { enabled: clientsEnabled },
  )
  const clients = userPage?.users.filter((candidate) => candidate.roles?.includes('Client')) ?? []

  const handleFormSubmit = handleSubmit(async (values) => {
    if (files.length === 0) {
      await onSubmit(values)
      return
    }

    const fd = new FormData()
    fd.append('ticket_type_id', values.ticket_type_id)
    if (values.tenant_id) fd.append('tenant_id', values.tenant_id)
    if (values.client_id) fd.append('client_id', values.client_id)
    fd.append('subject', values.subject)
    fd.append('description', values.description)
    if (values.priority) fd.append('priority', values.priority)

    files.forEach((file) => fd.append('attachments[]', file))

    await onSubmit(fd)
  })

  return (
    <form onSubmit={handleFormSubmit} noValidate className="space-y-4">
      {formError && (
        <Alert variant="destructive" role="alert">
          <AlertDescription>{formError}</AlertDescription>
        </Alert>
      )}

      {showTenantSelector && (
        <div className="space-y-2">
          <Label htmlFor="tenant_id">{t('tickets.form.tenant')}</Label>
          {tenantsLoading ? (
            <Skeleton className="h-9 w-full" />
          ) : (
            <Select
              value={tenantId}
              onValueChange={(value) => {
                setValue('tenant_id', value, { shouldValidate: true })
                setValue('client_id', '', { shouldValidate: false })
              }}
            >
              <SelectTrigger id="tenant_id" className="w-full" aria-invalid={!!errors.tenant_id}>
                <SelectValue placeholder={t('tickets.form.chooseTenant')} />
              </SelectTrigger>
              <SelectContent>
                {tenants.map((tenant) => (
                  <SelectItem key={tenant.id} value={tenant.id}>
                    {tenant.name}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          )}
          {errors.tenant_id && <p className="text-destructive text-sm">{errors.tenant_id.message}</p>}
        </div>
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
        <Input id="subject" aria-invalid={!!errors.subject} {...register('subject')} disabled={locked.has('subject')} />
        {locked.has('subject') && <p className="text-muted-foreground text-sm">{t('tickets.form.lockedField')}</p>}
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
          disabled={locked.has('description')}
        />
        {locked.has('description') && <p className="text-muted-foreground text-sm">{t('tickets.form.lockedField')}</p>}
        {errors.description && <p className="text-destructive text-sm">{errors.description.message}</p>}
      </div>

      <div className="space-y-2">
        <Label htmlFor="attachments">{t('tickets.form.attachments')}</Label>
        <input
          id="attachments"
          type="file"
          className="w-full"
          multiple
          accept="image/*,application/pdf"
          onChange={(e) => onFilesChange(e.target.files)}
        />
        {files.length > 0 && (
          <div className="mt-2 space-y-2">
            {files.map((file, idx) => (
              <div key={idx} className="flex items-start justify-between gap-2 rounded border p-2">
                <div className="flex gap-3">
                  <div className="flex-shrink-0">
                    <AttachmentLightbox file={file} mimeType={file.type} title={file.name}>
                      <AttachmentPreview file={file} className="max-h-20 max-w-[120px] object-cover rounded" />
                    </AttachmentLightbox>
                  </div>
                  <div className="min-w-0">
                    <div className="truncate font-medium">{file.name}</div>
                    <div className="text-muted-foreground text-xs">{(file.size / 1024).toFixed(1)} KB</div>
                  </div>
                </div>
                <div>
                  <button type="button" className="text-destructive" onClick={() => removeFileAt(idx)} aria-label={t('tickets.form.removeAttachment')}>
                    <XIcon className="size-4" />
                  </button>
                </div>
              </div>
            ))}
          </div>
        )}
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
