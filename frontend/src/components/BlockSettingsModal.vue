<template>
  <AppModal :model-value="modelValue" :title="title" @update:model-value="emit('update:modelValue', $event)">
    <form class="space-y-4" @submit.prevent="save">
      <div v-for="field in schema" :key="field.key">
        <label class="mb-1.5 block text-sm font-medium text-gray-700">{{ field.label }}</label>
        <textarea
          v-if="field.type === 'textarea'"
          v-model="form[field.key]"
          rows="5"
          class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand-500"
        />
        <select
          v-else-if="field.type === 'select'"
          v-model="form[field.key]"
          class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand-500"
        >
          <option
            v-for="option in field.options ?? []"
            :key="option.value"
            :value="option.value"
          >
            {{ option.label }}
          </option>
        </select>
        <input
          v-else
          v-model="form[field.key]"
          :type="field.type === 'number' ? 'number' : 'text'"
          :min="field.min"
          :max="field.max"
          class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand-500"
        />
      </div>
    </form>

    <template #footer>
      <button
        type="button"
        class="rounded-lg border border-gray-200 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"
        @click="emit('update:modelValue', false)"
      >
        Отмена
      </button>
      <button
        type="button"
        class="rounded-lg bg-brand-500 px-4 py-2 text-sm font-medium text-white hover:bg-brand-600"
        @click="save"
      >
        Сохранить
      </button>
    </template>
  </AppModal>
</template>

<script setup lang="ts">
import { reactive, watch } from 'vue'
import AppModal from '@/components/AppModal.vue'
import type { BlockSettingsField } from '@/types'

const props = defineProps<{
  modelValue: boolean
  title: string
  schema: BlockSettingsField[]
  settings: Record<string, unknown> | null
}>()

const emit = defineEmits<{
  'update:modelValue': [value: boolean]
  save: [settings: Record<string, unknown>]
}>()

const form = reactive<Record<string, string>>({})

function resetForm() {
  for (const key of Object.keys(form)) {
    delete form[key]
  }

  for (const field of props.schema) {
    const current = props.settings?.[field.key]
    if (current !== undefined && current !== null) {
      form[field.key] = String(current)
    } else if (field.default !== undefined && field.default !== null) {
      form[field.key] = String(field.default)
    } else {
      form[field.key] = ''
    }
  }
}

watch(
  () => [props.modelValue, props.settings, props.schema] as const,
  () => {
    if (props.modelValue) {
      resetForm()
    }
  },
  { immediate: true, deep: true },
)

function save() {
  const payload: Record<string, unknown> = {}
  for (const field of props.schema) {
    const raw = form[field.key] ?? ''
    payload[field.key] = field.type === 'number' ? Number(raw) : raw
  }
  emit('save', payload)
  emit('update:modelValue', false)
}
</script>
