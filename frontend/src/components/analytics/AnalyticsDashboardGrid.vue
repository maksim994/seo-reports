<template>
  <!-- Просмотр: высота по контенту, без скролла внутри карточки -->
  <div v-if="!editMode" class="analytics-flow">
    <div
      v-for="widget in sortedWidgets"
      :key="widget.id"
      class="analytics-flow-item"
      :style="flowStyle(widget)"
    >
      <slot :widget="widget" :layout="widget.layout" />
    </div>
  </div>

  <!-- Редактирование: drag & resize -->
  <div v-else class="analytics-grid-editor">
    <p class="analytics-grid-hint">
      Сетка из 12 колонок. Тяните за
      <span class="analytics-grid-hint-mark">угол</span>
      или
      <span class="analytics-grid-hint-mark">правый край</span>
      карточки; ширину можно выбрать кнопками в заголовке.
    </p>
    <GridLayout
      :layout="layoutItems"
      :col-num="12"
      :row-height="52"
      :margin="[12, 12]"
      :is-draggable="true"
      :is-resizable="true"
      :vertical-compact="true"
      :use-css-transforms="false"
      class="analytics-grid"
      @layout-updated="onLayoutUpdated"
    >
      <GridItem
        v-for="widget in widgets"
        :key="widget.id"
        :i="widget.id"
        :x="widget.layout.x"
        :y="widget.layout.y"
        :w="widget.layout.w"
        :h="widget.layout.h"
        :min-w="3"
        :min-h="5"
        :max-w="12"
        drag-allow-from=".widget-drag-handle"
        drag-ignore-from=".widget-no-drag, button, a, input, select, textarea"
      >
        <div class="analytics-grid-item-inner h-full">
          <slot
            :widget="widget"
            :layout="widget.layout"
            :on-width-resize="(w: number) => setWidgetWidth(widget.id, w)"
            :on-half-width="() => setWidgetHalfWidth(widget.id)"
            :on-full-width="() => setWidgetFullWidth(widget.id)"
          />
        </div>
      </GridItem>
    </GridLayout>
  </div>
</template>

<script setup lang="ts">
import { GridItem, GridLayout } from 'grid-layout-plus'
import { computed } from 'vue'
import type { DashboardWidget, DashboardWidgetLayout } from '@/types'

const props = defineProps<{
  widgets: DashboardWidget[]
  editMode?: boolean
}>()

const emit = defineEmits<{
  'update:widgets': [widgets: DashboardWidget[]]
}>()

interface GridLayoutItem {
  i: string
  x: number
  y: number
  w: number
  h: number
}

const sortedWidgets = computed(() =>
  [...props.widgets].sort(
    (a, b) => a.layout.y - b.layout.y || a.layout.x - b.layout.x,
  ),
)

function flowStyle(widget: DashboardWidget) {
  const x = Math.max(0, Math.min(11, widget.layout.x))
  const w = Math.max(1, Math.min(12 - x, widget.layout.w))

  return {
    gridColumn: `${x + 1} / span ${w}`,
  }
}

const layoutItems = computed(() =>
  props.widgets.map((w) => ({
    i: w.id,
    x: w.layout.x,
    y: w.layout.y,
    w: w.layout.w,
    h: w.layout.h,
  })),
)

function layoutEqual(a: DashboardWidget['layout'], b: DashboardWidget['layout']): boolean {
  return a.x === b.x && a.y === b.y && a.w === b.w && a.h === b.h
}

function onLayoutUpdated(items: GridLayoutItem[]) {
  const byId = Object.fromEntries(items.map((item) => [item.i, item]))
  let changed = false
  const updated = props.widgets.map((widget) => {
    const item = byId[widget.id]
    if (!item) return widget
    const layout = { x: item.x, y: item.y, w: item.w, h: item.h }
    if (!layoutEqual(widget.layout, layout)) {
      changed = true
    }
    return { ...widget, layout }
  })
  if (changed) {
    emit('update:widgets', updated)
  }
}

