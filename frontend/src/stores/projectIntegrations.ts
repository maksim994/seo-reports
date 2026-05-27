import { defineStore } from 'pinia'
import { ref } from 'vue'
import api from '@/lib/api'
import type { ProjectIntegrationBinding } from '@/types'

export const useProjectIntegrationsStore = defineStore('projectIntegrations', () => {
  const bindings = ref<ProjectIntegrationBinding[]>([])

  async function fetchBindings(projectId: number) {
    const { data } = await api.get<{ data: ProjectIntegrationBinding[] }>(
      `/projects/${projectId}/integrations`,
    )
    bindings.value = data.data
    return data.data
  }

  async function bind(
    projectId: number,
    payload: {
      integration_id: number
      external_resource_id: string
      external_resource_label?: string | null
      config?: Record<string, unknown>
    },
  ) {
    const { data } = await api.post<{ data: ProjectIntegrationBinding }>(
      `/projects/${projectId}/integrations`,
      payload,
    )
    const existing = bindings.value.findIndex((b) => b.integration_id === payload.integration_id)
    if (existing !== -1) {
      bindings.value[existing] = data.data
    } else {
      bindings.value.push(data.data)
    }
    return data.data
  }

  async function unbind(projectId: number, bindingId: number) {
    await api.delete(`/projects/${projectId}/integrations/${bindingId}`)
    bindings.value = bindings.value.filter((b) => b.id !== bindingId)
  }

  return {
    bindings,
    fetchBindings,
    bind,
    unbind,
  }
})
