<template>
  <AppModal v-model="open" title="Что нового" size="2xl">
    <div v-if="store.loading && !store.loaded" class="py-6 text-center text-sm text-gray-500">
      Загрузка…
    </div>

    <div v-else-if="store.error" class="space-y-3">
      <p class="text-sm text-amber-800">{{ store.error }}</p>
      <button
        type="button"
        class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs text-gray-700 hover:bg-gray-50"
        @click="store.fetchUpdates()"
      >
        Повторить
      </button>
    </div>

    <p v-else-if="!store.updates.length" class="text-sm text-gray-500">
      Сейчас нет активных анонсов. Новые появятся после обновления сервиса.
    </p>

    <div v-else class="max-h-[60vh] space-y-4 overflow-y-auto pr-1">
      <p
        v-if="store.unreadCount === 0"
        class="rounded-lg bg-gray-50 px-3 py-2 text-xs text-gray-500"
      >
        Все текущие обновления прочитаны. Ниже — история последних анонсов.
      </p>
      <article
        v-for="item in store.updates"
        :key="item.id"
        class="rounded-xl border p-4"
        :class="
          item.is_read
            ? 'border-gray-100 bg-gray-50/50'
            : 'border-brand-200 bg-brand-50/30'
        "
      >
        <div class="mb-1 flex items-start justify-between gap-2">
          <h3 class="text-sm font-semibold text-gray-900">{{ item.title }}</h3>
          <span
            v-if="!item.is_read"
            class="shrink-0 rounded-full bg-brand-500 px-2 py-0.5 text-[10px] font-medium text-white"
          >
            Новое
          </span>
        </div>
        <p class="text-sm leading-relaxed text-gray-600">{{ item.summary }}</p>
        <div class="mt-3 flex flex-wrap gap-2">
          <RouterLink
            v-if="item.cta_path"
            :to="item.cta_path"
            class="rounded-lg bg-brand-500 px-3 py-1.5 text-xs font-medium text-white hover:bg-brand-600"
            @click="onTry(item)"
          >
            {{ item.cta_label }}
          </RouterLink>
          <button
            v-if="!item.is_read"
            type="button"
            class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs text-gray-700 hover:bg-gray-50"
            @click="onDismiss(item.id)"
          >
            Понятно
          </button>
        </div>
      </article>
    </div>

    <template v-if="store.unreadCount > 0" #footer>
      <button
        type="button"
        class="rounded-lg border border-gray-200 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"
        @click="onDismissAll"
      >
        Отметить все прочитанными
      </button>
    </template>
  </AppModal>
</template>

<script setup lang="ts">
import { RouterLink } from 'vue-router'
import AppModal from '@/components/AppModal.vue'
import { useProductUpdatesStore } from '@/stores/productUpdates'
import type { ProductUpdate } from '@/types'

const open = defineModel<boolean>({ required: true })

const store = useProductUpdatesStore()

async function onDismiss(id: string) {
  await store.dismiss(id)
}

async function onDismissAll() {
  await store.dismissAll()
}

async function onTry(item: ProductUpdate) {
  if (!item.is_read) {
    await store.dismiss(item.id)
  }
  open.value = false
}
</script>
