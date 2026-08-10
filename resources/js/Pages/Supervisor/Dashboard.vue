<template>
  <div class="py-2 space-y-6">
    <!-- Top Greeting Row -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold tracking-tight text-(--text-main)">
          {{ greetingText }}!
        </h1>
        <p class="text-sm text-(--text-muted) flex items-center gap-1.5 mt-0.5">
          <span class="inline-block w-2 h-2 rounded-full bg-(--success)"></span>
          {{ formattedDate }} &middot; Ringkasan data tim Anda hari ini.
        </p>
      </div>
      <div class="flex items-center gap-2">
        <BaseButton variant="secondary" size="md" @click="fetchDashboard" :loading="loading">
          <template #icon-left>
            <IconRefresh class="w-4 h-4" :class="{ 'animate-spin': loading }" />
          </template>
          Refresh Data
        </BaseButton>
      </div>
    </div>

    <!-- Quick Actions Row -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
      <router-link
        to="/supervisor/employee-data"
        class="flex items-center gap-3.5 p-4 rounded-md bg-(--bg-card) border border-(--border-soft) hover:border-(--primary) hover:shadow-md transition-all duration-300 group"
      >
        <div class="p-2.5 rounded-md bg-(--primary)/10 text-(--primary) group-hover:scale-110 transition-transform duration-300">
          <IconUsers class="w-5 h-5" />
        </div>
        <div>
          <p class="text-sm font-bold text-(--text-main) group-hover:text-(--primary) transition-colors">Data Karyawan</p>
          <p class="text-[11px] text-(--text-muted) mt-0.5">Daftar database staf</p>
        </div>
      </router-link>

      <router-link
        to="/supervisor/leave"
        class="flex items-center gap-3.5 p-4 rounded-md bg-(--bg-card) border border-(--border-soft) hover:border-(--warning) hover:shadow-md transition-all duration-300 group"
      >
        <div class="p-2.5 rounded-md bg-(--warning)/10 text-(--warning) group-hover:scale-110 transition-transform duration-300">
          <IconClock class="w-5 h-5" />
        </div>
        <div>
          <p class="text-sm font-bold text-(--text-main) group-hover:text-(--warning) transition-colors">Approval Cuti</p>
          <p class="text-[11px] text-(--text-muted) mt-0.5">Kelola permohonan cuti</p>
        </div>
      </router-link>

      <router-link
        to="/supervisor/attendance"
        class="flex items-center gap-3.5 p-4 rounded-md bg-(--bg-card) border border-(--border-soft) hover:border-(--success) hover:shadow-md transition-all duration-300 group"
      >
        <div class="p-2.5 rounded-md bg-(--success)/10 text-(--success) group-hover:scale-110 transition-transform duration-300">
          <IconCalendarCheck class="w-5 h-5" />
        </div>
        <div>
          <p class="text-sm font-bold text-(--text-main) group-hover:text-(--success) transition-colors">Data Absensi</p>
          <p class="text-[11px] text-(--text-muted) mt-0.5">Monitor kehadiran staf</p>
        </div>
      </router-link>

      <router-link
        to="/supervisor/reports"
        class="flex items-center gap-3.5 p-4 rounded-md bg-(--bg-card) border border-(--border-soft) hover:border-(--primary-hover) hover:shadow-md transition-all duration-300 group"
      >
        <div class="p-2.5 rounded-md bg-(--primary-hover)/10 text-(--primary-hover) group-hover:scale-110 transition-transform duration-300">
          <IconFileInvoice class="w-5 h-5" />
        </div>
        <div>
          <p class="text-sm font-bold text-(--text-main) group-hover:text-(--primary-hover) transition-colors">Laporan</p>
          <p class="text-[11px] text-(--text-muted) mt-0.5">Laporan & rekapitulasi</p>
        </div>
      </router-link>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
      <div
        v-for="card in statCards"
        :key="card.label"
        class="bg-(--bg-card) border border-(--border-soft) rounded-md p-6 relative overflow-hidden group hover:border-(--primary) hover:shadow-lg transition-all duration-300 flex flex-col justify-between"
      >
        <!-- Radial glow effect on hover -->
        <div class="absolute inset-0 bg-radial-gradient opacity-0 group-hover:opacity-100 pointer-events-none transition-opacity duration-500" :style="{ background: `radial-gradient(circle at 100% 0%, ${card.accentRGB} 0%, transparent 60%)` }"></div>

        <div class="absolute left-0 top-0 bottom-0 w-1 rounded-l-md group-hover:w-1.5 transition-all" :class="card.accent"></div>
        
        <div class="flex items-start justify-between pl-1">
          <div class="flex-1 min-w-0">
            <p class="text-xs font-bold text-(--text-muted) tracking-wider uppercase">{{ card.label }}</p>
            <div class="flex items-baseline gap-3 mt-2 flex-wrap">
              <span class="text-3xl font-extrabold text-(--text-main) tracking-tight">{{ card.value }}</span>
              <!-- Sparkline Trend Chart -->
              <svg class="w-16 h-7 opacity-60 group-hover:opacity-100 transition-opacity duration-300 shrink-0" viewBox="0 0 100 30" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" :class="card.iconColor">
                <path :d="card.sparkline" />
              </svg>
            </div>
          </div>
          <div class="p-2.5 rounded-md group-hover:scale-110 transition-transform duration-300 shrink-0" :class="[card.iconBg, card.iconColor]">
            <component :is="card.icon" class="w-5 h-5" />
          </div>
        </div>

        <div class="mt-4 pt-4 border-t border-(--border-soft)/50 flex items-center justify-between pl-1">
          <span class="text-xs text-(--text-muted) font-medium">{{ card.sub }}</span>
          
          <!-- Mini presence indicator -->
          <div v-if="card.label === 'Hadir Hari Ini'" class="flex items-center gap-2 w-24">
            <div class="w-full bg-(--bg-elevated) h-1 rounded-full overflow-hidden">
              <div class="bg-(--success) h-full rounded-full transition-all duration-500" :style="{ width: presencePercentage + '%' }"></div>
            </div>
            <span class="text-[10px] font-bold text-(--success)">{{ presencePercentage }}%</span>
          </div>

          <!-- Mini widget for pending leave -->
          <div v-else-if="card.label === 'Menunggu Cuti' && stats.menungguCuti > 0" class="flex items-center gap-1 animate-pulse">
            <span class="w-1.5 h-1.5 rounded-full bg-(--warning)"></span>
            <span class="text-[10px] font-bold text-(--warning)">Butuh Approval</span>
          </div>

          <!-- Total Payroll Trend -->
          <div v-else-if="card.label === 'Total Payroll'" class="flex items-center gap-0.5 text-(--success) text-[10px] font-bold">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor">
              <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
            </svg>
            <span>Periode Aktif</span>
          </div>
          
          <!-- Employees Active Indicator -->
          <div v-else-if="card.label === 'Total Karyawan'" class="flex items-center gap-1">
            <span class="w-1.5 h-1.5 rounded-full bg-(--primary)"></span>
            <span class="text-[10px] font-bold text-(--primary)">Aktif</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Row 2: Kontrak Expiring (full width) -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <div class="lg:col-span-3">
        <BaseCard class="h-full flex flex-col">
          <template #title>
            <div class="flex items-center gap-2">
              <IconFileInvoice class="w-5 h-5 text-(--primary)" />
              <span>Kontrak Akan Berakhir</span>
            </div>
          </template>
          <template #actions>
            <router-link
              to="/supervisor/employee"
              class="text-xs font-semibold text-(--primary) hover:text-(--primary-hover) hover:underline flex items-center gap-1 transition-colors"
            >
              <span>Lihat Semua</span>
              <IconChevronRight class="w-3.5 h-3.5" />
            </router-link>
          </template>

          <div v-if="loading" class="py-8 text-center text-(--text-muted)">Memuat data...</div>
          <div v-else-if="contractsExpiring.length === 0" class="py-8 text-center text-(--text-muted)">Tidak ada kontrak yang akan berakhir dalam 30 hari.</div>
          <div v-else class="space-y-3.5 max-h-[300px] overflow-y-auto pr-2 [&::-webkit-scrollbar]:w-1 [&::-webkit-scrollbar-track]:bg-transparent [&::-webkit-scrollbar-thumb]:bg-(--border-soft) [&::-webkit-scrollbar-thumb]:rounded-full hover:[&::-webkit-scrollbar-thumb]:bg-(--text-soft) transition-all duration-300">
            <div
              v-for="contract in contractsExpiring"
              :key="contract.id"
              class="flex items-center gap-3 border-b border-(--border-soft)/50 pb-3.5 last:border-0 last:pb-0 hover:bg-(--bg-elevated)/30 p-1 rounded-md transition-colors duration-300"
            >
              <div class="w-9 h-9 rounded-md bg-gradient-to-tr from-(--bg-elevated) to-(--bg-card) border border-(--border-soft) text-(--text-muted) flex items-center justify-center font-bold text-xs shrink-0 uppercase shadow-sm">
                {{ getInitials(contract.employee_name) }}
              </div>
              <div class="flex-1 min-w-0">
                <p class="text-sm font-semibold text-(--text-main) truncate">{{ contract.employee_name }}</p>
                <p class="text-xs text-(--text-muted) truncate">{{ contract.contract_type }} &middot; {{ contract.department }}</p>
                <p class="text-[11px] text-(--text-soft) mt-0.5">Berakhir {{ formatDateShort(contract.end_date) }}</p>
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
        <BaseCard class="h-full flex flex-col">
          <template #title>
            <div class="flex items-center gap-2">
              <IconChartBar class="w-5 h-5 text-(--primary)" />
              <span>Aktivitas Terbaru</span>
            </div>
          </template>
          <template #actions>
            <span class="text-xs font-semibold text-(--text-muted) tracking-wider uppercase bg-(--bg-elevated) px-2.5 py-1 rounded-md">Audit Log</span>
          </template>

          <div v-if="loading" class="py-8 text-center text-(--text-muted)">Memuat data...</div>
          <div v-else-if="recentAuditLogs.length === 0" class="py-8 text-center text-(--text-muted)">Belum ada aktivitas tercatat.</div>
          <DataTable v-else :headers="auditHeaders" :items="recentAuditLogs">
            <template #item.user_name="{ item }">
              <div class="flex items-center gap-2.5">
                <div class="w-7 h-7 rounded-md bg-(--bg-elevated) text-(--text-muted) flex items-center justify-center font-bold text-[10px] shrink-0 uppercase">
                  {{ getInitials(item.user_name) }}
                </div>
                <span class="text-sm font-medium text-(--text-main) truncate">{{ item.user_name }}</span>
              </div>
            </template>
            <template #item.action="{ value }">
              <Badge :variant="actionVariant(value)">{{ value }}</Badge>
            </template>
            <template #item.module="{ item }">
              <div class="min-w-0 py-0.5">
                <p class="text-sm font-semibold text-(--text-main)">{{ item.module }}</p>
                <p class="text-xs text-(--text-muted) mt-0.5">Entitas: {{ item.model_type }}</p>
              </div>
            </template>
            <template #item.created_at="{ value }">
              <span class="text-xs text-(--text-muted) font-medium">{{ formatTime(value) }}</span>
            </template>
          </DataTable>
        </BaseCard>
      </div>

      <div>
        <BaseCard class="h-full flex flex-col">
          <template #title>
            <div class="flex items-center gap-2">
              <IconGift class="w-5 h-5 text-(--warning)" />
              <span>Ulang Tahun Bulan Ini</span>
            </div>
          </template>
          <div v-if="loading" class="py-8 text-center text-(--text-muted)">Memuat data...</div>
          <div v-else-if="birthdays.length === 0" class="py-8 text-center text-(--text-muted)">Tidak ada yang ulang tahun bulan ini.</div>
          <div v-else class="space-y-3 max-h-[300px] overflow-y-auto pr-2 [&::-webkit-scrollbar]:w-1 [&::-webkit-scrollbar-track]:bg-transparent [&::-webkit-scrollbar-thumb]:bg-(--border-soft) [&::-webkit-scrollbar-thumb]:rounded-full hover:[&::-webkit-scrollbar-thumb]:bg-(--text-soft) transition-all duration-300">
            <div
              v-for="emp in birthdays"
              :key="emp.id"
              class="flex items-center gap-3 p-2 rounded-md hover:bg-(--bg-elevated)/40 border border-transparent hover:border-(--border-soft) transition-all duration-300"
            >
              <div class="w-9 h-9 rounded-full bg-gradient-to-tr from-(--warning)/15 to-(--warning)/5 text-(--warning) border border-(--warning)/10 flex items-center justify-center shrink-0">
                <span class="text-sm">🎈</span>
              </div>
              <div class="flex-1 min-w-0">
                <p class="text-sm font-semibold text-(--text-main) truncate">{{ emp.name }}</p>
                <p class="text-xs text-(--text-muted) truncate">{{ emp.department }}</p>
              </div>
              <div class="text-right shrink-0">
                <span class="text-xs font-bold text-(--warning) bg-(--warning)/10 px-2 py-0.5 rounded-full">{{ formatBirthday(emp.dob) }}</span>
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
import BaseButton from '../../Components/BaseButton.vue'
import { 
  IconUsers, 
  IconCalendarCheck, 
  IconClock, 
  IconRefresh,
  IconChevronRight,
  IconGift,
  IconFileInvoice,
  IconChartBar
} from '../../Components/Icons/index.js'

