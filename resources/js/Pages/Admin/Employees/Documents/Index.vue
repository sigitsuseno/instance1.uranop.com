<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useApi } from '../../../../composables/useApi'
import { useAuth } from '../../../../composables/useAuth'
import { useNotificationStore } from '../../../../Stores/notification'
import BaseModal from '../../../../Components/BaseModal.vue'
import DocumentForm from './Form.vue'

const router = useRouter()
const { get, post } = useApi()
const notification = useNotificationStore()
const { isManajemen } = useAuth()
if (isManajemen.value) { router.replace('/admin/employees') }

const employees = ref([])
const documents = ref([])
const selectedEmployee = ref(null)
const stats = ref({ total: 0, pending: 0, verified: 0, expired: 0, expiring_soon: 0 })
const searchEmployee = ref('')
const loading = ref(false)

const showModal = ref(false)
const selectedDocument = ref(null)

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

async function fetchDocuments() {
  if (!selectedEmployee.value) return
  try {
    const params = {}
    if (typeFilter.value) params.document_type = typeFilter.value
    if (statusFilter.value) params.verification_status = statusFilter.value
    const res = await get(`/api/v1/employees/${selectedEmployee.value.id}/documents`, { params })
    documents.value = res.data || []
    stats.value = res.meta?.stats || { total: 0, pending: 0, verified: 0, expired: 0, expiring_soon: 0 }
  } catch (e) {
    notification.error('Gagal memuat dokumen')
    documents.value = []
  }
}

function selectEmployee(employee) {
  selectedEmployee.value = employee
  typeFilter.value = ''
  statusFilter.value = ''
  fetchDocuments()
}

function resetEmployee() {
  selectedEmployee.value = null
  documents.value = []
  stats.value = { total: 0, pending: 0, verified: 0, expired: 0, expiring_soon: 0 }
}

function applyFilters() {
  fetchDocuments()
}

function openCreateModal() {
  selectedDocument.value = null
  showModal.value = true
}

function openEditModal(doc) {
  selectedDocument.value = doc
  showModal.value = true
}

async function verifyDocument(doc, status) {
  if (!confirm(`Yakin ingin ${status === 'verified' ? 'memverifikasi' : 'menolak'} dokumen ini?`)) return
  try {
    await post(`/api/v1/employees/${selectedEmployee.value.id}/documents/${doc.id}/verify`, { status })
    notification.success(`Dokumen berhasil ${status === 'verified' ? 'diverifikasi' : 'ditolak'}`)
    fetchDocuments()
  } catch (e) {
    notification.error('Gagal memproses dokumen')
  }
}

function downloadDocument(doc) {
  window.open(`/api/v1/employees/${selectedEmployee.value.id}/documents/${doc.id}/download`, '_blank')
}

function handleFormSuccess() {
  showModal.value = false
  fetchDocuments()
}

function goToEmployeeDetail(id) {
  router.push(`/admin/employees/${id}`)
}

function formatDate(date) {
  if (!date) return '-'
  return new Date(date).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' })
}

function formatFileSize(bytes) {
  if (!bytes) return '-'
  const units = ['B', 'KB', 'MB', 'GB']
  let size = bytes
  let unit = 0
  while (size >= 1024 && unit < units.length - 1) { size /= 1024; unit++ }
  return `${size.toFixed(1)} ${units[unit]}`
}

function getDocumentTypeLabel(type) {
  const labels = {
    'ktp': 'KTP', 'kk': 'Kartu Keluarga', 'npwp': 'NPWP', 'bpjs': 'BPJS',
    'ijazah': 'Ijazah', 'transkrip': 'Transkrip', 'sertifikat': 'Sertifikat',
    'kontrak': 'Kontrak', 'sk': 'SK', 'other': 'Lainnya'
  }
  return labels[type] || type
}

function getStatusBadge(status) {
  const classes = {
    'pending': 'bg-amber-100 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400',
    'verified': 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400',
    'rejected': 'bg-red-100 text-red-700 dark:bg-red-500/10 dark:text-red-400'
  }
  return classes[status] || 'bg-slate-100 text-slate-700 dark:bg-slate-500/10 dark:text-slate-400'
}

function getStatusIcon(status) {
  const icons = { 'pending': 'bx-time', 'verified': 'bx-check-circle', 'rejected': 'bx-x-circle' }
  return icons[status] || 'bx-file'
}

function getDocumentTypeIcon(type) {
  const icons = { 'ktp': 'bx-id-card', 'kk': 'bx-group', 'npwp': 'bx-tax', 'bpjs': 'bx-health', 'ijazah': 'bx-certification' }
  return icons[type] || 'bx-file'
}

