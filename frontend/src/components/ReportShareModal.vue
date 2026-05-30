<template>
  <AppModal v-model="open" title="Публичная ссылка на отчёт" size="lg">
    <div v-if="error" class="mb-4 rounded-lg bg-red-50 px-4 py-3 text-sm text-error-500">
      {{ error }}
    </div>

    <p class="text-sm text-gray-600">
      Любой, у кого есть ссылка, сможет просмотреть отчёт без входа в систему.
    </p>

    <div v-if="report.share_enabled && shareUrl" class="mt-4 space-y-3">
      <div>
        <label class="mb-1.5 block text-xs font-medium text-gray-500">Ссылка</label>
        <div class="flex gap-2">
          <input
            :value="shareUrl"
            readonly
            class="min-w-0 flex-1 rounded-lg border border-gray-200 px-3 py-2 text-sm text-gray-700 outline-none"
          />
          <button
            class="rounded-lg border border-gray-200 px-3 py-2 text-sm hover:bg-gray-50"
            @click="copyLink"
          >
            {{ copied ? 'Скопировано' : 'Копировать' }}
          </button>
        </div>
      </div>

      <div class="flex flex-wrap gap-2 text-sm">
        <a
          :href="shareUrl"
          target="_blank"
          class="rounded-lg border border-gray-200 px-3 py-1.5 text-gray-700 hover:bg-gray-50"
        >
          Открыть
        </a>
        <button
          class="rounded-lg border border-gray-200 px-3 py-1.5 text-gray-700 hover:bg-gray-50 disabled:opacity-50"
          :disabled="busy"
          @click="regenerate"
        >
          Новая ссылка
        </button>
        <button
          class="rounded-lg border border-red-200 px-3 py-1.5 text-error-500 hover:bg-red-50 disabled:opacity-50"
          :disabled="busy"
          @click="disable"
        >
          Отключить
        </button>
      </div>
    </div>

    <div v-else class="mt-4">
      <button
        class="rounded-lg bg-brand-500 px-4 py-2 text-sm font-medium text-white hover:bg-brand-600 disabled:opacity-50"
        :disabled="busy"
        @click="enable"
      >
        {{ busy ? 'Создание...' : 'Создать публичную ссылку' }}
      </button>
    </div>

    <template #footer>
      <button
        class="rounded-lg border border-gray-200 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"
        @click="open = false"
      >
        Закрыть
      </button>
    </template>
  </AppModal>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import AppModal from '@/components/AppModal.vue'
import { useReportsStore } from '@/stores/reports'
import type { ReportJob } from '@/types'

const props = defineProps<{
  modelValue: boolean
  report: ReportJob
}>()

const emit = defineEmits<{
  'update:modelValue': [value: boolean]
  updated: [report: ReportJob]
}>()

const store = useReportsStore()
const busy = ref(false)
const error = ref('')
const copied = ref(false)
const localReport = ref(props.report)

const open = computed({
  get: () => props.modelValue,
  set: (value: boolean) => emit('update:modelValue', value),
})

const shareUrl = computed(() =>
  localReport.value.share_enabled && localReport.value.share_token
    ? store.publicShareUrl(localReport.value.share_token)
    : '',
)

watch(
  () => props.report,
  (report) => {
    localReport.value = report
  },
)

async function enable() {
  busy.value = true
  error.value = ''
  try {
    localReport.value = await store.enableShare(localReport.value.id)
    emit('updated', localReport.value)
  } catch {
    error.value = 'Не удалось создать ссылку'
  } finally {
    busy.value = false
  }
}

async function disable() {
  busy.value = true
  error.value = ''
  try {
    localReport.value = await store.disableShare(localReport.value.id)
    emit('updated', localReport.value)
  } catch {
    error.value = 'Не удалось отключить ссылку'
  } finally {
    busy.value = false
  }
}

async function regenerate() {
  if (!confirm('Старая ссылка перестанет работать. Продолжить?')) return
  busy.value = true
  error.value = ''
  try {
    localReport.value = await store.regenerateShare(localReport.value.id)
    emit('updated', localReport.value)
  } catch {
    error.value = 'Не удалось обновить ссылку'
  } finally {
    busy.value = false
  }
}

async function copyLink() {
  if (!shareUrl.value) return
  try {
    await navigator.clipboard.writeText(shareUrl.value)
    copied.value = true
    setTimeout(() => {
      copied.value = false
    }, 2000)
  } catch {
    error.value = 'Не удалось скопировать ссылку'
  }
}
</script>
