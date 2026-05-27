<template>
  <Teleport to="body">
    <div v-if="modelValue" class="fixed inset-0 z-50 flex items-center justify-center p-4">
      <div class="absolute inset-0 bg-gray-900/50" @click="close" />
      <div class="relative w-full max-w-lg rounded-2xl bg-white shadow-xl">
        <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4">
          <h2 class="text-lg font-semibold text-gray-900">{{ title }}</h2>
          <button class="text-gray-400 hover:text-gray-600" @click="close">✕</button>
        </div>
        <div class="px-6 py-4">
          <slot />
        </div>
        <div v-if="$slots.footer" class="flex justify-end gap-3 border-t border-gray-200 px-6 py-4">
          <slot name="footer" />
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup lang="ts">
defineProps<{ modelValue: boolean; title: string }>()
const emit = defineEmits<{ 'update:modelValue': [value: boolean] }>()

function close() {
  emit('update:modelValue', false)
}
</script>
