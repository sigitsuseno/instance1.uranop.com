<script setup>
import { ref, watch, computed } from 'vue'
import { useRoute } from 'vue-router'
import { useAuth } from '../../composables/useAuth'

const props = defineProps({
  collapsed: Boolean,
})

const emit = defineEmits(['toggle'])

const route = useRoute()
const { isSuperadmin, isHrmanager, isAdmManager, isHrbranch, isHrAst } = useAuth()

const allMenus = [
  {
    title: 'Dashboard',
    icon: 'bx bx-home',
    route: '/admin',
    visible: true,
  },
  {
    title: 'Data Master',
    icon: 'bx bx-data',
    children: [
      { title: 'Bagian', icon: 'bx bx-folder-open', route: '/admin/organization/departments' },
      { title: 'Pekerjaan', icon: 'bx bx-briefcase', route: '/admin/organization/positions' },
      { title: 'Kalender', icon: 'bx bx-calendar', route: '/admin/schedule/calendars' },
      { title: 'Gaji & LTHR', icon: 'bx bx-money', route: '/admin/payroll/configs' },
      { title: 'Penggajian', icon: 'bx bx-time', route: '/admin/payroll/payroll-periode' },
      { title: 'Pengaturan Cuti', icon: 'bx bx-cog', route: '/admin/leave/settings', visible: isSuperadmin.value || isHrmanager.value },
      { title: 'PPh 21', icon: 'bx bx-receipt', route: '/admin/payroll/configs' },
      { title: 'BPJS', icon: 'bx bx-health', route: '/admin/payroll/configs' },
    ],
  },
  {
    title: 'Perusahaan',
    icon: 'bx bx-building-house',
    visible: isSuperadmin.value || isHrmanager.value,
    children: [
      { title: 'Profil Perusahaan', icon: 'bx bx-building', route: '/admin/organization/company-profile' },
      { title: 'Admin', icon: 'bx bx-user-pin', route: '/admin/organization/admins', visible: isSuperadmin.value },
    ],
  },
  {
    title: 'Data Karyawan',
    icon: 'bx bx-group',
    children: [
      { title: 'Karyawan', icon: 'bx bx-user', route: '/admin/employees' },
      { title: 'Import Karyawan', icon: 'bx bx-upload', route: '/admin/employees/import' },
      { title: 'Grouping Karyawan', icon: 'bx bx-layer', route: '/admin/employees/grouping' },
      { title: 'Gaji Karyawan', icon: 'bx bx-money', route: '/admin/employees/salaries' },
      { title: 'Kontrak Kerja', icon: 'bx bx-file', route: '/admin/employees/contracts' },
      { title: 'Kompensasi', icon: 'bx bx-money-withdraw', route: '/admin/employees/kompensasi' },
      { title: 'Riwayat Pekerjaan', icon: 'bx bx-history', route: '/admin/employees/position-histories' },
      { title: 'Keluarga & Tanggungan', icon: 'bx bx-heart', route: '/admin/employees/families' },
      { title: 'Dokumen', icon: 'bx bx-folder', route: '/admin/employees/documents' },
      { title: 'Resign & PHK', icon: 'bx bx-exit', route: '/admin/employees/terminations' },
    ],
  },
  {
    title: 'Jadwal Kerja',
    icon: 'bx bx-calendar',
    children: [
      { title: 'Jadwal Umum', icon: 'bx bx-calendar', route: '/admin/schedule/roster' },
      { title: 'Pola & Jadwal Kerja', icon: 'bx bx-time-five', route: '/admin/schedule/work-patterns' },
      { title: 'Shift', icon: 'bx bx-transfer-alt', route: '/admin/schedule/shifts' },
      { title: 'Buat Jadwal', icon: 'bx bx-calendar-plus', route: '/admin/schedule/roster/generate' },
    ],
  },
  {
    title: 'Pengelolaan Cuti',
    icon: 'bx bx-umbrella',
    children: [
      { title: 'Cuti & Izin', icon: 'bx bx-calendar-edit', route: '/admin/leave' },
      { title: 'Approval Cuti', icon: 'bx bx-check-shield', route: '/admin/leave/approvals' },
    ],
  },
  {
    title: 'Kehadiran',
    icon: 'bx bx-calendar-check',
    visible: isSuperadmin.value || isHrmanager.value,
    children: [
      { title: 'Import Kehadiran', icon: 'bx bx-upload', route: '/admin/attendance/import', visible: isSuperadmin.value || isHrmanager.value },
      { title: 'Sync Kehadiran', icon: 'bx bx-sync', route: '/admin/attendance/sync', visible: isSuperadmin.value || isHrmanager.value },
      { title: 'Data Kehadiran', icon: 'bx bx-file', route: '/admin/attendance', visible: true },
      { title: 'Consecutive Day', icon: 'bx bx-calendar-star', route: '/admin/attendance/consecutive', visible: true },
    ],
  },
  {
    title: 'Pengelolaan BPJS',
    icon: 'bx bx-health',
    visible: isSuperadmin.value || isHrmanager.value,
    children: [
      { title: 'Data Karyawan BPJS', icon: 'bx bx-group', route: '/admin/payroll/configs' },
      { title: 'Perubahan Data BPJS', icon: 'bx bx-edit', route: '/admin/payroll/configs' },
    ],
  },

  {
    title: 'Payroll',
    icon: 'bx bx-money',
    visible: isSuperadmin.value || isHrmanager.value,
    children: [
      { title: 'Payroll', icon: 'bx bx-cog', route: '/admin/payroll' },
      { title: 'Periode Payroll', icon: 'bx bx-calendar-edit', route: '/admin/payroll/payroll-periode' },
      { title: 'Payslip', icon: 'bx bx-file', route: '/admin/payroll' },
      { title: 'Perhitungan THR', icon: 'bx bx-gift', route: '/admin/payroll/thr' },
      { title: 'Laporan PPh 21', icon: 'bx bx-receipt', route: '/admin/payroll/configs' },
    ],
  },
  {
    title: 'Laporan',
    icon: 'bx bxs-report',
    visible: isSuperadmin.value || isHrmanager.value,
    children: [
      { title: 'Laporan Lembur', icon: 'bx bx-grid-alt', route: '/admin/reports' },
      { title: 'Laporan Kehadiran', icon: 'bx bx-calendar-check', route: '/admin/reports' },
      { title: 'Laporan Payroll', icon: 'bx bx-money', route: '/admin/reports' },
      { title: 'Laporan Pajak', icon: 'bx bx-receipt', route: '/admin/reports' },
      { title: 'Laporan BPJS', icon: 'bx bx-shield-quarter', route: '/admin/reports' },
      { title: 'Laporan Uang Makan', icon: 'bx bx-restaurant', route: '/admin/reports' },
    ],
  },
  {
    title: 'Settings',
    icon: 'bx bx-cog',
    route: '/admin/settings',
    visible: isSuperadmin.value || isHrmanager.value,
  },
]

const menuItems = computed(() => {
  return allMenus
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
  if (url === '/admin') return currentPath === url
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
