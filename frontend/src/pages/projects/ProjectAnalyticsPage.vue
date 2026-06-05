<template>
  <div>
    <div class="mb-6">
      <RouterLink :to="`/projects/${projectId}`" class="text-sm text-brand-600 hover:underline">
        ← Проект
      </RouterLink>
      <div class="mt-2 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
          <h1 class="text-2xl font-semibold text-gray-900">Аналитика</h1>
          <p class="mt-1 text-sm text-gray-500">
            {{ projectName }}
            <span v-if="dashStore.data?.compare_period">
              · сравнение с {{ formatPeriod(dashStore.data.compare_period) }}
            </span>
          </p>
          <p
            v-if="dashStore.config?.is_suggested && !editMode"
            class="mt-2 text-xs text-amber-700"
          >
            Показаны рекомендуемые виджеты. Сохраните раскладку, чтобы закрепить.
          </p>
        </div>
        <div class="flex flex-wrap items-end gap-2">
          <div>
            <label class="mb-1 block text-xs font-medium text-gray-500">Период с</label>
            <input
              v-model="periodStart"
              type="date"
              :disabled="editMode"
              class="rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand-500 disabled:bg-gray-50"
            />
          </div>
          <div>
            <label class="mb-1 block text-xs font-medium text-gray-500">Период по</label>
            <input
              v-model="periodEnd"
              type="date"
              :disabled="editMode"
              class="rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand-500 disabled:bg-gray-50"
            />
          </div>
          <div v-if="!editMode" class="flex flex-wrap gap-1.5">
            <button
              v-for="preset in periodPresets"
              :key="preset.value"
              type="button"
              class="rounded-lg border border-gray-200 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50"
              @click="applyPeriodPreset(preset.value)"
            >
              {{ preset.label }}
            </button>
          </div>
          <button
            v-if="!editMode"
            type="button"
            class="rounded-lg bg-brand-500 px-4 py-2 text-sm font-medium text-white hover:bg-brand-600 disabled:opacity-50"
            :disabled="dashStore.loadingData"
            @click="refreshData"
          >
            {{ dashStore.loadingData ? 'Загрузка…' : 'Обновить' }}
          </button>
          <button
            v-if="editMode"
            type="button"
            class="rounded-lg border border-gray-200 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"
            @click="cancelEdit"
          >
            Отмена
          </button>
          <button
            type="button"
            class="rounded-lg px-4 py-2 text-sm font-medium text-white disabled:opacity-50"
            :class="editMode ? 'bg-green-600 hover:bg-green-700' : 'bg-gray-800 hover:bg-gray-900'"
            :disabled="editMode && dashStore.saving"
            @click="editMode ? saveLayout() : (editMode = true)"
          >
            {{ editMode ? (dashStore.saving ? 'Сохранение…' : 'Сохранить') : 'Настроить' }}
          </button>
        </div>
      </div>
    </div>

    <div v-if="dashStore.error" class="mb-4 rounded-lg bg-red-50 px-4 py-3 text-sm text-error-500">
      {{ dashStore.error }}
    </div>

    <div class="grid gap-6" :class="editMode ? 'lg:grid-cols-[280px_1fr]' : ''">
      <aside
        v-if="editMode"
        class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm lg:max-h-[calc(100vh-12rem)] lg:overflow-y-auto"
      >
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
          <h2 class="text-sm font-semibold text-gray-900">Добавить виджет</h2>
          <button
            type="button"
            class="rounded-lg border border-gray-200 px-2.5 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50"
            @click="addAllWidgets"
          >
            Добавить все
          </button>
        </div>
        <input
          v-model="catalogSearch"
          type="search"
          placeholder="Поиск…"
          class="mb-3 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand-500"
        />
        <div class="space-y-4">
          <div v-for="(blocks, category) in groupedCatalog" :key="category">
            <div class="mb-1 text-xs font-semibold uppercase tracking-wide text-gray-400">
              {{ categories[category] || category }}
            </div>
            <div class="space-y-1">
              <button
                v-for="block in blocks"
                :key="block.block_type"
                type="button"
                class="flex w-full items-start justify-between gap-2 rounded-lg border px-2 py-2 text-left transition"
                :class="
                  isBlockAdded(block.block_type)
                    ? 'border-green-200 bg-green-50/70 text-green-900 hover:bg-green-50'
                    : 'border-gray-100 text-gray-900 hover:bg-gray-50'
                "
                @click="addWidget(block.block_type)"
              >
                <span class="text-sm">{{ block.label }}</span>
                <span
                  v-if="isBlockAdded(block.block_type)"
                  class="shrink-0 rounded-full bg-green-100 px-2 py-0.5 text-[11px] font-medium text-green-700"
                >
                  Добавлен
                </span>
                <span v-else class="shrink-0 text-gray-400">+</span>
              </button>
            </div>
          </div>
        </div>
      </aside>

      <section class="relative min-h-[240px]">
        <div
          v-if="dashStore.loadingData"
          class="absolute inset-0 z-20 flex flex-col items-center justify-center gap-3 rounded-2xl bg-white/85 backdrop-blur-[2px]"
          role="status"
          aria-live="polite"
          aria-busy="true"
        >
          <span
            class="h-10 w-10 animate-spin rounded-full border-[3px] border-brand-200 border-t-brand-500"
            aria-hidden="true"
          />
          <p class="text-sm font-medium text-gray-700">Загрузка метрик…</p>
          <p class="text-xs text-gray-500">Подключаемся к источникам данных проекта</p>
        </div>

        <div
          class="transition-opacity duration-150"
          :class="dashStore.loadingData ? 'pointer-events-none opacity-40' : ''"
        >
        <div v-if="dashStore.loadingConfig" class="text-sm text-gray-500">Загрузка дашборда…</div>

        <EmptyState
          v-else-if="!widgets.length"
          icon="📊"
          :title="editMode ? 'Добавьте виджеты' : 'Дашборд пуст'"
          :description="
            editMode
              ? 'Выберите блоки из каталога слева.'
              : 'Нажмите «Настроить», чтобы собрать дашборд.'
          "
        />

        <AnalyticsDashboardGrid
          v-else
          :widgets="widgets"
          :edit-mode="editMode"
          @update:widgets="onWidgetsLayoutChange"
        >
          <template #default="{ widget, layout, onWidthResize, onHalfWidth, onFullWidth }">
            <AnalyticsWidgetCard
              :title="widgetTitle(widget)"
              :chart-title="widgetData(widget.id)?.chart_title"
              :block-type="widget.block_type"
              :html="widgetData(widget.id)?.html ?? null"
              :loading="dashStore.loadingData"
              :success="widgetData(widget.id)?.success ?? true"
              :error="widgetData(widget.id)?.error ?? null"
              :edit-mode="editMode"
              :constrain-height="editMode"
              :has-settings="hasBlockSettings(widget.block_type)"
              :layout-x="(layout ?? widget.layout).x"
              :layout-w="(layout ?? widget.layout).w"
              :on-width-resize="onWidthResize"
              :on-half-width="onHalfWidth"
              :on-full-width="onFullWidth"
              @settings="openWidgetSettings(widget.id)"
              @remove="removeWidget(widget.id)"
            />
          </template>
        </AnalyticsDashboardGrid>
        </div>
      </section>
    </div>

    <BlockSettingsModal
      v-model="settingsModalOpen"
      :title="settingsModalTitle"
      :schema="settingsModalSchema"
      :settings="settingsModalSettings"
      :dynamic-options="metrikaDynamicOptions"
      :options-hint="metrikaOptionsHint"
      @save="saveWidgetSettings"
    />

    <div
      v-if="toast"
      class="fixed bottom-6 right-6 rounded-lg bg-green-600 px-4 py-2 text-sm text-white shadow-lg"
    >
      {{ toast }}
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import AnalyticsDashboardGrid from '@/components/analytics/AnalyticsDashboardGrid.vue'
import AnalyticsWidgetCard from '@/components/analytics/AnalyticsWidgetCard.vue'
import BlockSettingsModal from '@/components/BlockSettingsModal.vue'
import EmptyState from '@/components/EmptyState.vue'
import api from '@/lib/api'
import { useProjectAnalyticsDashboardStore } from '@/stores/projectAnalyticsDashboard'
import type { BlockSettingsField, DashboardWidget, ReportBlockCatalogItem } from '@/types'

