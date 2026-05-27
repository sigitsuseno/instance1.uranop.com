<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuth } from '../composables/useAuth'
import { useTheme } from '../composables/useTheme'
import {
  IconHome,
  IconBuilding,
  IconUsers,
  IconCalendarCheck,
  IconUmbrella,
  IconFileInvoice,
  IconGift,
  IconClock,
  IconChartBar,
  IconCog,
  IconUserClock,
  IconChevronDown,
  IconMoon,
  IconSun,
  IconLogOut,
} from '../Components/Icons/index.js'
import NotificationToast from '../Components/NotificationToast.vue'

const router = useRouter()
const route = useRoute()
const {
  user,
  userName,
  userRole,
  isSuperadmin,
  isHrmanager,
  canAccessAdmin,
  logout,
} = useAuth()
const { isDark, toggle } = useTheme()

const activeRole = ref(
  route.path.startsWith('/supervisor') ? 'supervisor' : 'admin'
)
const expanded = ref({})
const showUserMenu = ref(false)

watch(
  () => route.path,
  (path) => {
    if (isSuperadmin.value) {
      activeRole.value = path.startsWith('/supervisor') ? 'supervisor' : 'admin'
    }
  }
)

function switchRole(role) {
  activeRole.value = role
  expanded.value = {}
  router.push(role === 'supervisor' ? '/supervisor' : '/')
}

function toggleExpand(key) {
  expanded.value[key] = !expanded.value[key]
}

function isParentActive(children) {
  return children.some(
    (child) =>
      route.path === child.route || route.path.startsWith(child.route + '/')
  )
}

function onDocumentClick(e) {
  const dropdown = document.getElementById('user-dropdown')
  if (dropdown && !dropdown.contains(e.target)) {
    showUserMenu.value = false
  }
}

onMounted(() => document.addEventListener('click', onDocumentClick))
onUnmounted(() => document.removeEventListener('click', onDocumentClick))

async function handleLogout() {
  showUserMenu.value = false
  await logout()
  router.push('/login')
}

const pageTitle = computed(
  () => route.meta?.title || route.name || 'Dashboard'
)

const adminMenu = computed(() => {
  const items = [
    { label: 'Dashboard', icon: IconHome, route: '/' },
    {
      label: 'Organisasi',
      icon: IconBuilding,
      children: [
        { label: 'Departemen', route: '/organization/departments' },
        { label: 'Jabatan', route: '/organization/positions' },
        { label: 'Grade Gaji', route: '/organization/salary-grades' },
      ],
    },
    { label: 'Karyawan', icon: IconUsers, route: '/employees' },
    {
      label: 'Kehadiran',
      icon: IconCalendarCheck,
      children: [
        { label: 'Absensi', route: '/attendance' },
        { label: 'Import Log', route: '/attendance/import' },
        { label: 'Roster', route: '/attendance/roster' },
        { label: 'Lembur', route: '/attendance/overtime' },
      ],
    },
    {
      label: 'Cuti',
      icon: IconUmbrella,
      children: [
        { label: 'Daftar Cuti', route: '/leave' },
        { label: 'Approval', route: '/leave/approvals' },
        { label: 'Pengaturan', route: '/leave/settings' },
      ],
    },
    { label: 'Generate Gaji', icon: IconFileInvoice, route: '/payroll' },
    { label: 'THR', icon: IconGift, route: '/payroll/thr' },
    {
      label: 'Generate Jadwal',
      icon: IconClock,
      route: '/schedule/work-patterns',
    },
    { label: 'Laporan', icon: IconChartBar, route: '/reports' },
  ]

  if (isSuperadmin.value || isHrmanager.value) {
    items.push({ label: 'Pengaturan', icon: IconCog, route: '/settings' })
  }

  return items
})

const supervisorMenu = [
  { label: 'Dashboard', icon: IconHome, route: '/supervisor' },
  {
    label: 'Kehadiran',
    icon: IconCalendarCheck,
    route: '/supervisor/attendance',
  },
  {
    label: 'Roster',
    icon: IconUserClock,
    route: '/supervisor/attendance/roster',
  },
  {
    label: 'Generate Gaji',
    icon: IconFileInvoice,
    route: '/supervisor/payroll',
  },
  { label: 'THR', icon: IconGift, route: '/supervisor/payroll/thr' },
  { label: 'Cuti', icon: IconUmbrella, route: '/supervisor/leave' },
  { label: 'Karyawan', icon: IconUsers, route: '/supervisor/employee' },
  { label: 'Laporan', icon: IconChartBar, route: '/supervisor/reports' },
]

const sidebarMenu = computed(() => {
  if (isSuperadmin.value) {
    return activeRole.value === 'supervisor' ? supervisorMenu : adminMenu.value
  }
  if (canAccessAdmin.value) return adminMenu.value
  return supervisorMenu
})
</script>

