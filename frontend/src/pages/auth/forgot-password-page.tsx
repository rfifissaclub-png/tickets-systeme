import { useState } from 'react'
import { zodResolver } from '@hookform/resolvers/zod'
import { useForm } from 'react-hook-form'
import { useMutation } from '@tanstack/react-query'
import { Link } from 'react-router'
import { useTranslation } from 'react-i18next'
import { z } from 'zod'

import { forgotPassword } from '@/api/auth'
import { toApiError } from '@/lib/api-client'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'

function useForgotPasswordSchema() {
  const { t } = useTranslation()
  return z.object({
    tenant: z.string().trim().optional(),
    email: z.string().trim().email(t('auth.invalidEmail')),
  })
}

type FormValues = z.infer<ReturnType<typeof useForgotPasswordSchema>>

export function ForgotPasswordPage() {
  const { t } = useTranslation()
  const [message, setMessage] = useState<string | null>(null)
  const [formError, setFormError] = useState<string | null>(null)
  const schema = useForgotPasswordSchema()

  const {
    register,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = useForm<FormValues>({ resolver: zodResolver(schema), defaultValues: { tenant: '', email: '' } })

  const { mutateAsync } = useMutation({ mutationFn: forgotPassword })

  const onSubmit = async (values: FormValues) => {
    setFormError(null)
    setMessage(null)
    try {
      setMessage(await mutateAsync({ ...values, tenant: values.tenant || undefined }))
    } catch (error) {
      setFormError(toApiError(error).message)
    }
  }

  return (
    <div className="flex min-h-svh items-center justify-center p-4">
      <Card className="w-full max-w-sm">
        <CardHeader>
          <CardTitle className="text-xl">{t('auth.forgotTitle')}</CardTitle>
          <CardDescription>{t('auth.forgotDescription')}</CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          {message ? (
            <Alert variant="success">
              <AlertDescription>{message}</AlertDescription>
            </Alert>
          ) : (
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
              <Button type="submit" className="w-full" disabled={isSubmitting}>
                {isSubmitting ? t('auth.sending') : t('auth.sendResetLink')}
              </Button>
            </form>
          )}
          <p className="text-muted-foreground text-center text-sm">
            <Link to="/login" className="hover:underline">
              {t('auth.backToSignIn')}
            </Link>
          </p>
        </CardContent>
      </Card>
    </div>
  )
}
