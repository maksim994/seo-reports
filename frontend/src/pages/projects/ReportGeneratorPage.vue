<template>
  <div>
    <div class="mb-6">
      <RouterLink :to="`/projects/${projectId}`" class="text-sm text-brand-600 hover:underline">
        ← Настройки проекта
      </RouterLink>
      <h1 class="mt-2 text-2xl font-semibold text-gray-900">Генератор отчёта</h1>
      <p v-if="project" class="mt-1 text-sm text-gray-500">
        Проект: {{ project.name }}
        <span v-if="project.domain">({{ project.domain }})</span>
      </p>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
      <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
        <h2 class="mb-4 text-lg font-medium text-gray-900">Настройки отчёта</h2>

        <form class="space-y-4" @submit.prevent="generate">
          <div v-if="error" class="rounded-lg bg-red-50 px-4 py-3 text-sm text-error-500">
            {{ error }}
          </div>

          <div>
            <label class="mb-1.5 block text-sm font-medium text-gray-700">Шаблон</label>
            <select
              v-model.number="form.report_template_id"
              required
              class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand-500"
            >
              <option v-for="tpl in templates" :key="tpl.id" :value="tpl.id">
                {{ tpl.name }}
              </option>
            </select>
          </div>

          <div>
            <label class="mb-1.5 block text-sm font-medium text-gray-700">Период</label>
            <div class="mb-2 flex flex-wrap gap-2">
              <button
                v-for="preset in presets"
                :key="preset.label"
                type="button"
                class="rounded-lg border border-gray-200 px-3 py-1 text-xs hover:bg-gray-50"
                @click="applyPreset(preset)"
              >
                {{ preset.label }}
              </button>
            </div>
            <div class="grid grid-cols-2 gap-3">
              <input
                v-model="form.period_start"
                type="date"
                required
                class="rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand-500"
              />
              <input
                v-model="form.period_end"
                type="date"
                required
                class="rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand-500"
              />
            </div>
          </div>

          <label class="flex items-center gap-2 text-sm text-gray-700">
            <input v-model="compareEnabled" type="checkbox" class="rounded border-gray-300" />
            Сравнить с предыдущим периодом
          </label>

          <div v-if="compareEnabled" class="grid grid-cols-2 gap-3">
            <input
              v-model="form.compare_period_start"
              type="date"
              class="rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand-500"
            />
            <input
              v-model="form.compare_period_end"
              type="date"
              class="rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand-500"
            />
          </div>

          <button
            type="submit"
            :disabled="generating"
            class="w-full rounded-lg bg-brand-500 py-2.5 text-sm font-medium text-white hover:bg-brand-600 disabled:opacity-50"
          >
            {{ generating ? 'Запуск...' : 'Сгенерировать отчёт' }}
          </button>
        </form>
      </section>

      <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
        <div class="mb-4 flex items-center justify-between gap-3">
          <h2 class="text-lg font-medium text-gray-900">Статус генерации</h2>
          <RouterLink to="/reports" class="text-sm text-brand-600 hover:underline">
            Вся история →
          </RouterLink>
        </div>

        <div v-if="!recentJobs.length" class="text-sm text-gray-500">
          Запустите генерацию, чтобы отслеживать прогресс.
        </div>

        <div v-else class="space-y-3">
          <article
            v-for="job in recentJobs"
            :key="job.id"
            class="rounded-xl border border-gray-200 p-4 transition hover:border-gray-300"
            :class="isActiveStatus(job.status) ? 'border-blue-200 bg-blue-50/40 ring-2 ring-brand-100' : ''"
          >
            <div class="flex flex-wrap items-start justify-between gap-3">
              <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                  <span
                    :class="[
                      'inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium',
                      statusClass(job.status),
                    ]"
                  >
                    {{ statusLabel(job.status) }}
                  </span>
                  <span class="text-xs text-gray-500">#{{ job.id }}</span>
                </div>
                <p class="mt-2 text-sm font-medium text-gray-900">
                  {{ formatPeriod(job.period_start, job.period_end) }}
                </p>
                <p class="mt-1 text-xs text-gray-500">
                  {{ job.template?.name ?? 'Шаблон' }} · {{ formatDateTime(job.created_at) }}
                </p>
                <p v-if="job.error_message" class="mt-2 text-xs text-red-600">
                  {{ job.error_message }}
                </p>
              </div>

              <div class="flex flex-wrap items-center gap-2">
                <template v-if="job.status === 'done'">
                  <a
                    :href="reportsStore.previewUrl(job.id)"
                    target="_blank"
                    class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs text-gray-700 hover:bg-gray-50"
                  >
                    HTML
                  </a>
                  <a
                    :href="reportsStore.downloadUrl(job.id, 'pdf')"
                    class="rounded-lg bg-brand-500 px-3 py-1.5 text-xs font-medium text-white hover:bg-brand-600"
                  >
                    PDF
                  </a>
                  <button
                    class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs text-gray-700 hover:bg-gray-50"
                    @click="openShare(job)"
                  >
                    {{ job.share_enabled ? 'Ссылка ✓' : 'Ссылка' }}
                  </button>
                </template>
                <button
                  class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs text-error-500 hover:bg-red-50 disabled:opacity-50"
                  :disabled="deletingId === job.id"
                  @click="removeJob(job.id)"
                >
                  {{ deletingId === job.id ? 'Удаление...' : 'Удалить' }}
                </button>
              </div>
            </div>
          </article>
        </div>
      </section>
    </div>

    <ReportShareModal
      v-if="shareReport"
      v-model="shareOpen"
      :report="shareReport"
      @updated="onShareUpdated"
    />
  </div>
