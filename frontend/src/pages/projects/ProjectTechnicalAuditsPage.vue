<template>
  <div>
    <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
      <div>
        <RouterLink to="/projects" class="text-sm text-brand-600 hover:underline">
          ← К проектам
        </RouterLink>
        <h1 class="mt-2 text-2xl font-semibold text-gray-900">Технический SEO-аудит</h1>
        <p v-if="project" class="mt-1 text-sm text-gray-500">
          {{ project.name }}
          <span v-if="project.domain">· {{ project.domain }}</span>
        </p>
      </div>
      <button
        class="rounded-lg bg-brand-500 px-4 py-2 text-sm font-medium text-white hover:bg-brand-600 disabled:opacity-50"
        :disabled="starting"
        @click="showForm = !showForm"
      >
        {{ showForm ? 'Скрыть форму' : '+ Запустить аудит' }}
      </button>
    </div>

    <div
      v-if="showForm"
      class="mb-6 rounded-2xl border border-gray-200 bg-white p-6 shadow-sm"
    >
      <div class="mb-4 rounded-lg border border-blue-100 bg-blue-50 px-4 py-3 text-sm text-blue-800">
        Аудит выполняется через Cursor Cloud Agent и pipeline
        <code class="rounded bg-blue-100 px-1">skills-seo-audit</code>.
        Обычно занимает 10–30 минут. Результаты: JSON, Markdown и DOCX.
      </div>

      <form class="space-y-4" @submit.prevent="start">
        <div v-if="formError" class="rounded-lg bg-red-50 px-4 py-3 text-sm text-error-500">
          {{ formError }}
        </div>

        <div>
          <label class="mb-1.5 block text-sm font-medium text-gray-700">
            URL сайта <span class="text-error-500">*</span>
          </label>
          <input
            v-model="form.site_url"
            required
            type="url"
            class="w-full rounded-lg border border-gray-200 px-4 py-2.5 text-sm outline-none focus:border-brand-500"
            placeholder="https://example.com/"
          />
        </div>

        <div>
          <label class="mb-1.5 block text-sm font-medium text-gray-700">Название компании</label>
          <input
            v-model="form.site_name"
            class="w-full rounded-lg border border-gray-200 px-4 py-2.5 text-sm outline-none focus:border-brand-500"
            :placeholder="project?.name || 'Example'"
          />
        </div>

        <div>
          <label class="mb-1.5 block text-sm font-medium text-gray-700">
            Sample URL (по одному на строку)
          </label>
          <textarea
            v-model="form.sample_urls_text"
            rows="4"
            class="w-full rounded-lg border border-gray-200 px-4 py-2.5 text-sm outline-none focus:border-brand-500"
            placeholder="https://example.com/category/&#10;https://example.com/product/1"
          />
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
          <div>
            <label class="mb-1.5 block text-sm font-medium text-gray-700">Глубина</label>
            <select
              v-model="form.crawl_depth"
              class="w-full rounded-lg border border-gray-200 px-4 py-2.5 text-sm outline-none focus:border-brand-500"
            >
              <option value="light">Light — только sample URL</option>
              <option value="sitemap">Sitemap — до 500 URL</option>
            </select>
          </div>
          <div>
            <label class="mb-1.5 block text-sm font-medium text-gray-700">Язык отчёта</label>
            <select
              v-model="form.lang"
              class="w-full rounded-lg border border-gray-200 px-4 py-2.5 text-sm outline-none focus:border-brand-500"
            >
              <option value="ru">Русский</option>
              <option value="en">English</option>
            </select>
          </div>
        </div>

        <button
          type="submit"
          :disabled="starting"
          class="rounded-lg bg-success-500 px-4 py-2 text-sm font-medium text-white hover:bg-green-600 disabled:opacity-50"
        >
          {{ starting ? 'Запуск...' : 'Запустить аудит' }}
        </button>
      </form>
    </div>

    <div class="space-y-4">
      <div v-if="loading" class="rounded-2xl border border-gray-200 bg-white p-8 text-center text-sm text-gray-500 shadow-sm">
        Загрузка...
      </div>

      <EmptyState
        v-else-if="audits.length === 0"
        icon="🔍"
        title="Аудитов пока нет"
        description="Запустите первый технический SEO-аудит для этого проекта."
      />

      <div
        v-for="audit in audits"
        :key="audit.id"
        class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm"
      >
        <div class="flex flex-col gap-4 border-b border-gray-100 px-6 py-4 lg:flex-row lg:items-center lg:justify-between">
          <div>
            <div class="text-xs text-gray-500">{{ formatDate(audit.created_at) }}</div>
            <a
              :href="audit.site_url"
              target="_blank"
              rel="noopener noreferrer"
              class="mt-1 block text-sm font-medium text-brand-600 hover:underline"
            >
              {{ audit.site_url }}
            </a>
          </div>

          <div class="flex flex-wrap items-center gap-3">
            <span :class="['inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium', statusClass(audit.status)]">
              {{ statusLabel(audit.status) }}
            </span>
            <span v-if="isActive(audit)" class="text-xs text-gray-500">
              {{ elapsedLabel(audit) }}
            </span>
            <a
              v-if="audit.cursor_agent_url"
              :href="audit.cursor_agent_url"
              target="_blank"
              rel="noopener noreferrer"
              class="text-xs text-brand-600 hover:underline"
            >
              Открыть в Cursor
            </a>
            <button
              class="text-xs text-gray-600 hover:text-brand-600"
              @click="toggleLog(audit.id)"
            >
              {{ expandedId === audit.id ? 'Скрыть лог' : 'Показать лог' }}
            </button>
          </div>
        </div>

        <div v-if="isActive(audit) && audit.webhook_reachable === false" class="border-b border-amber-100 bg-amber-50 px-6 py-3 text-sm text-amber-800">
          Webhook недоступен из Cloud Agent (localhost). После завершения аудита нажмите
          «Получить из Cursor» или загрузите JSON вручную.
        </div>

        <div v-if="isActive(audit)" class="border-b border-gray-100 px-6 py-4">
          <div class="mb-2 text-xs font-medium uppercase tracking-wide text-gray-500">
            Прогресс
          </div>
          <div class="flex flex-wrap gap-2">
            <span
              v-for="step in progressSteps(audit)"
              :key="step.key"
              :class="[
                'rounded-full px-3 py-1 text-xs font-medium',
                step.done
                  ? 'bg-green-50 text-green-700'
                  : step.active
                    ? 'bg-blue-50 text-blue-700'
                    : 'bg-gray-100 text-gray-500',
              ]"
            >
              {{ step.label }}
            </span>
          </div>
        </div>

        <div
          v-if="audit.result_summary?.totals || audit.files.length > 0"
          class="flex flex-wrap items-center gap-4 border-b border-gray-100 px-6 py-4 text-sm"
        >
          <div v-if="audit.result_summary?.totals" class="text-gray-600">
            🔴 {{ audit.result_summary.totals.critical ?? 0 }}
            · ⚠️ {{ audit.result_summary.totals.warning ?? 0 }}
            · ✅ {{ audit.result_summary.totals.ok ?? 0 }}
          </div>
          <div class="flex flex-wrap gap-2">
            <a
              v-for="file in audit.files"
              :key="file.format"
              :href="store.downloadUrl(audit.id, file.format)"
              class="rounded-lg border border-gray-200 px-2 py-1 text-xs text-brand-600 hover:bg-gray-50"
            >
              {{ file.format.toUpperCase() }}
            </a>
          </div>
        </div>

        <div v-if="audit.error_message" class="border-b border-gray-100 px-6 py-3 text-sm text-error-500">
          {{ audit.error_message }}
        </div>

        <div v-if="expandedId === audit.id" class="px-6 py-4">
          <div class="mb-3 text-xs font-medium uppercase tracking-wide text-gray-500">
            Журнал выполнения
          </div>
          <div
            v-if="!audit.activity_log?.length"
            class="rounded-lg bg-gray-50 px-4 py-3 text-sm text-gray-500"
          >
            Записей пока нет. Если статус «В очереди» долго не меняется — проверьте, что worker запущен.
          </div>
          <div v-else class="max-h-72 space-y-2 overflow-y-auto rounded-lg bg-gray-50 p-4">
            <div
              v-for="(entry, index) in audit.activity_log"
              :key="`${audit.id}-${index}`"
              class="flex gap-3 text-sm"
            >
              <span class="shrink-0 text-xs text-gray-400">{{ formatLogTime(entry.at) }}</span>
              <span :class="['mt-0.5 h-2 w-2 shrink-0 rounded-full', logDotClass(entry.level)]" />
              <div class="min-w-0 flex-1">
                <div class="text-gray-800">{{ entry.message }}</div>
                <div v-if="entry.context" class="mt-1 break-all text-xs text-gray-500">
                  {{ formatContext(entry.context) }}
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3 px-6 py-3">
          <div v-if="!audit.files.length" class="flex flex-wrap gap-2">
            <button
              class="rounded-lg border border-brand-200 px-3 py-1.5 text-xs font-medium text-brand-600 hover:bg-brand-50 disabled:opacity-50"
              :disabled="syncingId === audit.id"
              @click="syncFromCursor(audit.id)"
            >
              {{ syncingId === audit.id ? 'Запрос...' : 'Получить из Cursor' }}
            </button>
            <label class="cursor-pointer rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50">
              {{ importingId === audit.id ? 'Импорт...' : 'Загрузить JSON' }}
              <input
                type="file"
                accept="application/json,.json"
                class="hidden"
                @change="importJson(audit.id, $event)"
              />
            </label>
          </div>
          <div v-else />
          <button class="text-xs text-error-500 hover:underline" @click="remove(audit.id)">
            Удалить
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, onUnmounted, reactive, ref } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import type { AxiosError } from 'axios'
import EmptyState from '@/components/EmptyState.vue'
import { useProjectsStore } from '@/stores/projects'
import { useTechnicalAuditsStore } from '@/stores/technicalAudits'
import type { ApiError, TechnicalAuditJob } from '@/types'

