<template>
  <div>
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <RouterLink to="/templates" class="text-sm text-brand-600 hover:underline">
          ← К списку шаблонов
        </RouterLink>
        <h1 class="mt-2 text-2xl font-semibold text-gray-900">
          {{ templateName || template?.name || 'Редактор шаблона' }}
        </h1>
      </div>
      <button
        :disabled="saving || !templateName.trim()"
        class="rounded-lg bg-brand-500 px-4 py-2 text-sm font-medium text-white hover:bg-brand-600 disabled:opacity-50"
        @click="save"
      >
        {{ saving ? 'Сохранение...' : 'Сохранить' }}
      </button>
    </div>

    <div v-if="loading" class="text-sm text-gray-500">Загрузка...</div>

    <div v-else class="grid gap-6 lg:grid-cols-2">
      <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm lg:col-span-2">
        <h2 class="mb-4 text-lg font-medium text-gray-900">Настройки шаблона</h2>
        <div class="grid gap-4 sm:grid-cols-2">
          <div>
            <label class="mb-1.5 block text-sm font-medium text-gray-700">Название</label>
            <input
              v-model="templateName"
              type="text"
              required
              maxlength="255"
              class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand-500"
            />
          </div>
          <div>
            <label class="mb-1.5 block text-sm font-medium text-gray-700">Описание</label>
            <input
              v-model="templateDescription"
              type="text"
              maxlength="2000"
              placeholder="Необязательно"
              class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand-500"
            />
          </div>
          <div class="sm:col-span-2">
            <label class="mb-1.5 block text-sm font-medium text-gray-700">Логотип</label>
            <div class="flex flex-wrap items-center gap-4">
              <div
                v-if="logoPreviewUrl"
                class="flex h-16 min-w-[120px] items-center justify-center rounded-lg border border-gray-200 bg-gray-50 px-3"
              >
                <img :src="logoPreviewUrl" alt="Логотип шаблона" class="max-h-12 max-w-[180px] object-contain" />
              </div>
              <div class="flex flex-wrap gap-2">
                <label
                  class="cursor-pointer rounded-lg border border-gray-200 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50"
                  :class="{ 'pointer-events-none opacity-50': logoUploading }"
                >
                  {{ logoUploading ? 'Загрузка...' : template?.logo_url ? 'Заменить' : 'Загрузить' }}
                  <input
                    type="file"
                    accept="image/png,image/jpeg,image/webp,image/gif"
                    class="hidden"
                    :disabled="logoUploading"
                    @change="onLogoSelected"
                  />
                </label>
                <button
                  v-if="template?.logo_url"
                  type="button"
                  class="rounded-lg border border-gray-200 px-3 py-2 text-sm text-error-500 hover:bg-red-50 disabled:opacity-50"
                  :disabled="logoUploading"
                  @click="removeLogo"
                >
                  Удалить
                </button>
              </div>
              <p class="text-xs text-gray-500">PNG, JPG или WebP до 2 МБ. Отображается на титульной странице отчёта.</p>
            </div>
          </div>
        </div>
      </section>

      <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
          <h2 class="text-lg font-medium text-gray-900">Доступные блоки</h2>
          <button
            class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50"
            @click="addAllBlocks"
          >
            Добавить все блоки
          </button>
        </div>
        <div class="mb-4">
          <input
            v-model="search"
            type="search"
            placeholder="Поиск блока..."
            class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand-500"
          />
        </div>
        <div class="max-h-[32rem] space-y-4 overflow-y-auto">
          <div v-for="(blocks, category) in groupedAvailable" :key="category">
            <div class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-400">
              {{ store.categories[category] || category }}
            </div>
            <div class="space-y-2">
              <div
                v-for="block in blocks"
                :key="block.block_type"
                class="flex items-start justify-between gap-3 rounded-lg border border-gray-100 px-3 py-2"
              >
                <div>
                  <div class="text-sm font-medium text-gray-900">{{ block.label }}</div>
                  <div class="text-xs text-gray-500">{{ block.description }}</div>
                </div>
                <button
                  class="shrink-0 rounded-lg bg-gray-100 px-2 py-1 text-xs font-medium text-gray-700 hover:bg-gray-200"
                  @click="addBlock(block.block_type)"
                >
                  +
                </button>
              </div>
            </div>
          </div>
        </div>
      </section>

      <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
          <h2 class="text-lg font-medium text-gray-900">
            Блоки отчёта
            <span class="text-sm font-normal text-gray-500">({{ selectedBlocks.length }})</span>
          </h2>
          <button
            v-if="selectedBlocks.length"
            class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-medium text-error-500 hover:bg-red-50"
            @click="clearBlocks"
          >
            Очистить
          </button>
        </div>

        <EmptyState
          v-if="selectedBlocks.length === 0"
          icon="📋"
          title="Блоки не выбраны"
          description="Добавьте блоки из каталога слева."
        />

        <div v-else ref="blocksListRef" class="space-y-2">
          <div
            v-for="(block, index) in selectedBlocks"
            :key="`${block.block_type}-${index}`"
            class="flex items-center gap-2 rounded-lg border border-gray-100 px-3 py-2"
            :data-index="index"
          >
            <button
              type="button"
              class="drag-handle cursor-grab px-1 text-gray-400 hover:text-gray-600 active:cursor-grabbing"
              title="Перетащите для сортировки"
            >
              ⠿
            </button>
            <div class="flex-1 min-w-0">
              <div class="text-sm font-medium text-gray-900">{{ blockLabel(block.block_type) }}</div>
              <div class="truncate text-xs text-gray-500">{{ block.block_type }}</div>
            </div>
            <div class="flex shrink-0 gap-1">
              <button
                v-if="hasBlockSettings(block.block_type)"
                type="button"
                class="rounded border border-gray-200 px-2 py-1 text-xs hover:bg-gray-50"
                title="Настройки блока"
                @click="openBlockSettings(index)"
              >
                ⚙
              </button>
              <button
                type="button"
                class="rounded border border-gray-200 px-2 py-1 text-xs hover:bg-gray-50 disabled:opacity-30"
                :disabled="index === 0"
                @click="moveBlock(index, -1)"
              >
                ↑
              </button>
              <button
                type="button"
                class="rounded border border-gray-200 px-2 py-1 text-xs hover:bg-gray-50 disabled:opacity-30"
                :disabled="index === selectedBlocks.length - 1"
                @click="moveBlock(index, 1)"
              >
                ↓
              </button>
              <button
                type="button"
                class="rounded border border-gray-200 px-2 py-1 text-xs text-error-500 hover:bg-red-50"
                @click="removeBlock(index)"
              >
                ✕
              </button>
            </div>
          </div>
        </div>
      </section>
    </div>

    <BlockSettingsModal
      v-model="settingsModalOpen"
      :title="settingsModalTitle"
      :schema="settingsModalSchema"
      :settings="settingsModalSettings"
      @save="saveBlockSettings"
    />

    <div
      v-if="message"
      class="fixed bottom-6 right-6 rounded-lg bg-green-600 px-4 py-2 text-sm text-white shadow-lg"
    >
      {{ message }}
    </div>
  </div>
