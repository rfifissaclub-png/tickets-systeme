import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { zodResolver } from '@hookform/resolvers/zod'
import { useForm } from 'react-hook-form'
import { BuildingIcon, PlusIcon, TrashIcon } from 'lucide-react'
import { toast } from 'sonner'
import { z } from 'zod'

import { EmptyState } from '@/components/common/empty-state'
import { Pagination } from '@/components/common/pagination'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Skeleton } from '@/components/ui/skeleton'
import { toApiError } from '@/lib/api-client'
import { useCreateTenant, useDeleteTenant, useTenants } from '@/hooks/use-tenants'
import type { Tenant } from '@/types/models'

function useCreateTenantFormSchema() {
  const { t } = useTranslation()
  return z.object({
    name: z.string().trim().min(1, t('tenants.form.nameRequiredError')).max(255),
    slug: z
      .string()
      .trim()
      .min(1, t('tenants.form.slugRequiredError'))
      .max(255)
      .regex(/^[a-z0-9-]+$/, t('tenants.form.slugFormatError')),
  })
}

type FormValues = z.infer<ReturnType<typeof useCreateTenantFormSchema>>

export function TenantsPage() {
  const { t } = useTranslation()
  const [page, setPage] = useState(1)
  const { data, isLoading, isError } = useTenants({ page })
  const { mutateAsync: create, isPending: creating } = useCreateTenant()
  const { mutateAsync: remove } = useDeleteTenant()
  const [creatingOpen, setCreatingOpen] = useState(false)

  const form = useForm<FormValues>({
    resolver: zodResolver(useCreateTenantFormSchema()),
    defaultValues: { name: '', slug: '' },
  })

  const onCreate = async (values: FormValues) => {
    try {
      await create(values)
      toast.success(t('tenants.createdToast'))
      form.reset()
      setCreatingOpen(false)
    } catch (error) {
      toast.error(toApiError(error).message)
    }
  }

  const onSuspend = async (tenant: Tenant) => {
    if (!window.confirm(t('tenants.suspendConfirm', { name: tenant.name }))) return

    try {
      await remove(tenant.id)
      toast.success(t('tenants.suspendedToast'))
    } catch (error) {
      toast.error(toApiError(error).message)
    }
  }

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl font-semibold tracking-tight">{t('tenants.title')}</h1>
          <p className="text-muted-foreground text-sm">{t('tenants.subtitle')}</p>
        </div>
        <Button
          onClick={() => {
            form.reset()
            setCreatingOpen(true)
          }}
        >
          <PlusIcon className="size-4" />
          {t('tenants.newTenant')}
        </Button>
      </div>

      {isLoading && (
        <div className="space-y-2">
          {Array.from({ length: 4 }).map((_, index) => (
            <Skeleton key={index} className="h-14 w-full" />
          ))}
        </div>
      )}

      {isError && (
        <EmptyState
          icon={BuildingIcon}
          title={t('tenants.loadErrorTitle')}
          description={t('tenants.loadErrorDescription')}
        />
      )}

      {!isLoading && !isError && data && data.tenants.length === 0 && (
        <EmptyState icon={BuildingIcon} title={t('tenants.emptyTitle')} />
      )}

      {!isLoading && !isError && data && data.tenants.length > 0 && (
        <>
          <div className="overflow-hidden rounded-lg border">
            <table className="w-full text-sm">
              <thead className="bg-muted/50 text-muted-foreground text-left">
                <tr>
                  <th className="px-4 py-3 font-medium">{t('tenants.columnName')}</th>
                  <th className="px-4 py-3 font-medium">{t('tenants.columnSlug')}</th>
                  <th className="px-4 py-3 font-medium">{t('tenants.columnStatus')}</th>
                  <th className="px-4 py-3 font-medium" />
                </tr>
              </thead>
              <tbody className="divide-border divide-y">
                {data.tenants.map((tenant) => (
                  <tr key={tenant.id}>
                    <td className="px-4 py-3 font-medium">{tenant.name}</td>
                    <td className="text-muted-foreground px-4 py-3">{tenant.slug}</td>
                    <td className="px-4 py-3">
                      <Badge variant={tenant.status === 'active' ? 'success' : 'secondary'}>
                        {tenant.status === 'active' ? t('tenants.active') : t('tenants.suspended')}
                      </Badge>
                    </td>
                    <td className="px-4 py-3">
                      <div className="flex justify-end">
                        <Button
                          variant="ghost"
                          size="icon"
                          aria-label={t('tenants.suspend')}
                          disabled={tenant.status === 'suspended'}
                          onClick={() => onSuspend(tenant)}
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

          <Pagination meta={data.meta} onPageChange={setPage} />
        </>
      )}

      <Dialog open={creatingOpen} onOpenChange={setCreatingOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{t('tenants.newTenant')}</DialogTitle>
          </DialogHeader>
          <form onSubmit={form.handleSubmit(onCreate)} noValidate className="space-y-4">
            <div className="space-y-2">
              <Label htmlFor="name">{t('tenants.form.name')}</Label>
              <Input id="name" aria-invalid={!!form.formState.errors.name} {...form.register('name')} />
              {form.formState.errors.name && (
                <p className="text-destructive text-sm">{form.formState.errors.name.message}</p>
              )}
            </div>
            <div className="space-y-2">
              <Label htmlFor="slug">{t('tenants.form.slug')}</Label>
              <Input
                id="slug"
                placeholder={t('tenants.form.slugPlaceholder')}
                aria-invalid={!!form.formState.errors.slug}
                {...form.register('slug')}
              />
              {form.formState.errors.slug && (
                <p className="text-destructive text-sm">{form.formState.errors.slug.message}</p>
              )}
            </div>
            <DialogFooter>
              <Button type="submit" disabled={creating}>
                {creating ? t('common.saving') : t('common.save')}
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>
    </div>
  )
}
