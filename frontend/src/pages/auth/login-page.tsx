import { useState } from 'react'
import { zodResolver } from '@hookform/resolvers/zod'
import { useForm } from 'react-hook-form'
import { useMutation } from '@tanstack/react-query'
import { Link, useLocation, useNavigate } from 'react-router'
import { useTranslation } from 'react-i18next'
import { z } from 'zod'
import { TicketIcon } from 'lucide-react'

import { login } from '@/api/auth'
import { toApiError } from '@/lib/api-client'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { useAuthStore } from '@/stores/auth-store'

function useLoginSchema() {
  const { t } = useTranslation()
  return z.object({
    tenant: z.string().trim().optional(),
    email: z.string().trim().email(t('auth.invalidEmail')),
    password: z.string().min(1, t('auth.passwordRequired')),
    remember: z.boolean().optional(),
  })
}

type FormValues = z.infer<ReturnType<typeof useLoginSchema>>

export function LoginPage() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const location = useLocation()
  const setSession = useAuthStore((state) => state.setSession)
  const [formError, setFormError] = useState<string | null>(null)
  const schema = useLoginSchema()

  const {
    register,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: { tenant: '', email: '', password: '', remember: false },
  })

  const { mutateAsync } = useMutation({ mutationFn: login })

  const onSubmit = async (values: FormValues) => {
    setFormError(null)
    try {
      const { token, user } = await mutateAsync({ ...values, tenant: values.tenant || undefined })
      setSession(token, user)
      const redirectTo = (location.state as { from?: string } | null)?.from ?? '/'
      navigate(redirectTo, { replace: true })
    } catch (error) {
      setFormError(toApiError(error).message)
    }
  }

  return (
    <div className="flex min-h-svh items-center justify-center p-4">
      <Card className="w-full max-w-sm">
        <CardHeader className="items-center text-center">
          <TicketIcon className="text-primary mb-2 size-8" aria-hidden="true" />
          <CardTitle className="text-xl">{t('auth.signInTitle')}</CardTitle>
          <CardDescription>{t('auth.signInDescription')}</CardDescription>
        </CardHeader>
        <CardContent>
          <form onSubmit={handleSubmit(onSubmit)} noValidate className="space-y-4">
            {formError && (
              <Alert variant="destructive" role="alert">
                <AlertDescription>{formError}</AlertDescription>
              </Alert>
            )}

            <div className="space-y-2">
              <Label htmlFor="tenant">{t('auth.workspace')}</Label>
              <Input
                id="tenant"
                placeholder={t('auth.workspacePlaceholder')}
                autoComplete="organization"
                {...register('tenant')}
              />
            </div>

            <div className="space-y-2">
              <Label htmlFor="email">{t('auth.email')}</Label>
              <Input
                id="email"
                type="email"
                autoComplete="email"
                aria-invalid={!!errors.email}
                {...register('email')}
              />
              {errors.email && <p className="text-destructive text-sm">{errors.email.message}</p>}
            </div>

            <div className="space-y-2">
              <div className="flex items-center justify-between">
                <Label htmlFor="password">{t('auth.password')}</Label>
                <Link to="/forgot-password" className="text-muted-foreground text-xs hover:underline">
                  {t('auth.forgotPassword')}
                </Link>
              </div>
              <Input
                id="password"
                type="password"
                autoComplete="current-password"
                aria-invalid={!!errors.password}
                {...register('password')}
              />
              {errors.password && <p className="text-destructive text-sm">{errors.password.message}</p>}
            </div>

            <label className="flex items-center gap-2 text-sm">
              <input type="checkbox" className="border-input size-4 rounded" {...register('remember')} />
              {t('auth.rememberMe')}
            </label>

            <Button type="submit" className="w-full" disabled={isSubmitting}>
              {isSubmitting ? t('auth.signingIn') : t('auth.signIn')}
            </Button>
          </form>
        </CardContent>
      </Card>
    </div>
  )
}
