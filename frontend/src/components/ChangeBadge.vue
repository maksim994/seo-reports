<template>
  <span
    v-if="value != null"
    :class="[
      'inline-flex rounded-full px-2 py-0.5 text-xs font-medium',
      value > 0 ? 'bg-green-50 text-green-700' : value < 0 ? 'bg-red-50 text-red-700' : 'bg-gray-100 text-gray-600',
    ]"
  >
    {{ signed && value > 0 ? '+' : '' }}{{ formatValue(value) }}{{ suffix }}
  </span>
</template>

<script setup lang="ts">
const props = withDefaults(
  defineProps<{
    value: number | null
    suffix?: string
    signed?: boolean
  }>(),
  {
    suffix: '%',
    signed: false,
  },
)

function formatValue(value: number) {
  const abs = Math.abs(value)
  const formatted = Number.isInteger(abs) ? String(abs) : abs.toFixed(1)
  return props.signed && value < 0 ? `-${formatted}` : formatted
}
</script>
