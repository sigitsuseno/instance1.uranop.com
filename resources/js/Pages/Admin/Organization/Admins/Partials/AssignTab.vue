<template>
  <div class="space-y-4">
    <div class="flex items-center justify-between">
      <div>
        <h2 class="text-lg font-semibold text-(--text-main)">Assign Role & Permission</h2>
        <p class="text-sm text-(--text-muted)">Berikan role atau permission spesifik kepada user</p>
      </div>
    </div>

    <div class="flex gap-4">
      <div class="relative w-64">
        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-(--text-muted)">
          <IconSearch class="w-4 h-4" />
        </div>
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Cari user..."
          @keyup.enter="fetchUsers()"
          class="w-full pl-10 pr-3 py-2 text-sm rounded-md border border-(--border-soft) bg-(--bg-card) text-(--text-main) placeholder:text-(--text-soft) focus:outline-none focus:ring-2 focus:ring-(--primary)/25 focus:border-(--primary) transition-colors"
        />
      </div>
      <BaseButton variant="secondary" @click="fetchUsers()">Cari</BaseButton>
    </div>

    <div v-if="loading" class="p-6 flex justify-center">
      <span class="loading loading-spinner text-(--primary)"></span>
    </div>

    <DataTable v-else :headers="headers" :items="users">
      <template #item.roles="{ value }">
        <div class="flex gap-1 flex-wrap">
          <Badge v-for="role in value" :key="role.id" variant="primary" class="text-xs">
            {{ role.name }}
          </Badge>
          <span v-if="!value || value.length === 0" class="text-xs text-(--text-muted)">-</span>
        </div>
      </template>
      <template #item.permissions="{ value }">
        <div class="flex gap-1 flex-wrap max-w-xs">
          <Badge v-for="perm in value" :key="perm.id" variant="secondary" class="text-xs">
            {{ perm.name }}
          </Badge>
          <span v-if="!value || value.length === 0" class="text-xs text-(--text-muted)">-</span>
        </div>
      </template>
      <template #item.aksi="{ item }">
        <div class="flex items-center gap-1 justify-center">
          <BaseButton variant="secondary" class="!py-1 !px-2 !text-xs" @click="openAssignModal(item)">Ubah Akses</BaseButton>
        </div>
      </template>
    </DataTable>

    <div v-if="totalPages > 1" class="pt-4">
      <Pagination :current-page="currentPage" :total-pages="totalPages" @page-change="fetchUsers" />
    </div>

    <!-- Assign Modal -->
    <BaseModal :show="modalOpen" :title="'Ubah Akses: ' + selectedUser?.name" @close="closeModal">
      <form @submit.prevent="handleSave" class="space-y-6">
        <div>
          <h3 class="font-semibold text-(--text-main) mb-2">Roles</h3>
          <div v-if="loadingOptions" class="text-sm text-(--text-muted)">Memuat roles...</div>
          <div v-else class="grid grid-cols-2 gap-2 max-h-40 overflow-y-auto p-2 border border-(--border-soft) rounded-md bg-(--bg-elevated)">
            <label v-for="role in allRoles" :key="role.id" class="flex items-center gap-2 cursor-pointer p-1 hover:bg-(--bg-card) rounded">
              <input type="checkbox" :value="role.name" v-model="form.roles" class="rounded text-(--primary) focus:ring-(--primary) border-(--border-soft)" />
              <span class="text-sm text-(--text-main)">{{ role.name }}</span>
            </label>
          </div>
        </div>

        <div>
          <h3 class="font-semibold text-(--text-main) mb-2">Direct Permissions</h3>
          <div v-if="loadingOptions" class="text-sm text-(--text-muted)">Memuat permissions...</div>
          <div v-else class="grid grid-cols-2 gap-2 max-h-40 overflow-y-auto p-2 border border-(--border-soft) rounded-md bg-(--bg-elevated)">
            <label v-for="perm in allPermissions" :key="perm.id" class="flex items-center gap-2 cursor-pointer p-1 hover:bg-(--bg-card) rounded">
              <input type="checkbox" :value="perm.name" v-model="form.permissions" class="rounded text-(--primary) focus:ring-(--primary) border-(--border-soft)" />
              <span class="text-sm text-(--text-main)">{{ perm.name }}</span>
            </label>
          </div>
          <p class="text-xs text-(--text-muted) mt-1">Permission tambahan di luar role (spesifik untuk user ini).</p>
        </div>
      </form>
      <template #footer>
        <BaseButton variant="ghost" @click="closeModal">Batal</BaseButton>
        <BaseButton variant="primary" @click="handleSave" :disabled="saving">
          <span v-if="saving" class="loading loading-spinner loading-sm mr-2"></span>
          Simpan Akses
        </BaseButton>
      </template>
    </BaseModal>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useApi } from '../../../../../composables/useApi'
