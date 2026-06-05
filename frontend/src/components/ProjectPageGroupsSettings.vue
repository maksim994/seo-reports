<template>
  <section class="rounded-2xl border border-blue-100 bg-white p-6 shadow-sm">
    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
      <div>
        <h2 class="text-lg font-medium text-gray-900">Типы страниц</h2>
        <p class="mt-1 text-sm text-gray-500">
          Regex-маски путей для графика Метрики: блог, каталог, услуги и другие разделы.
        </p>
      </div>
      <button
        type="button"
        class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50"
        @click="addGroup"
      >
        Добавить тип
      </button>
    </div>

    <div v-if="loading" class="text-sm text-gray-500">Загрузка...</div>
    <form v-else class="space-y-4" @submit.prevent="save">
      <div class="rounded-lg bg-blue-50 px-4 py-3 text-xs leading-5 text-blue-900">
        Маска применяется к path URL. Примеры: <code>^/blog/</code>, <code>^/catalog/</code>.
        Если URL подходит под несколько масок, используется первая сверху.
      </div>

      <div v-if="error" class="rounded-lg bg-red-50 px-4 py-3 text-sm text-error-500">
        {{ error }}
      </div>

      <div v-if="groups.length === 0" class="rounded-lg border border-dashed border-gray-200 p-4 text-sm text-gray-500">
        Добавьте типы страниц, чтобы в аналитике появился график по разделам сайта.
      </div>

      <div v-else class="space-y-3">
        <div
          v-for="(group, index) in groups"
          :key="group.id"
          class="rounded-xl border border-gray-200 p-4"
        >
          <div class="grid gap-3 lg:grid-cols-[1fr_1.5fr_auto] lg:items-start">
            <div>
              <label class="mb-1 block text-xs font-medium text-gray-500">Название</label>
              <input
                v-model="group.label"
                type="text"
                placeholder="Инфо раздел"
                class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-brand-500"
              />
            </div>
            <div>
              <label class="mb-1 block text-xs font-medium text-gray-500">Regex для path</label>
              <input
                v-model="group.pattern"
                type="text"
                placeholder="^/blog/"
                class="w-full rounded-lg border border-gray-200 px-3 py-2 font-mono text-sm outline-none focus:border-brand-500"
                :class="regexError(group.pattern) ? 'border-red-300 focus:border-red-400' : ''"
              />
              <p v-if="regexError(group.pattern)" class="mt-1 text-xs text-error-500">
                Некорректное регулярное выражение.
              </p>
            </div>
            <div class="flex flex-wrap items-center gap-2 lg:pt-6">
              <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                <input
                  v-model="group.enabled"
                  type="checkbox"
                  class="rounded border-gray-300 text-brand-500 focus:ring-brand-500"
                />
                Вкл.
              </label>
              <button
                type="button"
                class="rounded-lg border border-gray-200 px-2.5 py-1.5 text-xs text-gray-600 hover:bg-gray-50 disabled:opacity-40"
                :disabled="index === 0"
                @click="moveGroup(index, -1)"
              >
                Выше
              </button>
              <button
                type="button"
                class="rounded-lg border border-gray-200 px-2.5 py-1.5 text-xs text-gray-600 hover:bg-gray-50 disabled:opacity-40"
                :disabled="index === groups.length - 1"
                @click="moveGroup(index, 1)"
              >
                Ниже
              </button>
              <button
                type="button"
                class="rounded-lg border border-red-100 px-2.5 py-1.5 text-xs text-error-500 hover:bg-red-50"
                @click="removeGroup(group.id)"
              >
                Удалить
              </button>
            </div>
          </div>
        </div>
      </div>

      <div class="flex flex-wrap items-center gap-3">
        <button
          type="submit"
          :disabled="saving || hasInvalidGroups"
          class="rounded-lg bg-brand-500 px-4 py-2 text-sm font-medium text-white hover:bg-brand-600 disabled:opacity-50"
        >
          {{ saving ? 'Сохранение...' : 'Сохранить типы страниц' }}
        </button>
        <p v-if="savedMessage" class="text-sm text-green-700">{{ savedMessage }}</p>
      </div>
    </form>
  </section>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import api from '@/lib/api'
import type { ProjectPageGroup } from '@/types'

const props = defineProps<{ projectId: number }>()
const emit = defineEmits<{ saved: [] }>()

const loading = ref(false)
const saving = ref(false)
const error = ref('')
const savedMessage = ref('')
const groups = ref<ProjectPageGroup[]>([])

const hasInvalidGroups = computed(() =>
  groups.value.some((group) => !group.label.trim() || !group.pattern.trim() || Boolean(regexError(group.pattern))),
)

function regexError(pattern: string): string {
  if (!pattern.trim()) return ''
  try {
    new RegExp(pattern)
    return ''
  } catch {
    return 'invalid'
  }
}

function addGroup() {
  groups.value.push({
    id: crypto.randomUUID(),
    label: '',
    pattern: '',
    enabled: true,
  })
}

function removeGroup(id: string) {
  groups.value = groups.value.filter((group) => group.id !== id)
}

function moveGroup(index: number, direction: -1 | 1) {
  const next = index + direction
  if (next < 0 || next >= groups.value.length) return
  const copy = [...groups.value]
  const [item] = copy.splice(index, 1)
  copy.splice(next, 0, item)
  groups.value = copy
}

async function load() {
  loading.value = true
  error.value = ''
  try {
    const { data } = await api.get<{ data: { groups: ProjectPageGroup[] } }>(
      `/projects/${props.projectId}/page-groups`,
    )
    groups.value = data.data.groups
  } catch {
    error.value = 'Не удалось загрузить типы страниц'
  } finally {
    loading.value = false
  }
}

async function save() {
  saving.value = true
  savedMessage.value = ''
  error.value = ''
  try {
    const payload = {
      groups: groups.value.map((group) => ({
        ...group,
        label: group.label.trim(),
        pattern: group.pattern.trim(),
      })),
    }
    const { data } = await api.put<{ data: { groups: ProjectPageGroup[] } }>(
      `/projects/${props.projectId}/page-groups`,
      payload,
    )
    groups.value = data.data.groups
    savedMessage.value = 'Типы страниц сохранены'
    emit('saved')
  } catch {
    error.value = 'Не удалось сохранить типы страниц. Проверьте регулярные выражения.'
  } finally {
    saving.value = false
  }
}

watch(
  () => props.projectId,
  (projectId) => {
    if (projectId) void load()
  },
)

onMounted(load)
</script>
