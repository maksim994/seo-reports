<template>
  <AppModal :model-value="modelValue" :title="`Источники: ${projectName}`" @update:model-value="emit('update:modelValue', $event)">
    <div v-if="loading" class="text-sm text-gray-500">Загрузка...</div>

    <div v-else-if="integrations.length === 0" class="text-sm text-gray-600">
      Нет подключённых интеграций.
      <RouterLink to="/integrations" class="font-medium text-brand-600 hover:underline">
        Подключить источники данных
      </RouterLink>
    </div>

    <div v-else class="space-y-5">
      <div
        v-for="integration in integrations"
        :key="integration.id"
        class="rounded-xl border border-gray-200 p-4"
      >
        <div class="mb-3 flex items-center justify-between gap-2">
          <div>
            <div class="font-medium text-gray-900">{{ integration.label }}</div>
            <div class="text-xs text-gray-500">{{ integration.account_label }}</div>
          </div>
          <span
            v-if="currentBinding(integration.id)"
            class="rounded-full bg-green-50 px-2 py-0.5 text-xs text-green-700"
          >
            Привязано
          </span>
        </div>

        <div v-if="resourcesLoading[integration.id]" class="text-xs text-gray-500">
          Загрузка ресурсов...
        </div>
        <div v-else-if="resourcesError[integration.id]" class="text-xs text-error-500">
          {{ resourcesError[integration.id] }}
        </div>
        <div v-else-if="(resources[integration.id] ?? []).length === 0" class="text-xs text-gray-500">
          Ресурсы не найдены
        </div>
        <div v-else class="space-y-2">
          <select
            v-model="selectedResource[integration.id]"
            class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand-500"
          >
            <option value="">— выберите ресурс —</option>
            <option
              v-for="resource in resources[integration.id]"
              :key="resource.id"
              :value="resource.id"
            >
              {{ formatResourceOption(resource) }}
            </option>
          </select>
          <div class="flex gap-2">
            <button
              :disabled="!selectedResource[integration.id] || saving === integration.id"
              class="rounded-lg bg-brand-500 px-3 py-1.5 text-xs font-medium text-white hover:bg-brand-600 disabled:opacity-50"
              @click="save(integration)"
            >
              {{ saving === integration.id ? 'Сохранение...' : 'Привязать' }}
            </button>
            <button
              v-if="currentBinding(integration.id)"
              class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs text-gray-700 hover:bg-gray-50"
              @click="unbind(integration.id)"
            >
              Отвязать
            </button>
          </div>
        </div>
      </div>
    </div>

    <template #footer>
      <button
        type="button"
        class="rounded-lg border border-gray-200 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"
        @click="emit('update:modelValue', false)"
      >
        Закрыть
      </button>
    </template>
  </AppModal>
</template>

<script setup lang="ts">
import { reactive, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import AppModal from '@/components/AppModal.vue'
import { useIntegrationsStore } from '@/stores/integrations'
import { useProjectIntegrationsStore } from '@/stores/projectIntegrations'
import type { Integration, IntegrationResource } from '@/types'
import type { AxiosError } from 'axios'
import type { ApiError } from '@/types'

const props = defineProps<{
  modelValue: boolean
  projectId: number | null
  projectName: string
  projectDomain: string | null
}>()

const emit = defineEmits<{
  'update:modelValue': [value: boolean]
  saved: []
}>()

const integrationsStore = useIntegrationsStore()
const bindingsStore = useProjectIntegrationsStore()

const loading = ref(false)
const saving = ref<number | null>(null)
const integrations = ref<Integration[]>([])
const resources = reactive<Record<number, IntegrationResource[]>>({})
const resourcesLoading = reactive<Record<number, boolean>>({})
const resourcesError = reactive<Record<number, string>>({})
const selectedResource = reactive<Record<number, string>>({})

function currentBinding(integrationId: number) {
  return bindingsStore.bindings.find((b) => b.integration_id === integrationId)
}

function normalizeDomain(value: string): string {
  return value
    .replace(/^sc-domain:/, '')
    .replace(/^https?:\/\//, '')
    .replace(/^www\./, '')
    .replace(/\/$/, '')
    .toLowerCase()
}

function formatResourceOption(resource: IntegrationResource): string {
  if (resource.label?.trim()) {
    return resource.label
  }
  const counterId = resource.meta?.counter_id
  return counterId ? `#${counterId}` : resource.id
}

function resourceMatchesDomain(domain: string | null, resource: IntegrationResource): boolean {
  if (!domain) return false
  const norm = normalizeDomain(domain)
  const candidates = [
    resource.label,
    String(resource.meta?.site ?? ''),
    String(resource.meta?.name ?? ''),
    String(resource.meta?.account ?? ''),
  ].map(normalizeDomain)

  return candidates.some((c) => c && (c.includes(norm) || norm.includes(c)))
}

function suggestResource(integrationId: number) {
  const binding = currentBinding(integrationId)
  if (binding) {
    selectedResource[integrationId] = binding.external_resource_id
    return
  }

  const list = resources[integrationId] ?? []
  const match = list.find((r) => resourceMatchesDomain(props.projectDomain, r))
  if (match) {
    selectedResource[integrationId] = match.id
  }
}

async function loadResources(integration: Integration) {
  resourcesLoading[integration.id] = true
  resourcesError[integration.id] = ''
  try {
    resources[integration.id] = await integrationsStore.fetchResources(integration.id)
    suggestResource(integration.id)
  } catch (e) {
    const err = e as AxiosError<ApiError>
    resourcesError[integration.id] =
      err.response?.data?.message ?? 'Не удалось загрузить ресурсы'
    resources[integration.id] = []
  } finally {
    resourcesLoading[integration.id] = false
  }
}

async function load() {
  if (!props.projectId) return
  loading.value = true
  try {
    await integrationsStore.fetchIntegrations()
    integrations.value = integrationsStore.integrations.filter((i) => i.status === 'active')
    await bindingsStore.fetchBindings(props.projectId)

    await Promise.all(integrations.value.map((integration) => loadResources(integration)))
  } finally {
    loading.value = false
  }
}

async function save(integration: Integration) {
  if (!props.projectId || !selectedResource[integration.id]) return

  const resource = (resources[integration.id] ?? []).find(
    (r) => r.id === selectedResource[integration.id],
  )

  saving.value = integration.id
  try {
    await bindingsStore.bind(props.projectId, {
      integration_id: integration.id,
      external_resource_id: selectedResource[integration.id],
      external_resource_label: resource?.label ?? null,
    })
    emit('saved')
  } finally {
    saving.value = null
  }
}

async function unbind(integrationId: number) {
  if (!props.projectId) return
  const binding = currentBinding(integrationId)
  if (!binding) return

  await bindingsStore.unbind(props.projectId, binding.id)
  selectedResource[integrationId] = ''
  emit('saved')
}

watch(
  () => [props.modelValue, props.projectId] as const,
  ([open, projectId]) => {
    if (open && projectId) {
      load()
    }
  },
)
</script>