</template>

<script setup lang="ts">
import Sortable, { type SortableEvent } from 'sortablejs'
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import BlockSettingsModal from '@/components/BlockSettingsModal.vue'
import EmptyState from '@/components/EmptyState.vue'
import { ensureCsrfCookie } from '@/lib/api'
import { useTemplatesStore } from '@/stores/templates'
import type { BlockSettingsField, ReportTemplate, TemplateBlockItem } from '@/types'

const route = useRoute()
const store = useTemplatesStore()

const templateId = computed(() => Number(route.params.id))
const loading = ref(true)
const saving = ref(false)
const logoUploading = ref(false)
const logoCacheBust = ref(Date.now())
const message = ref('')
const search = ref('')
const template = ref<ReportTemplate | null>(null)
const templateName = ref('')
const templateDescription = ref('')
const selectedBlocks = ref<TemplateBlockItem[]>([])
const blocksListRef = ref<HTMLElement | null>(null)
let sortable: Sortable | null = null

const settingsModalOpen = ref(false)
const settingsBlockIndex = ref(-1)

const logoPreviewUrl = computed(() => {
  if (!template.value?.logo_url) return null
  return `${template.value.logo_url}?v=${logoCacheBust.value}`
})

const settingsModalTitle = computed(() => {
  const block = selectedBlocks.value[settingsBlockIndex.value]
  if (!block) return 'Настройки блока'
  return `Настройки: ${blockLabel(block.block_type)}`
})

const settingsModalSchema = computed((): BlockSettingsField[] => {
  const block = selectedBlocks.value[settingsBlockIndex.value]
  if (!block) return []
  return blockSchema(block.block_type)
})

const settingsModalSettings = computed(() => {
  const block = selectedBlocks.value[settingsBlockIndex.value]
  return block?.settings ?? null
})

const groupedAvailable = computed(() => {
  const term = search.value.trim().toLowerCase()
  const groups: Record<string, typeof store.catalog> = {}

  for (const block of store.catalog) {
    if (term && !block.label.toLowerCase().includes(term) && !block.block_type.includes(term)) {
      continue
    }
    if (!groups[block.category]) {
      groups[block.category] = []
    }
    groups[block.category].push(block)
  }

  return groups
})