const route = useRoute()
const dashStore = useProjectAnalyticsDashboardStore()

const projectId = computed(() => Number(route.params.id))
const projectName = ref('')
const periodStart = ref('')
const periodEnd = ref('')
const editMode = ref(false)
const widgets = ref<DashboardWidget[]>([])
const widgetsSnapshot = ref<DashboardWidget[]>([])
const catalogSearch = ref('')
const toast = ref('')

const settingsModalOpen = ref(false)
const settingsWidgetId = ref<string | null>(null)
const metrikaDynamicOptions = ref<Record<string, Array<{ value: string; label: string }>>>({})
const metrikaOptionsHint = ref<string | null>(null)

type PeriodPreset = 'previous_month' | '6_months' | '12_months' | '25_months' | 'year_to_date'

const periodPresets: Array<{ value: PeriodPreset; label: string }> = [
  { value: 'previous_month', label: 'Прошлый месяц' },
  { value: '6_months', label: '6 мес.' },
  { value: '12_months', label: '12 мес.' },
  { value: '25_months', label: '25 мес.' },
  { value: 'year_to_date', label: 'С 1 января' },
]

const catalog = computed(() => dashStore.config?.catalog.blocks ?? [])
const categories = computed(() => dashStore.config?.catalog.categories ?? {})
const addedBlockTypes = computed(() => new Set(widgets.value.map((w) => w.block_type)))

