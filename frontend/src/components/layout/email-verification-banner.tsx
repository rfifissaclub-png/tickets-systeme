import { useMutation } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'

import { resendVerificationEmail } from '@/api/auth'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { toApiError } from '@/lib/api-client'
import { useAuth } from '@/hooks/use-auth'

/**
 * Shown app-wide (mounted once in AppShell) rather than gating any
 * particular page: nothing in this app's routes/middleware actually
 * requires a verified email (no `verified` middleware anywhere in
 * routes/api_v1.php), so this is purely a reminder + self-service resend,
 * not an access gate.
 */
export function EmailVerificationBanner() {
  const { t } = useTranslation()
  const { user } = useAuth()
  const { mutate, isPending } = useMutation({ mutationFn: resendVerificationEmail })

  if (!user || user.email_verified_at) {
    return null
  }

  return (
    <Alert variant="warning" className="rounded-none border-x-0 border-t-0">
      <AlertDescription className="flex flex-wrap items-center justify-between gap-2">
        <span>{t('auth.verifyEmailBanner')}</span>
        <Button
          type="button"
          variant="outline"
          size="sm"
          disabled={isPending}
          onClick={() =>
            mutate(undefined, {
              onSuccess: (message) => toast.success(message),
              onError: (error) => toast.error(toApiError(error).message),
            })
          }
        >
          {isPending ? t('auth.sending') : t('auth.resendVerification')}
        </Button>
      </AlertDescription>
    </Alert>
  )
}
