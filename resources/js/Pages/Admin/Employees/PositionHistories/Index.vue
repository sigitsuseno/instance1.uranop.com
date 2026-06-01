<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useApi } from '../../../../composables/useApi'
import { useNotificationStore } from '../../../../Stores/notification'
import BaseModal from '../../../../Components/BaseModal.vue'
import BaseButton from '../../../../Components/BaseButton.vue'
import BaseCard from '../../../../Components/BaseCard.vue'
import PositionHistoryForm from './Form.vue'

const router = useRouter()
const { get } = useApi()
const notification = useNotificationStore()

// State
const employees = ref([])
const histories = ref([])
const selectedEmployee = ref(null)
const stats = ref({ total_changes: 0, promotions: 0, transfers: 0, demotions: 0 })
const searchEmployee = ref('')
const loading = ref(false)
const loadingHistories = ref(false)

// Modal
const showModal = ref(false)
const selectedHistory = ref(null)

// Filter employees berdasarkan search
const filteredEmployees = computed(() => {
  if (!searchEmployee.value) return employees.value
  const s = searchEmployee.value.toLowerCase()
  return employees.value.filter(emp =>
    emp.name.toLowerCase().includes(s) ||
    (emp.code && emp.code.toLowerCase().includes(s))
  )
})

// Fetch employees list for search
async function fetchEmployees() {
  try {
    const res = await get('/api/v1/employees/options')
    employees.value = res.data || []
  } catch (e) {
    notification.error('Gagal memuat data karyawan')
  }
}

// Fetch position histories for selected employee
async function fetchHistories() {
  if (!selectedEmployee.value) return
  loadingHistories.value = true
  try {
    const res = await get(`/api/v1/employees/${selectedEmployee.value.id}/position-histories`)
    histories.value = res.data || []
    stats.value = res.meta?.stats || { total_changes: 0, promotions: 0, transfers: 0, demotions: 0 }
  } catch (e) {
    notification.error('Gagal memuat riwayat pekerjaan')
    histories.value = []
  } finally {
    loadingHistories.value = false
  }
}

// Pilih karyawan
function selectEmployee(employee) {
  selectedEmployee.value = employee
  fetchHistories()
}

// Reset pilihan
function resetEmployee() {
  selectedEmployee.value = null
  histories.value = []
  stats.value = { total_changes: 0, promotions: 0, transfers: 0, demotions: 0 }
}

// Buka modal tambah
function openCreateModal() {
  selectedHistory.value = null
  showModal.value = true
}

// Buka modal edit
function openEditModal(history) {
  selectedHistory.value = history
  showModal.value = true
}

// Handle form success
function handleFormSuccess() {
  showModal.value = false
  fetchHistories()
}

// Helpers
function formatDate(date) {
  if (!date) return '-'
  return new Date(date).toLocaleDateString('id-ID', {
    day: 'numeric', month: 'long', year: 'numeric'
  })
}

function formatCurrency(value) {
  if (!value) return '-'
  return new Intl.NumberFormat('id-ID', {
    style: 'currency', currency: 'IDR', minimumFractionDigits: 0
  }).format(value)
}

function getReasonLabel(reason) {
  const labels = {
    'promotion': 'Promosi',
    'demotion': 'Demosi',
    'transfer': 'Mutasi',
    'rotation': 'Rotasi',
    'upgrade': 'Upgrade',
    'restructuring': 'Restrukturisasi',
    'initial': 'Awal'
  }
  return labels[reason] || reason
}

function getReasonBadge(reason) {
  const classes = {
    'promotion': 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400',
    'demotion': 'bg-red-100 text-red-700 dark:bg-red-500/10 dark:text-red-400',
    'transfer': 'bg-blue-100 text-blue-700 dark:bg-blue-500/10 dark:text-blue-400',
    'rotation': 'bg-indigo-100 text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-400',
    'upgrade': 'bg-amber-100 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400',
    'restructuring': 'bg-slate-100 text-slate-700 dark:bg-slate-500/10 dark:text-slate-400',
    'initial': 'bg-slate-100 text-slate-700 dark:bg-slate-500/10 dark:text-slate-400'
  }
  return classes[reason] || 'bg-slate-100 text-slate-700 dark:bg-slate-500/10 dark:text-slate-400'
}

function goToEmployeeDetail(id) {
  router.push(`/admin/employees/${id}`)
}