const { get } = useApi()

const loading = ref(true)
const stats = ref({ totalKaryawan: 0, hadirHariIni: 0, menungguCuti: 0, totalPayroll: 'Rp 0', totalPayrollMode: 'on_record' })
const contractsExpiring = ref([])
const recentAuditLogs = ref([])
const birthdays = ref([])

const greetingText = computed(() => {
  const hr = new Date().getHours()
  if (hr < 11) return 'Selamat Pagi'
  if (hr < 15) return 'Selamat Siang'
  if (hr < 19) return 'Selamat Sore'
  return 'Selamat Malam'
})

const formattedDate = computed(() => {
  return new Date().toLocaleDateString('id-ID', {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric'
  })
})

const presencePercentage = computed(() => {
  if (!stats.value.totalKaryawan) return 0
  return Math.round((stats.value.hadirHariIni / stats.value.totalKaryawan) * 100)
})

const statCards = computed(() => [
  {
    label: 'Total Karyawan',
    value: stats.value.totalKaryawan,
    sub: 'Karyawan aktif',
    icon: IconUsers,
    accent: 'bg-(--primary)',
    accentRGB: 'rgba(37, 99, 235, 0.12)',
    iconBg: 'bg-(--primary)/10',
    iconColor: 'text-(--primary)',
    sparkline: 'M 5 22 Q 25 18, 50 16 T 95 5',
  },
  {
    label: 'Hadir Hari Ini',
    value: stats.value.hadirHariIni,
    sub: `dari ${stats.value.totalKaryawan} karyawan`,
    icon: IconCalendarCheck,
    accent: 'bg-(--success)',
    accentRGB: 'rgba(16, 185, 129, 0.12)',
    iconBg: 'bg-(--success)/10',
    iconColor: 'text-(--success)',
    sparkline: 'M 5 12 L 20 18 L 40 8 L 60 18 L 80 5 L 95 8',
  },
  {
    label: 'Menunggu Cuti',
    value: stats.value.menungguCuti,
    sub: 'Perlu persetujuan',
    icon: IconClock,
    accent: 'bg-(--warning)',
    accentRGB: 'rgba(245, 158, 11, 0.12)',
    iconBg: 'bg-(--warning)/10',
    iconColor: 'text-(--warning)',
    sparkline: 'M 5 22 L 20 20 L 40 10 L 60 22 L 80 20 L 95 18',
  },
  {
    label: 'Total Payroll',
    value: stats.value.totalPayroll,
    sub: stats.value.totalPayrollMode === 'on_the_fly' ? 'Estimasi bulan ini' : 'Bulan ini',
    icon: IconFileInvoice,
    accent: 'bg-(--primary-hover)',
    accentRGB: 'rgba(29, 78, 216, 0.12)',
    iconBg: 'bg-(--primary-hover)/10',
    iconColor: 'text-(--primary-hover)',
    sparkline: 'M 5 22 Q 25 20, 50 12 T 95 5',
  },
])

