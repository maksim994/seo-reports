import { defineStore } from 'pinia'
import { ref } from 'vue'
import api from '@/lib/api'
import type { PublicReportMeta, ReportJob } from '@/types'

export const useReportsStore = defineStore('reports', () => {
  const reports = ref<ReportJob[]>([])
  const loading = ref(false)

  async function fetchReports() {
    loading.value = true
    try {
      const { data } = await api.get<{ data: ReportJob[] }>('/reports')
      reports.value = data.data
    } finally {
      loading.value = false
    }
  }

  async function fetchProjectReports(projectId: number) {
    const { data } = await api.get<{ data: ReportJob[] }>(`/projects/${projectId}/reports`)
    return data.data
  }

  async function fetchReport(id: number) {
    const { data } = await api.get<{ data: ReportJob }>(`/reports/${id}`)
    return data.data
  }

  async function generateReport(
    projectId: number,
    payload: {
      report_template_id: number
      period_start: string
      period_end: string
      compare_period_start?: string | null
      compare_period_end?: string | null
    },
  ) {
    const { data } = await api.post<{ data: ReportJob }>(`/projects/${projectId}/reports`, payload)
    return data.data
  }

  function previewUrl(id: number) {
    return `/api/reports/${id}/preview`
  }

  function downloadUrl(id: number, format: 'html' | 'pdf') {
    return `/api/reports/${id}/download/${format}`
  }

  function publicShareUrl(token: string) {
    return `${window.location.origin}/share/${token}`
  }

  function publicPreviewUrl(token: string) {
    return `/api/public/reports/${token}/preview`
  }

  function publicDownloadUrl(token: string, format: 'html' | 'pdf') {
    return `/api/public/reports/${token}/download/${format}`
  }

  async function enableShare(id: number, shareExpiresAt?: string | null) {
    const payload = shareExpiresAt ? { share_expires_at: shareExpiresAt } : {}
    const { data } = await api.post<{ data: ReportJob }>(`/reports/${id}/share`, payload)
    updateReportInList(data.data)
    return data.data
  }

  async function disableShare(id: number) {
    const { data } = await api.delete<{ data: ReportJob }>(`/reports/${id}/share`)
    updateReportInList(data.data)
    return data.data
  }

  async function regenerateShare(id: number) {
    const { data } = await api.post<{ data: ReportJob }>(`/reports/${id}/share/regenerate`)
    updateReportInList(data.data)
    return data.data
  }

  async function fetchPublicReport(token: string) {
    const { data } = await api.get<{ data: PublicReportMeta }>(`/public/reports/${token}`)
    return data.data
  }

  function updateReportInList(report: ReportJob) {
    const index = reports.value.findIndex((item) => item.id === report.id)
    if (index >= 0) {
      reports.value[index] = report
    }
  }

  async function deleteReport(id: number) {
    await api.delete(`/reports/${id}`)
    reports.value = reports.value.filter((r) => r.id !== id)
  }

  return {
    reports,
    loading,
    fetchReports,
    fetchProjectReports,
    fetchReport,
    generateReport,
    previewUrl,
    downloadUrl,
    publicShareUrl,
    publicPreviewUrl,
    publicDownloadUrl,
    enableShare,
    disableShare,
    regenerateShare,
    fetchPublicReport,
    deleteReport,
  }
})
