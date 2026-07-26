<template>
  <ReportPageLayout
    title="Laporan Lembur &amp; Uang Makan"
    description="Rincian gaji &amp; overtime — detail karyawan &amp; resume per bagian"
    @openSettings="showSettings = true"
  >
    <!-- Tombol Update Data -->
    <template #actions>
      <button
        @click="showUpdateModal = true"
        class="px-3 py-2 text-sm rounded-lg border border-(--border-soft) hover:bg-(--bg-hover) flex items-center gap-1.5 transition-colors"
        title="Update Data Lembur & Uang Makan — simpan ke employee_overtime"
      >
        <i class="bx bx-refresh text-lg"></i>
        <span class="hidden sm:inline">Update Data</span>
      </button>
    </template>

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
            activeTab === 'detail_pre'
              ? 'text-purple-600 border-purple-500'
              : 'text-(--text-muted) border-transparent hover:text-(--text-main) hover:border-(--border-soft)',
          ]"
          @click="activeTab = 'detail_pre'"
        >
          Detail Pre
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
    <TabDetailPre v-if="activeTab === 'detail_pre'" :groups="selectedGroups" />
    <TabResume v-if="activeTab === 'resume'" :groups="selectedGroups" />

    <!-- Update Data Modal -->
    <UpdateDataModal
      :show="showUpdateModal"
      :periods="periods"
      :allEmployees="allEmployees"
      :initialEmpTanpa="initialEmpTanpa"
      :initialPositionRules="initialPositionRules"
      :initialTechnicianRules="initialTechnicianRules"
      @close="showUpdateModal = false"
      @saved="onDataUpdated"
    />

    <!-- Settings Modal -->
    <ReportSettingsModal
      v-if="showSettings"
      report-type="lembur_uang_makan"
      report-label="Lembur & Uang Makan"
      :available-groups="groupCodes"
      :extra-data="extraData"
      @close="showSettings = false"
      @saved="onSettingsSaved"
    >
      <template #config="{ config, updateConfig, extraData }">
        <LemburUangMakanSettingsTable
          :config="config"
          :extra-data="extraData"
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
import TabDetailPre from './TabDetailPre.vue'
import TabResume from './TabResume.vue'
import UpdateDataModal from './UpdateDataModal.vue'

const { get } = useApi()

const activeTab = ref('detail')
const selectedGroups = ref([])
const availableGroups = ref([])
const showSettings = ref(false)
const showUpdateModal = ref(false)
const spcEmployees = ref([])
const jktEmployees = ref([])
const allinEmployees = ref([])
const tknEmployees = ref([])
const periods = ref([])

// Data awal untuk modal UpdateData (dari config)
const initialEmpTanpa = ref([])
const initialPositionRules = ref(null)
const initialTechnicianRules = ref(null)

const groupCodes = computed(() => availableGroups.value.map(g => g.code))
const extraData = computed(() => ({
  periods: periods.value,
  spcEmployees: spcEmployees.value,
  jktEmployees: jktEmployees.value,
  allinEmployees: allinEmployees.value,
  tknEmployees: tknEmployees.value,
}))

// Gabungan semua karyawan untuk modal UpdateData
const allEmployees = computed(() => {
  const seen = new Set()
  const result = []
  const all = [
    ...spcEmployees.value,
    ...jktEmployees.value,
    ...allinEmployees.value,
    ...tknEmployees.value,
  ]
  for (const e of all) {
    if (!seen.has(e.id)) {
      seen.add(e.id)
      result.push({ id: e.id, name: e.name || e.nama })
    }
  }
  return result.sort((a, b) => (a.name || '').localeCompare(b.name || ''))
})

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
      selectedGroups.value = ['GRP-JKT', 'GRP-PS1', 'GRP-ALLIN', 'KRY-SPC', 'KRY-TKN']
    }

    // Load initial config untuk modal UpdateData
    const cfg = configRes.config || configRes.data?.config || {}
    initialEmpTanpa.value = cfg.jkt_no_overtime_employees || []
    // Position rules dari config (jika ada), kalau tidak pakai null → modal pakai default
    if (cfg.KABAG || cfg.KASHIFT || cfg['ALL IN']) {
      initialPositionRules.value = {
        KABAG: cfg.KABAG || null,
        KASHIFT: cfg.KASHIFT || null,
        ALLIN: cfg['ALL IN'] || cfg.ALLIN || null,
      }
    }
    // Technician rules dari config
    if (cfg.tkn_weekday_flat !== undefined || cfg.tkn_saturday_rate !== undefined || cfg.tkn_holiday_rate !== undefined) {
      initialTechnicianRules.value = {
        weekday: cfg.tkn_weekday_flat ?? 15000,
        saturday: cfg.tkn_saturday_rate ?? 100000,
        holiday: cfg.tkn_holiday_rate ?? 200000,
      }
    }
  } catch (err) {
    console.error('Gagal fetch report config:', err)
    selectedGroups.value = ['GRP-JKT', 'GRP-PS1', 'GRP-ALLIN', 'KRY-SPC', 'KRY-TKN']
  }

  // 3. Load periods + employees (for settings modal & update modal)
  try {
    const [periodsRes, spcRes, jktRes, allinRes, sprRes, gdRes, tknRes] = await Promise.all([
      get('/api/v1/settings/employee-data/pay-periods'),
      get('/api/v1/settings/employee-data/by-group/KRY-SPC'),
      get('/api/v1/settings/employee-data/by-group/GRP-JKT'),
      get('/api/v1/settings/employee-data/by-group/GRP-ALLIN'),
      get('/api/v1/settings/employee-data/by-group/GRP-SPR'),
      get('/api/v1/settings/employee-data/by-group/GRP-GD'),
      get('/api/v1/settings/employee-data/by-group/KRY-TKN'),
    ])
    periods.value = periodsRes.data || []
    spcEmployees.value = spcRes.data || []
    jktEmployees.value = jktRes.data || []
    allinEmployees.value = [
      ...(allinRes.data || []),
      ...(sprRes.data || []),
      ...(gdRes.data || []),
    ]
    tknEmployees.value = tknRes.data || []
  } catch (err) {
    console.error('Gagal fetch extra data:', err)
  }
})

function onSettingsSaved(payload) {
  if (payload.employee_groups && payload.employee_groups.length > 0) {
    selectedGroups.value = payload.employee_groups
  }
}

function onDataUpdated(result) {
  // Data sudah tersimpan, Tab "Detail Pre" akan menampilkan data baru
  console.log('Update selesai:', result)
}
</script>
