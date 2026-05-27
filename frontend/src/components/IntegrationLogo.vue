<template>
  <div
    class="flex shrink-0 items-center justify-center rounded-xl border p-2"
    :class="[sizeClass, accentClass]"
  >
    <img
      v-if="logoUrl"
      :src="logoUrl"
      :alt="alt"
      class="max-h-full max-w-full object-contain"
      loading="lazy"
    />
    <span v-else class="leading-none" :class="iconSizeClass">{{ icon }}</span>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { integrationBranding, integrationLogoUrl } from '@/lib/integrationBranding'

const props = withDefaults(
  defineProps<{
    provider: string
    label?: string
    icon?: string
    logoUrl?: string | null
    size?: 'sm' | 'md' | 'lg'
  }>(),
  {
    label: '',
    size: 'md',
  },
)

const branding = computed(() => integrationBranding(props.provider, props.icon ?? '🔗'))

const logoUrl = computed(() => integrationLogoUrl(props.provider, props.logoUrl ?? branding.value.logoUrl))
const icon = computed(() => props.icon ?? branding.value.icon)
const alt = computed(() => props.label || props.provider)
const accentClass = computed(() => branding.value.accentClass ?? 'bg-gray-50 border-gray-100')

const sizeClass = computed(() => {
  if (props.size === 'sm') return 'h-9 w-9'
  if (props.size === 'lg') return 'h-12 min-w-[7.5rem] max-w-[11rem] px-3'
  return 'h-11 w-11'
})

const iconSizeClass = computed(() => {
  if (props.size === 'sm') return 'text-lg'
  if (props.size === 'lg') return 'text-2xl'
  return 'text-xl'
})
</script>
