import { defineStore } from 'pinia'
import { ref } from 'vue'
import api from '@/lib/api'
import type {
  AnalyticsDashboardConfig,
  AnalyticsDashboardData,
  DashboardWidget,
} from '@/types'

export const useProjectAnalyticsDashboardStore = defineStore('projectAnalyticsDashboard', () => {
  const config = ref<AnalyticsDashboardConfig | null>(null)
  const data = ref<AnalyticsDashboardData | null>(null)
  const loadingConfig = ref(false)
  const loadingData = ref(false)
  const saving = ref(false)
  const error = ref('')

  async function fetchConfig(projectId: number) {
    loadingConfig.value = true
    error.value = ''
    try {
      const { data: response } = await api.get<{ data: AnalyticsDashboardConfig }>(
        `/projects/${projectId}/analytics-dashboard`,
      )
      config.value = response.data
    } catch {
      error.value = 'Не удалось загрузить настройки дашборда'
      config.value = null
    } finally {
      loadingConfig.value = false
    }
  }

  async function saveConfig(projectId: number, widgets: DashboardWidget[]) {
    saving.value = true
    error.value = ''
    try {
      const { data: response } = await api.put<{ data: { widgets: DashboardWidget[]; is_suggested: boolean } }>(
        `/projects/${projectId}/analytics-dashboard`,
        { widgets },
      )
      if (config.value) {
        config.value.widgets = response.data.widgets
        config.value.is_suggested = response.data.is_suggested
      }
      return response.data.widgets
    } catch {
      error.value = 'Не удалось сохранить дашборд'
      throw new Error('save failed')
    } finally {
      saving.value = false
    }
  }

  async function fetchData(
    projectId: number,
    options?: {
      periodStart?: string
      periodEnd?: string
      widgets?: DashboardWidget[]
    },
  ) {
    loadingData.value = true
    error.value = ''
    try {
      const payload: Record<string, unknown> = {}
      if (options?.periodStart) payload.period_start = options.periodStart
      if (options?.periodEnd) payload.period_end = options.periodEnd
      if (options?.widgets?.length) payload.widgets = options.widgets

      const { data: response } = await api.post<{ data: AnalyticsDashboardData }>(
        `/projects/${projectId}/analytics-dashboard/data`,
        payload,
      )
      data.value = response.data
    } catch {
      error.value = 'Не удалось загрузить данные виджетов'
      data.value = null
    } finally {
      loadingData.value = false
    }
  }

  return {
    config,
    data,
    loadingConfig,
    loadingData,
    saving,
    error,
    fetchConfig,
    saveConfig,
    fetchData,
  }
})
