<template>
  <div>
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
      <div class="flex items-center gap-3">
        <h1 class="text-2xl font-semibold text-gray-900">Мои проекты</h1>
        <button
          class="rounded-lg bg-success-500 px-4 py-2 text-sm font-medium text-white hover:bg-green-600"
          @click="showModal = true"
        >
          + Добавить
        </button>
      </div>
      <select
        v-model="filter"
        class="rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm text-gray-700 outline-none focus:border-brand-500"
        @change="loadProjects"
      >
        <option value="all">Все проекты</option>
        <option value="linked">Привязаны к аналитике</option>
        <option value="unlinked">Без привязки к аналитике</option>
      </select>
    </div>

    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
      <div v-if="store.loading" class="p-8 text-center text-sm text-gray-500">
        Загрузка...
      </div>

      <EmptyState
        v-else-if="store.projects.length === 0"
        icon="📁"
        title="Проектов пока нет"
        description="Вы не добавили в систему ни одного проекта. Создайте первый проект, чтобы начать генерацию отчётов."
      >
        <template #action>
          <button
            class="rounded-lg bg-brand-500 px-4 py-2 text-sm font-medium text-white hover:bg-brand-600"
            @click="showModal = true"
          >
            + Добавить проект
          </button>
        </template>
      </EmptyState>

      <table v-else class="w-full text-left text-sm">
        <thead class="border-b border-gray-200 bg-gray-50 text-xs uppercase text-gray-500">
          <tr>
            <th class="px-6 py-3">ID</th>
            <th class="px-6 py-3">Название</th>
            <th class="px-6 py-3">URL / Домен</th>
            <th class="px-6 py-3">Аналитика</th>
            <th class="px-6 py-3 text-right">Действия</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-for="project in store.projects" :key="project.id" class="hover:bg-gray-50">
            <td class="px-6 py-4 text-gray-500">{{ project.id }}</td>
            <td class="px-6 py-4 font-medium text-gray-900">{{ project.name }}</td>
            <td class="px-6 py-4 text-gray-600">{{ project.domain || '—' }}</td>
            <td class="px-6 py-4">
              <span
                :class="[
                  'inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium',
                  project.has_analytics
                    ? 'bg-green-50 text-green-700'
                    : 'bg-gray-100 text-gray-600',
                ]"
              >
                {{ project.has_analytics ? 'Подключена' : 'Не подключена' }}
              </span>
            </td>
            <td class="px-6 py-4 text-right">
              <button
                class="mr-3 text-brand-600 hover:underline"
                @click="openSources(project)"
              >
                Источники
              </button>
              <RouterLink
                :to="`/projects/${project.id}/generate`"
                class="mr-3 text-brand-600 hover:underline"
              >
                Отчёт
              </RouterLink>
              <RouterLink
                :to="`/projects/${project.id}/work`"
                class="mr-3 text-brand-600 hover:underline"
              >
                Работы
              </RouterLink>
              <button class="text-error-500 hover:underline" @click="remove(project.id)">
                Удалить
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <AppModal v-model="showModal" title="Добавить проект">
      <div class="mb-4 rounded-lg border border-blue-100 bg-blue-50 px-4 py-3 text-sm text-blue-800">
        Проект создаётся без подключения системы аналитики. Вы сможете использовать данные из
        рекламных систем и других сервисов, но блоки отчёта с данными посещаемости сайта работать
        не будут.
      </div>
      <form id="project-form" class="space-y-4" @submit.prevent="create">
        <div v-if="formError" class="rounded-lg bg-red-50 px-4 py-3 text-sm text-error-500">
          {{ formError }}
        </div>
        <div>
          <label class="mb-1.5 block text-sm font-medium text-gray-700">
            Название проекта <span class="text-error-500">*</span>
          </label>
          <input
            v-model="form.name"
            required
            class="w-full rounded-lg border border-gray-200 px-4 py-2.5 text-sm outline-none focus:border-brand-500"
            placeholder="example.com"
          />
        </div>
        <div>
          <label class="mb-1.5 block text-sm font-medium text-gray-700">Домен (опционально)</label>
          <input
            v-model="form.domain"
            class="w-full rounded-lg border border-gray-200 px-4 py-2.5 text-sm outline-none focus:border-brand-500"
            placeholder="example.com"
          />
          <p class="mt-1 text-xs text-gray-500">
            При указании домена система автоматически свяжет проект с проектами из других сервисов.
          </p>
        </div>
      </form>
      <template #footer>
        <button
          type="button"
          class="rounded-lg border border-gray-200 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"
          @click="showModal = false"
        >
          Отмена
        </button>
        <button
          type="submit"
          form="project-form"
          :disabled="saving"
          class="rounded-lg bg-success-500 px-4 py-2 text-sm font-medium text-white hover:bg-green-600 disabled:opacity-50"
        >
          {{ saving ? 'Сохранение...' : 'Добавить' }}
        </button>
      </template>
    </AppModal>

    <ProjectSourcesModal
      v-model="showSourcesModal"
      :project-id="sourcesProject?.id ?? null"
      :project-name="sourcesProject?.name ?? ''"
      :project-domain="sourcesProject?.domain ?? null"
      @saved="loadProjects"
    />
  </div>
</template>

<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { RouterLink } from 'vue-router'
import AppModal from '@/components/AppModal.vue'
import EmptyState from '@/components/EmptyState.vue'
import ProjectSourcesModal from '@/components/ProjectSourcesModal.vue'
import { useProjectsStore } from '@/stores/projects'
import type { AxiosError } from 'axios'
import type { ApiError, Project } from '@/types'

const store = useProjectsStore()
const showModal = ref(false)
const showSourcesModal = ref(false)
const sourcesProject = ref<Project | null>(null)
const saving = ref(false)
const formError = ref('')
const filter = ref('all')

const form = reactive({
  name: '',
  domain: '',
})

async function loadProjects() {
  const analytics =
    filter.value === 'linked' ? true : filter.value === 'unlinked' ? false : null
  await store.fetchProjects(analytics)
}

async function create() {
  formError.value = ''
  saving.value = true
  try {
    await store.createProject({
      name: form.name,
      domain: form.domain || undefined,
    })
    form.name = ''
    form.domain = ''
    showModal.value = false
  } catch (e) {
    const err = e as AxiosError<ApiError>
    formError.value =
      err.response?.data?.errors?.name?.[0] ??
      err.response?.data?.message ??
      'Не удалось создать проект'
  } finally {
    saving.value = false
  }
}

async function remove(id: number) {
  if (!confirm('Удалить проект?')) return
  await store.deleteProject(id)
}

function openSources(project: Project) {
  sourcesProject.value = project
  showSourcesModal.value = true
}

onMounted(loadProjects)
</script>
