<template>
  <div>
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
      <h1 class="text-2xl font-semibold text-gray-900">Все проекты</h1>
      <div class="flex gap-3">
        <input
          v-model="search"
          type="search"
          placeholder="Поиск..."
          class="w-full rounded-lg border border-gray-200 px-4 py-2 text-sm outline-none focus:border-brand-500 sm:max-w-xs"
          @input="debouncedSearch"
        />
        <select
          v-model="analyticsFilter"
          class="rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm text-gray-700 outline-none focus:border-brand-500"
          @change="reload"
        >
          <option value="">Все</option>
          <option value="true">С аналитикой</option>
          <option value="false">Без аналитики</option>
        </select>
      </div>
    </div>

    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
      <div v-if="admin.loading" class="p-8 text-center text-sm text-gray-500">Загрузка...</div>

      <EmptyState
        v-else-if="admin.projects.length === 0"
        icon="📁"
        title="Проектов нет"
        description="Пользователи ещё не создали проекты."
      />

      <table v-else class="w-full text-left text-sm">
        <thead class="border-b border-gray-200 bg-gray-50 text-xs uppercase text-gray-500">
          <tr>
            <th class="px-6 py-3">ID</th>
            <th class="px-6 py-3">Проект</th>
            <th class="px-6 py-3">Владелец</th>
            <th class="px-6 py-3">Аналитика</th>
            <th class="px-6 py-3">Создан</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-for="project in admin.projects" :key="project.id" class="hover:bg-gray-50">
            <td class="px-6 py-4 text-gray-500">{{ project.id }}</td>
            <td class="px-6 py-4">
              <div class="font-medium text-gray-900">{{ project.name }}</div>
              <div class="text-gray-500">{{ project.domain || '—' }}</div>
            </td>
            <td class="px-6 py-4">
              <div class="text-gray-900">{{ project.user?.name }}</div>
              <div class="text-gray-500">{{ project.user?.email }}</div>
            </td>
            <td class="px-6 py-4">
              <span
                :class="[
                  'inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium',
                  project.has_analytics
                    ? 'bg-green-50 text-green-700'
                    : 'bg-gray-100 text-gray-600',
                ]"
              >
                {{ project.has_analytics ? 'Подключена' : 'Нет' }}
              </span>
            </td>
            <td class="px-6 py-4 text-gray-500">
              {{ formatDate(project.created_at) }}
            </td>
          </tr>
        </tbody>
      </table>

      <div
        v-if="admin.projectsMeta.last_page > 1"
        class="flex items-center justify-between border-t border-gray-200 px-6 py-4 text-sm text-gray-600"
      >
        <span>Всего: {{ admin.projectsMeta.total }}</span>
        <div class="flex gap-2">
          <button
            :disabled="page <= 1"
            class="rounded-lg border border-gray-200 px-3 py-1 disabled:opacity-50"
            @click="changePage(page - 1)"
          >
            ←
          </button>
          <span>{{ page }} / {{ admin.projectsMeta.last_page }}</span>
          <button
            :disabled="page >= admin.projectsMeta.last_page"
            class="rounded-lg border border-gray-200 px-3 py-1 disabled:opacity-50"
            @click="changePage(page + 1)"
          >
            →
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import EmptyState from '@/components/EmptyState.vue'
import { useAdminStore } from '@/stores/admin'

const admin = useAdminStore()
const search = ref('')
const analyticsFilter = ref('')
const page = ref(1)

let searchTimer: ReturnType<typeof setTimeout> | null = null

function formatDate(value: string) {
  return new Date(value).toLocaleDateString('ru-RU')
}

function debouncedSearch() {
  if (searchTimer) clearTimeout(searchTimer)
  searchTimer = setTimeout(reload, 300)
}

async function loadProjects() {
  const params: Record<string, string | number> = { page: page.value }
  if (search.value) params.search = search.value
  if (analyticsFilter.value) params.has_analytics = analyticsFilter.value
  await admin.fetchProjects(params)
}

function reload() {
  page.value = 1
  loadProjects()
}

function changePage(next: number) {
  page.value = next
  loadProjects()
}

onMounted(loadProjects)
</script>