const route = useRoute()
const projectsStore = useProjectsStore()
const store = useTechnicalAuditsStore()

const projectId = computed(() => Number(route.params.id))
const project = computed(() => projectsStore.projects.find((p) => p.id === projectId.value) ?? null)

const audits = ref<TechnicalAuditJob[]>([])
const loading = ref(true)
const starting = ref(false)
const showForm = ref(false)
const formError = ref('')
const expandedId = ref<number | null>(null)
const syncingId = ref<number | null>(null)
const importingId = ref<number | null>(null)
let pollTimer: ReturnType<typeof setInterval> | null = null

const form = reactive({
  site_url: '',
  site_name: '',
  sample_urls_text: '',
  crawl_depth: 'light' as 'light' | 'sitemap',
  lang: 'ru' as 'ru' | 'en',
})

function defaultSiteUrl() {
  const domain = project.value?.domain
  if (!domain) return ''
  return domain.startsWith('http') ? domain : `https://${domain}/`
}

async function loadAudits() {
  loading.value = true
  try {
    audits.value = await store.fetchProjectAudits(projectId.value)
    autoExpandActive()
  } finally {
    loading.value = false
  }
}

function autoExpandActive() {
  const active = audits.value.find((audit) => isActive(audit))
  if (active && expandedId.value === null) {
    expandedId.value = active.id
  }
}

