<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useApi } from '../../../../composables/useApi'
import { useAuth } from '../../../../composables/useAuth'
import { useNotificationStore } from '../../../../Stores/notification'
import BaseModal from '../../../../Components/BaseModal.vue'
import TerminationForm from './Form.vue'

const router = useRouter()
const { get, post } = useApi()
const notification = useNotificationStore()
const { isManajemen } = useAuth()
if (isManajemen.value) { router.replace('/admin/employees') }

const employees = ref([])
const terminations = ref([])
const selectedEmployee = ref(null)
const stats = ref({ total: 0, pending: 0, approved: 0, resign: 0, fired: 0 })
const searchEmployee = ref('')
const loading = ref(false)

const showModal = ref(false)
const selectedTermination = ref(null)
const typeFilter = ref('')
const statusFilter = ref('')

const filteredEmployees = computed(() => {
  if (!searchEmployee.value) return employees.value
  const s = searchEmployee.value.toLowerCase()
  return employees.value.filter(emp =>
    emp.name.toLowerCase().includes(s) ||
    (emp.code && emp.code.toLowerCase().includes(s))
  )
})

async function fetchEmployees() {
  try {
    const res = await get('/api/v1/employees/options')
    employees.value = res.data || []
  } catch (e) {
    notification.error('Gagal memuat data karyawan')
  }
}

async function fetchTerminations() {
  if (!selectedEmployee.value) return
  try {
    const params = {}
    if (typeFilter.value) params.termination_type = typeFilter.value
    if (statusFilter.value) params.approval_status = statusFilter.value
    const res = await get(`/api/v1/employees/${selectedEmployee.value.id}/terminations`, { params })
    terminations.value = res.data || []
    stats.value = res.meta?.stats || { total: 0, pending: 0, approved: 0, resign: 0, fired: 0 }
  } catch (e) {
    notification.error('Gagal memuat data terminasi')
    terminations.value = []
  }
}

function selectEmployee(employee) {
  selectedEmployee.value = employee
  typeFilter.value = ''
  statusFilter.value = ''
  fetchTerminations()
}

function resetEmployee() {
  selectedEmployee.value = null
  terminations.value = []
  stats.value = { total: 0, pending: 0, approved: 0, resign: 0, fired: 0 }
}

function applyFilters() {
  fetchTerminations()
}

function openCreateModal() {
  selectedTermination.value = null
  showModal.value = true
}

function openEditModal(termination) {
  selectedTermination.value = termination
  showModal.value = true
}

async function approveTermination(termination) {
  if (!confirm('Yakin ingin menyetujui terminasi ini?')) return
  try {
    await post(`/api/v1/employees/${selectedEmployee.value.id}/terminations/${termination.id}/approve`, {})
    notification.success('Terminasi berhasil disetujui')
    fetchTerminations()
  } catch (e) {
    notification.error('Gagal menyetujui terminasi')
  }
}

async function rejectTermination(termination) {
  const reason = prompt('Alasan penolakan:')
  if (!reason) return
  try {
    await post(`/api/v1/employees/${selectedEmployee.value.id}/terminations/${termination.id}/reject`, { reason })
    notification.success('Terminasi ditolak')
    fetchTerminations()
  } catch (e) {
    notification.error('Gagal menolak terminasi')
  }
}

function handleFormSuccess() {
  showModal.value = false
  fetchTerminations()
}

function goToEmployeeDetail(id) {
  router.push(`/admin/employees/${id}`)
}

function formatDate(date) {
  if (!date) return '-'
  return new Date(date).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' })
}

function formatCurrency(value) {
  if (!value) return '-'
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(value)
}

function getTerminationTypeLabel(type) {
  const labels = { 'resign': 'Resign', 'retirement': 'Pensiun', 'fired': 'PHK', 'contract_end': 'Kontrak Habis', 'death': 'Meninggal Dunia', 'other': 'Lainnya' }
  return labels[type] || type
}

