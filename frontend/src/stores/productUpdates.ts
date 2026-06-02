import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import api from '@/lib/api'
import { matchesAnyRoutePattern } from '@/lib/matchRoutePattern'
import type { ProductUpdate, ProductUpdatesPayload } from '@/types'

export const useProductUpdatesStore = defineStore('productUpdates', () => {
  const updates = ref<ProductUpdate[]>([])
  const unreadCount = ref(0)
  const loading = ref(false)
  const loaded = ref(false)
  const error = ref<string | null>(null)

  const unreadUpdates = computed(() => updates.value.filter((item) => !item.is_read))

  async function fetchUpdates() {
    loading.value = true
    error.value = null
    try {
      const { data } = await api.get<{ data: ProductUpdatesPayload }>('/product-updates')
      updates.value = data.data.updates
      unreadCount.value = data.data.unread_count
      loaded.value = true
    } catch {
      updates.value = []
      unreadCount.value = 0
      loaded.value = false
      error.value =
        'Не удалось загрузить обновления. Если вы только что обновили код — выполните миграции: docker compose exec app php artisan migrate'
    } finally {
      loading.value = false
    }
  }

  function applyPayload(payload: ProductUpdatesPayload) {
    updates.value = payload.updates
    unreadCount.value = payload.unread_count
    loaded.value = true
  }

  async function dismiss(id: string) {
    const { data } = await api.post<{ data: ProductUpdatesPayload }>(
      `/product-updates/${encodeURIComponent(id)}/dismiss`,
    )
    applyPayload(data.data)
  }

  async function dismissAll() {
    const { data } = await api.post<{ data: ProductUpdatesPayload }>(
      '/product-updates/dismiss-all',
    )
    applyPayload(data.data)
  }

  function unreadForRoute(path: string): ProductUpdate[] {
    return unreadUpdates.value.filter(
      (item) => item.context_paths.length > 0 && matchesAnyRoutePattern(path, item.context_paths),
    )
  }

  function reset() {
    updates.value = []
    unreadCount.value = 0
    loaded.value = false
    error.value = null
  }

  return {
    updates,
    unreadCount,
    unreadUpdates,
    loading,
    loaded,
    error,
    fetchUpdates,
    dismiss,
    dismissAll,
    unreadForRoute,
    reset,
  }
})
