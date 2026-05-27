import { defineStore } from 'pinia'
import { ref } from 'vue'
import api from '@/lib/api'
import type { WorkItem } from '@/types'

export const useWorkItemsStore = defineStore('workItems', () => {
  const items = ref<WorkItem[]>([])
  const loading = ref(false)

  async function fetchItems(projectId: number, params?: { from?: string; to?: string }) {
    loading.value = true
    try {
      const { data } = await api.get<{ data: WorkItem[] }>(`/projects/${projectId}/work-items`, {
        params,
      })
      items.value = data.data
      return data.data
    } finally {
      loading.value = false
    }
  }

  async function createItem(
    projectId: number,
    payload: { work_date: string; category: string; description: string },
  ) {
    const { data } = await api.post<{ data: WorkItem }>(`/projects/${projectId}/work-items`, payload)
    items.value = [data.data, ...items.value]
    return data.data
  }

  async function updateItem(
    projectId: number,
    id: number,
    payload: Partial<{ work_date: string; category: string; description: string }>,
  ) {
    const { data } = await api.patch<{ data: WorkItem }>(
      `/projects/${projectId}/work-items/${id}`,
      payload,
    )
    items.value = items.value.map((item) => (item.id === id ? data.data : item))
    return data.data
  }

  async function deleteItem(projectId: number, id: number) {
    await api.delete(`/projects/${projectId}/work-items/${id}`)
    items.value = items.value.filter((item) => item.id !== id)
  }

  return {
    items,
    loading,
    fetchItems,
    createItem,
    updateItem,
    deleteItem,
  }
})