</template>

<script setup lang="ts">
import { onUnmounted, reactive, ref } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import ReportShareModal from '@/components/ReportShareModal.vue'
import { useProjectsStore } from '@/stores/projects'
import { useReportsStore } from '@/stores/reports'
import { useTemplatesStore } from '@/stores/templates'
import type { AxiosError } from 'axios'
import type { ApiError, Project, ReportJob, ReportTemplate } from '@/types'

const route = useRoute()
const projectsStore = useProjectsStore()
const templatesStore = useTemplatesStore()
const reportsStore = useReportsStore()

const projectId = Number(route.params.id)
const project = ref<Project | null>(null)
const templates = ref<ReportTemplate[]>([])
const recentJobs = ref<ReportJob[]>([])
const generating = ref(false)
const error = ref('')
const compareEnabled = ref(false)
const deletingId = ref<number | null>(null)
const shareOpen = ref(false)
const shareReport = ref<ReportJob | null>(null)
let pollTimer: ReturnType<typeof setInterval> | null = null

function openShare(job: ReportJob) {
  shareReport.value = job
  shareOpen.value = true
}

function onShareUpdated(job: ReportJob) {
  shareReport.value = job
  upsertRecentJob(job)
}

const form = reactive({
  report_template_id: 0,
  period_start: '',
  period_end: '',
  compare_period_start: '',
  compare_period_end: '',
})

const presets = [
  {
    label: 'Последние 30 дней',
    apply: () => {
      const end = new Date()
      const start = new Date()
      start.setDate(end.getDate() - 29)
      return { start, end }
    },
  },
  {
    label: 'Прошлый месяц',
    apply: () => {
      const now = new Date()
      const start = new Date(now.getFullYear(), now.getMonth() - 1, 1)
      const end = new Date(now.getFullYear(), now.getMonth(), 0)
      return { start, end }
    },
  },
]

function formatDateInput(date: Date) {
  return date.toISOString().slice(0, 10)
}

function applyPreset(preset: (typeof presets)[number]) {
  const { start, end } = preset.apply()
  form.period_start = formatDateInput(start)
  form.period_end = formatDateInput(end)
}

function formatPeriod(start: string, end: string) {
  return `${formatDisplayDate(start)} — ${formatDisplayDate(end)}`
}

function formatDisplayDate(value: string) {
  const [year, month, day] = value.split('-')
  if (!year || !month || !day) return value
  return `${day}.${month}.${year}`
}

function formatDateTime(value?: string | null) {
  if (!value) return '—'
  return new Intl.DateTimeFormat('ru-RU', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  }).format(new Date(value))
}

function statusLabel(status: string) {
  const map: Record<string, string> = {
    queued: 'В очереди',
    fetching: 'Загрузка данных',
    rendering: 'Формирование',
    done: 'Готово',
    failed: 'Ошибка',
  }
  return map[status] ?? status
}

function statusClass(status: string) {
  if (status === 'done') return 'bg-green-50 text-green-700'
  if (status === 'failed') return 'bg-red-50 text-red-700'
  return 'bg-blue-50 text-blue-700'
}

function isActiveStatus(status: string) {
  return status === 'queued' || status === 'fetching' || status === 'rendering'
}

function stopPolling() {
  if (pollTimer) {
    clearInterval(pollTimer)
    pollTimer = null
  }
}

async function loadRecentJobs() {
  recentJobs.value = await reportsStore.fetchProjectReports(projectId)
}

function upsertRecentJob(job: ReportJob) {
  const index = recentJobs.value.findIndex((item) => item.id === job.id)
  if (index >= 0) {
    recentJobs.value[index] = job
  } else {
    recentJobs.value = [job, ...recentJobs.value].slice(0, 10)
  }
}

async function removeJob(id: number) {
  if (!confirm('Удалить отчёт?')) return
  deletingId.value = id
  try {
    await reportsStore.deleteReport(id)
    recentJobs.value = recentJobs.value.filter((job) => job.id !== id)
    if (pollTimer) {
      stopPolling()
    }
  } finally {
    deletingId.value = null
  }
}

function startPolling(jobId: number) {
  stopPolling()
  pollTimer = setInterval(async () => {
    const job = await reportsStore.fetchReport(jobId)
    upsertRecentJob(job)
    if (job.status === 'done' || job.status === 'failed') {
      stopPolling()
    }
  }, 2000)
}

async function generate() {
  error.value = ''
  generating.value = true
  try {
    const payload = {
      report_template_id: form.report_template_id,
      period_start: form.period_start,
      period_end: form.period_end,
      compare_period_start: compareEnabled.value ? form.compare_period_start : null,
      compare_period_end: compareEnabled.value ? form.compare_period_end : null,
    }
    const job = await reportsStore.generateReport(projectId, payload)
    upsertRecentJob(job)
    startPolling(job.id)
  } catch (e) {
    const err = e as AxiosError<ApiError>
    error.value = err.response?.data?.message ?? 'Не удалось запустить генерацию'
  } finally {
    generating.value = false
  }
}

async function load() {
  await projectsStore.fetchProjects()
  project.value = projectsStore.projects.find((p) => p.id === projectId) ?? null
  await templatesStore.fetchTemplates()
  templates.value = templatesStore.templates
  if (templates.value.length) {
    form.report_template_id = templates.value[0].id
  }
  applyPreset(presets[0])
  await loadRecentJobs()
}

load()

onUnmounted(stopPolling)
</script>
