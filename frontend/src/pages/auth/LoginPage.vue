<template>
  <AuthLayout title="Вход" subtitle="Войдите в SEO Reports">
    <form class="space-y-4" @submit.prevent="submit">
      <div v-if="error" class="rounded-lg bg-red-50 px-4 py-3 text-sm text-error-500">
        {{ error }}
      </div>
      <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700">Email</label>
        <input
          v-model="form.email"
          type="email"
          required
          class="w-full rounded-lg border border-gray-200 px-4 py-2.5 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20"
          placeholder="you@example.com"
        />
      </div>
      <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700">Пароль</label>
        <input
          v-model="form.password"
          type="password"
          required
          class="w-full rounded-lg border border-gray-200 px-4 py-2.5 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20"
        />
      </div>
      <button
        type="submit"
        :disabled="auth.loading"
        class="w-full rounded-lg bg-brand-500 py-2.5 text-sm font-medium text-white hover:bg-brand-600 disabled:opacity-50"
      >
        {{ auth.loading ? 'Вход...' : 'Войти' }}
      </button>
    </form>
    <template #footer>
      <template v-if="settings.publicSettings?.registration_enabled !== false">
        Нет аккаунта?
        <RouterLink to="/register" class="font-medium text-brand-600 hover:underline">
          Зарегистрироваться
        </RouterLink>
      </template>
      <p class="mt-4 text-xs text-gray-400">
        <RouterLink to="/privacy" class="hover:text-gray-600">Конфиденциальность</RouterLink>
        ·
        <RouterLink to="/terms" class="hover:text-gray-600">Условия</RouterLink>
      </p>
    </template>
  </AuthLayout>
</template>

<script setup lang="ts">
import { reactive, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import AuthLayout from '@/layouts/AuthLayout.vue'
import { useAuthStore } from '@/stores/auth'
import { useSettingsStore } from '@/stores/settings'
import type { AxiosError } from 'axios'
import type { ApiError } from '@/types'

const auth = useAuthStore()
const settings = useSettingsStore()
const router = useRouter()
const route = useRoute()
const error = ref('')

const form = reactive({
  email: '',
  password: '',
})

async function submit() {
  error.value = ''
  try {
    await auth.login(form)
    const redirect = (route.query.redirect as string) || '/projects'
    router.push(redirect)
  } catch (e) {
    const err = e as AxiosError<ApiError>
    error.value = err.response?.data?.message ?? 'Ошибка входа'
  }
}
</script>
