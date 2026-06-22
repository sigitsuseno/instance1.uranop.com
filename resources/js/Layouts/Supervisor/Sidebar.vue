<script setup>
import { ref, watch, computed } from 'vue'
import { useRoute } from 'vue-router'
import { useAuth } from '../../composables/useAuth'

const props = defineProps({
  collapsed: Boolean,
})

const emit = defineEmits(['toggle'])

const route = useRoute()

const { isSuperadmin, isHrmanager, isAdmManager } = useAuth()

const supervisorMenus = [
  {
    title: 'Dashboard',
    icon: 'bx bx-home',
    route: '/supervisor',
    visible: true,
  },
  {
    title: 'Data Master',
    icon: 'bx bx-server',
    route: '/supervisor/master',
  },
  {
    title: 'Data Karyawan',
    icon: 'bx bx-user',
    children: [
      { title: 'Karyawan', icon: 'bx bx-group', route: '/supervisor/employee-data/karyawan' },
      { title: 'Kontrak Kerja', icon: 'bx bx-file-blank', route: '/supervisor/employee-data/kontrak-kerja' },
      { title: 'Gaji Karyawan', icon: 'bx bx-money', route: '/supervisor/employee-data/gaji-karyawan' },
      { title: 'Kompensasi', icon: 'bx bx-gift', route: '/supervisor/employee-data/kompensasi' },
      { title: 'BPJS Karyawan', icon: 'bx bx-shield-plus', route: '/supervisor/employee-data/bpjs-karyawan' },
      { title: 'PPh Karyawan', icon: 'bx bx-receipt', route: '/supervisor/employee-data/pph-karyawan' },
    ],
  },
  {
    title: 'Jadwal Kerja',
    icon: 'bx bx-calendar-event',
    children: [
      { title: 'Jadwal Umum', icon: 'bx bx-calendar', route: '/supervisor/schedule/roster' },
      { title: 'Pola & Jadwal Kerja', icon: 'bx bx-time-five', route: '/supervisor/schedule/work-patterns' },
      { title: 'Shift', icon: 'bx bx-transfer-alt', route: '/supervisor/schedule/shifts' },
      { title: 'Buat Jadwal', icon: 'bx bx-calendar-plus', route: '/supervisor/schedule/roster/generate' },
    ],
  },
  {
    title: 'Pengelolaan Cuti',
    icon: 'bx bx-umbrella',
    route: '/supervisor/leave',
  },
  {
    title: 'Absensi',
    icon: 'bx bx-calendar-check',
    children: [
      { title: 'Import', icon: 'bx bx-upload', route: '/supervisor/attendance/import' },
      { title: 'Sync Kehadiran', icon: 'bx bx-sync', route: '/supervisor/attendance/sync' },
      { title: 'Data Absensi', icon: 'bx bx-file', route: '/supervisor/attendance' },
      { title: 'Consecutive Day', icon: 'bx bx-calendar-star', route: '/supervisor/attendance/consecutive' },
      { title: 'Roster', icon: 'bx bx-calendar', route: '/supervisor/attendance/roster' },
      { title: 'Lembur Staf', icon: 'bx bx-time', route: '/supervisor/attendance/overtime' },
      { title: 'Rekap Absensi', icon: 'bx bx-table', route: '/supervisor/attendance/recap' },
    ],
  },
  {
    title: 'Penggajian',
    icon: 'bx bx-money',
    children: [
      { title: 'Gaji Karyawan', icon: 'bx bx-cog', route: '/supervisor/payroll' },
      { title: 'Slip Gaji', icon: 'bx bx-file', route: '/supervisor/payroll/slip' },
      { title: 'Perhitungan THR', icon: 'bx bx-gift', route: '/supervisor/payroll/thr' },
    ],
  },
  {
    title: 'Laporan',
    icon: 'bx bxs-report',
    route: '/supervisor/reports',
  },
]

const menuItems = computed(() => {
  return supervisorMenus
    .filter(parent => parent.visible !== false)
    .map(parent => {
      if (parent.children) {
        const filteredChildren = parent.children.filter(child => child.visible !== false)
        if (filteredChildren.length === 0) return null
        return { ...parent, children: filteredChildren }
      }
      return parent
    })
    .filter(parent => parent !== null)
})

const openMenus = ref({})

function toggleMenu(menuTitle) {
  if (props.collapsed) return
  openMenus.value[menuTitle] = !openMenus.value[menuTitle]
}

function isMenuOpen(menuTitle) {
  return openMenus.value[menuTitle] || false
}

function isActive(url) {
  if (!url || url === '#') return false
  const currentPath = route.path
  if (currentPath === url) return true
  if (url === '/supervisor') return currentPath === url
  if (currentPath.startsWith(url + '/')) return true
  return false
}

function hasActiveChild(children) {
  if (!children) return false
  return children.some(child => isActive(child.route))
}

watch(
  () => route.path,
  () => {
    menuItems.value.forEach(item => {
      if (item.children && hasActiveChild(item.children)) {
        openMenus.value[item.title] = true
      }
    })
  },
  { immediate: true }
)
</script>