import { useNotificationStore } from '../../../../../Stores/notification'

import BaseButton from '../../../../../Components/BaseButton.vue'
import BaseModal from '../../../../../Components/BaseModal.vue'
import DataTable from '../../../../../Components/Table/DataTable.vue'
import Badge from '../../../../../Components/Badge.vue'
import Pagination from '../../../../../Components/Table/Pagination.vue'
import { IconSearch } from '../../../../../Components/Icons/index.js'

const { get, post } = useApi()
const notificationStore = useNotificationStore()

const searchQuery = ref('')
const loading = ref(true)
const saving = ref(false)
const loadingOptions = ref(false)
const users = ref([])

const allRoles = ref([])
const allPermissions = ref([])

const currentPage = ref(1)
const totalPages = ref(1)

const modalOpen = ref(false)
const selectedUser = ref(null)
const errors = ref({})

const headers = [
  { key: 'name', label: 'User' },
  { key: 'email', label: 'Email' },
  { key: 'roles', label: 'Roles' },
  { key: 'permissions', label: 'Direct Permissions' },
  { key: 'aksi', label: 'Aksi', sortable: false, align: 'center' },
]

const form = ref({
  roles: [],
  permissions: [],
})

const fetchUsers = async (page = 1) => {
  loading.value = true
  try {
    const response = await get(`/api/admins?page=${page}&search=${searchQuery.value}`)
    users.value = response.data
    currentPage.value = response.current_page || 1
    totalPages.value = response.last_page || 1
  } catch (error) {
    notificationStore.addNotification('Gagal mengambil data user', 'error')
  } finally {
    loading.value = false
  }
}

const fetchOptions = async () => {
  if (allRoles.value.length > 0 && allPermissions.value.length > 0) return
  
  loadingOptions.value = true
  try {
    const [rolesRes, permsRes] = await Promise.all([
      get('/api/roles?all=1'),
      get('/api/permissions?all=1')
    ])
    allRoles.value = rolesRes.data
    allPermissions.value = permsRes.data
  } catch (error) {
    notificationStore.addNotification('Gagal memuat roles & permissions', 'error')
  } finally {
    loadingOptions.value = false
  }
}

async function openAssignModal(user) {
  selectedUser.value = user
  form.value.roles = user.roles ? user.roles.map(r => r.name) : []
  form.value.permissions = user.permissions ? user.permissions.map(p => p.name) : []
  modalOpen.value = true
  
  await fetchOptions()
}

function closeModal() {
  modalOpen.value = false
  selectedUser.value = null
  form.value.roles = []
  form.value.permissions = []
}

async function handleSave() {
  saving.value = true
  try {
    await post(`/api/admins/${selectedUser.value.id}/sync-roles`, form.value)
    notificationStore.addNotification('Akses berhasil diperbarui', 'success')
    closeModal()
    fetchUsers(currentPage.value)
  } catch (error) {
    notificationStore.addNotification('Gagal memperbarui akses', 'error')
  } finally {
    saving.value = false
  }
}

onMounted(() => {
  fetchUsers()
})
</script>
