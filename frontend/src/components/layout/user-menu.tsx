import { useMutation } from '@tanstack/react-query'
import { useNavigate } from 'react-router'
import { LogOutIcon, UserIcon } from 'lucide-react'
import { toast } from 'sonner'

import { logout as logoutRequest } from '@/api/auth'
import { Avatar, AvatarFallback } from '@/components/ui/avatar'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { useAuth } from '@/hooks/use-auth'
import { useAuthStore } from '@/stores/auth-store'

function initials(name: string): string {
  return name
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase())
    .join('')
}

export function UserMenu() {
  const { user } = useAuth()
  const clearSession = useAuthStore((state) => state.logout)
  const navigate = useNavigate()

  const { mutate: signOut, isPending } = useMutation({
    mutationFn: logoutRequest,
    onSettled: () => {
      // Clear the local session even if the network call failed — a stale
      // token is useless once the user has asked to log out either way.
      clearSession()
      navigate('/login', { replace: true })
    },
    onError: () => toast.error('Logout request failed, but you have been signed out locally.'),
  })

  if (!user) return null

  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <button
          type="button"
          className="flex items-center gap-2 rounded-full outline-none focus-visible:ring-2 focus-visible:ring-ring"
          aria-label="Account menu"
        >
          <Avatar>
            <AvatarFallback>{initials(user.name) || <UserIcon className="size-4" />}</AvatarFallback>
          </Avatar>
        </button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end" className="w-56">
        <DropdownMenuLabel className="flex flex-col">
          <span className="truncate font-medium">{user.name}</span>
          <span className="text-muted-foreground truncate text-xs font-normal">{user.email}</span>
        </DropdownMenuLabel>
        <DropdownMenuSeparator />
        <DropdownMenuItem disabled={isPending} onClick={() => signOut()} variant="destructive">
          <LogOutIcon className="size-4" />
          Log out
        </DropdownMenuItem>
      </DropdownMenuContent>
    </DropdownMenu>
  )
}
