import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api, { ensureCsrfCookie } from '@/lib/api'
import type { User } from '@/types'

export const useAuthStore = defineStore('auth', () => {
  const user = ref<User | null>(null)
  const loading = ref(false)
  const initialized = ref(false)

  const isAuthenticated = computed(() => user.value !== null)

  async function fetchUser() {
    try {
      const { data } = await api.get<{ user: User }>('/user')
      user.value = data.user
    } catch {
      user.value = null
    } finally {
      initialized.value = true
    }
  }

  async function register(payload: {
    name: string
    email: string
    password: string
    password_confirmation: string
  }) {
    loading.value = true
    try {
      await ensureCsrfCookie()
      const { data } = await api.post<{ user: User }>('/register', payload)
      user.value = data.user
    } finally {
      loading.value = false
    }
  }

  async function login(payload: { email: string; password: string; remember?: boolean }) {
    loading.value = true
    try {
      await ensureCsrfCookie()
      const { data } = await api.post<{ user: User }>('/login', payload)
      user.value = data.user
    } finally {
      loading.value = false
    }
  }

  async function logout() {
    await api.post('/logout')
    user.value = null
  }

  return {
    user,
    loading,
    initialized,
    isAuthenticated,
    fetchUser,
    register,
    login,
    logout,
  }
})
