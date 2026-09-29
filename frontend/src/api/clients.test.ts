import { beforeEach, describe, expect, it, vi } from 'vitest'

const { get } = vi.hoisted(() => ({ get: vi.fn() }))

vi.mock('@/lib/api-client', () => ({ apiClient: { get } }))

import { fetchClients } from './clients'

describe('fetchClients', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('unwraps the Laravel paginator nested in the client response', async () => {
    const client = {
      id: 'client-1',
      tenant_id: 'tenant-1',
      name: 'Acme Client',
      email: 'client@example.com',
      is_linked: false,
      parent_tenant_id: null,
    }
    get.mockResolvedValue({
      data: {
        data: {
          data: [client],
          current_page: 2,
          per_page: 10,
          total: 11,
          last_page: 2,
        },
      },
    })

    await expect(fetchClients({ page: 2, per_page: 10 })).resolves.toEqual({
      clients: [client],
      meta: { current_page: 2, per_page: 10, total: 11, last_page: 2 },
    })
    expect(get).toHaveBeenCalledWith('/clients', { params: { page: 2, per_page: 10 } })
  })
})