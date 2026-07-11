import { useState } from 'react'
import { zodResolver } from '@hookform/resolvers/zod'
import { useForm } from 'react-hook-form'
import { useMutation } from '@tanstack/react-query'
import { Link, useNavigate, useSearchParams } from 'react-router'
import { useTranslation } from 'react-i18next'
import { z } from 'zod'

import { resetPassword } from '@/api/auth'
import { toApiError } from '@/lib/api-client'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'

function useResetPasswordSchema() {
  const { t } = useTranslation()
  return z
    .object({
      password: z.string().min(8, t('auth.passwordMinLength')),
      password_confirmation: z.string(),
    })
    .refine((data) => data.password === data.password_confirmation, {
      message: t('auth.passwordsDontMatch'),
      path: ['password_confirmation'],
    })
}

type FormValues = z.infer<ReturnType<typeof useResetPasswordSchema>>

export function ResetPasswordPage() {
  const { t } = useTranslation()
  const [searchParams] = useSearchParams()
  const navigate = useNavigate()
  const [formError, setFormError] = useState<string | null>(null)
  const schema = useResetPasswordSchema()

  // The backend's reset-password email links here with these three params
  // (see AppServiceProvider::boot()'s ResetPassword::createUrlUsing()).
  const token = searchParams.get('token') ?? ''
  const email = searchParams.get('email') ?? ''
  const tenant = searchParams.get('tenant') ?? undefined

  const {
    register,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: { password: '', password_confirmation: '' },
  })

  const { mutateAsync } = useMutation({ mutationFn: resetPassword })

  const onSubmit = async (values: FormValues) => {
    setFormError(null)
    try {
      await mutateAsync({ ...values, token, email, tenant })
      navigate('/login', { replace: true, state: { resetSuccess: true } })
    } catch (error) {
      setFormError(toApiError(error).message)
    }
  }

  if (!token || !email) {
    return (
      <div className="flex min-h-svh items-center justify-center p-4">
        <Card className="w-full max-w-sm">
          <CardHeader>
            <CardTitle className="text-xl">{t('auth.invalidResetLinkTitle')}</CardTitle>
            <CardDescription>{t('auth.invalidResetLinkDescription')}</CardDescription>
          </CardHeader>
          <CardContent>
            <Link to="/forgot-password" className="text-sm hover:underline">
              {t('auth.requestNewLink')}
            </Link>
          </CardContent>
        </Card>
      </div>
    )
  }

  return (
    <div className="flex min-h-svh items-center justify-center p-4">
      <Card className="w-full max-w-sm">
        <CardHeader>
          <CardTitle className="text-xl">{t('auth.resetTitle')}</CardTitle>
          <CardDescription>{t('auth.resetDescription', { email })}</CardDescription>
        </CardHeader>
        <CardContent>
          <form onSubmit={handleSubmit(onSubmit)} noValidate className="space-y-4">
            {formError && (
              <Alert variant="destructive" role="alert">
                <AlertDescription>{formError}</AlertDescription>
              </Alert>
            )}

            <div className="space-y-2">
              <Label htmlFor="password">{t('auth.newPassword')}</Label>
              <Input
                id="password"
                type="password"
                autoComplete="new-password"
                aria-invalid={!!errors.password}
                {...register('password')}
              />
              {errors.password && <p className="text-destructive text-sm">{errors.password.message}</p>}
            </div>

            <div className="space-y-2">
              <Label htmlFor="password_confirmation">{t('auth.confirmPassword')}</Label>
              <Input
                id="password_confirmation"
                type="password"
                autoComplete="new-password"
                aria-invalid={!!errors.password_confirmation}
                {...register('password_confirmation')}
              />
              {errors.password_confirmation && (
                <p className="text-destructive text-sm">{errors.password_confirmation.message}</p>
              )}
            </div>

            <Button type="submit" className="w-full" disabled={isSubmitting}>
              {isSubmitting ? t('auth.resetting') : t('auth.resetPassword')}
            </Button>
          </form>
        </CardContent>
      </Card>
    </div>
  )
}
