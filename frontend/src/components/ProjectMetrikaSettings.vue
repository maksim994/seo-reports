<template>
  <section
    v-if="integration"
    class="rounded-2xl border border-amber-200 bg-white p-6 shadow-sm"
  >
    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
      <div class="flex items-center gap-3">
        <IntegrationLogo
          :provider="integration.provider"
          :label="integration.label"
          :logo-url="integration.logo_url"
          size="md"
        />
        <div>
          <h2 class="text-lg font-medium text-gray-900">Яндекс.Метрика</h2>
          <p class="text-sm text-gray-500">
            Цели и канал для блоков «Цели» и «Конверсии по каналам» в отчётах
          </p>
        </div>
      </div>
      <span
        v-if="isBound"
        class="inline-flex rounded-full bg-green-50 px-3 py-1 text-xs font-medium text-green-700"
      >
        Счётчик привязан
      </span>
    </div>

    <div v-if="loading" class="text-sm text-gray-500">Загрузка...</div>
    <div v-else-if="!isBound" class="rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-900">
      Сначала привяжите счётчик Метрики в блоке «Источники данных» выше.
    </div>
    <form v-else class="space-y-6" @submit.prevent="save">
      <p v-if="bindingLabel" class="text-sm text-gray-600">
        Счётчик: <span class="font-medium text-gray-900">{{ bindingLabel }}</span>
      </p>

      <div v-if="optionsError" class="text-sm text-error-500">{{ optionsError }}</div>

      <div>
        <label class="mb-2 block text-sm font-medium text-gray-700">
          Цели в отчёте
        </label>
        <p class="mb-3 text-xs text-gray-500">
          Если ничего не выбрано — в отчёт попадут все активные цели счётчика.
        </p>
        <div
          class="max-h-56 space-y-2 overflow-y-auto rounded-lg border border-gray-200 p-3"
        >
          <label
            v-for="goal in goalOptions"
            :key="goal.value"
            class="flex cursor-pointer items-center gap-2 text-sm text-gray-700"
          >
            <input
              type="checkbox"
              class="rounded border-gray-300 text-brand-500 focus:ring-brand-500"
              :checked="form.goalIds.includes(goal.value)"
              @change="toggleGoal(goal.value, ($event.target as HTMLInputElement).checked)"
            />
            <span>{{ goal.label }}</span>
          </label>
          <p v-if="goalOptions.length === 0" class="text-xs text-gray-500">
            Активные цели не найдены для этого счётчика.
          </p>
        </div>
      </div>

      <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700">
          Канал для блока «Цели»
        </label>
        <p class="mb-2 text-xs text-gray-500">
          Показывать достижения целей только с выбранного источника трафика.
        </p>
        <select
          v-model="form.trafficSource"
          class="w-full max-w-md rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand-500"
        >
          <option
            v-for="source in trafficSourceOptions"
            :key="source.value"
            :value="source.value"
          >
            {{ source.label }}
          </option>
        </select>
      </div>

      <div class="flex flex-wrap items-center gap-3">
        <button
          type="submit"
          :disabled="saving"
          class="rounded-lg bg-brand-500 px-4 py-2 text-sm font-medium text-white hover:bg-brand-600 disabled:opacity-50"
        >
          {{ saving ? 'Сохранение...' : 'Сохранить настройки' }}
        </button>
        <p v-if="savedMessage" class="text-sm text-green-700">{{ savedMessage }}</p>
      </div>
    </form>
  </section>
</template>

<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import IntegrationLogo from '@/components/IntegrationLogo.vue'
import api from '@/lib/api'
import {
  emptyMetrikaFormState,
  metrikaConfigFromForm,
  metrikaFormFromConfig,
  type MetrikaFormState,
} from '@/lib/metrikaProjectConfig'
import { useIntegrationsStore } from '@/stores/integrations'
import { useProjectIntegrationsStore } from '@/stores/projectIntegrations'
import type { Integration } from '@/types'

const props = defineProps<{ projectId: number }>()

const emit = defineEmits<{ saved: [] }>()

const integrationsStore = useIntegrationsStore()
const bindingsStore = useProjectIntegrationsStore()

const loading = ref(false)
const saving = ref(false)
const savedMessage = ref('')
const optionsError = ref('')
const integration = ref<Integration | null>(null)
const goalOptions = ref<Array<{ value: string; label: string }>>([])
const trafficSourceOptions = ref<Array<{ value: string; label: string }>>([])
const form = reactive<MetrikaFormState>(emptyMetrikaFormState())

const isBound = computed(() =>
  integration.value
    ? bindingsStore.bindings.some((b) => b.integration_id === integration.value!.id)
    : false,
)

const bindingLabel = computed(() => {
  const binding = currentBinding()
  return binding?.external_resource_label ?? binding?.external_resource_id ?? null
})

function currentBinding() {
  if (!integration.value) return null
  return bindingsStore.bindings.find((b) => b.integration_id === integration.value!.id) ?? null
}

function toggleGoal(value: string, checked: boolean) {
  const set = new Set(form.goalIds)
  if (checked) {
    set.add(value)
  } else {
    set.delete(value)
  }
  form.goalIds = [...set]
}

function mergeConfig(
  existing: Record<string, unknown> | null | undefined,
  patch: ReturnType<typeof metrikaConfigFromForm>,
): Record<string, unknown> {
  const base = { ...(existing ?? {}) }
  if (patch.metrika) {
    base.metrika = patch.metrika
  }
  return base
}

async function loadOptions() {
  optionsError.value = ''
  goalOptions.value = []
  trafficSourceOptions.value = []

  if (!isBound.value) return

  try {
    const { data } = await api.get<{
      data: {
        goals: Array<{ value: string; label: string }>
        traffic_sources: Array<{ value: string; label: string }>
      }
    }>(`/projects/${props.projectId}/metrika/options`)

    goalOptions.value = data.data.goals
    trafficSourceOptions.value = data.data.traffic_sources
  } catch {
    optionsError.value = 'Не удалось загрузить цели Метрики. Проверьте OAuth и счётчик.'
  }
}

function applyBindingState() {
  const binding = currentBinding()
  Object.assign(form, metrikaFormFromConfig(binding?.config ?? null))
}

async function load() {
  loading.value = true
  optionsError.value = ''
  try {
    await integrationsStore.fetchIntegrations()
    integration.value =
      integrationsStore.integrations.find(
        (item) => item.provider === 'yandex_metrika' && item.status === 'active',
      ) ?? null

    if (!integration.value) return

    await bindingsStore.fetchBindings(props.projectId)
    applyBindingState()
    await loadOptions()
  } finally {
    loading.value = false
  }
}

async function save() {
  const binding = currentBinding()
  if (!integration.value || !binding) return

  saving.value = true
  savedMessage.value = ''
  try {
    const metrikaPatch = metrikaConfigFromForm(form)
    await bindingsStore.bind(props.projectId, {
      integration_id: integration.value.id,
      external_resource_id: binding.external_resource_id,
      external_resource_label: binding.external_resource_label,
      config: mergeConfig(binding.config, metrikaPatch),
    })
    savedMessage.value = 'Настройки сохранены'
    emit('saved')
  } finally {
    saving.value = false
  }
}

watch(
  () => props.projectId,
  (projectId) => {
    if (projectId) load()
  },
  { immediate: true },
)

watch(isBound, (bound) => {
  if (bound) {
    applyBindingState()
    loadOptions()
  }
})
</script>