async function start() {
  formError.value = ''
  starting.value = true
  try {
    const sampleUrls = form.sample_urls_text
      .split('\n')
      .map((line) => line.trim())
      .filter(Boolean)

    const audit = await store.startAudit(projectId.value, {
      site_url: form.site_url,
      site_name: form.site_name || undefined,
      sample_urls: sampleUrls.length > 0 ? sampleUrls : undefined,
      crawl_depth: form.crawl_depth,
      lang: form.lang,
    })

    audits.value = [audit, ...audits.value]
    expandedId.value = audit.id
    showForm.value = false
  } catch (e) {
    const err = e as AxiosError<ApiError>
    formError.value =
      err.response?.data?.message ??
      err.response?.data?.errors?.site_url?.[0] ??
      'Не удалось запустить аудит'
  } finally {
    starting.value = false
  }
}

async function remove(id: number) {
  if (!confirm('Удалить аудит?')) return
  await store.deleteAudit(id)
  audits.value = audits.value.filter((audit) => audit.id !== id)
  if (expandedId.value === id) expandedId.value = null
}

async function syncFromCursor(id: number) {
  syncingId.value = id
  try {
    const result = await store.syncAudit(id)
    audits.value = audits.value.map((audit) => (audit.id === id ? result.data : audit))
    expandedId.value = id
  } catch (e) {
    const err = e as AxiosError<ApiError>
    alert(err.response?.data?.message ?? 'Не удалось запустить синхронизацию')
  } finally {
    syncingId.value = null
  }
}