<template>
  <aside
    :class="[
      'fixed left-0 top-0 h-full bg-(--bg-sidebar) z-30 border-r border-(--border-soft)',
      'transition-all duration-300',
      collapsed ? 'w-20' : 'w-55',
    ]"
  >
    <div class="h-16 flex items-center px-4 sticky top-0 bg-(--bg-sidebar) z-10">
      <div
        :class="[
          'flex items-center w-full transition-all duration-300',
          collapsed ? 'justify-center' : 'justify-start',
        ]"
      >
        <div class="w-10 h-10 bg-(--primary) rounded-md flex items-center justify-center font-black text-white shadow-lg shrink-0">
          U
        </div>
        <transition name="fade">
          <span v-show="!collapsed" class="ml-3 text-lg font-bold text-(--text-main) whitespace-nowrap">
            Uranop
          </span>
        </transition>
      </div>
    </div>

    <div :class="collapsed ? 'h-8' : ''"></div>

    <nav
      :class="[
        'px-4 pb-4 space-y-1',
        collapsed ? '' : 'h-[calc(100vh-80px)] overflow-y-auto',
      ]"
    >
      <template v-for="item in menuItems" :key="item.title">
        <div v-if="item.children">
          <button
            @click="toggleMenu(item.title)"
            class="relative group w-full h-10 flex items-center justify-start px-3 rounded-md transition-all duration-200"
            :class="[
              isMenuOpen(item.title) || hasActiveChild(item.children)
                ? 'bg-(--primary)/10 text-(--primary) font-semibold shadow-[inset_3px_0_0_0_var(--primary)]'
                : 'text-(--text-muted) hover:bg-(--bg-elevated) hover:text-(--text-main)',
            ]"
          >
            <i :class="[item.icon, 'text-xl', collapsed ? 'mx-auto' : 'mr-3']"></i>
            <span v-show="!collapsed" class="text-sm font-medium flex-1 text-left">{{ item.title }}</span>
            <i
              v-show="!collapsed"
              :class="[
                'bx text-sm transition-transform duration-200',
                isMenuOpen(item.title) ? 'bx-chevron-down rotate-180' : 'bx-chevron-right',
              ]"
            ></i>

            <div
              v-if="collapsed"
              class="absolute left-full ml-3 top-1/2 -translate-y-1/2 px-3 py-1 rounded-md text-sm bg-(--text-main) text-(--bg-main) shadow-lg opacity-0 group-hover:opacity-100 scale-95 group-hover:scale-100 transition-all duration-200 whitespace-nowrap z-50 pointer-events-none"
            >
              {{ item.title }}
            </div>
          </button>

          <div
            v-show="!collapsed && isMenuOpen(item.title)"
            class="mt-1 ml-4 space-y-1 border-l border-(--border-soft) pl-2"
          >
            <router-link
              v-for="child in item.children"
              :key="child.title"
              :to="child.route !== '#' ? child.route : ''"
              :class="[
                'flex items-center px-3 py-2 rounded-md text-xs transition-all duration-200',
                child.route === '#'
                  ? 'opacity-40 pointer-events-none text-(--text-muted)'
                  : isActive(child.route)
                    ? 'bg-(--primary)/10 text-(--primary) font-semibold shadow-[inset_3px_0_0_0_var(--primary)]'
                    : 'text-(--text-muted) hover:bg-(--bg-elevated) hover:text-(--text-main)',
              ]"
              @click.prevent="child.route === '#' ? null : undefined"
            >
              <i :class="[child.icon, 'mr-3 text-lg']"></i>
              {{ child.title }}
            </router-link>
          </div>
        </div>

        <router-link
          v-else
          :to="item.route"
          class="relative group w-full h-10 flex items-center justify-start px-3 rounded-md transition-all duration-200"
          :class="
            isActive(item.route)
              ? 'bg-(--primary)/10 text-(--primary) font-semibold shadow-[inset_3px_0_0_0_var(--primary)]'
              : 'text-(--text-muted) hover:bg-(--bg-elevated) hover:text-(--text-main)'
          "
        >
          <i :class="[item.icon, 'text-xl', collapsed ? 'mx-auto' : 'mr-3']"></i>
          <span v-show="!collapsed" class="text-sm font-medium">{{ item.title }}</span>
          <div
            v-if="collapsed"
            class="absolute left-full ml-3 top-1/2 -translate-y-1/2 px-3 py-1 rounded-md text-sm bg-(--text-main) text-(--bg-main) shadow-lg opacity-0 group-hover:opacity-100 scale-95 group-hover:scale-100 transition-all duration-200 whitespace-nowrap z-50 pointer-events-none"
          >
            {{ item.title }}
          </div>
        </router-link>
      </template>
    </nav>

    <button
      @click="emit('toggle')"
      class="absolute -right-8 top-3.5 w-6 h-6 bg-(--primary) border rounded-full shadow flex items-center justify-center z-50"
    >
      <i :class="collapsed ? 'bx bx-chevron-right' : 'bx bx-chevron-left'" class="text-white"></i>
    </button>
  </aside>
</template>
