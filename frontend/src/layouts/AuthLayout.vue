<template>
  <div class="flex min-h-screen items-center justify-center bg-gray-50 p-4">
    <div class="w-full max-w-md">
      <div class="mb-8 text-center">
        <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-brand-500 text-lg font-bold text-white">
          SR
        </div>
        <h1 class="text-2xl font-semibold text-gray-900">{{ title }}</h1>
        <p class="mt-1 text-sm text-gray-500">{{ subtitle }}</p>
      </div>
      <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
        <slot />
      </div>
      <p v-if="$slots.footer" class="mt-6 text-center text-sm text-gray-500">
        <slot name="footer" />
      </p>
    </div>
  </div>
</template>

<script setup lang="ts">
defineProps<{
  title: string
  subtitle: string
}>()
</script>
