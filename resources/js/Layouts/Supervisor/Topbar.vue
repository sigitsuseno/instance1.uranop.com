<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuth } from '../../composables/useAuth'
import { useApi } from '../../composables/useApi'
import Dropdown from '../../Components/Dropdown.vue'

const props = defineProps({
  title: String,
  isDark: Boolean,
  sidebarCollapsed: Boolean,
})

const emit = defineEmits(['toggle-sidebar', 'toggle-theme'])

const router = useRouter()
const auth = useAuth()
const { user, userName, userRole, isSuperadmin, isManajemen, logout } = auth
const { get } = useApi()

const consecutiveNotifications = ref([])
const consecutiveSaturday = ref('')
const isLoadingNotifications = ref(false)

const notificationCount = computed(() => consecutiveNotifications.value.length)
const notificationBadge = computed(() => (notificationCount.value > 99 ? '99+' : String(notificationCount.value)))
const formattedSaturday = computed(() => {
  if (!consecutiveSaturday.value) return ''
  return new Date(`${consecutiveSaturday.value}T00:00:00`).toLocaleDateString('id-ID', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  })
})

async function fetchConsecutiveNotifications() {
  isLoadingNotifications.value = true
  try {
    const response = await get('/api/v1/supervisor/attendance/consecutive-notifications')
    consecutiveNotifications.value = response.notifications || []
    consecutiveSaturday.value = response.saturday || ''
  } catch (error) {
    console.error('Gagal memuat notifikasi consecutive day:', error)
  } finally {
    isLoadingNotifications.value = false
  }
}

onMounted(fetchConsecutiveNotifications)

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
  <header class="h-14 bg-(--bg-sidebar)/90 backdrop-blur-md border-b border-(--border-soft) flex items-center justify-between pl-12 pr-6 sticky top-0 z-20 transition-colors duration-300">
    <div class="flex items-center">
      <button
        @click="emit('toggle-sidebar')"
        class="lg:hidden mr-4 text-(--text-muted) hover:text-(--primary) transition-colors"
      >
        <i class="bx bx-menu text-2xl"></i>
      </button>

      <h1 class="text-xl font-semibold text-(--text-main)">{{ title }}</h1>

      <template v-if="isSuperadmin || isManajemen">
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

      <Dropdown position="right" width="w-96" :closeOnClickOutside="true" @open="fetchConsecutiveNotifications">
        <template #trigger="{ toggle, isOpen }">
          <button
            @click.stop="toggle"
            class="w-10 h-10 rounded-md bg-(--bg-elevated) text-(--text-muted) hover:text-(--primary) flex items-center justify-center transition-colors relative"
          >
            <i class="bx bx-bell"></i>
            <span
              v-if="notificationCount > 0"
              class="absolute -top-1 -right-1 min-w-[18px] h-[18px] px-1 rounded-full bg-(--danger) text-white text-[10px] font-bold flex items-center justify-center"
            >
              {{ notificationBadge }}
            </span>
          </button>
        </template>

        <template #content="{ close }">
          <div @click.stop>
            <div class="px-4 py-3 border-b border-(--border-soft) flex items-center justify-between">
              <span class="text-sm font-medium text-(--text-main)">Notifikasi</span>
              <span v-if="notificationCount > 0" class="text-xs text-(--text-muted)">
                {{ notificationCount }} karyawan
              </span>
            </div>
            <div v-if="formattedSaturday" class="px-4 py-2 text-[11px] text-(--text-muted) border-b border-(--border-soft)">
              Consecutive day — {{ formattedSaturday }}
            </div>
            <div class="max-h-96 overflow-y-auto">
              <div v-if="isLoadingNotifications" class="px-4 py-6 text-center text-xs text-(--text-muted)">
                Memuat notifikasi...
              </div>
              <template v-else-if="notificationCount > 0">
                <div
                  v-for="notification in consecutiveNotifications"
                  :key="notification.employee_id"
                  class="px-4 py-3 border-b border-(--border-soft) last:border-b-0 flex items-start gap-3"
                >
                  <i class="bx bx-calendar-check text-lg text-amber-500 mt-0.5 shrink-0"></i>
                  <p class="text-xs text-(--text-main) leading-relaxed">{{ notification.message }}</p>
                </div>
              </template>
              <div v-else class="px-4 py-6 text-center text-xs text-(--text-muted)">
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