function getTerminationTypeIcon(type) {
  const icons = { 'resign': 'bx-log-out', 'retirement': 'bx-time', 'fired': 'bx-x-circle', 'contract_end': 'bx-file', 'death': 'bx-heart', 'other': 'bx-dots-horizontal' }
  return icons[type] || 'bx-exit'
}

function getApprovalStatusBadge(status) {
  const classes = {
    'pending': 'bg-amber-100 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400',
    'approved': 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400',
    'rejected': 'bg-red-100 text-red-700 dark:bg-red-500/10 dark:text-red-400'
  }
  return classes[status] || 'bg-slate-100 text-slate-700 dark:bg-slate-500/10 dark:text-slate-400'
}

function getApprovalStatusLabel(status) {
  const labels = { 'pending': 'Pending', 'approved': 'Disetujui', 'rejected': 'Ditolak' }
  return labels[status] || status
}

onMounted(() => { fetchEmployees() })
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-(--text-main)">Resign & PHK</h1>
        <p class="text-(--text-muted) mt-1">Kelola data karyawan resign, PHK, pensiun</p>
      </div>
    </div>

    <!-- Stats Cards (tampil hanya jika ada selected employee) -->
    <div v-if="selectedEmployee" class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-4">
      <div class="bg-(--bg-card) rounded-md border border-(--border-soft) p-4">
        <div class="flex items-center justify-between">
          <div><p class="text-sm text-(--text-muted)">Total Terminasi</p><p class="text-2xl font-bold text-(--text-main)">{{ stats.total }}</p></div>
          <div class="w-10 h-10 bg-blue-500/10 rounded-md flex items-center justify-center"><i class="bx bx-exit text-blue-600 dark:text-blue-400 text-xl"></i></div>
        </div>
      </div>
      <div class="bg-(--bg-card) rounded-md border border-(--border-soft) p-4">
        <div class="flex items-center justify-between">
          <div><p class="text-sm text-(--text-muted)">Pending</p><p class="text-2xl font-bold text-amber-600 dark:text-amber-400">{{ stats.pending }}</p></div>
          <div class="w-10 h-10 bg-amber-500/10 rounded-md flex items-center justify-center"><i class="bx bx-time text-amber-600 dark:text-amber-400 text-xl"></i></div>
        </div>
      </div>
      <div class="bg-(--bg-card) rounded-md border border-(--border-soft) p-4">
        <div class="flex items-center justify-between">
          <div><p class="text-sm text-(--text-muted)">Disetujui</p><p class="text-2xl font-bold text-emerald-600 dark:text-emerald-400">{{ stats.approved }}</p></div>
          <div class="w-10 h-10 bg-emerald-500/10 rounded-md flex items-center justify-center"><i class="bx bx-check-circle text-emerald-600 dark:text-emerald-400 text-xl"></i></div>
        </div>
      </div>
      <div class="bg-(--bg-card) rounded-md border border-(--border-soft) p-4">
        <div class="flex items-center justify-between">
          <div><p class="text-sm text-(--text-muted)">Resign</p><p class="text-2xl font-bold text-indigo-600 dark:text-indigo-400">{{ stats.resign }}</p></div>
          <div class="w-10 h-10 bg-indigo-500/10 rounded-md flex items-center justify-center"><i class="bx bx-log-out text-indigo-600 dark:text-indigo-400 text-xl"></i></div>
        </div>
      </div>
      <div class="bg-(--bg-card) rounded-md border border-(--border-soft) p-4">
        <div class="flex items-center justify-between">
          <div><p class="text-sm text-(--text-muted)">PHK</p><p class="text-2xl font-bold text-red-600 dark:text-red-400">{{ stats.fired }}</p></div>
          <div class="w-10 h-10 bg-red-500/10 rounded-md flex items-center justify-center"><i class="bx bx-x-circle text-red-600 dark:text-red-400 text-xl"></i></div>
        </div>
      </div>
    </div>

    <!-- Step 1: Pilih Karyawan -->
    <div v-if="!selectedEmployee" class="bg-(--bg-card) rounded-md border border-(--border-soft) p-6">
      <h2 class="text-lg font-semibold text-(--text-main) mb-4">Pilih Karyawan</h2>
      <div class="relative mb-4">
        <i class="bx bx-search absolute left-3 top-1/2 -translate-y-1/2 text-(--text-muted)"></i>
        <input type="text" v-model="searchEmployee" placeholder="Cari karyawan..."
          class="w-full pl-10 pr-4 py-2 rounded-md bg-(--bg-elevated) border border-(--border-strong) text-(--text-main) focus:ring-2 focus:ring-(--primary-glow) focus:border-(--primary) transition-all outline-none">
      </div>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <button v-for="emp in filteredEmployees" :key="emp.id" @click="selectEmployee(emp)"
          class="flex items-center p-3 rounded-md border border-(--border-soft) hover:border-(--primary) hover:bg-(--primary)/5 transition-all text-left outline-none group">
          <div class="w-10 h-10 rounded-md bg-(--primary)/10 flex items-center justify-center mr-3 group-hover:scale-110 transition-transform">
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

    <!-- Step 2: Tampilkan Terminasi -->
    <div v-else>
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
              class="px-3 py-1.5 border border-(--border-strong) rounded-md text-sm text-(--text-muted) hover:bg-(--bg-elevated) transition-all outline-none">
              <i class="bx bx-chevron-left mr-1"></i> Ganti Karyawan
            </button>
            <button @click="openCreateModal"
              class="inline-flex items-center px-3 py-1.5 bg-(--primary) hover:bg-(--primary-hover) text-white text-sm font-medium rounded-md shadow-lg shadow-(--primary-glow) transition-all outline-none">
              <i class="bx bx-plus mr-1"></i> Buat Terminasi
            </button>
            <button @click="goToEmployeeDetail(selectedEmployee.id)"
              class="inline-flex items-center px-3 py-1.5 border border-(--border-strong) rounded-md text-sm text-(--text-main) hover:bg-(--bg-elevated) transition-all">
              <i class="bx bx-show mr-1"></i> Detail Karyawan
            </button>
          </div>
        </div>
      </div>

      <!-- Filters -->
      <div class="bg-(--bg-card) rounded-md border border-(--border-soft) p-4 mb-4">
        <div class="flex flex-wrap gap-4">
          <div class="w-48">
            <select v-model="typeFilter" @change="applyFilters"
              class="w-full px-3 py-2 rounded-md bg-(--bg-elevated) border border-(--border-strong) text-(--text-main) focus:ring-2 focus:ring-(--primary-glow) focus:border-(--primary) transition-all outline-none">
              <option value="">Semua Jenis</option>
              <option value="resign">Resign</option>
              <option value="retirement">Pensiun</option>
              <option value="fired">PHK</option>
              <option value="contract_end">Kontrak Habis</option>
              <option value="death">Meninggal Dunia</option>
              <option value="other">Lainnya</option>
            </select>
          </div>
          <div class="w-48">
            <select v-model="statusFilter" @change="applyFilters"
              class="w-full px-3 py-2 rounded-md bg-(--bg-elevated) border border-(--border-strong) text-(--text-main) focus:ring-2 focus:ring-(--primary-glow) focus:border-(--primary) transition-all outline-none">
              <option value="">Semua Status</option>
              <option value="pending">Pending</option>
              <option value="approved">Disetujui</option>
              <option value="rejected">Ditolak</option>
            </select>
          </div>
        </div>
      </div>

      <!-- Terminations Table -->
      <div class="bg-(--bg-card) rounded-md border border-(--border-soft) overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
          <table class="w-full">
            <thead class="bg-(--bg-elevated)/50 border-b border-(--border-soft)">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-(--text-muted) uppercase">Jenis</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-(--text-muted) uppercase">Tanggal</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-(--text-muted) uppercase">Efektif</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-(--text-muted) uppercase">Alasan</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-(--text-muted) uppercase">Settlement</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-(--text-muted) uppercase">Clearance</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-(--text-muted) uppercase">Status</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-(--text-muted) uppercase">Aksi</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-(--border-soft)">
              <tr v-for="term in terminations" :key="term.id" class="hover:bg-(--bg-elevated)/30 transition-colors">
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="flex items-center">
                    <div class="w-8 h-8 rounded-md bg-(--primary)/10 flex items-center justify-center mr-2">
                      <i :class="`bx ${getTerminationTypeIcon(term.termination_type)} text-(--primary)`"></i>
                    </div>
                    <span class="text-sm font-medium text-(--text-main)">{{ getTerminationTypeLabel(term.termination_type) }}</span>
                  </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap"><span class="text-sm text-(--text-muted)">{{ formatDate(term.termination_date) }}</span></td>
                <td class="px-6 py-4 whitespace-nowrap"><span class="text-sm text-(--text-muted)">{{ formatDate(term.effective_date) }}</span></td>
                <td class="px-6 py-4"><span class="text-sm text-(--text-muted) line-clamp-2">{{ term.reason }}</span></td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span v-if="term.settlement_amount" class="text-sm font-medium text-(--text-main)">{{ formatCurrency(term.settlement_amount) }}</span>
                  <span v-else class="text-xs text-(--text-muted)">-</span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="flex items-center space-x-1" :title="`Asset: ${term.clearance_asset ? '✓' : '✗'}, Finance: ${term.clearance_finance ? '✓' : '✗'}, IT: ${term.clearance_it ? '✓' : '✗'}`">
                    <span class="text-xs" :class="term.clearance_asset ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'">
                      <i :class="term.clearance_asset ? 'bx bx-check' : 'bx bx-x'"></i>
                    </span>
                    <span class="text-xs" :class="term.clearance_finance ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'">
                      <i :class="term.clearance_finance ? 'bx bx-check' : 'bx bx-x'"></i>
                    </span>
                    <span class="text-xs" :class="term.clearance_it ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'">
                      <i :class="term.clearance_it ? 'bx bx-check' : 'bx bx-x'"></i>
                    </span>
                  </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span class="px-2 py-0.5 text-[11px] font-medium rounded-md uppercase tracking-wider" :class="getApprovalStatusBadge(term.approval_status)">
                    {{ getApprovalStatusLabel(term.approval_status) }}
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-right space-x-2">
                  <template v-if="term.approval_status === 'pending'">
                    <button @click="approveTermination(term)"
                      class="inline-flex items-center p-1.5 text-emerald-600 dark:text-emerald-400 hover:bg-emerald-500/10 rounded-md transition-all outline-none" title="Setujui">
                      <i class="bx bx-check-circle text-lg"></i>
                    </button>
                    <button @click="rejectTermination(term)"
                      class="inline-flex items-center p-1.5 text-red-600 dark:text-red-400 hover:bg-red-500/10 rounded-md transition-all outline-none" title="Tolak">
                      <i class="bx bx-x-circle text-lg"></i>
                    </button>
                  </template>
                  <button @click="openEditModal(term)"
                    class="inline-flex items-center p-1.5 text-(--text-muted) hover:text-(--primary) hover:bg-(--primary)/10 rounded-md transition-all outline-none" title="Edit">
                    <i class="bx bx-edit text-lg"></i>
                  </button>
                </td>
              </tr>
              <tr v-if="!terminations?.length">
                <td colspan="8" class="px-6 py-12 text-center text-(--text-muted)">
                  <div class="flex flex-col items-center">
                    <i class="bx bx-exit text-4xl mb-2"></i>
                    <p>Belum ada data terminasi untuk karyawan ini</p>
                    <button @click="openCreateModal" class="mt-2 text-(--primary) hover:underline">Buat terminasi pertama</button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Modal Form -->
    <BaseModal :show="showModal" title="Terminasi" size="lg" @close="showModal = false">
      <TerminationForm :termination="selectedTermination" :employee="selectedEmployee" @close="showModal = false" @success="handleFormSuccess" />
    </BaseModal>
  </div>
</template>
