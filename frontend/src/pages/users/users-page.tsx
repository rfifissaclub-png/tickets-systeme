import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { zodResolver } from '@hookform/resolvers/zod'
import { useForm } from 'react-hook-form'
import { PlusIcon, ShieldIcon, TrashIcon, UsersIcon, WrenchIcon } from 'lucide-react'
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
import { useAuth, useHasRole } from '@/hooks/use-auth'
import { useTicketTypes } from '@/hooks/use-ticket-types'
import {
  useCreateUser,
  useDeleteUser,
  useSyncUserRoles,
  useSyncUserSkills,
  useUsers,
} from '@/hooks/use-users'
import { ROLE_NAMES, type RoleName, type User } from '@/types/models'

function useCreateUserFormSchema() {
  const { t } = useTranslation()
  return z.object({
    name: z.string().trim().min(1, t('users.form.nameRequiredError')).max(255),
    email: z.string().trim().email(t('auth.invalidEmail')),
    password: z.string().min(8, t('auth.passwordMinLength')),
  })
}

type CreateFormValues = z.infer<ReturnType<typeof useCreateUserFormSchema>>

export function UsersPage() {
  const { t } = useTranslation()
  const { user: currentUser } = useAuth()
  const isSuperAdmin = useHasRole('Super Admin')
  const canManageUsers = useHasRole('Tenant Admin', 'Super Admin')
  const canManageSkills = useHasRole('Tenant Admin', 'Manager', 'Super Admin')

  const [page, setPage] = useState(1)
  const { data, isLoading, isError } = useUsers({ page })
  const { data: ticketTypes } = useTicketTypes()

  const [creatingOpen, setCreatingOpen] = useState(false)
  // A user occupies exactly one position in this app's model (Tenant Admin,
  // Manager, Support Agent, or Client) — never two at once, hence a single
  // selection here rather than a checkbox list. null represents "no role
  // assigned yet", a real, submittable state (StoreUserRequest/
  // AssignUserRolesRequest both accept an empty roles array).
  const [creatingRole, setCreatingRole] = useState<RoleName | null>(null)
  const { mutateAsync: create, isPending: creating } = useCreateUser()

  const [rolesTarget, setRolesTarget] = useState<User | null>(null)
  const [pendingRole, setPendingRole] = useState<RoleName | null>(null)
  const { mutateAsync: syncRoles, isPending: syncingRoles } = useSyncUserRoles(rolesTarget?.id ?? '')

  const [skillsTarget, setSkillsTarget] = useState<User | null>(null)
  const [pendingSkillIds, setPendingSkillIds] = useState<string[]>([])
  const { mutateAsync: syncSkills, isPending: syncingSkills } = useSyncUserSkills(skillsTarget?.id ?? '')

  const { mutateAsync: remove } = useDeleteUser()

  const createForm = useForm<CreateFormValues>({
    resolver: zodResolver(useCreateUserFormSchema()),
    defaultValues: { name: '', email: '', password: '' },
  })

  const assignableRoles = ROLE_NAMES.filter((role) => role !== 'Super Admin' || isSuperAdmin)

  const onCreate = async (values: CreateFormValues) => {
    try {
      await create({ ...values, roles: creatingRole ? [creatingRole] : [] })
      toast.success(t('users.createdToast'))
      createForm.reset()
      setCreatingRole(null)
      setCreatingOpen(false)
    } catch (error) {
      toast.error(toApiError(error).message)
    }
  }

  const openRolesDialog = (target: User) => {
    setPendingRole(target.roles?.[0] ?? null)
    setRolesTarget(target)
  }

  const onSaveRoles = async () => {
    try {
      await syncRoles(pendingRole ? [pendingRole] : [])
      toast.success(t('users.rolesUpdatedToast'))
      setRolesTarget(null)
    } catch (error) {
      toast.error(toApiError(error).message)
    }
  }

  const openSkillsDialog = (target: User) => {
    setPendingSkillIds(target.skills?.map((skill) => skill.id) ?? [])
    setSkillsTarget(target)
  }

  const onSaveSkills = async () => {
    try {
      await syncSkills(pendingSkillIds)
      toast.success(t('users.skillsUpdatedToast'))
      setSkillsTarget(null)
    } catch (error) {
      toast.error(toApiError(error).message)
    }
  }

  const onDelete = async (target: User) => {
    if (!window.confirm(t('users.deleteConfirm', { name: target.name }))) return

    try {
      await remove(target.id)
      toast.success(t('users.deletedToast'))
    } catch (error) {
      toast.error(toApiError(error).message)
    }
  }

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl font-semibold tracking-tight">{t('users.title')}</h1>
          <p className="text-muted-foreground text-sm">{t('users.subtitle')}</p>
        </div>
        {canManageUsers && (
          <Button
            onClick={() => {
              createForm.reset()
              setCreatingRole(null)
              setCreatingOpen(true)
            }}
          >
            <PlusIcon className="size-4" />
            {t('users.newUser')}
          </Button>
        )}
      </div>

      {isLoading && (
        <div className="space-y-2">
          {Array.from({ length: 5 }).map((_, index) => (
            <Skeleton key={index} className="h-14 w-full" />
          ))}
        </div>
      )}

      {isError && (
        <EmptyState
          icon={UsersIcon}
          title={t('users.loadErrorTitle')}
          description={t('users.loadErrorDescription')}
        />
      )}

      {!isLoading && !isError && data && data.users.length === 0 && (
        <EmptyState icon={UsersIcon} title={t('users.emptyTitle')} />
      )}

      {!isLoading && !isError && data && data.users.length > 0 && (
        <>
          <div className="overflow-hidden rounded-lg border">
            <table className="w-full text-sm">
              <thead className="bg-muted/50 text-muted-foreground text-left">
                <tr>
                  <th className="px-4 py-3 font-medium">{t('users.columnName')}</th>
                  <th className="px-4 py-3 font-medium">{t('users.columnEmail')}</th>
                  <th className="px-4 py-3 font-medium">{t('users.columnRoles')}</th>
                  <th className="px-4 py-3 font-medium" />
                </tr>
              </thead>
              <tbody className="divide-border divide-y">
                {data.users.map((rowUser) => (
                  <tr key={rowUser.id}>
                    <td className="px-4 py-3 font-medium">{rowUser.name}</td>
                    <td className="text-muted-foreground px-4 py-3">{rowUser.email}</td>
                    <td className="px-4 py-3">
                      <div className="flex flex-wrap gap-1">
                        {(rowUser.roles ?? []).map((role) => (
                          <Badge key={role} variant="secondary">
                            {role}
                          </Badge>
                        ))}
                      </div>
                    </td>
                    <td className="px-4 py-3">
                      <div className="flex justify-end gap-1">
                        {canManageUsers && (
                          <Button
                            variant="ghost"
                            size="icon"
                            aria-label={t('users.editRoles')}
                            disabled={rowUser.id === currentUser?.id}
                            onClick={() => openRolesDialog(rowUser)}
                          >
                            <ShieldIcon className="size-4" />
                          </Button>
                        )}
                        {canManageSkills && (
                          <Button
                            variant="ghost"
                            size="icon"
                            aria-label={t('users.editSkills')}
                            onClick={() => openSkillsDialog(rowUser)}
                          >
                            <WrenchIcon className="size-4" />
                          </Button>
                        )}
                        {canManageUsers && (
                          <Button
                            variant="ghost"
                            size="icon"
                            aria-label={t('common.delete')}
                            disabled={rowUser.id === currentUser?.id}
                            onClick={() => onDelete(rowUser)}
                          >
                            <TrashIcon className="size-4" />
                          </Button>
                        )}
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

      {/* Create user */}
      <Dialog open={creatingOpen} onOpenChange={setCreatingOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{t('users.newUser')}</DialogTitle>
          </DialogHeader>
          <form onSubmit={createForm.handleSubmit(onCreate)} noValidate className="space-y-4">
            <div className="space-y-2">
              <Label htmlFor="name">{t('users.form.name')}</Label>
              <Input id="name" aria-invalid={!!createForm.formState.errors.name} {...createForm.register('name')} />
              {createForm.formState.errors.name && (
                <p className="text-destructive text-sm">{createForm.formState.errors.name.message}</p>
              )}
            </div>
            <div className="space-y-2">
              <Label htmlFor="email">{t('auth.email')}</Label>
              <Input
                id="email"
                type="email"
                aria-invalid={!!createForm.formState.errors.email}
                {...createForm.register('email')}
              />
              {createForm.formState.errors.email && (
                <p className="text-destructive text-sm">{createForm.formState.errors.email.message}</p>
              )}
            </div>
            <div className="space-y-2">
              <Label htmlFor="password">{t('users.form.initialPassword')}</Label>
              <Input
                id="password"
                type="password"
                aria-invalid={!!createForm.formState.errors.password}
                {...createForm.register('password')}
              />
              {createForm.formState.errors.password && (
                <p className="text-destructive text-sm">{createForm.formState.errors.password.message}</p>
              )}
            </div>
            <div className="space-y-2">
              <Label>{t('users.form.role')}</Label>
              <div className="flex flex-wrap gap-3">
                <label className="flex items-center gap-2 text-sm">
                  <input
                    type="radio"
                    name="creating-role"
                    className="border-input size-4"
                    checked={creatingRole === null}
                    onChange={() => setCreatingRole(null)}
                  />
                  {t('users.form.noRole')}
                </label>
                {assignableRoles.map((role) => (
                  <label key={role} className="flex items-center gap-2 text-sm">
                    <input
                      type="radio"
                      name="creating-role"
                      className="border-input size-4"
                      checked={creatingRole === role}
                      onChange={() => setCreatingRole(role)}
                    />
                    {role}
                  </label>
                ))}
              </div>
            </div>
            <DialogFooter>
              <Button type="submit" disabled={creating}>
                {creating ? t('common.saving') : t('common.save')}
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>

      {/* Edit roles */}
      <Dialog open={rolesTarget !== null} onOpenChange={(open) => !open && setRolesTarget(null)}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{t('users.editRolesFor', { name: rolesTarget?.name ?? '' })}</DialogTitle>
          </DialogHeader>
          <div className="flex flex-wrap gap-3">
            <label className="flex items-center gap-2 text-sm">
              <input
                type="radio"
                name="pending-role"
                className="border-input size-4"
                checked={pendingRole === null}
                onChange={() => setPendingRole(null)}
              />
              {t('users.form.noRole')}
            </label>
            {assignableRoles.map((role) => (
              <label key={role} className="flex items-center gap-2 text-sm">
                <input
                  type="radio"
                  name="pending-role"
                  className="border-input size-4"
                  checked={pendingRole === role}
                  onChange={() => setPendingRole(role)}
                />
                {role}
              </label>
            ))}
          </div>
          <DialogFooter>
            <Button onClick={onSaveRoles} disabled={syncingRoles}>
              {syncingRoles ? t('common.saving') : t('common.save')}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Edit skills */}
      <Dialog open={skillsTarget !== null} onOpenChange={(open) => !open && setSkillsTarget(null)}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{t('users.editSkillsFor', { name: skillsTarget?.name ?? '' })}</DialogTitle>
          </DialogHeader>
          {!ticketTypes || ticketTypes.length === 0 ? (
            <p className="text-muted-foreground text-sm">{t('users.noTicketTypesYet')}</p>
          ) : (
            <div className="flex flex-wrap gap-3">
              {ticketTypes.map((ticketType) => (
                <label key={ticketType.id} className="flex items-center gap-2 text-sm">
                  <input
                    type="checkbox"
                    className="border-input size-4 rounded"
                    checked={pendingSkillIds.includes(ticketType.id)}
                    onChange={(event) =>
                      setPendingSkillIds((prev) =>
                        event.target.checked
                          ? [...prev, ticketType.id]
                          : prev.filter((id) => id !== ticketType.id),
                      )
                    }
                  />
                  {ticketType.name}
                </label>
              ))}
            </div>
          )}
          <DialogFooter>
            <Button onClick={onSaveSkills} disabled={syncingSkills}>
              {syncingSkills ? t('common.saving') : t('common.save')}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  )
}
