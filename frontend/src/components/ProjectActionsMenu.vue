<template>
  <div class="inline-block text-left">
    <button
      ref="triggerRef"
      type="button"
      class="inline-flex items-center gap-1 rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50"
      @click.stop="toggle"
    >
      Действия
      <span class="text-xs text-gray-400" aria-hidden="true">▾</span>
    </button>

    <Teleport to="body">
      <div
        v-if="open"
        class="fixed z-[200] w-52 rounded-xl border border-gray-200 bg-white py-1 shadow-lg"
        :style="menuStyle"
        @click.stop
      >
        <RouterLink
          v-for="item in items"
          :key="item.to"
          :to="item.to"
          class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"
          @click="close"
        >
          <span class="mr-2" aria-hidden="true">{{ item.icon }}</span>
          {{ item.label }}
        </RouterLink>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'

const props = defineProps<{
  projectId: number
}>()

const open = ref(false)
const triggerRef = ref<HTMLButtonElement | null>(null)
const menuStyle = ref<{ top: string; left: string }>({ top: '0px', left: '0px' })

const MENU_WIDTH = 208

const items = computed(() => [
  { to: `/projects/${props.projectId}`, label: 'Настройки проекта', icon: '⚙️' },
  { to: `/projects/${props.projectId}/analytics`, label: 'Аналитика', icon: '📊' },
  { to: `/projects/${props.projectId}/generate`, label: 'Сформировать отчёт', icon: '📄' },
  { to: `/projects/${props.projectId}/work`, label: 'SEO-работы', icon: '📝' },
  { to: `/projects/${props.projectId}/audits`, label: 'Технический аудит', icon: '🔍' },
])

function updatePosition() {
  const el = triggerRef.value
  if (!el) return

  const rect = el.getBoundingClientRect()
  const left = Math.max(8, Math.min(rect.right - MENU_WIDTH, window.innerWidth - MENU_WIDTH - 8))

  menuStyle.value = {
    top: `${rect.bottom + 4}px`,
    left: `${left}px`,
  }
}

function toggle() {
  open.value = !open.value
}

function close() {
  open.value = false
}

function onDocumentClick() {
  close()
}

function bindListeners() {
  document.addEventListener('click', onDocumentClick)
  window.addEventListener('scroll', updatePosition, true)
  window.addEventListener('resize', updatePosition)
}

function unbindListeners() {
  document.removeEventListener('click', onDocumentClick)
  window.removeEventListener('scroll', updatePosition, true)
  window.removeEventListener('resize', updatePosition)
}

watch(open, async (isOpen) => {
  if (isOpen) {
    await nextTick()
    updatePosition()
    bindListeners()
  } else {
    unbindListeners()
  }
})

onBeforeUnmount(unbindListeners)
</script>
