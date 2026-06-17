<template>
  <ReportPageLayout
    title="Laporan Lembur &amp; Uang Makan"
    description="Rincian gaji &amp; overtime — detail karyawan &amp; resume per bagian"
    @openSettings="showSettings = true"
  >
    <!-- Tab Bar -->
    <div class="border-b border-(--border-soft) mb-6">
      <nav class="flex gap-0 -mb-px">
        <button
          :class="[
            'px-4 py-2.5 text-sm font-medium transition-colors border-b-2 rounded-t-md',
            activeTab === 'detail'
              ? 'text-(--primary) border-(--primary)'
              : 'text-(--text-muted) border-transparent hover:text-(--text-main) hover:border-(--border-soft)',
          ]"
          @click="activeTab = 'detail'"
        >
          Detail
        </button>
        <button
          :class="[
            'px-4 py-2.5 text-sm font-medium transition-colors border-b-2 rounded-t-md',
            activeTab === 'resume'
              ? 'text-(--primary) border-(--primary)'
              : 'text-(--text-muted) border-transparent hover:text-(--text-main) hover:border-(--border-soft)',
          ]"
          @click="activeTab = 'resume'"
        >
          Resume
        </button>
      </nav>
    </div>

    <!-- Tab Content -->
    <TabDetail v-if="activeTab === 'detail'" :groups="selectedGroups" />
    <TabResume v-if="activeTab === 'resume'" :groups="selectedGroups" />

    <!-- Settings Modal -->
    <ReportSettingsModal
      v-if="showSettings"
      report-type="lembur_uang_makan"
      report-label="Lembur & Uang Makan"
      :available-groups="groupCodes"
      @close="showSettings = false"
      @saved="onSettingsSaved"
    >
      <template #config="{ config, updateConfig }">
        <LemburUangMakanSettingsTable
          :config="config"
          @update:config="updateConfig"
        />
      </template>
    </ReportSettingsModal>
  </ReportPageLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useApi } from '@/composables/useApi'
import ReportPageLayout from '@/Components/ReportPage/ReportPageLayout.vue'
import ReportSettingsModal from '@/Components/ReportPage/ReportSettingsModal.vue'
import LemburUangMakanSettingsTable from '@/Components/ReportPage/settings/LemburUangMakanSettingsTable.vue'
import TabDetail from './TabDetail.vue'
import TabResume from './TabResume.vue'

const { get } = useApi()

const activeTab = ref('detail')
const selectedGroups = ref([])
const availableGroups = ref([])
const showSettings = ref(false)

// Extract just the codes for the modal checkboxes
const groupCodes = computed(() => availableGroups.value.map(g => g.code))

onMounted(async () => {
  // 1. Load all available groups (for modal checkboxes)
  try {
    const groupsRes = await get('/api/v1/settings/employee-data/groups')
    const allGroups = groupsRes.data || []
    availableGroups.value = allGroups
      .filter(g => g.group_label === 'Imported Shift/Group')
      .map(g => ({ code: g.code, name: g.name }))
  } catch (err) {
    console.error('Gagal fetch groups:', err)
  }

  // 2. Load saved group selection from report config
  try {
    const configRes = await get('/api/v1/settings/report-configs/lembur_uang_makan')
    const savedGroups = configRes.employee_groups || configRes.data?.employee_groups || []

    if (savedGroups.length > 0) {
      selectedGroups.value = savedGroups
    } else {
      selectedGroups.value = ['GRP-JKT', 'GRP-PS1', 'GRP-ALLIN', 'GRP-SPC']
    }
  } catch (err) {
    console.error('Gagal fetch report config:', err)
    // Fallback default
    selectedGroups.value = ['GRP-JKT', 'GRP-PS1', 'GRP-ALLIN', 'GRP-SPC']
  }
})

function onSettingsSaved(payload) {
  // Reload groups from saved settings
  if (payload.employee_groups && payload.employee_groups.length > 0) {
    selectedGroups.value = payload.employee_groups
  }
}
</script>
