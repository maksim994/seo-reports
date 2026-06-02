<template>
  <div
    class="relative flex flex-col rounded-2xl border border-gray-200 bg-white shadow-sm"
    :class="constrainHeight ? 'h-full min-h-0' : ''"
  >
    <div
      v-if="editMode"
      class="widget-width-handle widget-no-drag"
      title="Тяните вправо, чтобы изменить ширину"
      @mousedown.prevent="startWidthDrag"
    />
    <div class="flex items-start justify-between gap-2 border-b border-gray-100 px-4 py-3">
      <div
        class="widget-drag-handle min-w-0 flex-1 cursor-grab active:cursor-grabbing"
        :class="editMode ? 'rounded-lg pr-2 hover:bg-gray-50/80' : ''"
        :title="editMode ? 'Перетащите за заголовок' : undefined"
      >
        <h3 class="truncate text-sm font-semibold text-gray-900">{{ title }}</h3>
        <p v-if="chartTitle" class="mt-0.5 truncate text-sm text-gray-600">
          {{ chartTitle }}
        </p>
        <p
          v-else-if="editMode && blockType"
          class="mt-0.5 truncate text-xs text-gray-400"
          title="Технический тип блока (только в режиме настройки)"
        >
          {{ blockType }}
        </p>
      </div>
      <div v-if="editMode" class="widget-no-drag flex shrink-0 flex-col items-end gap-1.5">
        <div
          class="flex flex-wrap justify-end gap-0.5"
          role="group"
          aria-label="Ширина виджета"
        >
          <button
            v-for="preset in widthPresets"
            :key="preset.w"
            type="button"
            class="rounded-md border px-1.5 py-0.5 text-[10px] font-medium leading-tight transition-colors"
            :class="
              isWidthPresetActive(preset.w)
                ? 'border-brand-500 bg-brand-50 text-brand-700'
                : 'border-gray-200 text-gray-600 hover:bg-gray-50'
            "
            :title="preset.hint"
            @click="applyWidthPreset(preset)"
          >
            {{ preset.label }}
          </button>
        </div>
        <p class="text-[10px] text-gray-400">
          {{ layoutColsLabel }}
        </p>
        <div class="flex gap-1">
          <button
            v-if="hasSettings"
            type="button"
            class="rounded-lg border border-gray-200 px-2 py-1 text-xs hover:bg-gray-50"
            title="Настройки"
            @click="$emit('settings')"
          >
            ⚙
          </button>
          <button
            type="button"
            class="rounded-lg border border-gray-200 px-2 py-1 text-xs text-error-500 hover:bg-red-50"
            title="Удалить"
            @click="$emit('remove')"
          >
            ✕
          </button>
        </div>
      </div>
      <span
        v-else-if="!success"
        class="shrink-0 rounded-full bg-amber-50 px-2 py-0.5 text-[11px] text-amber-700"
      >
        Нет данных
      </span>
    </div>

    <div
      class="p-4"
      :class="constrainHeight ? 'min-h-0 flex-1 overflow-hidden' : ''"
    >
      <div
        v-if="error && !html"
        class="rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800"
      >
        {{ error }}
      </div>
      <div
        v-else-if="html"
        ref="contentRef"
        class="report-charts"
        v-html="html"
      />
      <p v-else-if="!loading" class="text-sm text-gray-500">Нет данных за выбранный период.</p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'
import {
  destroyReportCharts,
  observeReportCharts,
  renderReportCharts,
} from '@/composables/useReportCharts'
import '@/assets/report-charts.css'

const props = defineProps<{
  title: string
  chartTitle?: string | null
  blockType?: string
  html: string | null
  loading?: boolean
  success?: boolean
  error?: string | null
  editMode?: boolean
  constrainHeight?: boolean
  hasSettings?: boolean
  layoutX?: number
  layoutW?: number
  onWidthResize?: (w: number) => void
  onHalfWidth?: () => void
  onFullWidth?: () => void
}>()

const widthPresets = [
  { label: '½', w: 6, hint: 'Половина ширины (6 колонок)' },
  { label: '100%', w: 12, hint: 'На всю ширину (12 колонок)' },
] as const

const layoutColsLabel = computed(() => {
  const x = props.layoutX ?? 0
  const w = props.layoutW ?? 6
  if (x === 0 && w === 12) return '12 из 12 колонок · на всю ширину'
  return `колонки ${x + 1}–${x + w} · ширина ${w}/12`
})

function isWidthPresetActive(targetW: number) {
  const w = props.layoutW ?? 6
  const x = props.layoutX ?? 0
  if (targetW === 12) return x === 0 && w === 12
  return x === 0 && w === 6
}

function applyWidthPreset(preset: (typeof widthPresets)[number]) {
  if (preset.w === 12) {
    props.onFullWidth?.()
    return
  }
  props.onHalfWidth?.()
}

function startWidthDrag(event: MouseEvent) {
  const grid = document.querySelector('.analytics-grid')
  if (!grid || !props.onWidthResize) return

  const gridRect = grid.getBoundingClientRect()
  const colNum = 12
  const margin = 12
  const colWidth = (gridRect.width - margin * (colNum + 1)) / colNum
  const x = props.layoutX ?? 0

  const onMove = (ev: MouseEvent) => {
    const relX = ev.clientX - gridRect.left
    const colIndex = Math.round((relX - margin) / (colWidth + margin))
    const rightCol = Math.max(x, Math.min(colNum - 1, colIndex))
    const newW = Math.max(3, rightCol - x + 1)
    props.onWidthResize?.(newW)
  }

  const onUp = () => {
    window.removeEventListener('mousemove', onMove)
    window.removeEventListener('mouseup', onUp)
  }

  onMove(event)
  window.addEventListener('mousemove', onMove)
  window.addEventListener('mouseup', onUp)
}

defineEmits<{
  settings: []
  remove: []
}>()

const contentRef = ref<HTMLElement | null>(null)
let stopObserving: (() => void) | null = null

function teardownCharts() {
  stopObserving?.()
  stopObserving = null
  destroyReportCharts(contentRef.value)
}

async function refreshCharts() {
  if (props.loading || !props.html) {
    teardownCharts()
    return
  }

  await nextTick()
  destroyReportCharts(contentRef.value)

  requestAnimationFrame(() => {
    if (props.loading || !props.html || !contentRef.value) return
    renderReportCharts(contentRef.value, true)
    stopObserving?.()
    stopObserving = observeReportCharts(contentRef.value)
  })
}

onBeforeUnmount(teardownCharts)

watch(
  () => [props.loading, props.html] as const,
  () => {
    void refreshCharts()
  },
  { immediate: true },
)
</script>

<style scoped>
.widget-width-handle {
  position: absolute;
  top: 48px;
  right: 0;
  z-index: 25;
  width: 10px;
  height: calc(100% - 56px);
  cursor: ew-resize;
  border-radius: 4px 0 0 4px;
  background: linear-gradient(to left, rgb(37 99 235 / 0.2), transparent);
}

.widget-width-handle:hover,
.widget-width-handle:active {
  background: linear-gradient(to left, rgb(37 99 235 / 0.35), transparent);
}

.widget-width-handle::after {
  position: absolute;
  top: 50%;
  right: 2px;
  width: 3px;
  height: 36px;
  border-radius: 999px;
  background: #2563eb;
  content: '';
  transform: translateY(-50%);
  box-shadow: 0 0 0 1px #fff;
}
</style>
