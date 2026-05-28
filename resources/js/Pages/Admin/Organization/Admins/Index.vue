<script setup>
import { ref } from 'vue'
import BaseCard from '../../../../Components/BaseCard.vue'
import UsersTab from './Partials/UsersTab.vue'
import RolesTab from './Partials/RolesTab.vue'
import PermissionsTab from './Partials/PermissionsTab.vue'
import AssignTab from './Partials/AssignTab.vue'

const activeTab = ref('users')

const tabs = [
  { id: 'users', name: 'User Baru' },
  { id: 'roles', name: 'Roles' },
  { id: 'permissions', name: 'Permissions' },
  { id: 'assign', name: 'Assign Role & Permission' },
]
</script>

<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-2xl font-bold text-(--text-main)">Pengaturan Admin</h1>
      <p class="text-sm text-(--text-muted) mt-1">Kelola user baru, role, permission, dan alokasi hak akses.</p>
    </div>

    <BaseCard noPadding>
      <div class="border-b border-(--border-soft)">
        <nav class="flex -mb-px">
          <button
            v-for="tab in tabs"
            :key="tab.id"
            @click="activeTab = tab.id"
            :class="[
              'py-2 px-4 text-sm font-medium border-b-2 transition-colors duration-200 whitespace-nowrap',
              activeTab === tab.id
                ? 'border-(--primary) text-(--primary)'
                : 'border-transparent text-(--text-muted) hover:text-(--text-main) hover:border-(--border-hard)'
            ]"
          >
            {{ tab.name }}
          </button>
        </nav>
      </div>

      <div class="mt-4">
        <UsersTab v-if="activeTab === 'users'" />
        <RolesTab v-else-if="activeTab === 'roles'" />
        <PermissionsTab v-else-if="activeTab === 'permissions'" />
        <AssignTab v-else-if="activeTab === 'assign'" />
      </div>
    </BaseCard>
  </div>
</template>
