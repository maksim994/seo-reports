<template>
  <div>
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h1 class="text-2xl font-semibold text-gray-900">Шаблоны отчётов</h1>
        <p class="mt-1 text-sm text-gray-500">Конструктор блоков для генерации отчётов</p>
      </div>
      <button
        class="rounded-lg bg-brand-500 px-4 py-2 text-sm font-medium text-white hover:bg-brand-600"
        @click="showCreateModal = true"
      >
        + Создать шаблон
      </button>
    </div>

    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
      <div v-if="store.loading" class="p-8 text-center text-sm text-gray-500">Загрузка...</div>

      <EmptyState
        v-else-if="store.templates.length === 0"
        icon="📋"
        title="Шаблонов пока нет"
        description="Создайте первый шаблон или зарегистрируйте новый аккаунт — будет создан демо-шаблон."
      >
        <template #action>
          <button
            class="rounded-lg bg-brand-500 px-4 py-2 text-sm font-medium text-white hover:bg-brand-600"
            @click="showCreateModal = true"
          >
            + Создать шаблон
          </button>
        </template>
      </EmptyState>

      <table v-else class="w-full text-left text-sm">
        <thead class="border-b border-gray-200 bg-gray-50 text-xs uppercase text-gray-500">
          <tr>
            <th class="px-6 py-3">Название</th>
            <th class="px-6 py-3">Блоков</th>
            <th class="px-6 py-3">Обновлён</th>
            <th class="px-6 py-3 text-right">Действия</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-for="template in store.templates" :key="template.id" class="hover:bg-gray-50">
            <td class="px-6 py-4">
              <div class="font-medium text-gray-900">
                {{ template.name }}
                <span
                  v-if="template.is_default"
                  class="ml-2 rounded-full bg-blue-50 px-2 py-0.5 text-xs text-blue-700"
                >
                  по умолчанию
                </span>
              </div>
              <div v-if="template.description" class="text-gray-500">{{ template.description }}</div>
            </td>
            <td class="px-6 py-4 text-gray-600">{{ template.blocks_count ?? 0 }}</td>
            <td class="px-6 py-4 text-gray-500">{{ formatDate(template.updated_at) }}</td>
            <td class="px-6 py-4 text-right">
              <RouterLink
                :to="`/templates/${template.id}/edit`"
                class="mr-3 text-brand-600 hover:underline"
              >
                Редактировать
              </RouterLink>
              <button class="text-error-500 hover:underline" @click="remove(template.id)">
                Удалить
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <AppModal v-model="showCreateModal" title="Новый шаблон">
      <form id="create-template-form" class="space-y-4" @submit.prevent="create">
        <div v-if="formError" class="rounded-lg bg-red-50 px-4 py-3 text-sm text-error-500">
          {{ formError }}
        </div>
        <div>
          <label class="mb-1.5 block text-sm font-medium text-gray-700">Название</label>
          <input
            v-model="form.name"
            required
            class="w-full rounded-lg border border-gray-200 px-4 py-2.5 text-sm outline-none focus:border-brand-500"
            placeholder="Ежемесячный SEO-отчёт"
          />
        </div>
        <div>
          <label class="mb-1.5 block text-sm font-medium text-gray-700">Описание</label>
          <textarea
            v-model="form.description"
            rows="2"
            class="w-full rounded-lg border border-gray-200 px-4 py-2.5 text-sm outline-none focus:border-brand-500"
          />
        </div>
      </form>
      <template #footer>
        <button
          type="button"
          class="rounded-lg border border-gray-200 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"
          @click="showCreateModal = false"
        >
          Отмена
        </button>
        <button
          type="submit"
          form="create-template-form"
          :disabled="creating"
          class="rounded-lg bg-brand-500 px-4 py-2 text-sm font-medium text-white hover:bg-brand-600 disabled:opacity-50"
        >
          {{ creating ? 'Создание...' : 'Создать' }}
        </button>
      </template>
    </AppModal>
  </div>
</template>

<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import AppModal from '@/components/AppModal.vue'
import EmptyState from '@/components/EmptyState.vue'
import { useTemplatesStore } from '@/stores/templates'
import type { AxiosError } from 'axios'
import type { ApiError } from '@/types'

const store = useTemplatesStore()
const router = useRouter()
const showCreateModal = ref(false)
const creating = ref(false)
const formError = ref('')

const form = reactive({
  name: '',
  description: '',
})

function formatDate(value: string) {
  return new Date(value).toLocaleDateString('ru-RU')
}

async function create() {
  formError.value = ''
  creating.value = true
  try {
    const template = await store.createTemplate({
      name: form.name,
      description: form.description || undefined,
      blocks: [{ block_type: 'title_page' }, { block_type: 'table_of_contents' }],
    })
    showCreateModal.value = false
    form.name = ''
    form.description = ''
    router.push(`/templates/${template.id}/edit`)
  } catch (e) {
    const err = e as AxiosError<ApiError>
    formError.value = err.response?.data?.message ?? 'Не удалось создать шаблон'
  } finally {
    creating.value = false
  }
}

async function remove(id: number) {
  if (!confirm('Удалить шаблон?')) return
  await store.deleteTemplate(id)
}

onMounted(() => store.fetchTemplates())
</script>