const filteredCatalog = computed(() => {
  const term = catalogSearch.value.trim().toLowerCase()
  return catalog.value.filter((block) => {
    if (!term) return true
    return (
      block.label.toLowerCase().includes(term) ||
      block.block_type.toLowerCase().includes(term)
    )
  })
})

const groupedCatalog = computed(() => {
  const groups: Record<string, ReportBlockCatalogItem[]> = {}
  for (const block of filteredCatalog.value) {
    if (!groups[block.category]) groups[block.category] = []
    groups[block.category].push(block)
  }
  return groups
})

const settingsModalTitle = computed(() => {
  const widget = widgets.value.find((w) => w.id === settingsWidgetId.value)
  if (!widget) return 'Настройки виджета'
  return `Настройки: ${blockLabel(widget.block_type)}`
})

const settingsModalSchema = computed((): BlockSettingsField[] => {
  const widget = widgets.value.find((w) => w.id === settingsWidgetId.value)
  if (!widget) return []
  return blockSchema(widget.block_type)
})

const settingsModalSettings = computed(() => {
  const widget = widgets.value.find((w) => w.id === settingsWidgetId.value)
  return widget?.settings ?? null
})

function widgetData(id: string) {
  return dashStore.data?.widgets.find((w) => w.id === id)
}

function blockLabel(blockType: string) {
  return catalog.value.find((b) => b.block_type === blockType)?.label ?? blockType
}

function blockSchema(blockType: string): BlockSettingsField[] {
  return catalog.value.find((b) => b.block_type === blockType)?.settings_schema ?? []
}

function hasBlockSettings(blockType: string) {
  return blockSchema(blockType).length > 0
}

function isBlockAdded(blockType: string) {
  return addedBlockTypes.value.has(blockType)
}

function widgetTitle(widget: DashboardWidget) {
  return widgetData(widget.id)?.title ?? blockLabel(widget.block_type)
}

function formatPeriod(period: { start: string; end: string }) {
  const fmt = (v: string) => new Date(v).toLocaleDateString('ru-RU')
  return `${fmt(period.start)} — ${fmt(period.end)}`
}

function toInputDate(date: Date) {
  const y = date.getFullYear()
  const m = String(date.getMonth() + 1).padStart(2, '0')
  const d = String(date.getDate()).padStart(2, '0')
  return `${y}-${m}-${d}`
}

function applyPreviousMonth() {
  const now = new Date()
  const start = new Date(now.getFullYear(), now.getMonth() - 1, 1)
  const end = new Date(now.getFullYear(), now.getMonth(), 0)
  periodStart.value = toInputDate(start)
  periodEnd.value = toInputDate(end)
}

function applyRollingMonths(months: number) {
  const now = new Date()
  const end = new Date(now.getFullYear(), now.getMonth(), 0)
  const start = new Date(end.getFullYear(), end.getMonth() - months + 1, 1)
  periodStart.value = toInputDate(start)
  periodEnd.value = toInputDate(end)
}

