import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { PlusIcon, TrashIcon, PencilIcon } from 'lucide-react'
import { toast } from 'sonner'

import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Skeleton } from '@/components/ui/skeleton'
import { useClientContacts, useCreateClientContact, useDeleteClientContact } from '@/hooks/use-client-contacts'
import { updateClientContact } from '@/api/client-contacts'
import { useMutation, useQueryClient } from '@tanstack/react-query'

export function ClientContactsList({ clientId }: { clientId: string }) {
  const { t } = useTranslation()
  const { data, isLoading } = useClientContacts(clientId)
  const { mutateAsync: createContactMutate, isPending: creating } = useCreateClientContact(clientId)
  const { mutateAsync: deleteContactMutate } = useDeleteClientContact(clientId)
  const [creatingOpen, setCreatingOpen] = useState(false)
  const [name, setName] = useState('')
  const [email, setEmail] = useState('')

  

  const qc = useQueryClient()
  const { mutateAsync: updateContactMutate, isPending: updating } = useMutation({ mutationFn: (payload: any) => updateClientContact(clientId, editingContact?.id ?? '', payload), onSuccess: () => qc.invalidateQueries({ queryKey: ['client-contacts', clientId] }) })
  const [editingContact, setEditingContact] = useState<any | null>(null)

  const onEdit = (c: any) => {
    setEditingContact(c)
    setCreatingOpen(true)
    setName(c.name)
    setEmail(c.email)
  }

  const onSave = async () => {
    try {
      if (editingContact) {
        await updateContactMutate({ name, email })
        toast.success(t('clients.contacts.updatedToast'))
        setEditingContact(null)
        setCreatingOpen(false)
        setName('')
        setEmail('')
        return
      }
      await createContactMutate({ name, email })
      toast.success(t('clients.contacts.createdToast'))
      setCreatingOpen(false)
      setName('')
      setEmail('')
    } catch {
      toast.error('Unable to save contact')
    }
  }

  const onDelete = async (id: string) => {
    try {
      await deleteContactMutate(id)
      toast.success(t('clients.contacts.deletedToast'))
    } catch {
      toast.error('Unable to delete contact')
    }
  }

  if (isLoading) return <Skeleton className="h-24 w-full" />

  return (
    <div className="space-y-3">
      <div className="flex items-center justify-between">
        <h3 className="text-sm font-medium">{t('clients.contacts.title')}</h3>
        <Button variant="outline" size="sm" onClick={() => setCreatingOpen(true)}>
          <PlusIcon className="size-4" />
          {t('clients.contacts.new')}
        </Button>
      </div>

      {!data || data.length === 0 ? (
        <div className="text-sm text-muted-foreground">{t('clients.contacts.empty')}</div>
      ) : (
        <ul className="divide-border divide-y rounded-lg border">
          {data.map((c) => (
            <li key={c.id} className="flex items-center justify-between gap-3 px-3 py-2 text-sm">
              <div>
                <div className="font-medium">{c.name}</div>
                <div className="text-muted-foreground text-xs">{c.email}</div>
              </div>
                <div className="flex gap-2">
                  <Button variant="ghost" size="icon" onClick={() => onEdit(c)} aria-label={t('clients.contacts.edit')}>
                    <PencilIcon className="size-4" />
                  </Button>
                  <Button variant="ghost" size="icon" onClick={() => onDelete(c.id)} aria-label={t('clients.contacts.delete')}>
                    <TrashIcon className="size-4" />
                  </Button>
                </div>
            </li>
          ))}
        </ul>
      )}

      <Dialog open={creatingOpen} onOpenChange={setCreatingOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{t('clients.contacts.new')}</DialogTitle>
          </DialogHeader>
          <div className="space-y-3">
            <div>
              <Label>{t('clients.contacts.form.name')}</Label>
              <Input value={name} onChange={(e) => setName(e.target.value)} />
            </div>
            <div>
              <Label>{t('clients.contacts.form.email')}</Label>
              <Input value={email} onChange={(e) => setEmail(e.target.value)} />
            </div>
            <DialogFooter>
              <Button onClick={onSave} disabled={editingContact ? updating : creating}>{(editingContact ? updating : creating) ? t('common.saving') : t('common.save')}</Button>
            </DialogFooter>
          </div>
        </DialogContent>
      </Dialog>
    </div>
  )
}

export default ClientContactsList
