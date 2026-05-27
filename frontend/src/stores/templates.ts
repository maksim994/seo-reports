import { defineStore } from 'pinia'
import { ref } from 'vue'
import api from '@/lib/api'
import type { ReportBlockCatalogItem, ReportTemplate, TemplateBlockItem } from '@/types'

export const useTemplatesStore = defineStore('templates', () => {
  const templates = ref<ReportTemplate[]>([])
  const catalog = ref<ReportBlockCatalogItem[]>([])
  const categories = ref<Record<string, string>>({})
  const loading = ref(false)

  async function fetchTemplates() {
    loading.value = true
    try {
      const { data } = await api.get<{ data: ReportTemplate[] }>('/templates')
      templates.value = data.data
    } finally {
      loading.value = false
    }
  }

  async function fetchCatalog() {
    const { data } = await api.get<{
      data: { blocks: ReportBlockCatalogItem[]; categories: Record<string, string> }
    }>('/report-blocks/catalog')
    catalog.value = data.data.blocks
    categories.value = data.data.categories
  }

  async function fetchTemplate(id: number) {
    const { data } = await api.get<{ data: ReportTemplate }>(`/templates/${id}`)
    return data.data
  }

  async function createTemplate(payload: {
    name: string
    description?: string
    blocks?: TemplateBlockItem[]
  }) {
    const { data } = await api.post<{ data: ReportTemplate }>('/templates', payload)
    templates.value.unshift(data.data)
    return data.data
  }

  async function updateTemplate(
    id: number,
    payload: { name?: string; description?: string | null; blocks?: TemplateBlockItem[] },
  ) {
    const { data } = await api.put<{ data: ReportTemplate }>(`/templates/${id}`, payload)
    const index = templates.value.findIndex((t) => t.id === id)
    if (index !== -1) {
      templates.value[index] = { ...templates.value[index], ...data.data }
    }
    return data.data
  }

  async function deleteTemplate(id: number) {
    await api.delete(`/templates/${id}`)
    templates.value = templates.value.filter((t) => t.id !== id)
  }

  async function uploadLogo(id: number, file: File) {
    const form = new FormData()
    form.append('logo', file)
    const { data } = await api.post<{ data: ReportTemplate }>(`/templates/${id}/logo`, form, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    const index = templates.value.findIndex((t) => t.id === id)
    if (index !== -1) {
      templates.value[index] = { ...templates.value[index], ...data.data }
    }
    return data.data
  }

  async function deleteLogo(id: number) {
    const { data } = await api.delete<{ data: ReportTemplate }>(`/templates/${id}/logo`)
    const index = templates.value.findIndex((t) => t.id === id)
    if (index !== -1) {
      templates.value[index] = { ...templates.value[index], ...data.data }
    }
    return data.data
  }

  return {
    templates,
    catalog,
    categories,
    loading,
    fetchTemplates,
    fetchCatalog,
    fetchTemplate,
    createTemplate,
    updateTemplate,
    deleteTemplate,
    uploadLogo,
    deleteLogo,
  }
})
