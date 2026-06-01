<template>
  <section class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
    <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h2 class="text-lg font-medium text-gray-900">Источники данных</h2>
        <p class="mt-1 text-sm text-gray-500">
          Привязка счётчиков, сайтов и проектов мониторинга к этому проекту. Keys.so подключается
          только в «Интеграциях» — отчётные блоки берут данные по домену проекта.
        </p>
      </div>
      <RouterLink to="/integrations" class="text-sm font-medium text-brand-600 hover:underline">
        Управление интеграциями →
      </RouterLink>
    </div>

    <div v-if="loading" class="text-sm text-gray-500">Загрузка...</div>

    <div v-else-if="integrations.length === 0" class="text-sm text-gray-600">
      Нет подключённых интеграций.
      <RouterLink to="/integrations" class="font-medium text-brand-600 hover:underline">
        Подключить источники данных
      </RouterLink>
    </div>

    <div v-else class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
      <div
        v-for="integration in integrations"
        :key="integration.id"
        class="flex flex-col rounded-xl border border-gray-200 p-4"
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
        <div v-else class="mt-auto space-y-2">
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
  </section>
</template>

<script setup lang="ts">
import { reactive, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import IntegrationLogo from '@/components/IntegrationLogo.vue'
import {
  formatIntegrationResourceOption,
  formatTopvisorRegionOption,
  integrationResourceSupportsBinding,
  topvisorRegions,
} from '@/lib/integrationResource'
import { useIntegrationsStore } from '@/stores/integrations'
import { useProjectIntegrationsStore } from '@/stores/projectIntegrations'
import type { Integration, IntegrationResource } from '@/types'
import type { AxiosError } from 'axios'
import type { ApiError } from '@/types'

const props = defineProps<{
  projectId: number
  projectDomain: string | null
}>()

const emit = defineEmits<{ saved: [] }>()

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
  return list.filter((resource) => integrationResourceSupportsBinding(resource))
}

function topvisorRegionsFor(integrationId: number) {
  const resourceId = selectedResource[integrationId]
  if (!resourceId) return []
  const resource = (resources[integrationId] ?? []).find((item) => item.id === resourceId)
  return resource ? topvisorRegions(resource) : []
}

function resourceSelectPlaceholder(integration: Integration) {
  if (integration.provider === 'topvisor') return '— выберите проект Topvisor —'
  return '— выберите ресурс —'
}

function onResourceChange(integration: Integration) {
  selectedRegion[integration.id] = ''
  suggestRegion(integration.id)
}

function suggestRegion(integrationId: number) {
  const binding = currentBinding(integrationId)
  if (binding?.config && typeof binding.config.region_index === 'number') {
    selectedRegion[integrationId] = String(binding.config.region_index)
    return
  }

  const regions = topvisorRegionsFor(integrationId)
  const yandex = regions.find((region) => /yandex|яндекс/i.test(region.searcher))
  if (yandex) selectedRegion[integrationId] = String(yandex.index)
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

function topvisorRegionCount(resource: IntegrationResource): number {
  return topvisorRegions(resource).length
}

function suggestResource(integrationId: number) {
  const binding = currentBinding(integrationId)
  const integration = integrations.value.find((item) => item.id === integrationId)
  if (!integration) return

  if (binding) {
    selectedResource[integrationId] = binding.external_resource_id
    suggestRegion(integrationId)
    return
  }

  const list = bindableResources(integration)
  const match = list
    .filter((r) => resourceMatchesDomain(props.projectDomain, r))
    .sort((a, b) => topvisorRegionCount(b) - topvisorRegionCount(a))[0]
  if (match) {
    selectedResource[integrationId] = match.id
    suggestRegion(integrationId)
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
  loading.value = true
  try {
    await integrationsStore.fetchIntegrations()
    integrations.value = integrationsStore.integrations.filter(
      (i) => i.status === 'active' && i.provider !== 'yandex_wordstat' && i.provider !== 'keys_so',
    )
    await bindingsStore.fetchBindings(props.projectId)
    await Promise.all(integrations.value.map((integration) => loadResources(integration)))
  } finally {
    loading.value = false
  }
}

async function save(integration: Integration) {
  if (!selectedResource[integration.id]) return

  const resource = bindableResources(integration).find(
    (r) => r.id === selectedResource[integration.id],
  )
  if (!resource) return

  saving.value = integration.id
  try {
    const existingConfig = currentBinding(integration.id)?.config ?? undefined
    const config =
      integration.provider === 'topvisor' && selectedRegion[integration.id]
        ? { ...(existingConfig ?? {}), region_index: Number(selectedRegion[integration.id]) }
        : existingConfig

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
  const binding = currentBinding(integrationId)
  if (!binding) return

  await bindingsStore.unbind(props.projectId, binding.id)
  selectedResource[integrationId] = ''
  emit('saved')
}

watch(
  () => props.projectId,
  (projectId) => {
    if (projectId) load()
  },
  { immediate: true },
)
</script>
