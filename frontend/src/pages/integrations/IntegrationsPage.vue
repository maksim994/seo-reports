<template>
  <div>
    <div class="mb-6">
      <h1 class="text-2xl font-semibold text-gray-900">Источники данных</h1>
      <p class="mt-1 text-sm text-gray-500">
        Подключите аналитику и вебмастеры для генерации отчётов
      </p>
    </div>

    <div
      v-if="flashMessage"
      class="mb-4 rounded-lg px-4 py-3 text-sm"
      :class="
        flashType === 'success'
          ? 'border border-green-200 bg-green-50 text-green-800'
          : 'border border-red-200 bg-red-50 text-red-700'
      "
    >
      {{ flashMessage }}
    </div>

    <div v-if="loading" class="text-sm text-gray-500">Загрузка...</div>

    <div v-else class="grid gap-4 md:grid-cols-2 xl:grid-cols-2">
      <div
        v-for="provider in store.providers"
        :key="provider.provider"
        class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm transition-shadow hover:shadow-md"
      >
        <div class="mb-4 flex items-start justify-between gap-3">
          <div class="flex min-w-0 items-center gap-4">
            <IntegrationLogo
              :provider="provider.provider"
              :label="provider.label"
              :icon="provider.icon"
              :logo-url="provider.logo_url"
              :size="integrationLogoUrl(provider.provider, provider.logo_url) ? 'lg' : 'md'"
            />
            <div class="min-w-0">
              <h2 class="font-semibold text-gray-900">{{ provider.label }}</h2>
              <p class="text-sm text-gray-500">{{ provider.description }}</p>
            </div>
          </div>
          <span
            v-if="connected(provider.provider)"
            class="inline-flex rounded-full bg-green-50 px-2.5 py-0.5 text-xs font-medium text-green-700"
          >
            Подключено
          </span>
        </div>

        <div v-if="connected(provider.provider)" class="mb-4 text-sm text-gray-600">
          {{ connected(provider.provider)?.account_label || 'Аккаунт подключён' }}
          <span
            v-if="connected(provider.provider)?.status !== 'active'"
            class="ml-2 text-amber-600"
          >
            ({{ statusLabel(connected(provider.provider)!.status) }})
          </span>
        </div>

        <div class="flex flex-wrap gap-2">
          <button
            v-if="!connected(provider.provider) && provider.auth_type !== 'api_key'"
            :disabled="!provider.configured || store.connecting === provider.provider"
            class="rounded-lg bg-brand-500 px-4 py-2 text-sm font-medium text-white hover:bg-brand-600 disabled:cursor-not-allowed disabled:opacity-50"
            @click="handleConnect(provider.provider)"
          >
            {{
              store.connecting === provider.provider
                ? 'Подключение...'
                : provider.configured
                  ? 'Подключить'
                  : 'Скоро (нужен OAuth)'
            }}
          </button>
          <button
            v-if="!connected(provider.provider) && provider.auth_type === 'api_key'"
            :disabled="store.connecting === provider.provider"
            class="rounded-lg bg-brand-500 px-4 py-2 text-sm font-medium text-white hover:bg-brand-600 disabled:cursor-not-allowed disabled:opacity-50"
            @click="openApiKeyModal(provider)"
          >
            {{ store.connecting === provider.provider ? 'Подключение...' : 'Подключить по API key' }}
          </button>
          <button
            v-else
            class="rounded-lg border border-gray-200 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"
            @click="openResources(connected(provider.provider)!)"
          >
            Ресурсы
          </button>
          <button
            v-if="connected(provider.provider)"
            class="rounded-lg border border-gray-200 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"
            @click="handleDisconnect(connected(provider.provider)!.id)"
          >
            Отключить
          </button>
        </div>

        <p v-if="!provider.configured && provider.auth_type !== 'api_key'" class="mt-3 text-xs text-gray-400">
          Добавьте OAuth-ключи в backend/.env для активации подключения.
        </p>
        <p v-if="provider.auth_type === 'api_key'" class="mt-3 text-xs text-gray-400">
          {{ apiKeyHint(provider) }}
        </p>
      </div>
    </div>

    <AppModal v-model="showResourcesModal" :title="resourcesModalTitle">
      <div
        v-if="resourcesIntegration"
        class="mb-4 flex items-center gap-3 rounded-xl border border-gray-100 bg-gray-50 p-3"
      >
        <IntegrationLogo
          :provider="resourcesIntegration.provider"
          :label="resourcesIntegration.label"
          :logo-url="resourcesIntegration.logo_url"
          size="lg"
        />
        <div class="text-sm text-gray-600">{{ resourcesIntegration.account_label || 'Подключённый аккаунт' }}</div>
      </div>
      <div v-if="resourcesLoading" class="text-sm text-gray-500">Загрузка...</div>
      <div v-else-if="resourcesError" class="text-sm text-error-500">{{ resourcesError }}</div>
      <ul v-else-if="resourcesList.length" class="max-h-80 space-y-2 overflow-y-auto text-sm">
        <li class="sticky top-0 z-10 bg-white pb-2 text-xs text-gray-500">
          {{ resourcesSummaryText }}
        </li>
        <li
          v-for="resource in resourcesList"
          :key="resource.id"
          class="rounded-lg border border-gray-100 px-3 py-2"
        >
          <div class="font-medium text-gray-900">
            {{ integrationResourceTitle(resource) }}
          </div>
          <div v-if="integrationResourceSubtitle(resource)" class="text-gray-700">
            {{ integrationResourceSubtitle(resource) }}
          </div>
          <div v-if="integrationResourceHint(resource)" class="text-xs text-gray-500">
            {{ integrationResourceHint(resource) }}
          </div>
        </li>
      </ul>
      <div v-else class="text-sm text-gray-500">Ресурсы не найдены</div>
      <template #footer>
        <button
          class="rounded-lg border border-gray-200 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"
          @click="showResourcesModal = false"
        >
          Закрыть
        </button>
      </template>
    </AppModal>

    <AppModal v-model="showApiKeyModal" :title="apiKeyModalTitle">
      <div v-if="apiKeyProvider" class="mb-4 flex items-center gap-3 rounded-xl border border-gray-100 bg-gray-50 p-3">
        <IntegrationLogo
          :provider="apiKeyProvider.provider"
          :label="apiKeyProvider.label"
          :icon="apiKeyProvider.icon"
          :logo-url="apiKeyProvider.logo_url"
          size="lg"
        />
        <div>
          <div class="font-medium text-gray-900">{{ apiKeyProvider.label }}</div>
          <div class="text-xs text-gray-500">Подключение по API</div>
        </div>
      </div>
      <form id="api-key-form" class="space-y-4" @submit.prevent="submitApiKey">
        <div v-if="apiKeyError" class="rounded-lg bg-red-50 px-4 py-3 text-sm text-error-500">
          {{ apiKeyError }}
        </div>
        <div v-if="apiKeyProvider && needsUserId(apiKeyProvider)">
          <label class="mb-1.5 block text-sm font-medium text-gray-700">User ID</label>
          <input
            v-model="apiKeyForm.user_id"
            required
            class="w-full rounded-lg border border-gray-200 px-4 py-2.5 text-sm outline-none focus:border-brand-500"
            placeholder="12345"
          />
        </div>
        <div>
          <label class="mb-1.5 block text-sm font-medium text-gray-700">
            {{ apiKeyProvider?.provider === 'keys_so' ? 'API-токен' : 'API key' }}
          </label>
          <input
            v-model="apiKeyForm.api_key"
            required
            type="password"
            class="w-full rounded-lg border border-gray-200 px-4 py-2.5 text-sm outline-none focus:border-brand-500"
            placeholder="••••••••"
          />
        </div>
      </form>
      <template #footer>
        <button
          type="submit"
          form="api-key-form"
          class="rounded-lg bg-brand-500 px-4 py-2 text-sm font-medium text-white hover:bg-brand-600"
        >
          Подключить
        </button>
      </template>
    </AppModal>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AppModal from '@/components/AppModal.vue'
