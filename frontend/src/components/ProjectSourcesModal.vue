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
        <div class="mb-3 flex items-center gap-3">
          <IntegrationLogo
            :provider="integration.provider"
            :label="integration.label"
            :logo-url="integration.logo_url"
            size="sm"
          />
          <div class="min-w-0 flex-1">
            <div class="font-medium text-gray-900">{{ integration.label }}</div>
            <div class="text-xs text-gray-500">{{ integration.account_label }}</div>
          </div>
          <span
            v-if="currentBinding(integration.id)"
            class="shrink-0 rounded-full bg-green-50 px-2 py-0.5 text-xs text-green-700"
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
            @change="onResourceChange(integration)"
          >
            <option value="">{{ resourceSelectPlaceholder(integration) }}</option>
            <option
              v-for="resource in bindableResources(integration)"
              :key="resource.id"
              :value="resource.id"
            >
              {{ formatResourceOption(resource) }}
            </option>
          </select>
          <select
            v-if="integration.provider === 'topvisor' && topvisorRegionsFor(integration.id).length"
            v-model="selectedRegion[integration.id]"
            class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand-500"
          >
            <option value="">— регион для отчёта (по умолчанию Яндекс) —</option>
            <option
              v-for="region in topvisorRegionsFor(integration.id)"
              :key="region.index"
              :value="String(region.index)"
            >
              {{ formatTopvisorRegionOption(region) }}
            </option>
          </select>
          <select
            v-if="integration.provider === 'keys_so' && keysSoSettingsFor(integration.id).length"
            v-model="selectedKeysSoSetting[integration.id]"
            class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand-500"
          >
            <option value="">— регион и ПС (по умолчанию Яндекс) —</option>
            <option
              v-for="setting in keysSoSettingsFor(integration.id)"
              :key="`${setting.region_id}:${setting.engine}`"
              :value="`${setting.region_id}:${setting.engine}`"
            >
              {{ formatKeysSoSearchSettingOption(setting) }}
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
import IntegrationLogo from '@/components/IntegrationLogo.vue'
import { formatIntegrationResourceOption, formatKeysSoSearchSettingOption, formatTopvisorRegionOption, keysSoSearchSettings, topvisorRegions } from '@/lib/integrationResource'
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
const selectedRegion = reactive<Record<number, string>>({})
const selectedKeysSoSetting = reactive<Record<number, string>>({})

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
  return formatIntegrationResourceOption(resource)
}

function bindableResources(integration: Integration) {
  const list = resources[integration.id] ?? []
  if (integration.provider === 'keys_so') {
    return list.filter((resource) => resource.meta?.supports_positions !== false)
  }

  return list
}

function topvisorRegionsFor(integrationId: number) {
  const resourceId = selectedResource[integrationId]
  if (!resourceId) return []
  const resource = (resources[integrationId] ?? []).find((item) => item.id === resourceId)
  return resource ? topvisorRegions(resource) : []
}

function keysSoSettingsFor(integrationId: number) {
  const resourceId = selectedResource[integrationId]
  if (!resourceId) return []
  const resource = (resources[integrationId] ?? []).find((item) => item.id === resourceId)
  return resource ? keysSoSearchSettings(resource) : []
}

function resourceSelectPlaceholder(integration: Integration) {
  if (integration.provider === 'topvisor') return '— выберите проект Topvisor —'
  if (integration.provider === 'keys_so') return '— выберите проект Keys.so —'
  return '— выберите ресурс —'
}

function onResourceChange(integration: Integration) {
  selectedRegion[integration.id] = ''
  selectedKeysSoSetting[integration.id] = ''
  suggestRegion(integration.id)
  suggestKeysSoSetting(integration.id)
}

function suggestRegion(integrationId: number) {
  const binding = currentBinding(integrationId)
  if (binding?.config && typeof binding.config.region_index === 'number') {
    selectedRegion[integrationId] = String(binding.config.region_index)
    return
  }

  const regions = topvisorRegionsFor(integrationId)
  const yandex = regions.find((region) =>
    /yandex|яндекс/i.test(region.searcher),
  )
  if (yandex) {
    selectedRegion[integrationId] = String(yandex.index)
  }
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

function suggestKeysSoSetting(integrationId: number) {
  const binding = currentBinding(integrationId)
  if (binding?.config && typeof binding.config.region_id === 'number') {
    const engine = typeof binding.config.engine === 'number' ? binding.config.engine : 0
    selectedKeysSoSetting[integrationId] = `${binding.config.region_id}:${engine}`
    return
  }

  const settings = keysSoSettingsFor(integrationId)
  const yandex = settings.find((setting) => setting.engine === 0)
  if (yandex) {
    selectedKeysSoSetting[integrationId] = `${yandex.region_id}:${yandex.engine}`
  }
}

function suggestResource(integrationId: number) {
  const binding = currentBinding(integrationId)
  if (binding) {
    selectedResource[integrationId] = binding.external_resource_id
    suggestRegion(integrationId)
    suggestKeysSoSetting(integrationId)
    return
  }

  const list = resources[integrationId] ?? []
  const match = list.find((r) => resourceMatchesDomain(props.projectDomain, r))
  if (match) {
    selectedResource[integrationId] = match.id
    suggestRegion(integrationId)
    suggestKeysSoSetting(integrationId)
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
    const config =
      integration.provider === 'topvisor' && selectedRegion[integration.id]
        ? { region_index: Number(selectedRegion[integration.id]) }
        : integration.provider === 'keys_so' && selectedKeysSoSetting[integration.id]
          ? (() => {
              const [regionId, engine] = selectedKeysSoSetting[integration.id].split(':')
              return {
                region_id: Number(regionId),
                engine: Number(engine),
              }
            })()
          : undefined

    await bindingsStore.bind(props.projectId, {
      integration_id: integration.id,
      external_resource_id: selectedResource[integration.id],
      external_resource_label: resource?.label ?? null,
      config,
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
