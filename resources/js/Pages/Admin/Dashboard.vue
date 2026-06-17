<template>
  <div class="p-6 space-y-6">
    <div>
      <h1 class="text-2xl font-bold text-(--text-main)">Selamat Datang, Admin!</h1>
      <p class="text-(--text-muted) mt-1">Berikut ringkasan data HRIS hari ini.</p>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
      <div
        v-for="card in statCards"
        :key="card.label"
        class="bg-(--bg-card) border border-(--border-soft) rounded-md p-6 relative overflow-hidden group hover:border-(--primary) transition-colors duration-300 shadow-sm hover:shadow-md"
      >
        <div class="absolute left-0 top-0 bottom-0 w-1 rounded-l-md group-hover:w-1.5 transition-all" :class="card.accent"></div>
        <div class="flex items-start justify-between pl-1">
          <div>
            <p class="text-sm font-medium text-(--text-muted)">{{ card.label }}</p>
            <p class="text-3xl font-bold text-(--text-main) mt-2 tracking-tight">{{ card.value }}</p>
            <p class="text-xs text-(--text-muted) mt-1">{{ card.sub }}</p>
          </div>
          <div class="p-3 rounded-md group-hover:scale-110 transition-transform" :class="[card.iconBg, card.iconColor]">
            <component :is="card.icon" class="w-6 h-6" />
          </div>
        </div>
      </div>
    </div>

    <!-- Row 2: Pending Cuti + Kontrak Expiring -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <div class="lg:col-span-2">
        <BaseCard>
          <template #title>Permohonan Cuti Menunggu Approval</template>
          <template #actions>
            <span class="text-sm text-(--text-muted)">{{ pendingLeaves.length }} pengajuan</span>
          </template>
          <div v-if="loading" class="py-8 text-center text-(--text-muted)">Memuat data...</div>
          <div v-else-if="pendingLeaves.length === 0" class="py-8 text-center text-(--text-muted)">Tidak ada pengajuan cuti yang menunggu approval.</div>
          <DataTable v-else :headers="leaveHeaders" :items="pendingLeaves">
            <template #item.status="{ value }">
              <Badge variant="warning">{{ value === 'pending' ? 'Menunggu' : value }}</Badge>
            </template>
          </DataTable>
        </BaseCard>
      </div>

      <div>
        <BaseCard>
          <template #title>Kontrak Akan Berakhir</template>
          <div v-if="loading" class="py-8 text-center text-(--text-muted)">Memuat data...</div>
          <div v-else-if="contractsExpiring.length === 0" class="py-8 text-center text-(--text-muted)">Tidak ada kontrak yang akan berakhir dalam 30 hari.</div>
          <div v-else class="space-y-3">
            <div
              v-for="contract in contractsExpiring"
              :key="contract.id"
              class="flex items-center justify-between border-b border-(--border-soft) pb-3 last:border-0 last:pb-0"
            >
              <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-(--text-main) truncate">{{ contract.employee_name }}</p>
                <p class="text-xs text-(--text-muted)">{{ contract.contract_type }} &middot; {{ contract.department }}</p>
                <p class="text-xs text-(--text-soft)">Berakhir {{ contract.end_date }}</p>
              </div>
              <Badge :variant="sisaVariant(contract.days_left)">{{ contract.days_left }} hari</Badge>
            </div>
          </div>
        </BaseCard>
      </div>
    </div>

    <!-- Row 3: Audit Log + Ultah -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <div class="lg:col-span-2">
        <BaseCard>
          <template #title>Aktivitas Terbaru</template>
          <template #actions>
            <span class="text-xs text-(--text-muted)">Audit Log</span>
          </template>
          <div v-if="loading" class="py-8 text-center text-(--text-muted)">Memuat data...</div>
          <div v-else-if="recentAuditLogs.length === 0" class="py-8 text-center text-(--text-muted)">Belum ada aktivitas tercatat.</div>
          <DataTable v-else :headers="auditHeaders" :items="recentAuditLogs">
            <template #item.action="{ value }">
              <Badge :variant="actionVariant(value)">{{ value }}</Badge>
            </template>
          </DataTable>
        </BaseCard>
      </div>

      <div>
        <BaseCard>
          <template #title>🎂 Ultah Bulan Ini</template>
          <div v-if="loading" class="py-8 text-center text-(--text-muted)">Memuat data...</div>
          <div v-else-if="birthdays.length === 0" class="py-8 text-center text-(--text-muted)">Tidak ada yang ulang tahun bulan ini.</div>
          <div v-else class="space-y-3">
            <div
              v-for="emp in birthdays"
              :key="emp.id"
              class="flex items-center gap-3 p-2 rounded-md hover:bg-(--bg-elevated)/50 transition-colors"
            >
              <div class="w-9 h-9 rounded-full bg-(--warning)/10 flex items-center justify-center shrink-0">
                <span class="text-sm">🎂</span>
              </div>
              <div class="min-w-0">
                <p class="text-sm font-medium text-(--text-main) truncate">{{ emp.name }}</p>
                <p class="text-xs text-(--text-muted)">{{ emp.department }} &middot; {{ emp.dob }}</p>
              </div>
            </div>
          </div>
        </BaseCard>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useApi } from '../../composables/useApi.js'
