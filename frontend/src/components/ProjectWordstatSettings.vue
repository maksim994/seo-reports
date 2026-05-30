<template>
  <section v-if="integration" class="rounded-2xl border border-violet-200 bg-white p-6 shadow-sm">
    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
      <div class="flex items-center gap-3">
        <IntegrationLogo
          :provider="integration.provider"
          :label="integration.label"
          :logo-url="integration.logo_url"
          size="md"
        />
        <div>
          <h2 class="text-lg font-medium text-gray-900">Яндекс Вордстат</h2>
          <p class="text-sm text-gray-500">{{ integration.account_label }}</p>
        </div>
      </div>
      <span
        v-if="isBound"
        class="inline-flex rounded-full bg-green-50 px-3 py-1 text-xs font-medium text-green-700"
      >
        Привязано к проекту
      </span>
    </div>

    <div v-if="loading" class="text-sm text-gray-500">Загрузка...</div>
    <div v-else-if="resourcesError" class="text-sm text-error-500">{{ resourcesError }}</div>
    <form v-else class="space-y-6" @submit.prevent="save">
      <div class="grid gap-4 lg:grid-cols-2">
        <div>
          <label class="mb-1.5 block text-sm font-medium text-gray-700">Аккаунт</label>
          <select
            v-model="selectedResourceId"
            class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand-500"
          >
            <option value="">— выберите аккаунт —</option>
            <option v-for="resource in resources" :key="resource.id" :value="resource.id">
              {{ resource.label }}
            </option>
          </select>
        </div>
        <div>
          <label class="mb-1.5 block text-sm font-medium text-gray-700">ID региона</label>
          <input
            v-model="form.regionId"
            type="number"
            min="1"
            placeholder="Пусто = вся Россия"
            class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand-500"
          />
        </div>
      </div>

      <div class="rounded-xl border border-violet-100 bg-violet-50/50 p-5">
        <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-violet-800">
          Динамика спроса
        </h3>
        <div class="space-y-4">
          <div>
            <label class="mb-1.5 block text-sm font-medium text-gray-700">
              Ключевые фразы (по одной на строку)
            </label>
            <textarea
              v-model="form.dynamicsPhrases"
              rows="4"
              class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm outline-none focus:border-brand-500"
              placeholder="осевой вентилятор
центробежный вентилятор"
            />
          </div>
          <div class="grid gap-4 sm:grid-cols-2">
            <div>
              <label class="mb-1.5 block text-sm font-medium text-gray-700">Агрегация</label>
              <select
                v-model="form.dynamicsPeriod"
                class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm outline-none focus:border-brand-500"
              >
                <option value="monthly">По месяцам</option>
                <option value="weekly">По неделям</option>
                <option value="daily">По дням</option>
              </select>
            </div>
            <div>
              <label class="mb-1.5 block text-sm font-medium text-gray-700">Глубина, мес.</label>
              <input
                v-model="form.dynamicsLookback"
                type="number"
                min="1"
                max="36"
                class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm outline-none focus:border-brand-500"
              />
            </div>
          </div>
        </div>
      </div>

      <div class="grid gap-6 lg:grid-cols-2">
        <div class="rounded-xl border border-gray-200 p-5">
          <h3 class="mb-4 text-sm font-semibold text-gray-900">Популярные запросы</h3>
          <div class="space-y-4">
            <div>
              <label class="mb-1.5 block text-sm font-medium text-gray-700">Фраза</label>
              <input
                v-model="form.topPhrase"
                type="text"
                class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand-500"
              />
            </div>
            <div>
              <label class="mb-1.5 block text-sm font-medium text-gray-700">Количество</label>
              <input
                v-model="form.topLimit"
                type="number"
                min="1"
                max="25"
                class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand-500"
              />
            </div>
          </div>
        </div>

        <div class="rounded-xl border border-gray-200 p-5">
          <h3 class="mb-4 text-sm font-semibold text-gray-900">Регионы</h3>
          <div class="space-y-4">
            <div>
              <label class="mb-1.5 block text-sm font-medium text-gray-700">Фраза</label>
              <input
                v-model="form.regionsPhrase"
                type="text"
                class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand-500"
              />
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
              <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700">Тип</label>
                <select
                  v-model="form.regionsType"
                  class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand-500"
                >
                  <option value="all">Все</option>
                  <option value="cities">Города</option>
                  <option value="regions">Регионы</option>
                </select>
              </div>
              <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-700">Количество</label>
                <input
                  v-model="form.regionsLimit"
                  type="number"
                  min="1"
                  max="25"
                  class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand-500"
                />
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="flex flex-wrap items-center gap-3">
        <button
          type="submit"
          :disabled="!selectedResourceId || saving"
          class="rounded-lg bg-brand-500 px-4 py-2 text-sm font-medium text-white hover:bg-brand-600 disabled:opacity-50"
        >
          {{ saving ? 'Сохранение...' : 'Сохранить настройки' }}
        </button>
        <button
          v-if="isBound"
          type="button"
          class="rounded-lg border border-gray-200 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"
          @click="unbind"
        >
          Отвязать
        </button>
        <p v-if="savedMessage" class="text-sm text-green-700">{{ savedMessage }}</p>
      </div>
    </form>
  </section>