function clampLayout(layout: DashboardWidgetLayout): DashboardWidgetLayout {
  const w = Math.max(3, Math.min(12, layout.w))
  let x = Math.max(0, Math.min(11, layout.x))
  if (x + w > 12) {
    x = Math.max(0, 12 - w)
  }
  return { ...layout, x, w }
}

function patchWidgetLayout(id: string, patch: Partial<DashboardWidgetLayout>) {
  let changed = false
  const updated = props.widgets.map((widget) => {
    if (widget.id !== id) return widget
    const layout = clampLayout({ ...widget.layout, ...patch })
    if (!layoutEqual(widget.layout, layout)) {
      changed = true
    }
    return { ...widget, layout }
  })
  if (changed) {
    emit('update:widgets', updated)
  }
}

function setWidgetWidth(id: string, w: number) {
  const widget = props.widgets.find((item) => item.id === id)
  if (!widget) return
  patchWidgetLayout(id, { w })
}

function setWidgetHalfWidth(id: string) {
  patchWidgetLayout(id, { x: 0, w: 6 })
}

function setWidgetFullWidth(id: string) {
  patchWidgetLayout(id, { x: 0, w: 12 })
}

</script>

<style scoped>
.analytics-flow {
  display: grid;
  grid-template-columns: repeat(12, minmax(0, 1fr));
  gap: 12px;
  align-items: start;
}

.analytics-flow-item {
  min-width: 0;
}

@media (max-width: 768px) {
  .analytics-flow-item {
    grid-column: 1 / -1 !important;
  }
}

.analytics-grid-editor {
  --vgl-resizer-size: 22px;
  --vgl-resizer-border-color: #2563eb;
  --vgl-resizer-border-width: 3px;
}

.analytics-grid-hint {
  margin: 0 0 10px;
  font-size: 12px;
  line-height: 1.45;
  color: #6b7280;
}

.analytics-grid-hint-mark {
  font-weight: 600;
  color: #374151;
}

.analytics-grid {
  position: relative;
  --grid-col-count: 12;
  --grid-gap: 12px;
  background-image: repeating-linear-gradient(
    to right,
    transparent 0,
    transparent calc((100% - var(--grid-gap) * (var(--grid-col-count) - 1)) / var(--grid-col-count)),
    rgb(219 234 254 / 0.55)
      calc((100% - var(--grid-gap) * (var(--grid-col-count) - 1)) / var(--grid-col-count)),
    rgb(219 234 254 / 0.55)
      calc(
        (100% - var(--grid-gap) * (var(--grid-col-count) - 1)) / var(--grid-col-count) + var(--grid-gap)
      )
  );
  background-size: 100% 100%;
  border-radius: 16px;
  padding: 4px;
}

.analytics-grid :deep(.vgl-item) {
  transition: box-shadow 0.15s ease;
  outline: 1px dashed rgb(147 197 253 / 0.65);
  outline-offset: -1px;
  border-radius: 16px;
}

.analytics-grid :deep(.vgl-item--resizing),
.analytics-grid :deep(.vgl-item--dragging) {
  outline-color: #2563eb;
  box-shadow: 0 8px 24px rgb(37 99 235 / 0.15);
}

.analytics-grid-item-inner {
  height: 100%;
  min-height: 0;
}

.analytics-grid :deep(.vgl-item__resizer) {
  z-index: 30;
  opacity: 1;
}

.analytics-grid :deep(.vgl-item__resizer::after) {
  position: absolute;
  right: 2px;
  bottom: 2px;
  width: 14px;
  height: 14px;
  border-radius: 3px;
  background: #2563eb;
  content: '';
  box-shadow: 0 0 0 2px #fff;
}

.analytics-grid :deep(.vgl-item--placeholder) {
  background: #dbeafe;
  border-radius: 16px;
  opacity: 0.45;
}
</style>
