<template>
  <div>
    <div class="mb-6">
      <RouterLink to="/projects" class="text-sm text-brand-600 hover:underline">← Проекты</RouterLink>
      <div class="mt-2 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
          <h1 class="text-2xl font-semibold text-gray-900">{{ project?.name ?? 'Проект' }}</h1>
          <p v-if="project?.domain" class="mt-1 text-sm text-gray-500">{{ project.domain }}</p>
        </div>
        <nav class="flex flex-wrap gap-2">
          <RouterLink
            :to="`/projects/${projectId}/analytics`"
            class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50"
          >
            Аналитика
          </RouterLink>
          <RouterLink
            :to="`/projects/${projectId}/generate`"
            class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50"
          >
            Отчёт
          </RouterLink>
          <RouterLink
            :to="`/projects/${projectId}/work`"
            class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50"
          >
            Работы
          </RouterLink>
          <RouterLink
            :to="`/projects/${projectId}/audits`"
            class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50"
          >
            Аудит
          </RouterLink>
        </nav>
      </div>
    </div>

    <div v-if="loading" class="text-sm text-gray-500">Загрузка проекта...</div>
    <div v-else-if="error" class="rounded-lg bg-red-50 px-4 py-3 text-sm text-error-500">
      {{ error }}
    </div>
    <div v-else class="space-y-6">
      <ProjectIntegrationsPanel
        :project-id="projectId"
        :project-domain="project?.domain ?? null"
        @saved="onSaved"
      />
      <ProjectMetrikaSettings :project-id="projectId" @saved="onSaved" />
      <ProjectPageGroupsSettings :project-id="projectId" @saved="onSaved" />
      <ProjectWordstatSettings :project-id="projectId" @saved="onSaved" />
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import ProjectIntegrationsPanel from '@/components/ProjectIntegrationsPanel.vue'
import ProjectMetrikaSettings from '@/components/ProjectMetrikaSettings.vue'
import ProjectPageGroupsSettings from '@/components/ProjectPageGroupsSettings.vue'
import ProjectWordstatSettings from '@/components/ProjectWordstatSettings.vue'
import api from '@/lib/api'
import type { Project } from '@/types'

const route = useRoute()
const projectId = computed(() => Number(route.params.id))
const project = ref<Project | null>(null)
const loading = ref(true)
const error = ref('')

async function loadProject() {
  loading.value = true
  error.value = ''
  try {
    const { data } = await api.get<{ data: Project }>(`/projects/${projectId.value}`)
    project.value = data.data
  } catch {
    error.value = 'Не удалось загрузить проект'
    project.value = null
  } finally {
    loading.value = false
  }
}

function onSaved() {
  loadProject()
}

onMounted(loadProject)
</script>
