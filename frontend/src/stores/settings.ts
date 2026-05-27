import { defineStore } from 'pinia'
import { ref } from 'vue'
import api from '@/lib/api'
import type { AppSettings, PublicSettings } from '@/types'

export const useSettingsStore = defineStore('settings', () => {
  const publicSettings = ref<PublicSettings | null>(null)
  const loading = ref(false)

  async function fetchPublicSettings() {
    loading.value = true
    try {
      const { data } = await api.get<{ data: PublicSettings }>('/settings/public')
      publicSettings.value = data.data
    } catch {
      publicSettings.value = {
        app_name: 'SEO Reports',
        registration_enabled: true,
        maintenance_mode: false,
        maintenance_message: '',
      }
    } finally {
      loading.value = false
    }
  }

  async function fetchAdminSettings() {
    const { data } = await api.get<{ data: AppSettings }>('/admin/settings')
    return data.data
  }

  async function updateAdminSettings(payload: Partial<AppSettings>) {
    const { data } = await api.put<{ data: AppSettings }>('/admin/settings', payload)
    return data.data
  }

  return {
    publicSettings,
    loading,
    fetchPublicSettings,
    fetchAdminSettings,
    updateAdminSettings,
  }
})
