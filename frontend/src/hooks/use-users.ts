import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'

import {
  createUser,
  deleteUser,
  fetchUsers,
  syncUserRoles,
  syncUserSkills,
  updateUser,
  type CreateUserPayload,
  type UpdateUserPayload,
  type UserListParams,
} from '@/api/users'
import type { RoleName } from '@/types/models'

export const userKeys = {
  all: ['users'] as const,
  lists: () => [...userKeys.all, 'list'] as const,
  list: (params: UserListParams) => [...userKeys.lists(), params] as const,
}

export function useUsers(params: UserListParams = {}) {
  return useQuery({
    queryKey: userKeys.list(params),
    queryFn: () => fetchUsers(params),
    placeholderData: (previousData) => previousData,
  })
}

export function useCreateUser() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (payload: CreateUserPayload) => createUser(payload),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: userKeys.lists() }),
  })
}

export function useUpdateUser(id: string) {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (payload: UpdateUserPayload) => updateUser(id, payload),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: userKeys.lists() }),
  })
}

export function useDeleteUser() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (id: string) => deleteUser(id),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: userKeys.lists() }),
  })
}

export function useSyncUserRoles(id: string) {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (roles: RoleName[]) => syncUserRoles(id, roles),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: userKeys.lists() }),
  })
}

export function useSyncUserSkills(id: string) {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (ticketTypeIds: string[]) => syncUserSkills(id, ticketTypeIds),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: userKeys.lists() }),
  })
}
