<template>
  <div class="flex min-h-screen bg-gray-50">
    <!-- Sidebar -->
    <aside
      :class="[
        'fixed inset-y-0 left-0 z-50 flex w-72 flex-col border-r border-gray-200 bg-white transition-transform lg:static lg:translate-x-0',
        sidebarOpen ? 'translate-x-0' : '-translate-x-full',
      ]"
    >
      <div class="flex h-16 items-center gap-3 border-b border-gray-200 px-6">
        <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-brand-500 text-sm font-bold text-white">
          SR
        </div>
        <div>
          <div class="text-sm font-semibold text-gray-900">
            {{ settings.publicSettings?.app_name || 'SEO Reports' }}
          </div>
          <div class="text-xs text-gray-500">сервис генерации отчётов</div>
        </div>
      </div>

      <nav class="flex-1 space-y-1 p-4">
        <RouterLink
          v-for="item in navItems"
          :key="item.to"
          :to="item.to"
          class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors"
          :class="
            isActive(item.to)
              ? 'bg-brand-50 text-brand-600'
              : 'text-gray-700 hover:bg-gray-100'
          "
          @click="sidebarOpen = false"
        >
          <span class="text-base">{{ item.icon }}</span>
          {{ item.label }}
        </RouterLink>

        <template v-if="auth.user?.is_admin">
          <div class="pt-4 pb-2 px-3 text-xs font-semibold uppercase tracking-wide text-gray-400">
            Админ
          </div>
          <RouterLink
            v-for="item in adminNavItems"
            :key="item.to"
            :to="item.to"
            class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors"
            :class="
              isActive(item.to)
                ? 'bg-purple-50 text-purple-700'
                : 'text-gray-700 hover:bg-gray-100'
            "
            @click="sidebarOpen = false"
          >
            <span class="text-base">{{ item.icon }}</span>
            {{ item.label }}
          </RouterLink>
        </template>
      </nav>
    </aside>

    <div
      v-if="sidebarOpen"
      class="fixed inset-0 z-40 bg-gray-900/50 lg:hidden"
      @click="sidebarOpen = false"
    />

    <!-- Main -->
    <div class="flex flex-1 flex-col">
      <header class="sticky top-0 z-30 flex h-16 items-center justify-between border-b border-gray-200 bg-white px-4 lg:px-6">
        <button
          class="rounded-lg p-2 text-gray-500 hover:bg-gray-100 lg:hidden"
          @click="sidebarOpen = !sidebarOpen"
        >
          ☰
        </button>

        <div class="hidden text-sm text-gray-500 lg:block">
          Автоматизация отчётности в digital
        </div>

        <div class="relative">
          <button
            class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm hover:bg-gray-100"
            @click="menuOpen = !menuOpen"
          >
            <span class="hidden sm:inline text-gray-700">{{ auth.user?.email }}</span>
            <span class="flex h-8 w-8 items-center justify-center rounded-full bg-brand-500 text-xs font-semibold text-white">
              {{ initials }}
            </span>
            <span class="text-gray-400">▾</span>
          </button>
          <div
            v-if="menuOpen"
            class="absolute right-0 mt-2 w-48 rounded-xl border border-gray-200 bg-white py-1 shadow-lg"
          >
            <div class="border-b border-gray-100 px-4 py-2 text-xs text-gray-500">
              {{ auth.user?.name }}
            </div>
            <RouterLink
              to="/profile"
              class="block w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-50"
              @click="menuOpen = false"
            >
              Личный кабинет
            </RouterLink>
            <button
              class="w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-50"
              @click="handleLogout"
            >
              Выйти
            </button>
          </div>
        </div>
      </header>

      <main class="flex-1 p-4 lg:p-6">
        <div
          v-if="settings.publicSettings?.maintenance_mode"
          class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800"
        >
          {{
            settings.publicSettings.maintenance_message ||
            'Сервис находится в режиме обслуживания.'
          }}
        </div>
        <RouterView />
      </main>

      <footer class="border-t border-gray-200 bg-white px-6 py-4 text-center text-xs text-gray-500">
        2015–2026 © SEO Reports — автоматизация отчётности в digital
      </footer>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { RouterLink, RouterView, useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useSettingsStore } from '@/stores/settings'

const auth = useAuthStore()
const settings = useSettingsStore()
const route = useRoute()
const router = useRouter()
const sidebarOpen = ref(false)
const menuOpen = ref(false)

const navItems = [
  { to: '/dashboard', label: 'Дашборд', icon: '📈' },
  { to: '/projects', label: 'Мои проекты', icon: '📁' },
  { to: '/integrations', label: 'Источники данных', icon: '🔗' },
  { to: '/templates', label: 'Шаблоны отчётов', icon: '📋' },
  { to: '/reports', label: 'История отчётов', icon: '📊' },
  { to: '/schedules', label: 'Расписания', icon: '⏰' },
]

const adminNavItems = [
  { to: '/admin/users', label: 'Пользователи', icon: '👥' },
  { to: '/admin/settings', label: 'Настройки', icon: '⚙️' },
  { to: '/admin/projects', label: 'Все проекты', icon: '🗂️' },
]

const initials = computed(() => {
  const name = auth.user?.name ?? '?'
  return name
    .split(' ')
    .map((n) => n[0])
    .join('')
    .slice(0, 2)
    .toUpperCase()
})

function isActive(path: string) {
  return route.path === path || route.path.startsWith(path + '/')
}

async function handleLogout() {
  menuOpen.value = false
  await auth.logout()
  router.push({ name: 'login' })
}
</script>
