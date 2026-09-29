import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useDebouncedValue } from '@/hooks/use-debounced-value'
import { PlusIcon, PencilIcon, TrashIcon, UsersIcon } from 'lucide-react'
import { toast } from 'sonner'

import { EmptyState } from '@/components/common/empty-state'
import { Pagination } from '@/components/common/pagination'
// role badge not used here
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Skeleton } from '@/components/ui/skeleton'
import { useClients } from '@/hooks/use-clients'
import { useCreateClient, useDeleteClient } from '@/hooks/use-clients'
import { updateClient as apiUpdateClient } from '@/api/clients'
import { useMutation, useQueryClient } from '@tanstack/react-query'

function CreateClientForm({ onSubmit, submitting }: { onSubmit: (v: any) => void; submitting: boolean }) {
  const { t } = useTranslation()
  const [name, setName] = useState('')
  const [email, setEmail] = useState('')

  return (
    <form
      onSubmit={(e) => {
        e.preventDefault()
        onSubmit({ name, email })
      }}
      className="space-y-4"
    >
      <div>
        <Label>{t('clients.form.name')}</Label>
        <Input value={name} onChange={(e) => setName(e.target.value)} />
      </div>
      <div>
        <Label>{t('clients.form.email')}</Label>
        <Input value={email} onChange={(e) => setEmail(e.target.value)} />
      </div>
      <DialogFooter>
        <Button type="submit" disabled={submitting}>{submitting ? t('common.saving') : t('common.save')}</Button>
      </DialogFooter>
    </form>
  )
}

export function ClientsPage() {
  const { t } = useTranslation()
  const [page, setPage] = useState(1)
  const [query, setQuery] = useState('')
  const debouncedQuery = useDebouncedValue(query, 300)
  const [filterLinked, setFilterLinked] = useState<'all' | 'direct' | 'linked'>('all')
  const { data, isLoading } = useClients({ page, q: debouncedQuery, is_linked: filterLinked === 'linked' ? true : filterLinked === 'direct' ? false : undefined })
  const { mutateAsync: createClientMutate, isPending: creating } = useCreateClient()
  const { mutateAsync: deleteClientMutate } = useDeleteClient()
  const qc = useQueryClient()
  const { mutateAsync: updateClientMutate, isPending: updating } = useMutation({
    mutationFn: ({ id, payload }: { id: string; payload: any }) => apiUpdateClient(id, payload),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['clients'] }),
  })
  const [creatingOpen, setCreatingOpen] = useState(false)
  const [editingOpen, setEditingOpen] = useState(false)
  const [editingClient, setEditingClient] = useState<any | null>(null)

  const onCreate = async (payload: any) => {
    try {
      await createClientMutate(payload)
      toast.success(t('clients.createdToast'))
      setCreatingOpen(false)
    } catch {
      toast.error('Unable to create client')
    }
  }

  const onDelete = async (id: string) => {
    try {
      await deleteClientMutate(id)
      toast.success(t('clients.deletedToast'))
    } catch {
      toast.error('Unable to delete client')
    }
  }

  const onEdit = (client: any) => {
    setEditingClient(client)
    setEditingOpen(true)
  }

  const onUpdate = async (payload: any) => {
    if (!editingClient) return
    try {
      await updateClientMutate({ id: editingClient.id, payload })
      toast.success(t('clients.updatedToast'))
      setEditingOpen(false)
      setEditingClient(null)
    } catch {
      toast.error('Unable to update client')
    }
  }

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl font-semibold tracking-tight">{t('clients.title')}</h1>
          <p className="text-muted-foreground text-sm">{t('clients.subtitle')}</p>
        </div>
        <Button onClick={() => setCreatingOpen(true)}>
          <PlusIcon className="size-4" />
          {t('clients.new')}
        </Button>
      </div>

      <div className="flex flex-col gap-2 lg:flex-row lg:items-center">
        <Input className="min-w-0 flex-1" placeholder={t('clients.searchPlaceholder')} value={query} onChange={(e) => { setQuery(e.target.value); setPage(1) }} />
        <div className="flex flex-wrap gap-1">
          <Button variant={filterLinked === 'all' ? 'default' : 'outline'} size="sm" onClick={() => { setFilterLinked('all'); setPage(1) }}>{t('clients.filter.all')}</Button>
          <Button variant={filterLinked === 'direct' ? 'default' : 'outline'} size="sm" onClick={() => { setFilterLinked('direct'); setPage(1) }}>{t('clients.filter.direct')}</Button>
          <Button variant={filterLinked === 'linked' ? 'default' : 'outline'} size="sm" onClick={() => { setFilterLinked('linked'); setPage(1) }}>{t('clients.filter.linked')}</Button>
        </div>
      </div>

      {isLoading && (
        <div className="space-y-2">
          {Array.from({ length: 4 }).map((_, i) => (
            <Skeleton key={i} className="h-14 w-full" />
          ))}
        </div>
      )}

      {!isLoading && data && data.clients.length === 0 && (
        <EmptyState icon={UsersIcon} title={t('clients.emptyTitle')} />
      )}

      {!isLoading && data && data.clients.length > 0 && (
        <div className="overflow-hidden rounded-lg border">
          <table className="w-full text-sm">
            <thead className="bg-muted/50 text-muted-foreground text-left">
              <tr>
                <th className="px-4 py-3 font-medium">{t('clients.columnName')}</th>
                <th className="px-4 py-3 font-medium">{t('clients.columnEmail')}</th>
                <th className="px-4 py-3 font-medium" />
              </tr>
            </thead>
            <tbody className="divide-border divide-y">
              {data.clients.map((c) => (
                <tr key={c.id}>
                  <td className="px-4 py-3 font-medium">{c.name}</td>
                  <td className="text-muted-foreground px-4 py-3">{c.email}</td>
                  <td className="px-4 py-3">
                    <div className="flex justify-end gap-2">
                      <Button variant="ghost" size="icon" onClick={() => onEdit(c)}>
                        <PencilIcon className="size-4" />
                      </Button>
                      <Button variant="ghost" size="icon" onClick={() => onDelete(c.id)}>
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

      {!isLoading && data && data.meta && (
        <Pagination meta={data.meta} onPageChange={(p) => setPage(p)} />
      )}

      <Dialog open={creatingOpen} onOpenChange={setCreatingOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{t('clients.new')}</DialogTitle>
          </DialogHeader>
          <CreateClientForm onSubmit={onCreate} submitting={creating} />
        </DialogContent>
      </Dialog>
  
      <Dialog open={editingOpen} onOpenChange={setEditingOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{t('clients.edit')}</DialogTitle>
          </DialogHeader>
          {editingClient && (
            <form
              onSubmit={(e) => {
                e.preventDefault()
                const form = e.target as HTMLFormElement
                const fd = new FormData(form)
                const payload = { name: fd.get('name') as string, email: fd.get('email') as string }
                onUpdate(payload)
              }}
              className="space-y-4"
            >
              <div>
                <Label>{t('clients.form.name')}</Label>
                <Input name="name" defaultValue={editingClient.name} />
              </div>
              <div>
                <Label>{t('clients.form.email')}</Label>
                <Input name="email" defaultValue={editingClient.email} />
              </div>
              <DialogFooter>
                <Button type="submit" disabled={updating}>{updating ? t('common.saving') : t('common.save')}</Button>
              </DialogFooter>
            </form>
          )}
        </DialogContent>
      </Dialog>

    </div>
  )
}

export default ClientsPage