onMounted(() => { fetchEmployees() })
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-(--text-main)">Dokumen Karyawan</h1>
        <p class="text-(--text-muted) mt-1">Kelola dokumen-dokumen karyawan</p>
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

    <!-- Step 2: Tampilkan Dokumen -->
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
              class="px-3 py-1.5 border border-(--border-strong) rounded-md text-sm text-(--text-muted) hover:bg-(--bg-elevated) transition-all">
              <i class="bx bx-chevron-left mr-1"></i> Ganti Karyawan
            </button>
            <button @click="openCreateModal"
              class="inline-flex items-center px-3 py-1.5 bg-(--primary) hover:bg-(--primary-hover) text-white text-sm font-medium rounded-md shadow-lg shadow-(--primary-glow) transition-all">
              <i class="bx bx-upload mr-1"></i> Upload Dokumen
            </button>
            <button @click="goToEmployeeDetail(selectedEmployee.id)"
              class="inline-flex items-center px-3 py-1.5 border border-(--border-strong) rounded-md text-sm text-(--text-main) hover:bg-(--bg-elevated) transition-all">
              <i class="bx bx-show mr-1"></i> Detail Karyawan
            </button>
          </div>
        </div>
      </div>

      <!-- Stats Cards -->
      <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-4">
        <div class="bg-(--bg-card) rounded-md border border-(--border-soft) p-4">
          <div class="flex items-center justify-between">
            <div><p class="text-xs font-medium text-(--text-muted) uppercase tracking-wider mb-1">Total</p><p class="text-2xl font-bold text-(--text-main)">{{ stats.total }}</p></div>
            <div class="w-10 h-10 bg-blue-500/10 rounded-md flex items-center justify-center"><i class="bx bx-folder text-blue-500 text-xl"></i></div>
          </div>
        </div>
        <div class="bg-(--bg-card) rounded-md border border-(--border-soft) p-4">
          <div class="flex items-center justify-between">
            <div><p class="text-xs font-medium text-(--text-muted) uppercase tracking-wider mb-1">Pending</p><p class="text-2xl font-bold text-amber-600 dark:text-amber-400">{{ stats.pending }}</p></div>
            <div class="w-10 h-10 bg-amber-500/10 rounded-md flex items-center justify-center"><i class="bx bx-time text-amber-500 text-xl"></i></div>
          </div>
        </div>
        <div class="bg-(--bg-card) rounded-md border border-(--border-soft) p-4">
          <div class="flex items-center justify-between">
            <div><p class="text-xs font-medium text-(--text-muted) uppercase tracking-wider mb-1">Verified</p><p class="text-2xl font-bold text-emerald-600 dark:text-emerald-400">{{ stats.verified }}</p></div>
            <div class="w-10 h-10 bg-emerald-500/10 rounded-md flex items-center justify-center"><i class="bx bx-check-circle text-emerald-500 text-xl"></i></div>
          </div>
        </div>
        <div class="bg-(--bg-card) rounded-md border border-(--border-soft) p-4">
          <div class="flex items-center justify-between">
            <div><p class="text-xs font-medium text-(--text-muted) uppercase tracking-wider mb-1">Expired</p><p class="text-2xl font-bold text-red-600 dark:text-red-400">{{ stats.expired }}</p></div>
            <div class="w-10 h-10 bg-red-500/10 rounded-md flex items-center justify-center"><i class="bx bx-error text-red-500 text-xl"></i></div>
          </div>
        </div>
        <div class="bg-(--bg-card) rounded-md border border-(--border-soft) p-4">
          <div class="flex items-center justify-between">
            <div><p class="text-xs font-medium text-(--text-muted) uppercase tracking-wider mb-1">Near Expire</p><p class="text-2xl font-bold text-orange-600 dark:text-orange-400">{{ stats.expiring_soon }}</p></div>
            <div class="w-10 h-10 bg-orange-500/10 rounded-md flex items-center justify-center"><i class="bx bx-alarm text-orange-500 text-xl"></i></div>
          </div>
        </div>
      </div>

      <!-- Filters -->
      <div class="bg-(--bg-card) rounded-md border border-(--border-soft) p-4 mb-4">
        <div class="flex flex-wrap gap-4">
          <div class="w-48">
            <select v-model="typeFilter" @change="applyFilters"
              class="w-full px-3 py-2 rounded-md bg-(--bg-elevated) border border-(--border-strong) text-(--text-main) focus:ring-2 focus:ring-(--primary-glow) focus:border-(--primary) outline-none transition-all">
              <option value="">Semua Tipe</option>
              <option value="ktp">KTP</option>
              <option value="kk">Kartu Keluarga</option>
              <option value="npwp">NPWP</option>
              <option value="bpjs">BPJS</option>
              <option value="ijazah">Ijazah</option>
              <option value="sertifikat">Sertifikat</option>
              <option value="kontrak">Kontrak</option>
            </select>
          </div>
          <div class="w-48">
            <select v-model="statusFilter" @change="applyFilters"
              class="w-full px-3 py-2 rounded-md bg-(--bg-elevated) border border-(--border-strong) text-(--text-main) focus:ring-2 focus:ring-(--primary-glow) focus:border-(--primary) outline-none transition-all">
              <option value="">Semua Status</option>
              <option value="pending">Pending</option>
              <option value="verified">Terverifikasi</option>
              <option value="rejected">Ditolak</option>
            </select>
          </div>
        </div>
      </div>

      <!-- Documents Grid -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <div v-for="doc in documents" :key="doc.id"
          class="bg-(--bg-card) rounded-md border border-(--border-soft) p-4 hover:shadow-md transition-all">
          <div class="flex items-start justify-between mb-3">
            <div class="flex items-center space-x-3">
              <div class="w-10 h-10 rounded-md bg-(--primary)/10 flex items-center justify-center">
                <i :class="`bx ${getDocumentTypeIcon(doc.document_type)} text-(--primary) text-xl`"></i>
              </div>
              <div>
                <h3 class="font-semibold text-(--text-main)">{{ doc.title }}</h3>
                <p class="text-xs text-(--text-muted)">{{ getDocumentTypeLabel(doc.document_type) }}</p>
              </div>
            </div>
            <button @click="openEditModal(doc)" class="p-1 text-(--text-muted) hover:text-(--primary) hover:bg-(--primary)/10 rounded transition-colors" title="Edit">
              <i class="bx bx-edit text-lg"></i>
            </button>
          </div>

          <div class="mb-3">
            <span class="px-2.5 py-0.5 text-[11px] font-medium rounded-md uppercase tracking-wider inline-flex items-center"
              :class="getStatusBadge(doc.verification_status)">
              <i :class="`bx ${getStatusIcon(doc.verification_status)} mr-1`"></i>
              {{ doc.verification_status_label || doc.verification_status }}
            </span>
          </div>

          <div class="space-y-2 text-sm">
            <div v-if="doc.document_number" class="flex justify-between">
              <span class="text-(--text-muted)">Nomor:</span>
              <span class="text-(--text-main) font-medium">{{ doc.document_number }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-(--text-muted)">Ukuran:</span>
              <span class="text-(--text-main)">{{ formatFileSize(doc.file_size) }}</span>
            </div>
            <div v-if="doc.issue_date" class="flex justify-between">
              <span class="text-(--text-muted)">Tanggal Terbit:</span>
              <span class="text-(--text-main)">{{ formatDate(doc.issue_date) }}</span>
            </div>
            <div v-if="doc.expiry_date" class="flex justify-between">
              <span class="text-(--text-muted)">Berlaku s/d:</span>
              <span class="text-(--text-main)" :class="{ 'text-red-600 dark:text-red-400 font-semibold': doc.is_expired }">
                {{ formatDate(doc.expiry_date) }}
                <span v-if="doc.is_expired" class="ml-1 text-xs">(Kadaluarsa)</span>
              </span>
            </div>
          </div>

          <div class="mt-4 pt-3 border-t border-(--border-soft) flex justify-between items-center">
            <button @click="downloadDocument(doc)" class="text-xs text-(--primary) hover:underline inline-flex items-center">
              <i class="bx bx-download mr-1"></i> Download
            </button>
            <div class="flex space-x-2">
              <button v-if="doc.verification_status === 'pending'"
                @click="verifyDocument(doc, 'verified')" class="text-xs text-emerald-600 dark:text-emerald-400 font-medium hover:underline">
                Verifikasi
              </button>
              <button v-if="doc.verification_status === 'pending'"
                @click="verifyDocument(doc, 'rejected')" class="text-xs text-red-600 dark:text-red-400 font-medium hover:underline">
                Tolak
              </button>
            </div>
          </div>
        </div>

        <div v-if="!documents?.length" class="col-span-full text-center py-12">
          <div class="w-20 h-20 mx-auto bg-(--bg-elevated) rounded-full flex items-center justify-center mb-4">
            <i class="bx bx-folder-open text-4xl text-(--text-soft)"></i>
          </div>
          <h3 class="text-lg font-medium text-(--text-main) mb-2">Belum Ada Dokumen</h3>
          <p class="text-(--text-muted) mb-4">Belum ada dokumen untuk karyawan ini</p>
          <button @click="openCreateModal"
            class="inline-flex items-center px-4 py-2 bg-(--primary) hover:bg-(--primary-hover) text-white rounded-md shadow-lg shadow-(--primary-glow) transition-all">
            <i class="bx bx-upload mr-2"></i> Upload Dokumen Pertama
          </button>
        </div>
      </div>
    </div>

    <!-- Modal Form -->
    <BaseModal :show="showModal" title="Dokumen" size="lg" @close="showModal = false">
      <DocumentForm :document="selectedDocument" :employee="selectedEmployee" @close="showModal = false" @success="handleFormSuccess" />
    </BaseModal>
  </div>
</template>
