import { defineStore } from 'pinia'
import { ref } from 'vue'
import api from '@/lib/api'
import type { Project } from '@/types'

export const useProjectsStore = defineStore('projects', () => {
  const projects = ref<Project[]>([])
  const loading = ref(false)

  async function fetchProjects(analyticsFilter?: boolean | null) {
    loading.value = true
    try {
      const params: Record<string, string> = {}
      if (analyticsFilter !== null && analyticsFilter !== undefined) {
        params.analytics = String(analyticsFilter)
      }
      const { data } = await api.get<{ data: Project[] }>('/projects', { params })
      projects.value = data.data
    } finally {
      loading.value = false
    }
  }

  async function createProject(payload: {
    name: string
    domain?: string
    promotion_start_date?: string
  }) {
    const { data } = await api.post<{ data: Project }>('/projects', payload)
    projects.value.unshift(data.data)
    return data.data
  }

  async function updateProject(id: number, payload: Partial<Project>) {
    const { data } = await api.put<{ data: Project }>(`/projects/${id}`, payload)
    const index = projects.value.findIndex((p) => p.id === id)
    if (index !== -1) projects.value[index] = data.data
    return data.data
  }

  async function deleteProject(id: number) {
    await api.delete(`/projects/${id}`)
    projects.value = projects.value.filter((p) => p.id !== id)
  }

  return {
    projects,
    loading,
    fetchProjects,
    createProject,
    updateProject,
    deleteProject,
  }
})
