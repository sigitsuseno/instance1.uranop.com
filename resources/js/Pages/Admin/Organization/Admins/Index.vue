<script setup>
import { ref } from 'vue'
import BaseCard from '../../../../Components/BaseCard.vue'
import UsersTab from './Partials/UsersTab.vue'
import RolesTab from './Partials/RolesTab.vue'
import PermissionsTab from './Partials/PermissionsTab.vue'
import AssignTab from './Partials/AssignTab.vue'

const activeTab = ref('users')

const tabs = [
  { id: 'users', name: 'User Baru', icon: 'bx-user-plus' },
  { id: 'roles', name: 'Roles', icon: 'bx-shield-quarter' },
  { id: 'permissions', name: 'Permissions', icon: 'bx-key' },
  { id: 'assign', name: 'Assign Role & Permission', icon: 'bx-link-alt' },
]
</script>

<template>
  <div class="space-y-6">
    <!-- Header Section -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div class="flex items-center gap-4">
        <div class="w-12 h-12 rounded-xl bg-(--primary)/10 flex items-center justify-center text-(--primary) shadow-sm">
          <i class="bx bx-user-pin text-2xl"></i>
        </div>
        <div>
          <h1 class="text-2xl font-bold text-(--text-main)">Pengaturan Admin</h1>
          <p class="text-sm text-(--text-muted) mt-1">Kelola user baru, role, permission, dan alokasi hak akses sistem</p>
        </div>
      </div>
    </div>

    <BaseCard padding="p-0" class="overflow-hidden border-(--border-soft) shadow-sm">
      <div class="border-b border-(--border-soft) bg-(--bg-card)">
        <nav class="flex overflow-x-auto custom-scrollbar px-2 pt-2">
          <button
            v-for="tab in tabs"
            :key="tab.id"
            @click="activeTab = tab.id"
            class="group flex items-center gap-2 py-3 px-5 text-sm font-medium border-b-2 transition-all duration-300 whitespace-nowrap focus:outline-none"
            :class="[
              activeTab === tab.id
                ? 'border-(--primary) text-(--primary)'
                : 'border-transparent text-(--text-muted) hover:text-(--text-main) hover:bg-(--bg-elevated)/50 rounded-t-lg'
            ]"
          >
            <i :class="['bx text-lg', tab.icon, activeTab === tab.id ? 'text-(--primary)' : 'text-(--text-soft) group-hover:text-(--text-muted)']"></i>
            {{ tab.name }}
          </button>
        </nav>
      </div>

      <div class="p-6 bg-(--bg-card)">
        <Transition name="fade" mode="out-in">
          <KeepAlive>
            <component :is="activeTab === 'users' ? UsersTab : 
                          activeTab === 'roles' ? RolesTab : 
                          activeTab === 'permissions' ? PermissionsTab : 
                          AssignTab" />
          </KeepAlive>
        </Transition>
      </div>
    </BaseCard>
  </div>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease, transform 0.2s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
  transform: translateY(4px);
}

.custom-scrollbar::-webkit-scrollbar {
  height: 4px;
}
.custom-scrollbar::-webkit-scrollbar-track {
  background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
  background-color: var(--border-soft);
  border-radius: 20px;
}
</style>
