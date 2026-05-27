<script setup>
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuth } from '../../composables/useAuth'
import Dropdown from '../../Components/Dropdown.vue'

const props = defineProps({
  title: String,
  isDark: Boolean,
  sidebarCollapsed: Boolean,
})

const emit = defineEmits(['toggle-sidebar', 'toggle-theme'])

const router = useRouter()
const auth = useAuth()
const { user, userName, userRole, isSuperadmin, logout } = auth

const userInitial = computed(() => (user.value?.name?.charAt(0) || 'U'))

function switchToAdmin() {
  router.push('/admin')
}

async function handleLogout() {
  await logout()
  router.push('/login')
}
</script>

<template>
  <header class="h-14 bg-(--bg-sidebar) flex items-center justify-between pl-12 pr-6 sticky top-0 z-20">
    <div class="flex items-center">
      <button
        @click="emit('toggle-sidebar')"
        class="lg:hidden mr-4 text-(--text-muted) hover:text-(--primary) transition-colors"
      >
        <i class="bx bx-menu text-2xl"></i>
      </button>

      <h1 class="text-xl font-semibold text-(--text-main)">{{ title }}</h1>

      <template v-if="isSuperadmin">
        <span class="text-(--text-soft) mx-2">|</span>
        <div class="flex rounded-md bg-(--bg-elevated) p-0.5">
          <button
            @click="switchToAdmin"
            class="px-3 py-1 rounded-md text-xs font-medium text-(--text-muted) hover:text-(--text-main) transition-all"
          >
            Admin
          </button>
          <div class="px-3 py-1 rounded-md text-xs font-medium bg-(--primary) text-white shadow">
            Supervisor
          </div>
        </div>
      </template>
    </div>

    <div class="flex items-center space-x-4">
      <button
        @click="emit('toggle-theme')"
        class="w-10 h-10 rounded-md bg-(--bg-elevated) text-(--text-muted) hover:text-(--primary) flex items-center justify-center transition-colors"
      >
        <i :class="isDark ? 'bx bx-sun' : 'bx bx-moon'"></i>
      </button>

      <Dropdown position="right" width="w-80" :closeOnClickOutside="true">
        <template #trigger="{ toggle, isOpen }">
          <button
            @click.stop="toggle"
            class="w-10 h-10 rounded-md bg-(--bg-elevated) text-(--text-muted) hover:text-(--primary) flex items-center justify-center transition-colors relative"
          >
            <i class="bx bx-bell"></i>
          </button>
        </template>

        <template #content="{ close }">
          <div @click.stop>
            <div class="px-4 py-3 border-b border-(--border-soft)">
              <span class="text-sm font-medium text-(--text-main)">Notifikasi</span>
            </div>
            <div class="max-h-96 overflow-y-auto">
              <div class="px-4 py-6 text-center text-xs text-(--text-muted)">
                Tidak ada notifikasi baru
              </div>
            </div>
          </div>
        </template>
      </Dropdown>

      <Dropdown position="right" width="w-64" :closeOnClickOutside="true">
        <template #trigger="{ toggle, isOpen }">
          <button
            @click.stop="toggle"
            class="flex items-center space-x-3 p-2 rounded-md hover:bg-(--bg-elevated) transition-colors"
          >
            <div class="w-8 h-8 bg-(--primary) rounded-md flex items-center justify-center text-white font-semibold">
              {{ userInitial }}
            </div>
            <div class="hidden md:block text-left">
              <div class="text-sm font-medium text-(--text-main)">{{ userName }}</div>
              <div class="text-xs text-(--text-muted)">{{ userRole }}</div>
            </div>
            <i class="bx bx-chevron-down text-(--text-muted)" :class="{ 'rotate-180': isOpen }"></i>
          </button>
        </template>

        <template #content="{ close }">
          <div @click.stop>
            <div class="px-4 py-3 border-b border-(--border-soft)">
              <div class="text-sm font-medium text-(--text-main)">{{ userName }}</div>
              <div class="text-xs text-(--text-muted) mt-1">{{ user?.email }}</div>
            </div>

            <div class="border-t border-(--border-soft) my-2"></div>

            <button @click.stop="close(); handleLogout()" class="flex items-center w-full px-4 py-2 text-sm text-\(--danger\) hover:bg-(--bg-elevated) transition-colors">
              <i class="bx bx-log-out mr-3 text-lg"></i>
              Logout
            </button>
          </div>
        </template>
      </Dropdown>
    </div>
  </header>
</template>

<style scoped>
.rotate-180 {
  transform: rotate(180deg);
  transition: transform 0.2s ease;
}
</style>
