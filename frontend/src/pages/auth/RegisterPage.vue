<template>
  <AuthLayout title="Регистрация" subtitle="Создайте аккаунт SEO Reports">
    <form class="space-y-4" @submit.prevent="submit">
      <div v-if="error" class="rounded-lg bg-red-50 px-4 py-3 text-sm text-error-500">
        {{ error }}
      </div>
      <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700">Имя</label>
        <input
          v-model="form.name"
          type="text"
          required
          class="w-full rounded-lg border border-gray-200 px-4 py-2.5 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20"
        />
      </div>
      <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700">Email</label>
        <input
          v-model="form.email"
          type="email"
          required
          class="w-full rounded-lg border border-gray-200 px-4 py-2.5 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20"
        />
      </div>
      <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700">Пароль</label>
        <input
          v-model="form.password"
          type="password"
          required
          minlength="8"
          class="w-full rounded-lg border border-gray-200 px-4 py-2.5 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20"
        />
      </div>
      <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700">Подтверждение пароля</label>
        <input
          v-model="form.password_confirmation"
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
        {{ auth.loading ? 'Регистрация...' : 'Зарегистрироваться' }}
      </button>
    </form>
    <template #footer>
      Уже есть аккаунт?
      <RouterLink to="/login" class="font-medium text-brand-600 hover:underline">
        Войти
      </RouterLink>
    </template>
  </AuthLayout>
</template>

<script setup lang="ts">
import { reactive, ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import AuthLayout from '@/layouts/AuthLayout.vue'
import { useAuthStore } from '@/stores/auth'
import type { AxiosError } from 'axios'
import type { ApiError } from '@/types'

const auth = useAuthStore()
const router = useRouter()
const error = ref('')

const form = reactive({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
})

async function submit() {
  error.value = ''
  try {
    await auth.register(form)
    router.push('/projects')
  } catch (e) {
    const err = e as AxiosError<ApiError>
    const errors = err.response?.data?.errors
    if (errors) {
      error.value = Object.values(errors).flat().join(' ')
    } else {
      error.value = err.response?.data?.message ?? 'Ошибка регистрации'
    }
  }
}
</script>
