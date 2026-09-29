import { render, screen } from '@testing-library/react'
import { describe, expect, it, vi } from 'vitest'
import { MemoryRouter, Route, Routes } from 'react-router'

vi.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (key: string) => ({
      'clients.notFoundTitle': 'Client not found',
      'clients.notFoundDescription': 'This client may not be available.',
      'clients.backToList': 'Back to clients',
    })[key] ?? key,
  }),
}))

vi.mock('@/hooks/use-clients', () => ({
  useClient: () => ({ data: undefined, isLoading: false, isError: true }),
}))

import ClientDetailPage from './client-detail-page'

describe('ClientDetailPage', () => {
  it('shows a translated not-found state when the client request fails', () => {
    render(
      <MemoryRouter initialEntries={['/clients/missing']}>
        <Routes>
          <Route path="/clients/:clientId" element={<ClientDetailPage />} />
        </Routes>
      </MemoryRouter>,
    )

    expect(screen.getByRole('heading', { name: 'Client not found' })).toBeDefined()
    expect(screen.getByText('This client may not be available.')).toBeDefined()
    expect(screen.getByRole('link', { name: 'Back to clients' }).getAttribute('href')).toBe('/clients')
    expect(screen.queryByText('loading')).toBeNull()
  })
})