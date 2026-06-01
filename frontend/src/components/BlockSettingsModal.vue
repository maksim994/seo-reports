<template>
  <AppModal :model-value="modelValue" :title="title" @update:model-value="emit('update:modelValue', $event)">
    <p v-if="optionsHint" class="mb-4 text-xs text-gray-500">{{ optionsHint }}</p>
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
            v-for="option in resolvedOptions(field)"
            :key="option.value"
            :value="option.value"
          >
            {{ option.label }}
          </option>
        </select>
        <div
          v-else-if="field.type === 'multiselect'"
          class="max-h-48 space-y-2 overflow-y-auto rounded-lg border border-gray-200 p-3"
        >
          <label
            v-for="option in resolvedOptions(field)"
            :key="option.value"
            class="flex cursor-pointer items-center gap-2 text-sm text-gray-700"
          >
            <input
              type="checkbox"
              class="rounded border-gray-300 text-brand-500 focus:ring-brand-500"
              :checked="multiValues(field.key).includes(option.value)"
              @change="toggleMulti(field.key, option.value, ($event.target as HTMLInputElement).checked)"
            />
            <span>{{ option.label }}</span>
          </label>
          <p v-if="resolvedOptions(field).length === 0" class="text-xs text-gray-500">
            Нет доступных целей. Подключите Метрику к проекту с счётчиком.
          </p>
        </div>
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
  dynamicOptions?: Record<string, Array<{ value: string; label: string }>>
  optionsHint?: string | null
}>()

const emit = defineEmits<{
  'update:modelValue': [value: boolean]
  save: [settings: Record<string, unknown>]
}>()

const form = reactive<Record<string, string>>({})
const multiForm = reactive<Record<string, string[]>>({})

function resolvedOptions(field: BlockSettingsField) {
  if (field.options_key && props.dynamicOptions?.[field.options_key]) {
    return props.dynamicOptions[field.options_key]
  }

  return field.options ?? []
}

function multiValues(key: string): string[] {
  return multiForm[key] ?? []
}

function toggleMulti(key: string, value: string, checked: boolean) {
  const current = new Set(multiForm[key] ?? [])
  if (checked) {
    current.add(value)
  } else {
    current.delete(value)
  }
  multiForm[key] = [...current]
}

function resetForm() {
  for (const key of Object.keys(form)) {
    delete form[key]
  }
  for (const key of Object.keys(multiForm)) {
    delete multiForm[key]
  }

  for (const field of props.schema) {
    const current = props.settings?.[field.key]

    if (field.type === 'multiselect') {
      const values = Array.isArray(current)
        ? current.map(String)
        : typeof current === 'string' && current !== ''
          ? current.split(',').map((v) => v.trim())
          : []
      multiForm[field.key] = values
      continue
    }

    if (current !== undefined && current !== null) {
      form[field.key] = String(current)
    } else if (field.default !== undefined && field.default !== null && !Array.isArray(field.default)) {
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
    if (field.type === 'multiselect') {
      const values = multiForm[field.key] ?? []
      payload[field.key] = values.map((v) => Number(v)).filter((n) => n > 0)
      continue
    }

    const raw = form[field.key] ?? ''
    payload[field.key] = field.type === 'number' ? Number(raw) : raw
  }
  emit('save', payload)
  emit('update:modelValue', false)
}
</script>
