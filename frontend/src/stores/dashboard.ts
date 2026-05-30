import { defineStore } from 'pinia'
import { ref } from 'vue'
import api from '@/lib/api'
import type { DashboardData } from '@/types'

export const useDashboardStore = defineStore('dashboard', () => {
  const data = ref<DashboardData | null>(null)
  const loading = ref(false)
  const error = ref('')

  async function fetchDashboard(periodStart?: string, periodEnd?: string) {
    loading.value = true
    error.value = ''
    try {
      const params: Record<string, string> = {}
      if (periodStart) params.period_start = periodStart
      if (periodEnd) params.period_end = periodEnd

      const { data: response } = await api.get<{ data: DashboardData }>('/dashboard', { params })
      data.value = response.data
    } catch {
      error.value = 'Не удалось загрузить дашборд'
      data.value = null
    } finally {
      loading.value = false
    }
  }

  return {
    data,
    loading,
    error,
    fetchDashboard,
  }
})
