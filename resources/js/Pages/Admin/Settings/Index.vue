<script setup>
import { ref, computed } from 'vue'
import GeneralSettings from './Partials/GeneralSettings.vue'
import EmployeeSettings from './Partials/EmployeeSettings.vue'
import AttendanceSettings from './Partials/AttendanceSettings.vue'
import PayrollSettings from './Partials/PayrollSettings.vue'
import WorkPatternSettings from './Partials/WorkPatternSettings.vue'
import ExtraEmployeesSettings from './Partials/ExtraEmployeesSettings.vue'

const tabs = [
  { id: 'general', name: 'Umum', icon: 'bx-cog', component: GeneralSettings },
  { id: 'employee', name: 'Karyawan', icon: 'bx-group', component: EmployeeSettings },
  { id: 'extra_emp', name: 'EXTRA EMP', icon: 'bx-user-plus', component: ExtraEmployeesSettings },
  { id: 'work_pattern', name: 'Pola Kerja', icon: 'bx-calendar-star', component: WorkPatternSettings },
  { id: 'attendance', name: 'Absensi & Lembur', icon: 'bx-time', component: AttendanceSettings },
  { id: 'payroll', name: 'Penggajian & Pajak', icon: 'bx-money', component: PayrollSettings },
]

const activeTabId = ref(tabs[0].id)
const activeTab = computed(() => tabs.find(t => t.id === activeTabId.value))
</script>

<template>
  <div class="h-full flex flex-col">
    <div class="mb-6 shrink-0">
      <h1 class="text-xl font-semibold text-(--text-main)">Pengaturan Sistem</h1>
      <p class="text-sm text-(--text-muted) mt-1">Konfigurasi dan pengaturan aplikasi HRIS</p>
    </div>

    <div class="flex flex-col md:flex-row gap-8 flex-1 min-h-0">
      <!-- Side Tabs Navigation -->
      <div class="w-full md:w-64 shrink-0 flex flex-col gap-2">
        <button
          v-for="tab in tabs"
          :key="tab.id"
          @click="activeTabId = tab.id"
          class="w-full flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium transition-all duration-200"
          :class="
            activeTabId === tab.id
              ? 'bg-(--primary) text-white shadow-md'
              : 'text-(--text-muted) hover:bg-(--bg-elevated) hover:text-(--text-main)'
          "
        >
          <i class="bx text-xl" :class="tab.icon"></i>
          {{ tab.name }}
        </button>
      </div>

      <!-- Tab Content -->
      <div class="flex-1 min-w-0">
        <transition name="fade" mode="out-in">
          <component :is="activeTab.component" :key="activeTab.id" />
        </transition>
      </div>
    </div>
  </div>
</template>

