<template>
  <div>
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <RouterLink to="/projects" class="text-sm text-brand-600 hover:underline">← Проекты</RouterLink>
        <h1 class="mt-2 text-2xl font-semibold text-gray-900">Проделанная работа</h1>
        <p v-if="project" class="mt-1 text-sm text-gray-500">{{ project.name }}</p>
      </div>
      <div class="flex flex-wrap gap-2">
        <RouterLink
          :to="`/projects/${projectId}/generate`"
          class="rounded-lg border border-gray-200 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"
        >
          Генератор отчёта
        </RouterLink>
        <button
          class="rounded-lg bg-brand-500 px-4 py-2 text-sm font-medium text-white hover:bg-brand-600"
          @click="openCreate"
        >
          + Добавить работу
        </button>
      </div>
    </div>

    <div v-if="formError" class="mb-4 rounded-lg bg-red-50 px-4 py-3 text-sm text-error-500">
      {{ formError }}
    </div>

    <div class="mb-4 flex flex-wrap gap-3">
      <input
        v-model="filterFrom"
        type="date"
        class="rounded-lg border border-gray-200 px-3 py-2 text-sm"
        @change="loadItems"
      />
      <input
        v-model="filterTo"
        type="date"
        class="rounded-lg border border-gray-200 px-3 py-2 text-sm"
        @change="loadItems"
      />
      <button
        class="rounded-lg border border-gray-200 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"
        @click="clearFilters"
      >
        Сбросить
      </button>
    </div>

    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
      <div v-if="store.loading" class="p-8 text-center text-sm text-gray-500">Загрузка...</div>
      <div v-else-if="store.items.length === 0" class="p-8 text-center text-sm text-gray-500">
        Работы не добавлены. Они попадут в блок «Проделанная работа» при генерации отчёта.
      </div>
      <table v-else class="w-full text-left text-sm">
        <thead class="border-b border-gray-200 bg-gray-50 text-xs uppercase text-gray-500">
          <tr>
            <th class="px-6 py-3">Дата</th>
            <th class="px-6 py-3">Категория</th>
            <th class="px-6 py-3">Описание</th>
            <th class="px-6 py-3 text-right">Действия</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-for="item in store.items" :key="item.id" class="hover:bg-gray-50">
            <td class="px-6 py-4 text-gray-600">{{ formatDate(item.work_date) }}</td>
            <td class="px-6 py-4">
              <span class="rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-700">
                {{ item.category_label }}
              </span>
            </td>
            <td class="px-6 py-4 text-gray-800">{{ item.description }}</td>
            <td class="px-6 py-4 text-right">
              <button class="mr-3 text-brand-600 hover:underline" @click="openEdit(item)">
                Изменить
              </button>
              <button class="text-error-500 hover:underline" @click="remove(item.id)">Удалить</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <AppModal v-model="showModal" :title="editing ? 'Редактировать работу' : 'Добавить работу'">
      <form id="work-form" class="space-y-4" @submit.prevent="save">
        <div>
          <label class="mb-1.5 block text-sm font-medium text-gray-700">Дата</label>
          <input
            v-model="form.work_date"
            type="date"
            required
            class="w-full rounded-lg border border-gray-200 px-4 py-2.5 text-sm outline-none focus:border-brand-500"
          />
        </div>
        <div>
          <label class="mb-1.5 block text-sm font-medium text-gray-700">Категория</label>
          <select
            v-model="form.category"
            required
            class="w-full rounded-lg border border-gray-200 px-4 py-2.5 text-sm outline-none focus:border-brand-500"
          >
            <option value="seo">SEO</option>
            <option value="content">Контент</option>
            <option value="technical">Техническое</option>
          </select>
        </div>
        <div>
          <label class="mb-1.5 block text-sm font-medium text-gray-700">Описание</label>
          <textarea
            v-model="form.description"
            required
            rows="4"
            class="w-full rounded-lg border border-gray-200 px-4 py-2.5 text-sm outline-none focus:border-brand-500"
            placeholder="Что было сделано..."
          />
        </div>
      </form>
      <template #footer>
        <button
          type="submit"
          form="work-form"
          class="rounded-lg bg-brand-500 px-4 py-2 text-sm font-medium text-white hover:bg-brand-600"
        >
          {{ editing ? 'Сохранить' : 'Добавить' }}
        </button>
      </template>
    </AppModal>
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import AppModal from '@/components/AppModal.vue'
import api from '@/lib/api'
import { useProjectsStore } from '@/stores/projects'
import { useWorkItemsStore } from '@/stores/workItems'
import type { Project, WorkItem } from '@/types'

const route = useRoute()
const projectId = Number(route.params.id)
const projectsStore = useProjectsStore()
const store = useWorkItemsStore()

const project = ref<Project | null>(null)
const showModal = ref(false)
const editing = ref<WorkItem | null>(null)
const formError = ref('')
const filterFrom = ref('')
const filterTo = ref('')

const form = ref({
  work_date: new Date().toISOString().slice(0, 10),
  category: 'seo',
  description: '',
})

onMounted(async () => {
  project.value = projectsStore.projects.find((p) => p.id === projectId) ?? null
  if (!project.value) {
    const { data } = await api.get<{ data: Project }>(`/projects/${projectId}`)
    project.value = data.data
  }
  await loadItems()
})

async function loadItems() {
  await store.fetchItems(projectId, {
    from: filterFrom.value || undefined,
    to: filterTo.value || undefined,
  })
}

function clearFilters() {
  filterFrom.value = ''
  filterTo.value = ''
  loadItems()
}

function formatDate(value: string) {
  const [y, m, d] = value.split('-')
  return `${d}.${m}.${y}`
}

function openCreate() {
  editing.value = null
  form.value = {
    work_date: new Date().toISOString().slice(0, 10),
    category: 'seo',
    description: '',
  }
  formError.value = ''
  showModal.value = true
}

function openEdit(item: WorkItem) {
  editing.value = item
  form.value = {
    work_date: item.work_date,
    category: item.category,
    description: item.description,
  }
  formError.value = ''
  showModal.value = true
}

async function save() {
  formError.value = ''
  try {
    if (editing.value) {
      await store.updateItem(projectId, editing.value.id, form.value)
    } else {
      await store.createItem(projectId, form.value)
    }
    showModal.value = false
  } catch {
    formError.value = 'Не удалось сохранить работу'
  }
}

async function remove(id: number) {
  if (!confirm('Удалить запись?')) return
  await store.deleteItem(projectId, id)
}
</script>
