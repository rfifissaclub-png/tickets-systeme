import { useQuery } from '@tanstack/react-query'
import { Link, useSearchParams } from 'react-router'
import { useTranslation } from 'react-i18next'
import { CheckCircle2Icon, XCircleIcon } from 'lucide-react'

import { verifyEmail } from '@/api/auth'
import { toApiError } from '@/lib/api-client'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'

export function VerifyEmailPage() {
  const { t } = useTranslation()
  const [searchParams] = useSearchParams()

  // The backend's verification email links here with these four params —
  // see AppServiceProvider::boot()'s VerifyEmail::createUrlUsing(). All four
  // must be forwarded unchanged to the real API endpoint: `expires` and
  // `signature` are what the `signed` middleware actually validates.
  const id = searchParams.get('id') ?? ''
  const hash = searchParams.get('hash') ?? ''
  const expires = searchParams.get('expires') ?? ''
  const signature = searchParams.get('signature') ?? ''
  const hasAllParams = !!(id && hash && expires && signature)

  const { data, isLoading, isError, error } = useQuery({
    queryKey: ['verify-email', id, hash, expires, signature],
    queryFn: () => verifyEmail({ id, hash, expires, signature }),
    enabled: hasAllParams,
    retry: false,
  })

  return (
    <div className="flex min-h-svh items-center justify-center p-4">
      <Card className="w-full max-w-sm">
        <CardHeader>
          <CardTitle className="text-xl">{t('auth.verifyEmailTitle')}</CardTitle>
        </CardHeader>
        <CardContent className="space-y-4">
          {!hasAllParams && (
            <Alert variant="destructive" role="alert">
              <XCircleIcon className="size-4" />
              <AlertDescription>{t('auth.invalidVerificationLinkDescription')}</AlertDescription>
            </Alert>
          )}

          {hasAllParams && isLoading && (
            <div className="space-y-2">
              <Skeleton className="h-4 w-3/4" />
              <Skeleton className="h-4 w-1/2" />
            </div>
          )}

          {hasAllParams && isError && (
            <Alert variant="destructive" role="alert">
              <XCircleIcon className="size-4" />
              <AlertDescription>{toApiError(error).message}</AlertDescription>
            </Alert>
          )}

          {hasAllParams && data && (
            <Alert variant="success" role="status">
              <CheckCircle2Icon className="size-4" />
              <AlertDescription>{data}</AlertDescription>
            </Alert>
          )}

          <CardDescription>
            <Link to="/login" className="hover:underline">
              {t('auth.backToSignIn')}
            </Link>
          </CardDescription>
        </CardContent>
      </Card>
    </div>
  )
}