</template>

<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import IntegrationLogo from '@/components/IntegrationLogo.vue'
import {
  emptyWordstatFormState,
  wordstatConfigFromForm,
  wordstatFormFromConfig,
  type WordstatFormState,
} from '@/lib/wordstatProjectConfig'
import { useIntegrationsStore } from '@/stores/integrations'
import { useProjectIntegrationsStore } from '@/stores/projectIntegrations'
import type { Integration, IntegrationResource } from '@/types'
import type { AxiosError } from 'axios'
import type { ApiError } from '@/types'

const props = defineProps<{ projectId: number }>()

const emit = defineEmits<{ saved: [] }>()

const integrationsStore = useIntegrationsStore()
const bindingsStore = useProjectIntegrationsStore()

const loading = ref(false)
const saving = ref(false)
const savedMessage = ref('')
const resourcesError = ref('')
const integration = ref<Integration | null>(null)
const resources = ref<IntegrationResource[]>([])
const selectedResourceId = ref('')
const form = reactive<WordstatFormState>(emptyWordstatFormState())

const isBound = computed(() =>
  integration.value
    ? bindingsStore.bindings.some((b) => b.integration_id === integration.value!.id)
    : false,
)

function currentBinding() {
  if (!integration.value) return null
  return bindingsStore.bindings.find((b) => b.integration_id === integration.value!.id) ?? null
}

function applyBindingState() {
  const binding = currentBinding()
  Object.assign(form, wordstatFormFromConfig(binding?.config ?? null))
  selectedResourceId.value = binding?.external_resource_id ?? resources.value[0]?.id ?? ''
}

async function load() {
  loading.value = true
  resourcesError.value = ''
  try {
    await integrationsStore.fetchIntegrations()
    integration.value =
      integrationsStore.integrations.find(
        (item) => item.provider === 'yandex_wordstat' && item.status === 'active',
      ) ?? null

    if (!integration.value) return

    await bindingsStore.fetchBindings(props.projectId)

    try {
      resources.value = await integrationsStore.fetchResources(integration.value.id)
    } catch (e) {
      const err = e as AxiosError<ApiError>
      resourcesError.value =
        err.response?.data?.message ?? 'Не удалось загрузить ресурсы Вордстата'
      resources.value = []
    }

    applyBindingState()
  } finally {
    loading.value = false
  }
}

async function save() {
  if (!integration.value || !selectedResourceId.value) return

  const resource = resources.value.find((item) => item.id === selectedResourceId.value)
  saving.value = true
  savedMessage.value = ''
  try {
    await bindingsStore.bind(props.projectId, {
      integration_id: integration.value.id,
      external_resource_id: selectedResourceId.value,
      external_resource_label: resource?.label ?? null,
      config: wordstatConfigFromForm(form),
    })
    savedMessage.value = 'Настройки сохранены'
    emit('saved')
  } finally {
    saving.value = false
  }
}

async function unbind() {
  const binding = currentBinding()
  if (!binding) return

  await bindingsStore.unbind(props.projectId, binding.id)
  selectedResourceId.value = resources.value[0]?.id ?? ''
  Object.assign(form, emptyWordstatFormState())
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
