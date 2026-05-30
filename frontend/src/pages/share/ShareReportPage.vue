<template>
  <div class="min-h-screen bg-gray-50">
    <header class="border-b border-gray-200 bg-white">
      <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-4 lg:px-6">
        <div>
          <div class="text-sm font-semibold text-gray-900">
            {{ settings.publicSettings?.app_name || 'SEO Reports' }}
          </div>
          <div v-if="meta" class="mt-1 text-sm text-gray-600">
            {{ meta.project_name || 'Отчёт' }}
            <span v-if="meta.template_name">· {{ meta.template_name }}</span>
          </div>
        </div>
        <div v-if="meta" class="flex flex-wrap gap-2">
          <a
            v-if="meta.formats.includes('pdf')"
            :href="downloadUrl('pdf')"
            class="rounded-lg bg-brand-500 px-4 py-2 text-sm font-medium text-white hover:bg-brand-600"
          >
            Скачать PDF
          </a>
          <a
            v-if="meta.formats.includes('html')"
            :href="downloadUrl('html')"
            class="rounded-lg border border-gray-200 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"
          >
            Скачать HTML
          </a>
        </div>
      </div>
    </header>

    <main class="mx-auto max-w-6xl px-4 py-6 lg:px-6">
      <div v-if="loading" class="rounded-2xl border border-gray-200 bg-white p-8 text-center text-sm text-gray-500">
        Загрузка отчёта...
      </div>

      <div v-else-if="error" class="rounded-2xl border border-red-200 bg-red-50 p-8 text-center text-sm text-red-700">
        {{ error }}
      </div>

      <div v-else class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
        <div v-if="meta" class="border-b border-gray-100 px-6 py-3 text-sm text-gray-500">
          Период: {{ formatDate(meta.period_start) }} — {{ formatDate(meta.period_end) }}
        </div>
        <iframe
          v-if="token"
          :src="previewUrl"
          title="SEO Report"
          class="block h-[calc(100vh-180px)] min-h-[640px] w-full"
        />
      </div>
    </main>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useReportsStore } from '@/stores/reports'
import { useSettingsStore } from '@/stores/settings'
import type { PublicReportMeta } from '@/types'

const route = useRoute()
const store = useReportsStore()
const settings = useSettingsStore()

const loading = ref(true)
const error = ref('')
const meta = ref<PublicReportMeta | null>(null)

const token = computed(() => String(route.params.token ?? ''))
const previewUrl = computed(() => store.publicPreviewUrl(token.value))

function formatDate(value: string) {
  return new Date(value).toLocaleDateString('ru-RU')
}

function downloadUrl(format: 'html' | 'pdf') {
  return store.publicDownloadUrl(token.value, format)
}

onMounted(async () => {
  if (!settings.publicSettings) {
    await settings.fetchPublicSettings()
  }

  try {
    meta.value = await store.fetchPublicReport(token.value)
  } catch {
    error.value = 'Ссылка недействительна, срок действия истёк или отчёт был удалён.'
  } finally {
    loading.value = false
  }
})
</script>