import IntegrationLogo from '@/components/IntegrationLogo.vue'
import {
  integrationResourceHint,
  integrationResourceSubtitle,
  integrationResourceTitle,
  keysSoResourceGroups,
} from '@/lib/integrationResource'
import { integrationLogoUrl } from '@/lib/integrationBranding'
import { useIntegrationsStore } from '@/stores/integrations'
import type { AxiosError } from 'axios'
import type { ApiError, Integration, IntegrationProviderMeta, IntegrationResource } from '@/types'

const store = useIntegrationsStore()
const route = useRoute()
const router = useRouter()
const loading = ref(true)
const flashMessage = ref('')
const flashType = ref<'success' | 'error'>('success')
const showResourcesModal = ref(false)
const resourcesModalTitle = ref('Ресурсы')
const resourcesIntegration = ref<Integration | null>(null)
const resourcesLoading = ref(false)
const resourcesError = ref('')
const resourcesList = ref<IntegrationResource[]>([])
const showApiKeyModal = ref(false)
const apiKeyProvider = ref<IntegrationProviderMeta | null>(null)
const apiKeyError = ref('')
const apiKeyForm = ref({ user_id: '', api_key: '' })

const apiKeyModalTitle = computed(() =>
  apiKeyProvider.value ? `Подключение: ${apiKeyProvider.value.label}` : 'Подключение по API key',
)

