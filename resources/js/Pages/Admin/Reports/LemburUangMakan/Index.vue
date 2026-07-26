<template>
  <ReportPageLayout
    title="Laporan Lembur &amp; Uang Makan"
    description="Rincian gaji &amp; overtime — detail karyawan &amp; resume per bagian"
    @openSettings="showSettings = true"
  >
    <!-- Tombol Update Data -->
    <template #actions>
      <select
        v-model="updatePeriodId"
        class="px-3 py-2 text-sm rounded-lg border border-(--border-soft) bg-(--bg-elevated) text-(--text-main) min-w-[200px]"
        :disabled="updating"
      >
        <option :value="null" disabled>-- Pilih Periode --</option>
        <option v-for="p in periods" :key="p.id" :value="p.id">
          {{ p.name }} ({{ formatPeriodDate(p) }})
        </option>
      </select>
      <button
        @click="updateData"
        :disabled="updating || !updatePeriodId"
        class="px-3 py-2 text-sm rounded-lg border border-(--border-soft) hover:bg-(--bg-hover) flex items-center gap-1.5 transition-colors"
        :class="{ 'opacity-50 cursor-not-allowed': updating || !updatePeriodId }"
        title="Update Data Lembur & Uang Makan — ambil dari att_prepare ke employee_overtime"
      >
        <i v-if="updating" class="bx bx-loader-alt text-lg animate-spin"></i>
        <i v-else class="bx bx-refresh text-lg"></i>
        <span class="hidden sm:inline">{{ updating ? 'Mengupdate...' : 'Update Data' }}</span>
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
import { useNotificationStore } from '@/Stores/notification'
import ReportPageLayout from '@/Components/ReportPage/ReportPageLayout.vue'
import ReportSettingsModal from '@/Components/ReportPage/ReportSettingsModal.vue'
import LemburUangMakanSettingsTable from '@/Components/ReportPage/settings/LemburUangMakanSettingsTable.vue'
import TabDetail from './TabDetail.vue'
import TabDetailPre from './TabDetailPre.vue'
import TabResume from './TabResume.vue'

const { get, post } = useApi()

const activeTab = ref('detail')
const selectedGroups = ref([])
const availableGroups = ref([])
const showSettings = ref(false)
const spcEmployees = ref([])
const jktEmployees = ref([])
const allinEmployees = ref([])
const tknEmployees = ref([])
const periods = ref([])

const groupCodes = computed(() => availableGroups.value.map(g => g.code))
const extraData = computed(() => ({
  periods: periods.value,
  spcEmployees: spcEmployees.value,
  jktEmployees: jktEmployees.value,
  allinEmployees: allinEmployees.value,
  tknEmployees: tknEmployees.value,
}))

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
  } catch (err) {
    console.error('Gagal fetch report config:', err)
    selectedGroups.value = ['GRP-JKT', 'GRP-PS1', 'GRP-ALLIN', 'KRY-SPC', 'KRY-TKN']
  }

  // 3. Load periods + employees (for settings modal)
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

const updating = ref(false)
const updatePeriodId = ref(null)
const notification = useNotificationStore()

function formatPeriodDate(p) {
  if (!p.start_date || !p.end_date) return ''
  const fmt = new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: 'short', year: 'numeric' })
  return fmt.format(new Date(p.start_date)) + ' - ' + fmt.format(new Date(p.end_date))
}

async function updateData() {
  if (!updatePeriodId.value) {
    notification.addNotification('Pilih periode terlebih dahulu', 'warning')
    return
  }

  // Cari nama periode yang dipilih
  const selectedPeriod = periods.value.find(p => p.id === updatePeriodId.value)
  const periodName = selectedPeriod?.name || 'periode ini'

  if (!confirm(`Update data lembur & uang makan untuk periode "${periodName}"?\n\nData akan diambil dari att_prepare → dihitung per karyawan → disimpan ke employee_overtime.\n\nLanjutkan?`)) return

  updating.value = true
  try {
    const res = await post('/api/v1/reports/lembur/update-data', {
      period_id: updatePeriodId.value
    })
    notification.addNotification(res.message || 'Data berhasil diupdate', 'success')
  } catch (err) {
    console.error('Gagal update data:', err)
    const msg = err?.response?.data?.message || 'Gagal update data lembur & uang makan'
    notification.addNotification(msg, 'error')
  } finally {
    updating.value = false
  }
}

function onSettingsSaved(payload) {
  if (payload.employee_groups && payload.employee_groups.length > 0) {
    selectedGroups.value = payload.employee_groups
  }
}
</script>
