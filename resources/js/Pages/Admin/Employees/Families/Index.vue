<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useApi } from '../../../../composables/useApi'
import { useAuth } from '../../../../composables/useAuth'
import { useNotificationStore } from '../../../../Stores/notification'
import BaseModal from '../../../../Components/BaseModal.vue'
import FamilyForm from './Form.vue'

const router = useRouter()
const { get, destroy } = useApi()
const notification = useNotificationStore()
const { isManajemen } = useAuth()
if (isManajemen.value) { router.replace('/admin/employees') }

// State
const employees = ref([])
const families = ref([])
const selectedEmployee = ref(null)
const stats = ref({ total: 0, dependents: 0, emergency_contacts: 0, children: 0 })
const searchEmployee = ref('')
const loading = ref(false)

// Modal
const showModal = ref(false)
const selectedFamily = ref(null)

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

async function fetchFamilies() {
  if (!selectedEmployee.value) return
  try {
    const res = await get(`/api/v1/employees/${selectedEmployee.value.id}/families`)
    families.value = res.data || []
    stats.value = res.meta?.stats || { total: 0, dependents: 0, emergency_contacts: 0, children: 0 }
  } catch (e) {
    notification.error('Gagal memuat data keluarga')
    families.value = []
  }
}

function selectEmployee(employee) {
  selectedEmployee.value = employee
  fetchFamilies()
}

function resetEmployee() {
  selectedEmployee.value = null
  families.value = []
  stats.value = { total: 0, dependents: 0, emergency_contacts: 0, children: 0 }
}

function openCreateModal() {
  selectedFamily.value = null
  showModal.value = true
}

function openEditModal(family) {
  selectedFamily.value = family
  showModal.value = true
}

async function handleDelete(id) {
  if (!confirm('Yakin ingin menghapus data keluarga ini?')) return
  try {
    await destroy(`/api/v1/employees/${selectedEmployee.value.id}/families/${id}`)
    notification.success('Data keluarga berhasil dihapus')
    fetchFamilies()
  } catch (e) {
    notification.error('Gagal menghapus data')
  }
}

function handleFormSuccess() {
  showModal.value = false
  fetchFamilies()
}

function goToEmployeeDetail(id) {
  router.push(`/admin/employees/${id}`)
}

function formatDate(date) {
  if (!date) return '-'
  return new Date(date).toLocaleDateString('id-ID', {
    day: 'numeric', month: 'long', year: 'numeric'
  })
}

function getRelationLabel(relation) {
  const labels = {
    'spouse': 'Suami/Istri',
    'child': 'Anak',
    'parent': 'Orang Tua',
    'sibling': 'Saudara',
    'other': 'Lainnya'
  }
  return labels[relation] || relation
}