const resourcesSummaryText = computed(() => {
  if (resourcesIntegration.value?.provider === 'keys_so') {
    const { monitoring, dashboard } = keysSoResourceGroups(resourcesList.value)
    return `Мониторинг: ${monitoring.length} · Дашборд: ${dashboard.length} · Всего: ${resourcesList.value.length}`
  }

  return `Найдено: ${resourcesList.value.length}`
})

const flashFromQuery = computed(() => ({
  integration: route.query.integration as string | undefined,
  detail: route.query.detail as string | undefined,
}))

function connected(provider: string) {
  return store.findByProvider(provider)
}

function statusLabel(status: string) {
  if (status === 'token_expired') return 'токен истёк'
  if (status === 'error') return 'ошибка'
  return status
}

function needsUserId(provider: IntegrationProviderMeta) {
  return (provider.api_key_fields ?? ['user_id', 'api_key']).includes('user_id')
}

function apiKeyHint(provider: IntegrationProviderMeta) {
  if (provider.provider === 'keys_so') {
    return 'API-токен из личного кабинета Keys.so (Настройки → API). Лимит: 10 запросов / 10 сек.'
  }

  return 'User ID — это ID аккаунта Topvisor (из настроек профиля), не ID проекта. API key — из того же раздела.'
}

async function load() {
  loading.value = true
  try {
    await Promise.all([store.fetchProviders(), store.fetchIntegrations()])
  } finally {
    loading.value = false
  }
}

async function handleConnect(provider: string) {
  try {
    await store.connect(provider)
  } catch (e) {
    const err = e as AxiosError<ApiError>
    flashType.value = 'error'
    flashMessage.value = err.response?.data?.message ?? 'Не удалось начать OAuth'
  }
}

function openApiKeyModal(provider: IntegrationProviderMeta) {
  apiKeyProvider.value = provider
  apiKeyForm.value = { user_id: '', api_key: '' }
  apiKeyError.value = ''
  showApiKeyModal.value = true
}

async function submitApiKey() {
  if (!apiKeyProvider.value) return
  apiKeyError.value = ''
  try {
    await store.connectApiKey(
      apiKeyProvider.value.provider,
      apiKeyForm.value.user_id,
      apiKeyForm.value.api_key,
    )
    showApiKeyModal.value = false
    flashType.value = 'success'
    flashMessage.value = `${apiKeyProvider.value.label} подключён`
  } catch (e) {
    const err = e as AxiosError<ApiError>
    apiKeyError.value = err.response?.data?.message ?? 'Не удалось подключить'
  }
}

async function handleDisconnect(id: number) {
  if (!confirm('Отключить интеграцию?')) return
  await store.disconnect(id)
  flashType.value = 'success'
  flashMessage.value = 'Интеграция отключена'
}

async function openResources(integration: Integration) {
  resourcesModalTitle.value = `Ресурсы: ${integration.label}`
  resourcesIntegration.value = integration
  showResourcesModal.value = true
  resourcesLoading.value = true
  resourcesError.value = ''
  resourcesList.value = []
  try {
    resourcesList.value = await store.fetchResources(integration.id)
  } catch (e) {
    const err = e as AxiosError<ApiError>
    resourcesError.value = err.response?.data?.message ?? 'Не удалось загрузить ресурсы'
  } finally {
    resourcesLoading.value = false
  }
}

function applyOAuthFlash() {
  const { integration, detail } = flashFromQuery.value
  if (!integration) return

  if (integration === 'connected') {
    flashType.value = 'success'
    flashMessage.value = 'Интеграция успешно подключена'
  } else {
    flashType.value = 'error'
    flashMessage.value = `Ошибка подключения: ${detail ?? 'unknown'}`
  }

  router.replace({ query: {} })
}

onMounted(async () => {
  applyOAuthFlash()
  await load()
})
</script>