function blockLabel(blockType: string) {
  return store.catalog.find((b) => b.block_type === blockType)?.label ?? blockType
}

function blockSchema(blockType: string): BlockSettingsField[] {
  return store.catalog.find((b) => b.block_type === blockType)?.settings_schema ?? []
}

function hasBlockSettings(blockType: string) {
  return blockSchema(blockType).length > 0
}

function addBlock(blockType: string) {
  selectedBlocks.value.push({ block_type: blockType, settings: null })
}

function addAllBlocks() {
  const existing = new Set(selectedBlocks.value.map((b) => b.block_type))
  for (const block of store.catalog) {
    if (!existing.has(block.block_type)) {
      selectedBlocks.value.push({ block_type: block.block_type, settings: null })
      existing.add(block.block_type)
    }
  }
}

function clearBlocks() {
  if (!confirm('Убрать все блоки из шаблона?')) return
  selectedBlocks.value = []
}

function removeBlock(index: number) {
  selectedBlocks.value.splice(index, 1)
}

function moveBlock(index: number, direction: number) {
  const next = index + direction
  if (next < 0 || next >= selectedBlocks.value.length) return
  const copy = [...selectedBlocks.value]
  const [item] = copy.splice(index, 1)
  copy.splice(next, 0, item)
  selectedBlocks.value = copy
}

function openBlockSettings(index: number) {
  settingsBlockIndex.value = index
  settingsModalOpen.value = true
}

function saveBlockSettings(settings: Record<string, unknown>) {
  const index = settingsBlockIndex.value
  if (index < 0 || !selectedBlocks.value[index]) return
  selectedBlocks.value[index] = {
    ...selectedBlocks.value[index],
    settings,
  }
}

function initSortable() {
  sortable?.destroy()
  sortable = null

  if (!blocksListRef.value || selectedBlocks.value.length === 0) return

  sortable = Sortable.create(blocksListRef.value, {
    animation: 150,
    handle: '.drag-handle',
    draggable: '[data-index]',
    onEnd(event: SortableEvent) {
      const { oldIndex, newIndex } = event
      if (oldIndex == null || newIndex == null || oldIndex === newIndex) return
      const copy = [...selectedBlocks.value]
      const [item] = copy.splice(oldIndex, 1)
      copy.splice(newIndex, 0, item)
      selectedBlocks.value = copy
    },
  })
}

async function onLogoSelected(event: Event) {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]
  input.value = ''
  if (!file || !template.value) return

  logoUploading.value = true
  try {
    await ensureCsrfCookie()
    template.value = await store.uploadLogo(templateId.value, file)
    logoCacheBust.value = Date.now()
    message.value = 'Логотип загружен'
    setTimeout(() => {
      message.value = ''
    }, 2500)
  } finally {
    logoUploading.value = false
  }
}

async function removeLogo() {
  if (!template.value?.logo_url || !confirm('Удалить логотип шаблона?')) return

  logoUploading.value = true
  try {
    template.value = await store.deleteLogo(templateId.value)
    logoCacheBust.value = Date.now()
    message.value = 'Логотип удалён'
    setTimeout(() => {
      message.value = ''
    }, 2500)
  } finally {
    logoUploading.value = false
  }
}

async function load() {
  loading.value = true
  try {
    await store.fetchCatalog()
    template.value = await store.fetchTemplate(templateId.value)
    templateName.value = template.value.name
    templateDescription.value = template.value.description ?? ''
    selectedBlocks.value = (template.value.blocks ?? []).map((b) => ({
      block_type: b.block_type,
      settings: b.settings ?? null,
    }))
    logoCacheBust.value = Date.now()
  } finally {
    loading.value = false
    await nextTick()
    initSortable()
  }
}

async function save() {
  if (!template.value) return
  const name = templateName.value.trim()
  if (!name) return
  saving.value = true
  try {
    template.value = await store.updateTemplate(templateId.value, {
      name,
      description: templateDescription.value.trim() || null,
      blocks: selectedBlocks.value.map((b) => ({
        block_type: b.block_type,
        settings: b.settings ?? null,
      })),
    })
    templateName.value = template.value.name
    templateDescription.value = template.value.description ?? ''
    message.value = 'Шаблон сохранён'
    setTimeout(() => {
      message.value = ''
    }, 2500)
  } finally {
    saving.value = false
  }
}

watch(
  () => selectedBlocks.value.length,
  async () => {
    await nextTick()
    initSortable()
  },
)

onMounted(load)
onBeforeUnmount(() => sortable?.destroy())
</script>
