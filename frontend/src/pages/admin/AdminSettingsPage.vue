<template>
  <div>
    <div class="mb-6">
      <h1 class="text-2xl font-semibold text-gray-900">Настройки сервиса</h1>
      <p class="mt-1 text-sm text-gray-500">Глобальные параметры SEO Reports</p>
    </div>

    <div v-if="loading" class="rounded-2xl border border-gray-200 bg-white p-8 text-center text-sm text-gray-500">
      Загрузка...
    </div>

    <form v-else class="space-y-6" @submit.prevent="save">
      <div v-if="message" class="rounded-lg bg-green-50 px-4 py-3 text-sm text-green-700">
        {{ message }}
      </div>
      <div v-if="error" class="rounded-lg bg-red-50 px-4 py-3 text-sm text-error-500">
        {{ error }}
      </div>

      <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
        <h2 class="mb-4 text-lg font-medium text-gray-900">Общие</h2>
        <div class="grid gap-4 sm:grid-cols-2">
          <div>
            <label class="mb-1.5 block text-sm font-medium text-gray-700">Название сервиса</label>
            <input
              v-model="form.app_name"
              required
              class="w-full rounded-lg border border-gray-200 px-4 py-2.5 text-sm outline-none focus:border-brand-500"
            />
          </div>
          <div>
            <label class="mb-1.5 block text-sm font-medium text-gray-700">Email поддержки</label>
            <input
              v-model="form.support_email"
              type="email"
              required
              class="w-full rounded-lg border border-gray-200 px-4 py-2.5 text-sm outline-none focus:border-brand-500"
            />
          </div>
        </div>
      </section>

      <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
        <h2 class="mb-4 text-lg font-medium text-gray-900">Регистрация и доступ</h2>
        <div class="space-y-3">
          <label class="flex items-center gap-2 text-sm text-gray-700">
            <input v-model="form.registration_enabled" type="checkbox" class="rounded border-gray-300" />
            Регистрация новых пользователей
          </label>
          <label class="flex items-center gap-2 text-sm text-gray-700">
            <input
              v-model="form.email_verification_required"
              type="checkbox"
              class="rounded border-gray-300"
            />
            Обязательная верификация email
          </label>
        </div>
      </section>

      <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
        <h2 class="mb-4 text-lg font-medium text-gray-900">Отчёты</h2>
        <div>
          <label class="mb-1.5 block text-sm font-medium text-gray-700">
            Хранение отчётов (месяцев)
          </label>
          <input
            v-model.number="form.report_retention_months"
            type="number"
            min="1"
            max="120"
            class="w-full max-w-xs rounded-lg border border-gray-200 px-4 py-2.5 text-sm outline-none focus:border-brand-500"
          />
        </div>
      </section>

      <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
        <h2 class="mb-4 text-lg font-medium text-gray-900">Техобслуживание</h2>
        <div class="space-y-4">
          <label class="flex items-center gap-2 text-sm text-gray-700">
            <input v-model="form.maintenance_mode" type="checkbox" class="rounded border-gray-300" />
            Режим обслуживания
          </label>
          <div>
            <label class="mb-1.5 block text-sm font-medium text-gray-700">Сообщение</label>
            <textarea
              v-model="form.maintenance_message"
              rows="3"
              class="w-full rounded-lg border border-gray-200 px-4 py-2.5 text-sm outline-none focus:border-brand-500"
              placeholder="Сервис временно недоступен..."
            />
          </div>
        </div>
      </section>

      <button
        type="submit"
        :disabled="saving"
        class="rounded-lg bg-brand-500 px-6 py-2.5 text-sm font-medium text-white hover:bg-brand-600 disabled:opacity-50"
      >
        {{ saving ? 'Сохранение...' : 'Сохранить настройки' }}
      </button>
    </form>
  </div>
</template>

<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { useSettingsStore } from '@/stores/settings'
import type { AppSettings } from '@/types'
import type { AxiosError } from 'axios'
import type { ApiError } from '@/types'

const settingsStore = useSettingsStore()
const loading = ref(true)
const saving = ref(false)
const error = ref('')
const message = ref('')

const form = reactive<AppSettings>({
  app_name: 'SEO Reports',
  support_email: 'support@seo-reports.local',
  registration_enabled: true,
  email_verification_required: false,
  report_retention_months: 12,
  maintenance_mode: false,
  maintenance_message: '',
})

async function load() {
  loading.value = true
  try {
    const data = await settingsStore.fetchAdminSettings()
    Object.assign(form, data)
  } finally {
    loading.value = false
  }
}

async function save() {
  error.value = ''
  message.value = ''
  saving.value = true
  try {
    const data = await settingsStore.updateAdminSettings({ ...form })
    Object.assign(form, data)
    await settingsStore.fetchPublicSettings()
    message.value = 'Настройки сохранены'
  } catch (e) {
    const err = e as AxiosError<ApiError>
    error.value = err.response?.data?.message ?? 'Не удалось сохранить настройки'
  } finally {
    saving.value = false
  }
}

onMounted(load)
</script>
