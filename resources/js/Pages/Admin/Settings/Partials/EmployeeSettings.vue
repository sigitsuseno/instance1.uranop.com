<script setup>
import { ref } from 'vue'
import EmployeeDefaultsTab from './Tabs/EmployeeDefaultsTab.vue'
import EmployeeGroupMastersTab from './Tabs/EmployeeGroupMastersTab.vue'
import EmployeeGroupSettingsTab from './Tabs/EmployeeGroupSettingsTab.vue'
import EmployeeStatusTab from './Tabs/EmployeeStatusTab.vue'

const activeTab = ref('defaults')

const tabs = [
  { key: 'defaults', label: 'Pengaturan Default' },
  { key: 'masters', label: 'Master Group' },
  { key: 'group_settings', label: 'Pengaturan Tab Grouping' },
  { key: 'status', label: 'Tipe & Status' },
]
</script>

<template>
  <div class="space-y-6">
    <div>
      <h2 class="text-lg font-medium text-(--text-main)">Pengaturan Karyawan</h2>
      <p class="text-sm text-(--text-muted)">Atur nilai default untuk karyawan baru dan kelola dimensi/kategori beserta grup di dalamnya.</p>
    </div>

    <div class="border-b border-(--border-soft) mb-6">
      <nav class="flex gap-0 -mb-px">
        <button
          v-for="tab in tabs"
          :key="tab.key"
          :class="[
            'px-4 py-2.5 text-sm font-medium transition-colors border-b-2 rounded-t-md',
            activeTab === tab.key
              ? 'text-(--primary) border-(--primary)'
              : 'text-(--text-muted) border-transparent hover:text-(--text-main) hover:border-(--border-soft)',
          ]"
          @click="activeTab = tab.key"
        >
          {{ tab.label }}
        </button>
      </nav>
    </div>

    <!-- Tab Contents -->
    <div v-if="activeTab === 'defaults'">
      <EmployeeDefaultsTab />
    </div>

    <div v-if="activeTab === 'masters'">
      <EmployeeGroupMastersTab />
    </div>

    <div v-if="activeTab === 'group_settings'">
      <EmployeeGroupSettingsTab />
    </div>

    <div v-if="activeTab === 'status'">
      <EmployeeStatusTab />
    </div>

  </div>
</template>
