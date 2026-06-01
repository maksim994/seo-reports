<template>
  <div class="min-h-screen bg-gray-50">
    <header class="border-b border-gray-200 bg-white">
      <div class="mx-auto flex max-w-3xl items-center justify-between gap-4 px-4 py-4 lg:px-6">
        <RouterLink to="/login" class="flex items-center gap-3">
          <div
            class="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-500 text-sm font-bold text-white"
          >
            SR
          </div>
          <span class="text-sm font-semibold text-gray-900">
            {{ settings.publicSettings?.app_name || 'SEO Reports' }}
          </span>
        </RouterLink>
        <nav class="flex items-center gap-4 text-sm">
          <RouterLink
            to="/privacy"
            class="text-gray-600 hover:text-gray-900"
            :class="{ 'font-medium text-brand-600': $route.name === 'privacy' }"
          >
            Конфиденциальность
          </RouterLink>
          <RouterLink
            to="/terms"
            class="text-gray-600 hover:text-gray-900"
            :class="{ 'font-medium text-brand-600': $route.name === 'terms' }"
          >
            Условия
          </RouterLink>
        </nav>
      </div>
    </header>

    <main class="mx-auto max-w-3xl px-4 py-8 lg:px-6 lg:py-12">
      <article class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm lg:p-10">
        <h1 class="text-2xl font-semibold text-gray-900 lg:text-3xl">{{ title }}</h1>
        <p v-if="updatedAt" class="mt-2 text-sm text-gray-500">Последнее обновление: {{ updatedAt }}</p>
        <div class="legal-prose mt-8">
          <slot />
        </div>
      </article>
    </main>
  </div>
</template>

<script setup lang="ts">
import { onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import { useSettingsStore } from '@/stores/settings'

defineProps<{
  title: string
  updatedAt?: string
}>()

const settings = useSettingsStore()

onMounted(() => {
  if (!settings.publicSettings) {
    void settings.fetchPublicSettings()
  }
})
</script>

<style scoped>
.legal-prose :deep(h2) {
  margin-top: 2rem;
  margin-bottom: 0.75rem;
  font-size: 1.125rem;
  font-weight: 600;
  color: rgb(17 24 39);
}

.legal-prose :deep(h2:first-child) {
  margin-top: 0;
}

.legal-prose :deep(p),
.legal-prose :deep(li) {
  margin-bottom: 0.75rem;
  font-size: 0.9375rem;
  line-height: 1.65;
  color: rgb(55 65 81);
}

.legal-prose :deep(ul) {
  margin-bottom: 1rem;
  list-style-type: disc;
  padding-left: 1.25rem;
}

.legal-prose :deep(a) {
  color: rgb(70 95 255);
  text-decoration: underline;
}

.legal-prose :deep(code) {
  border-radius: 0.25rem;
  background-color: rgb(243 244 246);
  padding: 0.125rem 0.375rem;
  font-size: 0.875rem;
  color: rgb(31 41 55);
}

.legal-prose :deep(a:hover) {
  color: rgb(54 65 245);
}
</style>
