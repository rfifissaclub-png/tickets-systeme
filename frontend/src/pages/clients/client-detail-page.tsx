import { useParams, useNavigate } from 'react-router'
import { Link } from 'react-router'
import { useTranslation } from 'react-i18next'
import { UsersIcon } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { EmptyState } from '@/components/common/empty-state'
import { useClient } from '@/hooks/use-clients'
// TicketList placeholder removed; not used
import ClientContactsList from '@/components/clients/client-contacts-list'

export function ClientDetailPage() {
  const { t } = useTranslation()
  const { clientId } = useParams<{ clientId: string }>()
  const navigate = useNavigate()
  const { data: client, isLoading, isError } = useClient(clientId)

  if (isLoading) return <div>{t('common.loading')}</div>

  if (isError || !client) {
    return (
      <EmptyState
        icon={UsersIcon}
        title={t('clients.notFoundTitle')}
        description={t('clients.notFoundDescription')}
        action={
          <Button asChild variant="outline">
            <Link to="/clients">{t('clients.backToList')}</Link>
          </Button>
        }
      />
    )
  }

  return (
    <div>
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-semibold">{client.name}</h1>
        <div>
          <Button onClick={() => navigate(-1)} variant="outline">{t('common.back')}</Button>
        </div>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>{t('clients.details')}</CardTitle>
        </CardHeader>
        <CardContent>
          <p>{client.email}</p>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>{t('clients.contacts.title')}</CardTitle>
        </CardHeader>
        <CardContent>
          <ClientContactsList clientId={client.id} />
        </CardContent>
      </Card>

      {/* Placeholder: tickets created by this client */}
      {/* <TicketList clientId={client.id} /> */}
    </div>
  )
}

export default ClientDetailPage
