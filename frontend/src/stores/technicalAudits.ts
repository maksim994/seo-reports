import { defineStore } from 'pinia'
import { ref } from 'vue'
import api from '@/lib/api'
import type { TechnicalAuditJob } from '@/types'

export const useTechnicalAuditsStore = defineStore('technicalAudits', () => {
  const audits = ref<TechnicalAuditJob[]>([])
  const loading = ref(false)

  async function fetchAudits() {
    loading.value = true
    try {
      const { data } = await api.get<{ data: TechnicalAuditJob[] }>('/technical-audits')
      audits.value = data.data
    } finally {
      loading.value = false
    }
  }

  async function fetchProjectAudits(projectId: number) {
    const { data } = await api.get<{ data: TechnicalAuditJob[] }>(
      `/projects/${projectId}/technical-audits`,
    )
    return data.data
  }

  async function fetchAudit(id: number) {
    const { data } = await api.get<{ data: TechnicalAuditJob }>(`/technical-audits/${id}`)
    return data.data
  }

  async function startAudit(
    projectId: number,
    payload: {
      site_url: string
      site_name?: string
      sample_urls?: string[]
      crawl_depth?: 'light' | 'sitemap'
      lang?: 'ru' | 'en'
    },
  ) {
    const { data } = await api.post<{ data: TechnicalAuditJob }>(
      `/projects/${projectId}/technical-audits`,
      payload,
    )
    return data.data
  }

  function downloadUrl(id: number, format: 'json' | 'md' | 'docx') {
    return `/api/technical-audits/${id}/download/${format}`
  }

  async function deleteAudit(id: number) {
    await api.delete(`/technical-audits/${id}`)
    audits.value = audits.value.filter((audit) => audit.id !== id)
  }

  async function syncAudit(id: number) {
    const { data } = await api.post<{ data: TechnicalAuditJob; message?: string }>(
      `/technical-audits/${id}/sync`,
    )
    return data
  }

  async function importAuditJson(id: number, file: File) {
    const formData = new FormData()
    formData.append('file', file)
    const { data } = await api.post<{ data: TechnicalAuditJob; message?: string }>(
      `/technical-audits/${id}/import`,
      formData,
      { headers: { 'Content-Type': 'multipart/form-data' } },
    )
    return data
  }

  return {
    audits,
    loading,
    fetchAudits,
    fetchProjectAudits,
    fetchAudit,
    startAudit,
    syncAudit,
    importAuditJson,
    downloadUrl,
    deleteAudit,
  }
})