<template>
  <div class="flex h-screen overflow-hidden bg-(--bg-main)">
    <aside
      class="fixed inset-y-0 left-0 z-30 flex w-64 flex-col bg-(--bg-sidebar) border-r border-(--border-soft)"
    >
      <div
        class="flex flex-col justify-center h-16 px-6 border-b border-(--border-soft) shrink-0"
      >
        <h1 class="text-lg font-semibold text-(--text-main)">Uranop</h1>
        <span class="text-xs text-(--text-muted)">Enterprise</span>
      </div>

      <div
        v-if="isSuperadmin"
        class="flex border-b border-(--border-soft) shrink-0"
      >
        <button
          @click="switchRole('admin')"
          :class="[
            'flex-1 px-3 py-2 text-xs font-medium transition-colors',
            activeRole === 'admin'
              ? 'text-(--primary) border-b-2 border-(--primary) bg-(--primary-glow)/10'
              : 'text-(--text-muted) hover:text-(--text-main)',
          ]"
        >
          Admin
        </button>
        <button
          @click="switchRole('supervisor')"
          :class="[
            'flex-1 px-3 py-2 text-xs font-medium transition-colors',
            activeRole === 'supervisor'
              ? 'text-(--primary) border-b-2 border-(--primary) bg-(--primary-glow)/10'
              : 'text-(--text-muted) hover:text-(--text-main)',
          ]"
        >
          Supervisor
        </button>
      </div>

      <nav class="flex-1 overflow-y-auto py-4 px-3 space-y-1">
        <template v-for="item in sidebarMenu" :key="item.label">
          <div v-if="item.children">
            <button
              @click="toggleExpand(item.label)"
              :class="[
                'flex items-center gap-3 px-3 py-2 rounded-md text-sm w-full transition-colors',
                isParentActive(item.children)
                  ? '!text-(--primary) bg-(--primary-glow)/10 font-medium'
                  : 'text-(--text-muted) hover:text-(--text-main) hover:bg-(--bg-elevated)',
              ]"
            >
              <component :is="item.icon" class="w-5 h-5 shrink-0" />
              <span class="text-left">{{ item.label }}</span>
              <IconChevronDown
                class="w-4 h-4 ml-auto shrink-0 transition-transform"
                :class="{ 'rotate-180': expanded[item.label] }"
              />
            </button>
            <div
              v-show="expanded[item.label]"
              class="ml-4 mt-1 space-y-1 border-l border-(--border-soft) pl-2"
            >
              <router-link
                v-for="child in item.children"
                :key="child.route"
                :to="child.route"
                active-class="!text-(--primary) bg-(--primary-glow)/10 font-medium"
                class="flex items-center gap-3 px-3 py-2 rounded-md text-sm text-(--text-muted) hover:text-(--text-main) hover:bg-(--bg-elevated) transition-colors"
              >
                {{ child.label }}
              </router-link>
            </div>
          </div>

          <router-link
            v-else
            :to="item.route"
            active-class="!text-(--primary) bg-(--primary-glow)/10 font-medium"
            class="flex items-center gap-3 px-3 py-2 rounded-md text-sm text-(--text-muted) hover:text-(--text-main) hover:bg-(--bg-elevated) transition-colors"
          >
            <component :is="item.icon" class="w-5 h-5 shrink-0" />
            <span>{{ item.label }}</span>
          </router-link>
        </template>
      </nav>

      <div class="p-4 border-t border-(--border-soft) shrink-0">
        <button
          @click="toggle()"
          class="flex items-center gap-3 px-3 py-2 rounded-md text-sm w-full text-(--text-muted) hover:text-(--text-main) hover:bg-(--bg-elevated) transition-colors"
        >
          <IconMoon v-if="!isDark" class="w-5 h-5 shrink-0" />
          <IconSun v-else class="w-5 h-5 shrink-0" />
          <span>{{ isDark ? 'Light Mode' : 'Dark Mode' }}</span>
        </button>
      </div>
    </aside>

    <div class="ml-64 flex-1 flex flex-col min-h-screen">
      <header
        class="h-16 bg-(--bg-card) border-b border-(--border-soft) px-6 flex items-center justify-between shrink-0"
      >
        <h2 class="text-lg font-semibold text-(--text-main) capitalize">
          {{ pageTitle }}
        </h2>

        <div id="user-dropdown" class="relative">
          <button
            @click.stop="showUserMenu = !showUserMenu"
            class="flex items-center gap-3 px-3 py-2 rounded-md text-sm text-(--text-main) hover:bg-(--bg-elevated) transition-colors"
          >
            <span class="font-medium">{{ userName }}</span>
            <span
              class="px-2 py-0.5 rounded text-xs font-medium bg-(--primary-glow)/10 text-(--primary)"
            >
              {{ userRole }}
            </span>
            <IconChevronDown
              class="w-4 h-4 transition-transform"
              :class="{ 'rotate-180': showUserMenu }"
            />
          </button>

          <Transition name="fade">
            <div
              v-if="showUserMenu"
              class="absolute right-0 mt-2 w-56 bg-(--bg-card) rounded-md shadow-lg border border-(--border-soft) py-1 z-50"
            >
              <div class="px-4 py-2 border-b border-(--border-soft)">
                <p class="text-sm font-medium text-(--text-main)">
                  {{ userName }}
                </p>
                <p
                  v-if="user?.email"
                  class="text-xs text-(--text-muted) truncate"
                >
                  {{ user.email }}
                </p>
              </div>
              <button
                @click="handleLogout"
                class="flex items-center gap-3 w-full px-4 py-2 text-sm text-(--text-muted) hover:text-red-500 hover:bg-(--bg-elevated) transition-colors"
              >
                <IconLogOut class="w-4 h-4" />
                <span>Logout</span>
              </button>
            </div>
          </Transition>
        </div>
      </header>

      <main class="p-6 flex-1 overflow-auto">
        <slot />
      </main>
    </div>

    <NotificationToast />
  </div>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.15s ease, transform 0.15s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
  transform: scale(0.95);
}
</style>
