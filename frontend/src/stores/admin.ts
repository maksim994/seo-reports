import { defineStore } from 'pinia'
import { ref } from 'vue'
import api from '@/lib/api'
import type { PaginatedResponse, Project, User } from '@/types'

export const useAdminStore = defineStore('admin', () => {
  const users = ref<User[]>([])
  const projects = ref<Project[]>([])
  const usersMeta = ref({ current_page: 1, last_page: 1, total: 0 })
  const projectsMeta = ref({ current_page: 1, last_page: 1, total: 0 })
  const loading = ref(false)

  async function fetchUsers(params: Record<string, string | number | boolean> = {}) {
    loading.value = true
    try {
      const { data } = await api.get<PaginatedResponse<User>>('/admin/users', { params })
      users.value = data.data
      usersMeta.value = {
        current_page: data.current_page,
        last_page: data.last_page,
        total: data.total,
      }
    } finally {
      loading.value = false
    }
  }

  async function updateUser(id: number, payload: Partial<User>) {
    const { data } = await api.patch<{ data: User }>(`/admin/users/${id}`, payload)
    const index = users.value.findIndex((u) => u.id === id)
    if (index !== -1) {
      users.value[index] = data.data
    }
    return data.data
  }

  async function deleteUser(id: number) {
    await api.delete(`/admin/users/${id}`)
    users.value = users.value.filter((u) => u.id !== id)
  }

  async function fetchProjects(params: Record<string, string | number | boolean> = {}) {
    loading.value = true
    try {
      const { data } = await api.get<PaginatedResponse<Project>>('/admin/projects', { params })
      projects.value = data.data
      projectsMeta.value = {
        current_page: data.current_page,
        last_page: data.last_page,
        total: data.total,
      }
    } finally {
      loading.value = false
    }
  }

  return {
    users,
    projects,
    usersMeta,
    projectsMeta,
    loading,
    fetchUsers,
    updateUser,
    deleteUser,
    fetchProjects,
  }
})
