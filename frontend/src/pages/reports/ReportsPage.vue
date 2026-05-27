<template>
  <div>
    <div class="mb-6">
      <h1 class="text-2xl font-semibold text-gray-900">История отчётов</h1>
      <p class="mt-1 text-sm text-gray-500">Сгенерированные HTML и PDF отчёты</p>
    </div>

    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
      <div v-if="store.loading" class="p-8 text-center text-sm text-gray-500">Загрузка...</div>

      <EmptyState
        v-else-if="store.reports.length === 0"
        icon="📊"
        title="Отчётов пока нет"
        description="Перейдите в проект и запустите генерацию отчёта."
      >
        <template #action>
          <RouterLink
            to="/projects"
            class="rounded-lg bg-brand-500 px-4 py-2 text-sm font-medium text-white hover:bg-brand-600"
          >
            К проектам
          </RouterLink>
        </template>
      </EmptyState>

      <table v-else class="w-full text-left text-sm">
        <thead class="border-b border-gray-200 bg-gray-50 text-xs uppercase text-gray-500">
          <tr>
            <th class="px-6 py-3">ID</th>
            <th class="px-6 py-3">Проект</th>
            <th class="px-6 py-3">Шаблон</th>
            <th class="px-6 py-3">Период</th>
            <th class="px-6 py-3">Статус</th>
            <th class="px-6 py-3 text-right">Действия</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-for="report in store.reports" :key="report.id" class="hover:bg-gray-50">
            <td class="px-6 py-4 text-gray-500">#{{ report.id }}</td>
            <td class="px-6 py-4 font-medium text-gray-900">{{ report.project?.name }}</td>
            <td class="px-6 py-4 text-gray-600">{{ report.template?.name }}</td>
            <td class="px-6 py-4 text-gray-600">
              {{ formatDate(report.period_start) }} — {{ formatDate(report.period_end) }}
            </td>
            <td class="px-6 py-4">
              <span
                :class="[
                  'inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium',
                  statusClass(report.status),
                ]"
              >
                {{ statusLabel(report.status) }}
              </span>
            </td>
            <td class="px-6 py-4 text-right">
              <template v-if="report.status === 'done'">
                <a
                  :href="store.previewUrl(report.id)"
                  target="_blank"
                  class="mr-3 text-brand-600 hover:underline"
                >
                  HTML
                </a>
                <a :href="store.downloadUrl(report.id, 'pdf')" class="text-brand-600 hover:underline">
                  PDF
                </a>
              </template>
              <span v-else class="text-gray-400">—</span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup lang="ts">
import { onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import EmptyState from '@/components/EmptyState.vue'
import { useReportsStore } from '@/stores/reports'

const store = useReportsStore()

function formatDate(value: string) {
  return new Date(value).toLocaleDateString('ru-RU')
}

function statusLabel(status: string) {
  const map: Record<string, string> = {
    queued: 'В очереди',
    fetching: 'Загрузка',
    rendering: 'Формирование',
    done: 'Готово',
    failed: 'Ошибка',
  }
  return map[status] ?? status
}

function statusClass(status: string) {
  if (status === 'done') return 'bg-green-50 text-green-700'
  if (status === 'failed') return 'bg-red-50 text-red-700'
  return 'bg-gray-100 text-gray-600'
}

onMounted(() => store.fetchReports())
</script>
