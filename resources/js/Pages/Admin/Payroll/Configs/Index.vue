<template>
  <div>
    <div class="mb-6">
      <h1 class="text-xl font-semibold text-(--text-main)">Gaji & LTHR</h1>
      <p class="text-sm text-(--text-muted) mt-1">Konfigurasi Komponen Gaji, Aturan Lembur, dan Tunjangan Hari Raya (THR)</p>
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

    <div class="mt-4">
      <SalaryComponentsTab v-if="activeTab === 'components'" />
      <OvertimeRulesTab v-if="activeTab === 'overtime'" />
      <ThrConfigsTab v-if="activeTab === 'thr'" />
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import SalaryComponentsTab from './Tabs/SalaryComponentsTab.vue'
import OvertimeRulesTab from './Tabs/OvertimeRulesTab.vue'
import ThrConfigsTab from './Tabs/ThrConfigsTab.vue'

const activeTab = ref('components')

const tabs = [
  { key: 'components', label: 'Komponen Gaji' },
  { key: 'overtime', label: 'Aturan Lembur' },
  { key: 'thr', label: 'Tunjangan Hari Raya' },
]
</script>
