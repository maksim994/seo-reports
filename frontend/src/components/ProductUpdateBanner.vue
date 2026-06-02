<template>
  <div
    v-if="highlight"
    class="mb-4 flex flex-col gap-3 rounded-xl border border-brand-200 bg-brand-50 px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
  >
    <div class="min-w-0">
      <p class="text-xs font-semibold uppercase tracking-wide text-brand-700">Новое</p>
      <p class="mt-0.5 text-sm font-medium text-gray-900">{{ highlight.title }}</p>
      <p class="mt-1 text-sm text-gray-600">{{ highlight.summary }}</p>
    </div>
    <div class="flex shrink-0 flex-wrap gap-2">
      <RouterLink
        v-if="highlight.cta_path"
        :to="highlight.cta_path"
        class="rounded-lg bg-brand-500 px-3 py-1.5 text-xs font-medium text-white hover:bg-brand-600"
        @click="onTry"
      >
        {{ highlight.cta_label }}
      </RouterLink>
      <button
        type="button"
        class="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs text-gray-700 hover:bg-gray-50"
        @click="onDismiss"
      >
        Понятно
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { useProductUpdatesStore } from '@/stores/productUpdates'

const route = useRoute()
const store = useProductUpdatesStore()

const highlight = computed(() => {
  const matches = store.unreadForRoute(route.path)
  if (!matches.length) return null

  return [...matches].sort((a, b) => b.priority - a.priority)[0] ?? null
})

async function onDismiss() {
  if (!highlight.value) return
  await store.dismiss(highlight.value.id)
}

async function onTry() {
  if (!highlight.value || highlight.value.is_read) return
  await store.dismiss(highlight.value.id)
}
</script>
