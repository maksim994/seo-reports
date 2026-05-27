<template>
  <div>
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <RouterLink to="/templates" class="text-sm text-brand-600 hover:underline">
          ← К списку шаблонов
        </RouterLink>
        <h1 class="mt-2 text-2xl font-semibold text-gray-900">
          {{ template?.name || 'Редактор шаблона' }}
        </h1>
      </div>
      <button
        :disabled="saving"
        class="rounded-lg bg-brand-500 px-4 py-2 text-sm font-medium text-white hover:bg-brand-600 disabled:opacity-50"
        @click="save"
      >
        {{ saving ? 'Сохранение...' : 'Сохранить' }}
      </button>
    </div>

    <div v-if="loading" class="text-sm text-gray-500">Загрузка...</div>

    <div v-else class="grid gap-6 lg:grid-cols-2">
      <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
        <h2 class="mb-4 text-lg font-medium text-gray-900">Доступные блоки</h2>
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
        <h2 class="mb-4 text-lg font-medium text-gray-900">
          Блоки отчёта
          <span class="text-sm font-normal text-gray-500">({{ selectedBlocks.length }})</span>
        </h2>

        <EmptyState
          v-if="selectedBlocks.length === 0"
          icon="📋"
          title="Блоки не выбраны"
          description="Добавьте блоки из каталога слева."
        />

        <div v-else class="space-y-2">
          <div
            v-for="(block, index) in selectedBlocks"
            :key="`${block.block_type}-${index}`"
            class="flex items-center gap-2 rounded-lg border border-gray-100 px-3 py-2"
          >
            <div class="flex-1">
              <div class="text-sm font-medium text-gray-900">{{ blockLabel(block.block_type) }}</div>
              <div class="text-xs text-gray-500">{{ block.block_type }}</div>
            </div>
            <div class="flex gap-1">
              <button
                class="rounded border border-gray-200 px-2 py-1 text-xs hover:bg-gray-50 disabled:opacity-30"
                :disabled="index === 0"
                @click="moveBlock(index, -1)"
              >
                ↑
              </button>
              <button
                class="rounded border border-gray-200 px-2 py-1 text-xs hover:bg-gray-50 disabled:opacity-30"
                :disabled="index === selectedBlocks.length - 1"
                @click="moveBlock(index, 1)"
              >
                ↓
              </button>
              <button
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

    <div
      v-if="message"
      class="fixed bottom-6 right-6 rounded-lg bg-green-600 px-4 py-2 text-sm text-white shadow-lg"
    >
      {{ message }}
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import EmptyState from '@/components/EmptyState.vue'
import { useTemplatesStore } from '@/stores/templates'
import type { ReportTemplate, TemplateBlockItem } from '@/types'

const route = useRoute()
const store = useTemplatesStore()

const templateId = computed(() => Number(route.params.id))
const loading = ref(true)
const saving = ref(false)
const message = ref('')
const search = ref('')
const template = ref<ReportTemplate | null>(null)
const selectedBlocks = ref<TemplateBlockItem[]>([])

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

function addBlock(blockType: string) {
  selectedBlocks.value.push({ block_type: blockType, settings: null })
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

async function load() {
  loading.value = true
  try {
    await store.fetchCatalog()
    template.value = await store.fetchTemplate(templateId.value)
    selectedBlocks.value = (template.value.blocks ?? []).map((b) => ({
      block_type: b.block_type,
      settings: b.settings ?? null,
    }))
  } finally {
    loading.value = false
  }
}

async function save() {
  if (!template.value) return
  saving.value = true
  try {
    template.value = await store.updateTemplate(templateId.value, {
      blocks: selectedBlocks.value.map((b) => ({
        block_type: b.block_type,
        settings: b.settings ?? null,
      })),
    })
    message.value = 'Шаблон сохранён'
    setTimeout(() => {
      message.value = ''
    }, 2500)
  } finally {
    saving.value = false
  }
}

onMounted(load)
</script>