onMounted(() => {
  fetchEmployees()
})
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-(--text-main)">Riwayat Pekerjaan</h1>
        <p class="text-(--text-muted) mt-1">Kelola riwayat perubahan posisi karyawan</p>
      </div>
    </div>

    <!-- Step 1: Pilih Karyawan -->
    <div v-if="!selectedEmployee" class="bg-(--bg-card) rounded-xl border border-(--border-soft) p-6">
      <h2 class="text-lg font-semibold text-(--text-main) mb-4">Pilih Karyawan</h2>

      <div class="relative mb-4">
        <i class="bx bx-search absolute left-3 top-1/2 -translate-y-1/2 text-(--text-muted)"></i>
        <input type="text" v-model="searchEmployee" placeholder="Cari karyawan..."
          class="w-full pl-10 pr-4 py-2 rounded-md bg-(--bg-elevated) border border-(--border-strong) text-(--text-main) focus:ring-2 focus:ring-(--primary-glow) focus:border-(--primary) outline-none transition-all">
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <button v-for="emp in filteredEmployees" :key="emp.id" @click="selectEmployee(emp)"
          class="flex items-center p-3 rounded-md border border-(--border-soft) hover:border-(--primary) hover:bg-(--primary)/5 transition-all text-left">
          <div class="w-10 h-10 rounded-md bg-(--primary)/10 flex items-center justify-center mr-3">
            <span class="text-(--primary) font-semibold">{{ emp.name.charAt(0) }}</span>
          </div>
          <div>
            <p class="font-medium text-(--text-main)">{{ emp.name }}</p>
            <p class="text-xs text-(--text-muted)">{{ emp.code }} - {{ emp.department?.name }}</p>
          </div>
        </button>
      </div>

      <div v-if="filteredEmployees.length === 0" class="text-center py-8">
        <i class="bx bx-user-x text-4xl text-(--text-muted) mb-2"></i>
        <p class="text-(--text-muted)">Tidak ada karyawan ditemukan</p>
      </div>
    </div>

    <!-- Step 2: Tampilkan Riwayat -->
    <div v-else>
      <!-- Header info karyawan -->
      <div class="bg-(--bg-card) rounded-md border border-(--border-soft) p-4 mb-4">
        <div class="flex items-center justify-between">
          <div class="flex items-center">
            <div class="w-12 h-12 rounded-md bg-(--primary)/10 flex items-center justify-center mr-4">
              <span class="text-(--primary) font-bold text-xl">{{ selectedEmployee.name.charAt(0) }}</span>
            </div>
            <div>
              <h3 class="font-semibold text-(--text-main)">{{ selectedEmployee.name }}</h3>
              <p class="text-sm text-(--text-muted)">{{ selectedEmployee.code }} - {{ selectedEmployee.department?.name }}</p>
            </div>
          </div>
          <div class="flex space-x-2">
            <button @click="resetEmployee"
              class="px-3 py-1.5 border border-(--border-strong) rounded-md text-sm text-(--text-muted) hover:bg-(--bg-elevated) transition-all">
              <i class="bx bx-chevron-left mr-1"></i>
              Ganti Karyawan
            </button>
            <button @click="openCreateModal"
              class="inline-flex items-center px-3 py-1.5 bg-(--primary) hover:bg-(--primary-hover) text-white text-sm font-medium rounded-md shadow-lg shadow-(--primary-glow) transition-all">
              <i class="bx bx-plus mr-1"></i>
              Tambah Riwayat
            </button>
            <button @click="goToEmployeeDetail(selectedEmployee.id)"
              class="inline-flex items-center px-3 py-1.5 border border-(--border-strong) rounded-md text-sm text-(--text-main) hover:bg-(--bg-elevated) transition-all">
              <i class="bx bx-show mr-1"></i>
              Detail Karyawan
            </button>
          </div>
        </div>
      </div>

      <!-- Stats Cards -->
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
        <div class="bg-(--bg-card) rounded-md border border-(--border-soft) p-4">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-xs font-medium text-(--text-muted) uppercase tracking-wider mb-1">Changes</p>
              <p class="text-2xl font-bold text-(--text-main)">{{ stats.total_changes }}</p>
            </div>
            <div class="w-10 h-10 bg-blue-500/10 rounded-md flex items-center justify-center">
              <i class="bx bx-history text-blue-500 text-xl"></i>
            </div>
          </div>
        </div>
        <div class="bg-(--bg-card) rounded-md border border-(--border-soft) p-4">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-xs font-medium text-(--text-muted) uppercase tracking-wider mb-1">Promotions</p>
              <p class="text-2xl font-bold text-emerald-600 dark:text-emerald-400">{{ stats.promotions }}</p>
            </div>
            <div class="w-10 h-10 bg-emerald-500/10 rounded-md flex items-center justify-center">
              <i class="bx bx-trending-up text-emerald-500 text-xl"></i>
            </div>
          </div>
        </div>
        <div class="bg-(--bg-card) rounded-md border border-(--border-soft) p-4">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-xs font-medium text-(--text-muted) uppercase tracking-wider mb-1">Transfers</p>
              <p class="text-2xl font-bold text-blue-600 dark:text-blue-400">{{ stats.transfers }}</p>
            </div>
            <div class="w-10 h-10 bg-blue-500/10 rounded-md flex items-center justify-center">
              <i class="bx bx-transfer text-blue-500 text-xl"></i>
            </div>
          </div>
        </div>
        <div class="bg-(--bg-card) rounded-md border border-(--border-soft) p-4">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-xs font-medium text-(--text-muted) uppercase tracking-wider mb-1">Demotions</p>
              <p class="text-2xl font-bold text-red-600 dark:text-red-400">{{ stats.demotions }}</p>
            </div>
            <div class="w-10 h-10 bg-red-500/10 rounded-md flex items-center justify-center">
              <i class="bx bx-trending-down text-red-500 text-xl"></i>
            </div>
          </div>
        </div>
      </div>

      <!-- Loading -->
      <div v-if="loadingHistories" class="text-center py-12">
        <i class="bx bx-loader-alt bx-spin text-3xl text-(--text-muted)"></i>
        <p class="text-(--text-muted) mt-2">Memuat riwayat...</p>
      </div>

      <!-- Timeline View -->
      <div v-else class="bg-(--bg-card) rounded-md border border-(--border-soft) p-6">
        <div class="relative">
          <div v-for="(history, index) in histories" :key="history.id"
            class="flex items-start space-x-4 pb-8 relative">

            <!-- Timeline line -->
            <div v-if="index < histories.length - 1"
              class="absolute left-5 top-8 bottom-0 w-0.5 bg-(--border-soft)"></div>

            <!-- Timeline dot -->
            <div
              class="w-10 h-10 rounded-md bg-(--primary)/10 flex items-center justify-center flex-shrink-0 relative z-10">
              <i class="bx bx-briefcase text-(--primary)"></i>
            </div>

            <!-- Content -->
            <div class="flex-1">
              <div class="bg-(--bg-elevated)/30 border border-(--border-soft) rounded-md p-4">
                <div class="flex items-center justify-between mb-2">
                  <span class="text-sm font-medium text-(--text-main)">
                    {{ formatDate(history.effective_date) }}
                  </span>
                  <span class="px-2 py-0.5 text-[11px] font-medium rounded-md uppercase tracking-wider"
                    :class="getReasonBadge(history.change_reason)">
                    {{ getReasonLabel(history.change_reason) }}
                  </span>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <p class="text-xs text-(--text-muted) mb-1">Posisi Lama</p>
                    <p class="text-sm font-medium text-(--text-main)">
                      {{ history.old_position?.name || '-' }}
                    </p>
                    <p class="text-xs text-(--text-muted) mt-1">
                      {{ history.old_department?.name }}
                    </p>
                    <p v-if="history.old_salary" class="text-xs text-(--text-muted) mt-1">
                      Gaji: {{ formatCurrency(history.old_salary) }}
                    </p>
                  </div>

                  <div>
                    <p class="text-xs text-(--text-muted) mb-1">Posisi Baru</p>
                    <p class="text-sm font-medium text-(--text-main)">
                      {{ history.new_position?.name || '-' }}
                    </p>
                    <p class="text-xs text-(--text-muted) mt-1">
                      {{ history.new_department?.name }}
                    </p>
                    <p v-if="history.new_salary" class="text-xs text-(--text-muted) mt-1">
                      Gaji: {{ formatCurrency(history.new_salary) }}
                    </p>
                  </div>
                </div>

                <div v-if="history.notes" class="mt-3 pt-3 border-t border-(--border-soft)">
                  <p class="text-xs text-(--text-muted) mb-1">Catatan:</p>
                  <p class="text-sm text-(--text-main)">{{ history.notes }}</p>
                </div>

                <div class="mt-3 flex justify-end space-x-2">
                  <button @click="openEditModal(history)"
                    class="text-xs text-(--primary) hover:underline">
                    Edit
                  </button>
                </div>
              </div>
            </div>
          </div>

          <!-- Empty State -->
          <div v-if="!histories?.length" class="text-center py-12">
            <div class="w-20 h-20 mx-auto bg-(--bg-elevated) rounded-full flex items-center justify-center mb-4">
              <i class="bx bx-history text-4xl text-(--text-soft)"></i>
            </div>
            <h3 class="text-lg font-medium text-(--text-main) mb-2">Belum Ada Riwayat</h3>
            <p class="text-(--text-muted) mb-4">Belum ada perubahan posisi untuk karyawan ini</p>
            <button @click="openCreateModal"
              class="inline-flex items-center px-4 py-2 bg-(--primary) hover:bg-(--primary-hover) text-white rounded-md shadow-lg shadow-(--primary-glow) transition-all">
              <i class="bx bx-plus mr-2"></i>
              Tambah Riwayat Pertama
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal Form -->
    <BaseModal :show="showModal" title="Riwayat Pekerjaan" size="lg" @close="showModal = false">
      <PositionHistoryForm
        :positionHistory="selectedHistory"
        :employee="selectedEmployee"
        @close="showModal = false"
        @success="handleFormSuccess" />
    </BaseModal>
  </div>
</template>