function applyYearToDate() {
  const now = new Date()
  const end = new Date(now)
  end.setDate(end.getDate() - 1)
  const start = new Date(end.getFullYear(), 0, 1)
  periodStart.value = toInputDate(start)
  periodEnd.value = toInputDate(end)
}

function applyPeriodPreset(preset: PeriodPreset) {
  if (preset === 'previous_month') {
    applyPreviousMonth()
    return
  }

  if (preset === 'year_to_date') {
    applyYearToDate()
    return
  }

  applyRollingMonths(Number.parseInt(preset, 10))
}

function nextLayoutPosition(): DashboardWidget['layout'] {
  const count = widgets.value.length
  const y = Math.floor(count / 2) * 7
  return {
    x: count % 2 === 0 ? 0 : 6,
    y,
    w: 6,
    h: 7,
  }
}

function addWidget(blockType: string) {
  widgets.value.push({
    id: crypto.randomUUID(),
    block_type: blockType,
    settings: {},
    layout: nextLayoutPosition(),
  })
}

function addAllWidgets() {
  const existing = new Set(widgets.value.map((w) => w.block_type))
  for (const block of filteredCatalog.value) {
    if (!existing.has(block.block_type)) {
      addWidget(block.block_type)
      existing.add(block.block_type)
    }
  }
}

function removeWidget(id: string) {
  widgets.value = widgets.value.filter((w) => w.id !== id)
}

function onWidgetsLayoutChange(updated: DashboardWidget[]) {
  widgets.value = updated
}

function openWidgetSettings(id: string) {
  settingsWidgetId.value = id
  settingsModalOpen.value = true
}

function saveWidgetSettings(settings: Record<string, unknown>) {
  const id = settingsWidgetId.value
  if (!id) return
  widgets.value = widgets.value.map((w) =>
    w.id === id ? { ...w, settings } : w,
  )
}

function cancelEdit() {
  widgets.value = JSON.parse(JSON.stringify(widgetsSnapshot.value)) as DashboardWidget[]
  editMode.value = false
}

async function saveLayout() {
  try {
    await dashStore.saveConfig(projectId.value, widgets.value)
    widgetsSnapshot.value = JSON.parse(JSON.stringify(widgets.value)) as DashboardWidget[]
    editMode.value = false
    toast.value = 'Дашборд сохранён'
    setTimeout(() => { toast.value = '' }, 2500)
    await refreshData()
  } catch {
    // error in store
  }
}

async function refreshData() {
  await dashStore.fetchData(projectId.value, {
    periodStart: periodStart.value || undefined,
    periodEnd: periodEnd.value || undefined,
    widgets: widgets.value,
  })
  if (dashStore.data && !periodStart.value) {
    periodStart.value = dashStore.data.period.start
    periodEnd.value = dashStore.data.period.end
  }
}

async function loadMetrikaOptions() {
  try {
    const { data } = await api.get<{
      data: {
        goals: Array<{ value: string; label: string }>
        traffic_sources: Array<{ value: string; label: string }>
      }
    }>(`/projects/${projectId.value}/metrika/options`)
    metrikaDynamicOptions.value = {
      metrika_goals: data.data.goals,
      metrika_traffic_sources: data.data.traffic_sources,
    }
    metrikaOptionsHint.value = null
  } catch {
    metrikaOptionsHint.value = 'Подключите Яндекс.Метрику к проекту для списка целей.'
  }
}

async function loadProject() {
  try {
    const { data } = await api.get<{ data: { name: string } }>(`/projects/${projectId.value}`)
    projectName.value = data.data.name
  } catch {
    projectName.value = 'Проект'
  }
}

async function init() {
  await Promise.all([loadProject(), dashStore.fetchConfig(projectId.value), loadMetrikaOptions()])
  if (dashStore.config) {
    widgets.value = JSON.parse(JSON.stringify(dashStore.config.widgets)) as DashboardWidget[]
    widgetsSnapshot.value = JSON.parse(JSON.stringify(widgets.value)) as DashboardWidget[]
  }
  if (!periodStart.value) {
    applyPreviousMonth()
  }
  await refreshData()
}

watch(editMode, (editing) => {
  if (editing) {
    widgetsSnapshot.value = JSON.parse(JSON.stringify(widgets.value)) as DashboardWidget[]
  }
})

onMounted(init)
</script>
