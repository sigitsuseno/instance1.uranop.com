<template>
  <div>
    <div class="mb-6">
      <h1 class="text-xl font-semibold text-(--text-main)">Rekap Uang Makan</h1>
      <p class="text-sm text-(--text-muted) mt-1">Rekapitulasi uang makan & lembur — per karyawan & resume per bagian</p>
    </div>

    <!-- Tab Bar -->
    <div class="border-b border-(--border-soft) mb-6">
      <nav class="flex gap-0 -mb-px">
        <button
          :class="[
            'px-4 py-2.5 text-sm font-medium transition-colors border-b-2 rounded-t-md',
            activeTab === 'rekap'
              ? 'text-(--primary) border-(--primary)'
              : 'text-(--text-muted) border-transparent hover:text-(--text-main) hover:border-(--border-soft)',
          ]"
          @click="activeTab = 'rekap'"
        >
          Rekap
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

    <!-- Filter Bar (shared) -->
    <div class="flex flex-wrap items-center justify-between gap-4 mb-4">
      <div class="flex items-center gap-3">
        <span class="text-sm font-semibold text-(--text-muted) uppercase tracking-wider">Periode:</span>
        <select
          v-model="selectedPeriodId"
          class="bg-(--bg-elevated) border border-(--border-soft) text-(--text-main) text-sm rounded-lg focus:ring-(--primary) focus:border-(--primary) p-2 min-w-[300px]"
        >
          <option :value="null" disabled>-- Pilih Periode --</option>
          <option v-for="p in periods" :key="p.id" :value="p.id">
            {{ p.name }} ({{ formatDateRange(p.start_date, p.end_date) }})
          </option>
        </select>
      </div>
      <div class="flex items-center gap-2">
        <BaseButton variant="secondary" size="sm" @click="handleExport" :disabled="loading || !hasData">
          Export Excel
        </BaseButton>
        <BaseButton variant="secondary" size="sm" @click="handlePrint" :disabled="loading || !hasData">
          Print
        </BaseButton>
      </div>
    </div>

    <!-- Group Filters -->
    <BaseCard class="mb-4">
      <div class="flex flex-wrap items-center gap-4">
        <span class="text-sm font-semibold text-(--text-muted) uppercase tracking-wider">Filter Grup:</span>
        <label
          v-for="group in availableGroups"
          :key="group.code"
          class="flex items-center gap-2 cursor-pointer group select-none"
        >
          <input
            type="checkbox"
            :value="group.code"
            v-model="selectedGroups"
            class="w-4 h-4 rounded text-(--primary) focus:ring-(--primary-glow) border-(--border-soft)"
          />
          <span class="text-sm font-medium text-(--text-main) group-hover:text-(--primary) transition-colors">
            {{ group.name }}
          </span>
        </label>
      </div>
    </BaseCard>

    <!-- Tab Content -->
    <TabRekap
      v-if="activeTab === 'rekap'"
      ref="tabRekapRef"
      :period-id="selectedPeriodId"
      :groups="selectedGroups"
      @has-data="(val) => rekapHasData = val"
      @loading="(val) => rekapLoading = val"
    />
    <TabResume
      v-if="activeTab === 'resume'"
      ref="tabResumeRef"
      :period-id="selectedPeriodId"
      :groups="selectedGroups"
      @has-data="(val) => resumeHasData = val"
      @loading="(val) => resumeLoading = val"
    />
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useApi } from '../../../../composables/useApi'
import BaseCard from '../../../../Components/BaseCard.vue'
import BaseButton from '../../../../Components/BaseButton.vue'
import TabRekap from './TabRekap.vue'
import TabResume from './TabResume.vue'

const { get } = useApi()

const activeTab = ref('rekap')
const selectedPeriodId = ref(null)
const periods = ref([])
const selectedGroups = ref([])
const availableGroups = ref([])

// Refs to child components
const tabRekapRef = ref(null)
const tabResumeRef = ref(null)
const rekapHasData = ref(false)
const resumeHasData = ref(false)
const rekapLoading = ref(false)
const resumeLoading = ref(false)

const hasData = computed(() => {
  return activeTab.value === 'rekap' ? rekapHasData.value : resumeHasData.value
})
const loading = computed(() => {
  return activeTab.value === 'rekap' ? rekapLoading.value : resumeLoading.value
})

onMounted(async () => {
  try {
    const res = await get('/api/v1/payroll/periods')
    periods.value = (res.data || []).map(p => ({
      id: p.id,
      name: p.name,
      start_date: p.start_date,
      end_date: p.end_date,
    }))
    if (periods.value.length > 0) {
      const today = new Date().toISOString().split('T')[0]
      const valid = periods.value.find(p => p.start_date <= today)
      selectedPeriodId.value = valid ? valid.id : periods.value[0].id
    }
  } catch (err) {
    console.error('Gagal fetch periods:', err)
  }

  try {
    const res = await get('/api/v1/settings/employee-data/groups')
    const allGroups = res.data || []
    availableGroups.value = allGroups
      .filter(g => g.group_label === 'Imported Shift/Group')
      .map(g => ({ code: g.code, name: g.name }))

    const defaultGroups = ['GRP-JKT', 'GRP-ALLIN', 'GRP-GD', 'GRP-SPR']
    selectedGroups.value = availableGroups.value
      .filter(g => defaultGroups.includes(g.code))
      .map(g => g.code)
  } catch (err) {
    console.error('Gagal fetch groups:', err)
  }
})

function formatDateRange(start, end) {
  if (!start || !end) return ''
  const fmt = new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: 'short', year: 'numeric' })
  return fmt.format(new Date(start)) + ' - ' + fmt.format(new Date(end))
}

function handleExport() {
  if (activeTab.value === 'rekap') {
    tabRekapRef.value?.exportExcel()
  } else {
    tabResumeRef.value?.exportExcel()
  }
}

function handlePrint() {
  if (activeTab.value === 'rekap') {
    tabRekapRef.value?.openPrint()
  } else {
    tabResumeRef.value?.openPrint()
  }
}
</script>
