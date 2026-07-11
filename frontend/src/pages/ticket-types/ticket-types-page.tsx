import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { zodResolver } from '@hookform/resolvers/zod'
import { useForm } from 'react-hook-form'
import { PencilIcon, PlusIcon, TagsIcon, TrashIcon } from 'lucide-react'
import { toast } from 'sonner'
import { z } from 'zod'

import { EmptyState } from '@/components/common/empty-state'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Skeleton } from '@/components/ui/skeleton'
import { Textarea } from '@/components/ui/textarea'
import { toApiError } from '@/lib/api-client'
import {
  useCreateTicketType,
  useDeleteTicketType,
  useTicketTypes,
  useUpdateTicketType,
} from '@/hooks/use-ticket-types'
import type { TicketType } from '@/types/models'

function useTicketTypeFormSchema() {
  const { t } = useTranslation()
  return z.object({
    name: z.string().trim().min(1, t('ticketTypes.form.nameRequiredError')).max(255),
    description: z.string().trim().optional(),
    is_active: z.boolean().optional(),
  })
}

type FormValues = z.infer<ReturnType<typeof useTicketTypeFormSchema>>

function TicketTypeFormFields({
  register,
  errors,
}: {
  register: ReturnType<typeof useForm<FormValues>>['register']
  errors: ReturnType<typeof useForm<FormValues>>['formState']['errors']
}) {
  const { t } = useTranslation()

  return (
    <div className="space-y-4">
      <div className="space-y-2">
        <Label htmlFor="name">{t('ticketTypes.form.name')}</Label>
        <Input id="name" aria-invalid={!!errors.name} {...register('name')} />
        {errors.name && <p className="text-destructive text-sm">{errors.name.message}</p>}
      </div>
      <div className="space-y-2">
        <Label htmlFor="description">{t('ticketTypes.form.description')}</Label>
        <Textarea id="description" rows={3} {...register('description')} />
      </div>
      <label className="flex items-center gap-2 text-sm">
        <input type="checkbox" className="border-input size-4 rounded" {...register('is_active')} />
        {t('ticketTypes.form.isActive')}
      </label>
    </div>
  )
}

export function TicketTypesPage() {
  const { t } = useTranslation()
  const { data: ticketTypes, isLoading } = useTicketTypes()
  const { mutateAsync: create, isPending: creating } = useCreateTicketType()
  const [editing, setEditing] = useState<TicketType | null>(null)
  const [creatingOpen, setCreatingOpen] = useState(false)
  const { mutateAsync: update, isPending: updating } = useUpdateTicketType(editing?.id ?? '')
  const { mutateAsync: remove } = useDeleteTicketType()

  const createForm = useForm<FormValues>({
    resolver: zodResolver(useTicketTypeFormSchema()),
    defaultValues: { name: '', description: '', is_active: true },
  })

  const editForm = useForm<FormValues>({
    resolver: zodResolver(useTicketTypeFormSchema()),
    values: editing
      ? { name: editing.name, description: editing.description ?? '', is_active: editing.is_active }
      : undefined,
  })

  const onCreate = async (values: FormValues) => {
    try {
      await create(values)
      toast.success(t('ticketTypes.createdToast'))
      createForm.reset()
      setCreatingOpen(false)
    } catch (error) {
      toast.error(toApiError(error).message)
    }
  }

  const onUpdate = async (values: FormValues) => {
    try {
      await update(values)
      toast.success(t('ticketTypes.updatedToast'))
      setEditing(null)
    } catch (error) {
      toast.error(toApiError(error).message)
    }
  }

  const onDelete = async (ticketType: TicketType) => {
    if (!window.confirm(t('ticketTypes.deleteConfirm', { name: ticketType.name }))) return

    try {
      await remove(ticketType.id)
      toast.success(t('ticketTypes.deletedToast'))
    } catch (error) {
      toast.error(toApiError(error).message)
    }
  }

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl font-semibold tracking-tight">{t('ticketTypes.title')}</h1>
          <p className="text-muted-foreground text-sm">{t('ticketTypes.subtitle')}</p>
        </div>
        <Button
          onClick={() => {
            createForm.reset()
            setCreatingOpen(true)
          }}
        >
          <PlusIcon className="size-4" />
          {t('ticketTypes.newType')}
        </Button>
        <Dialog open={creatingOpen} onOpenChange={setCreatingOpen}>
          <DialogContent>
            <DialogHeader>
              <DialogTitle>{t('ticketTypes.newType')}</DialogTitle>
              <DialogDescription>{t('ticketTypes.form.createDescription')}</DialogDescription>
            </DialogHeader>
            <form onSubmit={createForm.handleSubmit(onCreate)} noValidate className="space-y-4">
              <TicketTypeFormFields register={createForm.register} errors={createForm.formState.errors} />
              <DialogFooter>
                <Button type="submit" disabled={creating}>
                  {creating ? t('common.saving') : t('common.save')}
                </Button>
              </DialogFooter>
            </form>
          </DialogContent>
        </Dialog>
      </div>

      {isLoading && (
        <div className="space-y-2">
          {Array.from({ length: 3 }).map((_, index) => (
            <Skeleton key={index} className="h-14 w-full" />
          ))}
        </div>
      )}

      {!isLoading && ticketTypes && ticketTypes.length === 0 && (
        <EmptyState icon={TagsIcon} title={t('ticketTypes.emptyTitle')} description={t('ticketTypes.emptyDescription')} />
      )}

      {!isLoading && ticketTypes && ticketTypes.length > 0 && (
        <div className="overflow-hidden rounded-lg border">
          <table className="w-full text-sm">
            <thead className="bg-muted/50 text-muted-foreground text-left">
              <tr>
                <th className="px-4 py-3 font-medium">{t('ticketTypes.columnName')}</th>
                <th className="px-4 py-3 font-medium">{t('ticketTypes.columnDescription')}</th>
                <th className="px-4 py-3 font-medium">{t('ticketTypes.columnStatus')}</th>
                <th className="px-4 py-3 font-medium" />
              </tr>
            </thead>
            <tbody className="divide-border divide-y">
              {ticketTypes.map((ticketType) => (
                <tr key={ticketType.id}>
                  <td className="px-4 py-3 font-medium">{ticketType.name}</td>
                  <td className="text-muted-foreground max-w-[320px] truncate px-4 py-3">
                    {ticketType.description ?? t('common.dash')}
                  </td>
                  <td className="px-4 py-3">
                    <Badge variant={ticketType.is_active ? 'success' : 'secondary'}>
                      {ticketType.is_active ? t('ticketTypes.active') : t('ticketTypes.inactive')}
                    </Badge>
                  </td>
                  <td className="px-4 py-3">
                    <div className="flex justify-end gap-1">
                      <Button variant="ghost" size="icon" aria-label={t('common.edit')} onClick={() => setEditing(ticketType)}>
                        <PencilIcon className="size-4" />
                      </Button>
                      <Button
                        variant="ghost"
                        size="icon"
                        aria-label={t('common.delete')}
                        onClick={() => onDelete(ticketType)}
                      >
                        <TrashIcon className="size-4" />
                      </Button>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      <Dialog open={editing !== null} onOpenChange={(open) => !open && setEditing(null)}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{t('ticketTypes.editType')}</DialogTitle>
          </DialogHeader>
          <form onSubmit={editForm.handleSubmit(onUpdate)} noValidate className="space-y-4">
            <TicketTypeFormFields register={editForm.register} errors={editForm.formState.errors} />
            <DialogFooter>
              <Button type="submit" disabled={updating}>
                {updating ? t('common.saving') : t('common.save')}
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>
    </div>
  )
}
