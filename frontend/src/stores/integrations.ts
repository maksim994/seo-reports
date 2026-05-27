import { defineStore } from 'pinia'
import { ref } from 'vue'
import api from '@/lib/api'
import type { Integration, IntegrationProviderMeta, IntegrationResource } from '@/types'

export const useIntegrationsStore = defineStore('integrations', () => {
  const providers = ref<IntegrationProviderMeta[]>([])
  const integrations = ref<Integration[]>([])
  const loading = ref(false)
  const connecting = ref<string | null>(null)

  async function fetchProviders() {
    const { data } = await api.get<{ data: IntegrationProviderMeta[] }>('/integrations/providers')
    providers.value = data.data
  }

  async function fetchIntegrations() {
    loading.value = true
    try {
      const { data } = await api.get<{ data: Integration[] }>('/integrations')
      integrations.value = data.data
    } finally {
      loading.value = false
    }
  }

  async function connect(provider: string) {
    connecting.value = provider
    try {
      const { data } = await api.post<{ data: { redirect_url: string } }>(
        `/integrations/${provider}/connect`,
      )
      window.location.href = data.data.redirect_url
    } finally {
      connecting.value = null
    }
  }

  async function connectApiKey(provider: string, userId: string, apiKey: string) {
    connecting.value = provider
    try {
      await api.post(`/integrations/${provider}/api-key`, {
        user_id: userId,
        api_key: apiKey,
      })
      await fetchIntegrations()
    } finally {
      connecting.value = null
    }
  }

  async function disconnect(id: number) {
    await api.delete(`/integrations/${id}`)
    integrations.value = integrations.value.filter((i) => i.id !== id)
  }

  function findByProvider(provider: string) {
    return integrations.value.find((i) => i.provider === provider)
  }

  async function fetchResources(integrationId: number): Promise<IntegrationResource[]> {
    const { data } = await api.get<{ data: IntegrationResource[] }>(
      `/integrations/${integrationId}/resources`,
    )
    return data.data
  }

  return {
    providers,
    integrations,
    loading,
    connecting,
    fetchProviders,
    fetchIntegrations,
    connect,
    connectApiKey,
    disconnect,
    findByProvider,
    fetchResources,
  }
})