function getRelationIcon(relation) {
  const icons = {
    'spouse': 'bx-heart',
    'child': 'bx-child',
    'parent': 'bx-user',
    'sibling': 'bx-group',
    'other': 'bx-user-pin'
  }
  return icons[relation] || 'bx-user'
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
        <h1 class="text-2xl font-bold text-(--text-main)">Keluarga Karyawan</h1>
        <p class="text-(--text-muted) mt-1">Kelola data keluarga dan tanggungan karyawan</p>
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

    <!-- Step 2: Tampilkan Keluarga -->
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
              Tambah Keluarga
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
              <p class="text-xs font-medium text-(--text-muted) uppercase tracking-wider mb-1">Total</p>
              <p class="text-2xl font-bold text-(--text-main)">{{ stats.total }}</p>
            </div>
            <div class="w-10 h-10 bg-blue-500/10 rounded-md flex items-center justify-center">
              <i class="bx bx-group text-blue-500 text-xl"></i>
            </div>
          </div>
        </div>
        <div class="bg-(--bg-card) rounded-md border border-(--border-soft) p-4">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-xs font-medium text-(--text-muted) uppercase tracking-wider mb-1">Dependents</p>
              <p class="text-2xl font-bold text-emerald-600 dark:text-emerald-400">{{ stats.dependents }}</p>
            </div>
            <div class="w-10 h-10 bg-emerald-500/10 rounded-md flex items-center justify-center">
              <i class="bx bx-tax text-emerald-500 text-xl"></i>
            </div>
          </div>
        </div>
        <div class="bg-(--bg-card) rounded-md border border-(--border-soft) p-4">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-xs font-medium text-(--text-muted) uppercase tracking-wider mb-1">Emergency</p>
              <p class="text-2xl font-bold text-red-600 dark:text-red-400">{{ stats.emergency_contacts }}</p>
            </div>
            <div class="w-10 h-10 bg-red-500/10 rounded-md flex items-center justify-center">
              <i class="bx bx-phone-call text-red-500 text-xl"></i>
            </div>
          </div>
        </div>
        <div class="bg-(--bg-card) rounded-md border border-(--border-soft) p-4">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-xs font-medium text-(--text-muted) uppercase tracking-wider mb-1">Children</p>
              <p class="text-2xl font-bold text-indigo-600 dark:text-indigo-400">{{ stats.children }}</p>
            </div>
            <div class="w-10 h-10 bg-indigo-500/10 rounded-md flex items-center justify-center">
              <i class="bx bx-child text-indigo-500 text-xl"></i>
            </div>
          </div>
        </div>
      </div>

      <!-- Family Cards Grid -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <div v-for="family in families" :key="family.id"
          class="bg-(--bg-card) rounded-md border border-(--border-soft) p-4 hover:shadow-md transition-all">

          <div class="flex items-start justify-between mb-3">
            <div class="flex items-center space-x-3">
              <div class="w-10 h-10 rounded-md bg-(--primary)/10 flex items-center justify-center">
                <i :class="`bx ${getRelationIcon(family.relation)} text-(--primary) text-xl`"></i>
              </div>
              <div>
                <h3 class="font-semibold text-(--text-main)">{{ family.name }}</h3>
                <p class="text-xs text-(--text-muted)">{{ getRelationLabel(family.relation) }}</p>
              </div>
            </div>
            <div class="flex space-x-1">
              <button @click="openEditModal(family)"
                class="p-1 text-(--text-muted) hover:text-(--primary) hover:bg-(--primary)/10 rounded transition-colors" title="Edit">
                <i class="bx bx-edit text-lg"></i>
              </button>
              <button @click="handleDelete(family.id)"
                class="p-1 text-(--text-muted) hover:text-red-500 hover:bg-red-500/10 rounded transition-colors" title="Hapus">
                <i class="bx bx-trash text-lg"></i>
              </button>
            </div>
          </div>

          <div class="space-y-2 text-sm">
            <div class="flex justify-between">
              <span class="text-(--text-muted)">Jenis Kelamin:</span>
              <span class="text-(--text-main)">{{ family.gender === 'L' ? 'Laki-laki' : 'Perempuan' }}</span>
            </div>
            <div v-if="family.nik" class="flex justify-between">
              <span class="text-(--text-muted)">NIK:</span>
              <span class="text-(--text-main)">{{ family.nik }}</span>
            </div>
            <div v-if="family.date_of_birth" class="flex justify-between">
              <span class="text-(--text-muted)">Tanggal Lahir:</span>
              <span class="text-(--text-main)">{{ formatDate(family.date_of_birth) }}</span>
            </div>
            <div v-if="family.education" class="flex justify-between">
              <span class="text-(--text-muted)">Pendidikan:</span>
              <span class="text-(--text-main)">{{ family.education }}</span>
            </div>
            <div v-if="family.occupation" class="flex justify-between">
              <span class="text-(--text-muted)">Pekerjaan:</span>
              <span class="text-(--text-main)">{{ family.occupation }}</span>
            </div>
            <div v-if="family.emergency_phone" class="flex justify-between">
              <span class="text-(--text-muted)">Telepon:</span>
              <span class="text-(--text-main)">{{ family.emergency_phone }}</span>
            </div>
          </div>

          <div class="mt-3 pt-3 border-t border-(--border-soft) flex flex-wrap gap-2">
            <span v-if="family.is_dependent"
              class="px-2 py-0.5 text-[11px] font-medium bg-blue-100 text-blue-700 dark:bg-blue-500/10 dark:text-blue-400 rounded-md uppercase tracking-wider">
              <i class="bx bx-tax mr-1"></i> Tanggungan
            </span>
            <span v-if="family.is_emergency_contact"
              class="px-2 py-0.5 text-[11px] font-medium bg-red-100 text-red-700 dark:bg-red-500/10 dark:text-red-400 rounded-md uppercase tracking-wider">
              <i class="bx bx-phone-call mr-1"></i> Kontak Darurat
            </span>
          </div>

          <div v-if="family.is_emergency_contact && family.emergency_phone"
            class="mt-2 bg-red-500/10 border border-red-500/20 p-2 rounded-md">
            <div class="flex items-center text-xs">
              <i class="bx bx-phone-call text-red-500 mr-1"></i>
              <span class="text-red-600 dark:text-red-400 font-semibold">{{ family.emergency_phone }}</span>
            </div>
          </div>
        </div>

        <div v-if="!families?.length" class="col-span-full text-center py-12">
          <div class="w-20 h-20 mx-auto bg-(--bg-elevated) rounded-full flex items-center justify-center mb-4">
            <i class="bx bx-heart text-4xl text-(--text-soft)"></i>
          </div>
          <h3 class="text-lg font-medium text-(--text-main) mb-2">Belum Ada Data Keluarga</h3>
          <p class="text-(--text-muted) mb-4">Belum ada anggota keluarga untuk karyawan ini</p>
          <button @click="openCreateModal"
            class="inline-flex items-center px-4 py-2 bg-(--primary) hover:bg-(--primary-hover) text-white rounded-md shadow-lg shadow-(--primary-glow) transition-all">
            <i class="bx bx-plus mr-2"></i>
            Tambah Anggota Keluarga
          </button>
        </div>
      </div>
    </div>

    <!-- Modal Form -->
    <BaseModal :show="showModal" title="Data Keluarga" size="lg" @close="showModal = false">
      <FamilyForm :family="selectedFamily" :employee="selectedEmployee" @close="showModal = false" @success="handleFormSuccess" />
    </BaseModal>
  </div>
</template>
