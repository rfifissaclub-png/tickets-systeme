import { render, screen } from '@testing-library/react'
import { describe, it, vi, expect } from 'vitest'

vi.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (key: string) => ({ 'clients.title': 'Clients', 'clients.new': 'New client' })[key] ?? key,
  }),
}))

vi.mock('@/hooks/use-clients', () => ({
  useClients: (_params: any) => ({ data: { clients: [], meta: null }, isLoading: false }),
  useCreateClient: () => ({ mutateAsync: async () => {}, isPending: false }),
  useDeleteClient: () => ({ mutateAsync: async () => {}, isPending: false }),
}))

import ClientsPage from './clients-page'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'

describe('ClientsPage', () => {
  it('renders title and new button', () => {
    const qc = new QueryClient()
    render(
      <QueryClientProvider client={qc}>
        <ClientsPage />
      </QueryClientProvider>
    )
    expect(screen.getByRole('heading', { name: 'Clients' })).toBeDefined()
    expect(screen.getByRole('button', { name: 'New client' })).toBeDefined()
  })
})
