<template>
  <div class="rounded-xl border border-dashed border-gray-200 bg-white p-12 text-center">
    <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-brand-50 text-2xl">
      {{ icon }}
    </div>
    <h3 class="text-lg font-semibold text-gray-900">{{ title }}</h3>
    <p class="mx-auto mt-2 max-w-md text-sm text-gray-500">{{ description }}</p>
    <div v-if="$slots.action" class="mt-6">
      <slot name="action" />
    </div>
  </div>
</template>

<script setup lang="ts">
defineProps<{
  icon?: string
  title: string
  description: string
}>()
</script>
