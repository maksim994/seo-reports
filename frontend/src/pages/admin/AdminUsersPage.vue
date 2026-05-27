<template>
  <div>
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
      <h1 class="text-2xl font-semibold text-gray-900">Пользователи</h1>
      <input
        v-model="search"
        type="search"
        placeholder="Поиск по email или имени..."
        class="w-full rounded-lg border border-gray-200 px-4 py-2 text-sm outline-none focus:border-brand-500 sm:max-w-xs"
        @input="debouncedSearch"
      />
    </div>

    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
      <div v-if="admin.loading" class="p-8 text-center text-sm text-gray-500">Загрузка...</div>

      <table v-else class="w-full text-left text-sm">
        <thead class="border-b border-gray-200 bg-gray-50 text-xs uppercase text-gray-500">
          <tr>
            <th class="px-6 py-3">ID</th>
            <th class="px-6 py-3">Имя / Email</th>
            <th class="px-6 py-3">Проекты</th>
            <th class="px-6 py-3">Роль</th>
            <th class="px-6 py-3">Статус</th>
            <th class="px-6 py-3 text-right">Действия</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-for="user in admin.users" :key="user.id" class="hover:bg-gray-50">
            <td class="px-6 py-4 text-gray-500">{{ user.id }}</td>
            <td class="px-6 py-4">
              <div class="font-medium text-gray-900">{{ user.name }}</div>
              <div class="text-gray-500">{{ user.email }}</div>
            </td>
            <td class="px-6 py-4 text-gray-600">{{ user.projects_count ?? 0 }}</td>
            <td class="px-6 py-4">
              <span
                :class="[
                  'inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium',
                  user.is_admin ? 'bg-purple-50 text-purple-700' : 'bg-gray-100 text-gray-600',
                ]"
              >
                {{ user.is_admin ? 'Admin' : 'User' }}
              </span>
            </td>
            <td class="px-6 py-4">
              <span
                :class="[
                  'inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium',
                  user.is_blocked ? 'bg-red-50 text-red-700' : 'bg-green-50 text-green-700',
                ]"
              >
                {{ user.is_blocked ? 'Заблокирован' : 'Активен' }}
              </span>
            </td>
            <td class="px-6 py-4 text-right">
              <button class="mr-3 text-brand-600 hover:underline" @click="openEdit(user)">
                Изменить
              </button>
              <button
                v-if="user.id !== auth.user?.id"
                class="text-error-500 hover:underline"
                @click="remove(user.id)"
              >
                Удалить
              </button>
            </td>
          </tr>
        </tbody>
      </table>

      <div
        v-if="admin.usersMeta.last_page > 1"
        class="flex items-center justify-between border-t border-gray-200 px-6 py-4 text-sm text-gray-600"
      >
        <span>Всего: {{ admin.usersMeta.total }}</span>
        <div class="flex gap-2">
          <button
            :disabled="page <= 1"
            class="rounded-lg border border-gray-200 px-3 py-1 disabled:opacity-50"
            @click="changePage(page - 1)"
          >
            ←
          </button>
          <span>{{ page }} / {{ admin.usersMeta.last_page }}</span>
          <button
            :disabled="page >= admin.usersMeta.last_page"
            class="rounded-lg border border-gray-200 px-3 py-1 disabled:opacity-50"
            @click="changePage(page + 1)"
          >
            →
          </button>
        </div>
      </div>
    </div>

    <AppModal v-model="showModal" title="Редактирование пользователя">
      <form id="user-form" class="space-y-4" @submit.prevent="save">
        <div v-if="formError" class="rounded-lg bg-red-50 px-4 py-3 text-sm text-error-500">
          {{ formError }}
        </div>
        <div>
          <label class="mb-1.5 block text-sm font-medium text-gray-700">Имя</label>
          <input
            v-model="form.name"
            required
            class="w-full rounded-lg border border-gray-200 px-4 py-2.5 text-sm outline-none focus:border-brand-500"
          />
        </div>
        <label class="flex items-center gap-2 text-sm text-gray-700">
          <input v-model="form.is_admin" type="checkbox" class="rounded border-gray-300" />
          Администратор
        </label>
        <label class="flex items-center gap-2 text-sm text-gray-700">
          <input v-model="form.is_blocked" type="checkbox" class="rounded border-gray-300" />
          Заблокирован
        </label>
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
          form="user-form"
          :disabled="saving"
          class="rounded-lg bg-brand-500 px-4 py-2 text-sm font-medium text-white hover:bg-brand-600 disabled:opacity-50"
        >
          {{ saving ? 'Сохранение...' : 'Сохранить' }}
        </button>
      </template>
    </AppModal>
  </div>
</template>

<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import AppModal from '@/components/AppModal.vue'
import { useAdminStore } from '@/stores/admin'
import { useAuthStore } from '@/stores/auth'
import type { AxiosError } from 'axios'
import type { ApiError, User } from '@/types'

const admin = useAdminStore()
const auth = useAuthStore()
const search = ref('')
const page = ref(1)
const showModal = ref(false)
const saving = ref(false)
const formError = ref('')
const editingId = ref<number | null>(null)

const form = reactive({
  name: '',
  is_admin: false,
  is_blocked: false,
})

let searchTimer: ReturnType<typeof setTimeout> | null = null

function debouncedSearch() {
  if (searchTimer) clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    page.value = 1
    loadUsers()
  }, 300)
}

async function loadUsers() {
  const params: Record<string, string | number | boolean> = { page: page.value }
  if (search.value) {
    params.search = search.value
  }
  await admin.fetchUsers(params)
}

function changePage(next: number) {
  page.value = next
  loadUsers()
}

function openEdit(user: User) {
  editingId.value = user.id
  form.name = user.name
  form.is_admin = user.is_admin
  form.is_blocked = user.is_blocked
  formError.value = ''
  showModal.value = true
}

async function save() {
  if (!editingId.value) return
  formError.value = ''
  saving.value = true
  try {
    await admin.updateUser(editingId.value, { ...form })
    showModal.value = false
  } catch (e) {
    const err = e as AxiosError<ApiError>
    formError.value = err.response?.data?.message ?? 'Не удалось сохранить'
  } finally {
    saving.value = false
  }
}

async function remove(id: number) {
  if (!confirm('Удалить пользователя?')) return
  try {
    await admin.deleteUser(id)
  } catch (e) {
    const err = e as AxiosError<ApiError>
    alert(err.response?.data?.message ?? 'Не удалось удалить')
  }
}

onMounted(loadUsers)
</script>