import BaseCard from '../../Components/BaseCard.vue'
import DataTable from '../../Components/Table/DataTable.vue'
import Badge from '../../Components/Badge.vue'
import { IconUsers, IconCalendarCheck, IconClock, IconDollarSign } from '../../Components/Icons/index.js'

const { get } = useApi()

const loading = ref(true)
const stats = ref({ totalKaryawan: 0, hadirHariIni: 0, menungguCuti: 0, totalPayroll: 'Rp 0' })
const pendingLeaves = ref([])
const contractsExpiring = ref([])
const recentAuditLogs = ref([])
const birthdays = ref([])

const statCards = computed(() => [
  {
    label: 'Total Karyawan',
    value: stats.value.totalKaryawan,
    sub: 'Karyawan aktif',
    icon: IconUsers,
    accent: 'bg-(--primary)',
    iconBg: 'bg-(--primary)/10',
    iconColor: 'text-(--primary)',
  },
  {
    label: 'Hadir Hari Ini',
    value: stats.value.hadirHariIni,
    sub: `dari ${stats.value.totalKaryawan} karyawan`,
    icon: IconCalendarCheck,
    accent: 'bg-(--success)',
    iconBg: 'bg-(--success)/10',
    iconColor: 'text-(--success)',
  },
  {
    label: 'Menunggu Cuti',
    value: stats.value.menungguCuti,
    sub: 'Perlu persetujuan',
    icon: IconClock,
    accent: 'bg-(--warning)',
    iconBg: 'bg-(--warning)/10',
    iconColor: 'text-(--warning)',
  },
  {
    label: 'Total Payroll',
    value: stats.value.totalPayroll,
    sub: 'Bulan ini',
    icon: IconDollarSign,
    accent: 'bg-(--primary-hover)',
    iconBg: 'bg-(--primary-hover)/10',
    iconColor: 'text-(--primary-hover)',
  },
])

const leaveHeaders = [
  { key: 'employee_name', label: 'Nama Karyawan' },
  { key: 'department', label: 'Departemen' },
  { key: 'leave_type', label: 'Jenis Cuti' },
  { key: 'start_date', label: 'Mulai' },
  { key: 'end_date', label: 'Selesai' },
  { key: 'days', label: 'Hari' },
  { key: 'status', label: 'Status' },
]

const auditHeaders = [
  { key: 'user_name', label: 'User' },
  { key: 'action', label: 'Aksi' },
  { key: 'module', label: 'Modul' },
  { key: 'model_type', label: 'Entitas' },
  { key: 'created_at', label: 'Waktu' },
]

function sisaVariant(sisa) {
  if (sisa == null) return 'neutral'
  if (sisa > 14) return 'success'
  if (sisa >= 7) return 'warning'
  return 'danger'
}

function actionVariant(action) {
  switch (action) {
    case 'created': return 'success'
    case 'updated': return 'info'
    case 'deleted': return 'danger'
    case 'approved': return 'success'
    case 'rejected': return 'danger'
    default: return 'neutral'
  }
}

async function fetchDashboard() {
  loading.value = true
  try {
    const res = await get('/api/v1/dashboard')
    stats.value = res.stats
    pendingLeaves.value = res.pendingLeaves
    contractsExpiring.value = res.contractsExpiring
    recentAuditLogs.value = res.recentAuditLogs
    birthdays.value = res.birthdays
  } catch (err) {
    console.error('Gagal memuat dashboard:', err)
  } finally {
    loading.value = false
  }
}

onMounted(fetchDashboard)
</script>
