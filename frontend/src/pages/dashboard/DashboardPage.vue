<template>
  <div>
    <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
      <div>
        <h1 class="text-2xl font-semibold text-gray-900">Дашборд проектов</h1>
        <p class="mt-1 text-sm text-gray-500">
          Сводка по всем проектам за выбранный период
          <span v-if="store.data">· сравнение с {{ formatPeriod(store.data.compare_period) }}</span>
        </p>
      </div>
      <div class="flex flex-wrap items-end gap-3">
        <div>
          <label class="mb-1 block text-xs font-medium text-gray-500">Период с</label>
          <input
            v-model="periodStart"
            type="date"
            class="rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand-500"
          />
        </div>
        <div>
          <label class="mb-1 block text-xs font-medium text-gray-500">Период по</label>
          <input
            v-model="periodEnd"
            type="date"
            class="rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand-500"
          />
        </div>
        <button
          class="rounded-lg border border-gray-200 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50"
          @click="applyPreviousMonth"
        >
          Прошлый месяц
        </button>
        <button
          class="rounded-lg bg-brand-500 px-4 py-2 text-sm font-medium text-white hover:bg-brand-600 disabled:opacity-50"
          :disabled="store.loading"
          @click="load"
        >
          {{ store.loading ? 'Загрузка...' : 'Обновить' }}
        </button>
      </div>
    </div>

    <div v-if="store.error" class="mb-4 rounded-lg bg-red-50 px-4 py-3 text-sm text-error-500">
      {{ store.error }}
    </div>

    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
      <div v-if="store.loading && !store.data" class="p-8 text-center text-sm text-gray-500">
        Загрузка метрик...
      </div>

      <EmptyState
        v-else-if="!store.data?.projects.length"
        icon="📈"
        title="Нет проектов"
        description="Добавьте проект и подключите источники данных, чтобы увидеть сводку."
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

      <div v-else class="overflow-x-auto">
        <table class="w-full min-w-[960px] text-left text-sm">
          <thead class="border-b border-gray-200 bg-gray-50 text-xs uppercase text-gray-500">
            <tr>
              <th class="px-6 py-3">Проект</th>
              <th class="px-6 py-3">Визиты</th>
              <th class="px-6 py-3">Клики в поиске</th>
              <th class="px-6 py-3">Видимость</th>
              <th class="px-6 py-3">TOP-10</th>
              <th class="px-6 py-3">Последний отчёт</th>
              <th class="px-6 py-3 text-right">Действия</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="project in store.data?.projects" :key="project.id" class="hover:bg-gray-50">
              <td class="px-6 py-4">
                <div class="font-medium text-gray-900">{{ project.name }}</div>
                <div v-if="project.domain" class="text-xs text-gray-500">{{ project.domain }}</div>
                <div v-if="project.errors.length" class="mt-1 flex flex-wrap gap-1">
                  <span
                    v-for="issue in project.errors"
                    :key="`${issue.provider}-${issue.message}`"
                    class="inline-flex rounded-full bg-amber-50 px-2 py-0.5 text-[11px] text-amber-700"
                    :title="issue.message"
                  >
                    ⚠ {{ issue.message }}
                  </span>
                </div>
              </td>
              <td class="px-6 py-4">
                <template v-if="project.metrics.metrika">
                  <div class="font-medium text-gray-900">
                    {{ formatNumber(project.metrics.metrika.visits) }}
                  </div>
                  <ChangeBadge :value="project.metrics.metrika.visits_change_pct" />
                </template>
                <span v-else class="text-gray-400">—</span>
              </td>
              <td class="px-6 py-4">
                <template v-if="project.metrics.search">
                  <div class="font-medium text-gray-900">
                    {{ formatNumber(project.metrics.search.clicks) }}
                  </div>
                  <div class="text-xs text-gray-500">
                    {{ project.metrics.search.source === 'google_search_console' ? 'Google' : 'Яндекс' }}
                  </div>
                  <ChangeBadge :value="project.metrics.search.clicks_change_pct" />
                </template>
                <span v-else class="text-gray-400">—</span>
              </td>
              <td class="px-6 py-4">
                <template v-if="project.metrics.positions?.visibility != null">
                  <div class="font-medium text-gray-900">
                    {{ formatNumber(project.metrics.positions.visibility, 1) }}%
                  </div>
                  <ChangeBadge
                    v-if="project.metrics.positions.visibility_dynamic != null"
                    :value="project.metrics.positions.visibility_dynamic"
                    suffix=" п.п."
                    signed
                  />
                </template>
                <span v-else class="text-gray-400">—</span>
              </td>
              <td class="px-6 py-4">
                <span v-if="project.metrics.positions?.top10 != null" class="text-gray-900">
                  {{ formatNumber(project.metrics.positions.top10) }}
                </span>
                <span v-else class="text-gray-400">—</span>
              </td>
              <td class="px-6 py-4 text-gray-600">
                <template v-if="project.last_report">
                  <div>{{ formatDate(project.last_report.period_end) }}</div>
                  <div class="text-xs text-gray-500">#{{ project.last_report.id }}</div>
                </template>
                <span v-else class="text-gray-400">Нет</span>
              </td>
              <td class="px-6 py-4 text-right">
                <RouterLink
                  :to="`/projects/${project.id}`"
                  class="mr-3 text-brand-600 hover:underline"
                >
                  Настройки
                </RouterLink>
                <RouterLink
                  :to="`/projects/${project.id}/generate`"
                  class="text-brand-600 hover:underline"
                >
                  Отчёт
                </RouterLink>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import ChangeBadge from '@/components/ChangeBadge.vue'
import EmptyState from '@/components/EmptyState.vue'
import { useDashboardStore } from '@/stores/dashboard'

const store = useDashboardStore()
const periodStart = ref('')
const periodEnd = ref('')

function formatNumber(value: number, fractionDigits = 0) {
  return new Intl.NumberFormat('ru-RU', {
    maximumFractionDigits: fractionDigits,
    minimumFractionDigits: fractionDigits,
  }).format(value)
}

function formatDate(value: string) {
  return new Date(value).toLocaleDateString('ru-RU')
}

function formatPeriod(period: { start: string; end: string }) {
  return `${formatDate(period.start)} — ${formatDate(period.end)}`
}

function applyPreviousMonth() {
  const now = new Date()
  const start = new Date(now.getFullYear(), now.getMonth() - 1, 1)
  const end = new Date(now.getFullYear(), now.getMonth(), 0)
  periodStart.value = toInputDate(start)
  periodEnd.value = toInputDate(end)
}

function toInputDate(date: Date) {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

async function load() {
  await store.fetchDashboard(periodStart.value || undefined, periodEnd.value || undefined)
  if (store.data && !periodStart.value) {
    periodStart.value = store.data.period.start
    periodEnd.value = store.data.period.end
  }
}

onMounted(load)
</script>