async function importJson(id: number, event: Event) {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]
  input.value = ''
  if (!file) return

  importingId.value = id
  try {
    const result = await store.importAuditJson(id, file)
    audits.value = audits.value.map((audit) => (audit.id === id ? result.data : audit))
    expandedId.value = id
  } catch (e) {
    const err = e as AxiosError<ApiError>
    alert(err.response?.data?.message ?? 'Не удалось импортировать JSON')
  } finally {
    importingId.value = null
  }
}

function toggleLog(id: number) {
  expandedId.value = expandedId.value === id ? null : id
}

function formatDate(value: string) {
  return new Date(value).toLocaleString('ru-RU')
}

function formatLogTime(value: string) {
  return new Date(value).toLocaleTimeString('ru-RU')
}

function formatContext(context: Record<string, unknown>) {
  return Object.entries(context)
    .map(([key, value]) => `${key}: ${typeof value === 'object' ? JSON.stringify(value) : value}`)
    .join(' · ')
}

function statusLabel(status: TechnicalAuditJob['status']) {
  const labels: Record<TechnicalAuditJob['status'], string> = {
    queued: 'В очереди',
    launching: 'Запуск',
    running: 'Выполняется',
    processing: 'Обработка',
    done: 'Готово',
    failed: 'Ошибка',
  }
  return labels[status]
}

function statusClass(status: TechnicalAuditJob['status']) {
  if (status === 'done') return 'bg-green-50 text-green-700'
  if (status === 'failed') return 'bg-red-50 text-red-700'
  if (status === 'queued' || status === 'launching') return 'bg-gray-100 text-gray-600'
  return 'bg-blue-50 text-blue-700'
}

function logDotClass(level: string) {
  if (level === 'success') return 'bg-green-500'
  if (level === 'warning') return 'bg-amber-500'
  if (level === 'error') return 'bg-red-500'
  return 'bg-blue-500'
}

function isActive(audit: TechnicalAuditJob) {
  return !['done', 'failed'].includes(audit.status)
}

function elapsedLabel(audit: TechnicalAuditJob) {
  const start = audit.started_at ?? audit.created_at
  const minutes = Math.max(0, Math.floor((Date.now() - new Date(start).getTime()) / 60000))
  if (minutes === 0) return 'только что запущен'
  return `${minutes} мин`
}

function progressSteps(audit: TechnicalAuditJob) {
  const order = ['queued', 'launching', 'running', 'processing', 'done'] as const
  const currentIndex = order.indexOf(audit.status === 'failed' ? 'running' : audit.status)

  return [
    { key: 'queued', label: '1. Очередь', done: currentIndex > 0, active: currentIndex === 0 },
    { key: 'launching', label: '2. Запуск агента', done: currentIndex > 1, active: currentIndex === 1 },
    { key: 'running', label: '3. Аудит на Cursor', done: currentIndex > 2, active: currentIndex === 2 },
    { key: 'processing', label: '4. Сохранение', done: currentIndex > 3, active: currentIndex === 3 },
    { key: 'done', label: '5. Готово', done: audit.status === 'done', active: false },
  ]
}

function hasActiveAudits() {
  return audits.value.some((audit) => isActive(audit))
}

onMounted(async () => {
  if (projectsStore.projects.length === 0) {
    await projectsStore.fetchProjects()
  }
  form.site_url = defaultSiteUrl()
  form.site_name = project.value?.name ?? ''
  await loadAudits()

  pollTimer = setInterval(async () => {
    if (!hasActiveAudits()) return
    audits.value = await store.fetchProjectAudits(projectId.value)
  }, 10000)
})

onUnmounted(() => {
  if (pollTimer) clearInterval(pollTimer)
})
</script>