const auditHeaders = [
  { key: 'user_name', label: 'User' },
  { key: 'action', label: 'Aksi' },
  { key: 'module', label: 'Detail Aktivitas' },
  { key: 'created_at', label: 'Waktu' },
]

function getInitials(name) {
  if (!name) return '?'
  const parts = name.trim().split(/\s+/)
  if (parts.length === 1) return parts[0].substring(0, 2).toUpperCase()
  return (parts[0][0] + parts[1][0]).toUpperCase()
}

function formatDateShort(dateStr) {
  if (!dateStr) return '-'
  const date = new Date(dateStr)
  if (isNaN(date)) return dateStr
  return date.toLocaleDateString('id-ID', { day: 'numeric', month: 'short' })
}

function formatTime(timeStr) {
  if (!timeStr) return '-'
  const date = new Date(timeStr)
  if (isNaN(date)) return timeStr
  return date.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }) + ' - ' + date.toLocaleDateString('id-ID', { day: 'numeric', month: 'short' })
}

function formatBirthday(dob) {
  if (!dob) return '-'
  const parts = dob.split('-')
  if (parts.length === 3) {
    const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des']
    const day = parseInt(parts[2], 10)
    const monthIdx = parseInt(parts[1], 10) - 1
    return `${day} ${monthNames[monthIdx] || ''}`
  }
  return dob
}

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
    const res = await get('/api/v1/supervisor/dashboard')
    stats.value = res.stats
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
