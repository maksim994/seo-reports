<template>
  <div>
    <div class="mb-6">
      <h1 class="text-2xl font-semibold text-gray-900">Личный кабинет</h1>
      <p class="mt-1 text-sm text-gray-500">Имя, email и пароль вашего аккаунта</p>
    </div>

    <div
      v-if="successMessage"
      class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800"
    >
      {{ successMessage }}
    </div>

    <form class="max-w-2xl space-y-6" @submit.prevent="submit">
      <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
        <h2 class="mb-4 text-lg font-semibold text-gray-900">Основные данные</h2>

        <div v-if="error" class="mb-4 rounded-lg bg-red-50 px-4 py-3 text-sm text-error-500">
          {{ error }}
        </div>

        <div class="space-y-4">
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

          <div class="rounded-xl border border-gray-100 bg-gray-50 px-4 py-3 text-sm text-gray-600">
            <div class="flex flex-wrap items-center gap-2">
              <span>Роль:</span>
              <span
                :class="[
                  'inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium',
                  auth.user?.is_admin ? 'bg-purple-50 text-purple-700' : 'bg-gray-100 text-gray-600',
                ]"
              >
                {{ auth.user?.is_admin ? 'Администратор' : 'Пользователь' }}
              </span>
            </div>
          </div>
        </div>
      </section>

      <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
        <h2 class="mb-1 text-lg font-semibold text-gray-900">Смена пароля</h2>
        <p class="mb-4 text-sm text-gray-500">Оставьте поля пустыми, если пароль менять не нужно</p>

        <div class="space-y-4">
          <div>
            <label class="mb-1.5 block text-sm font-medium text-gray-700">Текущий пароль</label>
            <input
              v-model="form.current_password"
              type="password"
              autocomplete="current-password"
              class="w-full rounded-lg border border-gray-200 px-4 py-2.5 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20"
            />
          </div>

          <div>
            <label class="mb-1.5 block text-sm font-medium text-gray-700">Новый пароль</label>
            <input
              v-model="form.password"
              type="password"
              autocomplete="new-password"
              minlength="8"
              class="w-full rounded-lg border border-gray-200 px-4 py-2.5 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20"
            />
          </div>

          <div>
            <label class="mb-1.5 block text-sm font-medium text-gray-700">Подтверждение пароля</label>
            <input
              v-model="form.password_confirmation"
              type="password"
              autocomplete="new-password"
              class="w-full rounded-lg border border-gray-200 px-4 py-2.5 text-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20"
            />
          </div>
        </div>
      </section>

      <div class="flex justify-end">
        <button
          type="submit"
          :disabled="saving"
          class="rounded-lg bg-brand-500 px-5 py-2.5 text-sm font-medium text-white hover:bg-brand-600 disabled:cursor-not-allowed disabled:opacity-50"
        >
          {{ saving ? 'Сохранение...' : 'Сохранить изменения' }}
        </button>
      </div>
    </form>
  </div>
</template>

<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { useAuthStore } from '@/stores/auth'
import type { AxiosError } from 'axios'
import type { ApiError } from '@/types'

const auth = useAuthStore()
const saving = ref(false)
const error = ref('')
const successMessage = ref('')

const form = reactive({
  name: '',
  email: '',
  current_password: '',
  password: '',
  password_confirmation: '',
})

function fillForm() {
  form.name = auth.user?.name ?? ''
  form.email = auth.user?.email ?? ''
  form.current_password = ''
  form.password = ''
  form.password_confirmation = ''
}

function validationMessage(err: AxiosError<ApiError>) {
  const errors = err.response?.data?.errors
  if (errors) {
    return Object.values(errors).flat().join(' ')
  }

  return err.response?.data?.message ?? 'Не удалось сохранить профиль'
}

async function submit() {
  error.value = ''
  successMessage.value = ''
  saving.value = true

  try {
    const payload: Record<string, string> = {
      name: form.name,
      email: form.email,
    }

    if (form.password) {
      payload.current_password = form.current_password
      payload.password = form.password
      payload.password_confirmation = form.password_confirmation
    }

    await auth.updateProfile(payload)
    fillForm()
    successMessage.value = 'Профиль успешно обновлён.'
  } catch (e) {
    error.value = validationMessage(e as AxiosError<ApiError>)
  } finally {
    saving.value = false
  }
}

onMounted(fillForm)
</script>
