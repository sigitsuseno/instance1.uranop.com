<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-(--text-main)">Keanggotaan BPJS</h1>
        <p class="text-sm text-(--text-muted) mt-1">
          Data keanggotaan BPJS per karyawan — atur status & generate iuran
        </p>
      </div>
      <div class="flex gap-2">
        <BaseButton variant="primary" @click="openAddModal" v-if="selectedEmployee === null" :disabled="isManajemen">
          <template #icon-left>
            <span class="text-lg">+</span>
          </template>
          Tambah BPJS
        </BaseButton>
        <BaseButton variant="amber" :loading="isGenerating" @click="handleGenerate" :disabled="isManajemen">
          <template #icon-left>
            <span class="text-lg">⚡</span>
          </template>
          Generate Iuran
        </BaseButton>
      </div>
    </div>

    <!-- Filter Bar -->
    <div class="bg-(--bg-card) border border-(--border-soft) rounded-xl p-4 mb-6">
      <div class="flex gap-4">
        <div class="flex-1 relative">
          <input v-model="search" type="text" placeholder="Cari nama, NIP, atau kode..."
            class="w-full pl-10 pr-4 py-2 bg-(--bg-input) border border-(--border-soft) rounded-lg text-sm"
            @input="fetchData" />
          <span class="absolute left-3 top-2.5 text-(--text-muted)">🔍</span>
        </div>
        <select v-model="payPeriodId" @change="fetchData"
          class="bg-(--bg-input) border border-(--border-soft) rounded-lg px-3 py-2 text-sm">
          <option value="">Semua Periode</option>
          <option v-for="p in payPeriods" :key="p.id" :value="p.id">{{ p.name }}</option>
        </select>
      </div>
    </div>

    <!-- Table -->
    <BaseCard>
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="border-b border-(--border-soft)">
            <tr class="text-left text-xs uppercase text-(--text-muted) tracking-wider">
              <th class="p-3 font-medium w-[50px]">#</th>
              <th class="p-3 font-medium">Nama</th>
              <th class="p-3 font-medium">No. BPJS TK</th>
              <th class="p-3 font-medium">No. BPJS KES</th>
              <th class="p-3 font-medium text-center w-[80px]">BPJS TK</th>
              <th class="p-3 font-medium text-center w-[80px]">BPJS KES</th>
              <th class="p-3 font-medium text-center w-[80px]">BPJS PEN</th>
              <th class="p-3 font-medium text-center w-[100px]">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-(--border-soft)">
            <tr v-if="loading">
              <td colspan="8" class="p-8 text-center text-(--text-muted)">Memuat data...</td>
            </tr>
            <tr v-else-if="employees.length === 0">
              <td colspan="8" class="p-8 text-center text-(--text-muted)">Belum ada data BPJS.</td>
            </tr>
            <tr v-for="(emp, idx) in employees" :key="emp.id" class="hover:bg-(--bg-hover)">
              <td class="p-3">{{ (page - 1) * perPage + idx + 1 }}</td>
              <td class="p-3">
                <div class="font-medium text-(--text-main)">{{ emp.name }}</div>
                <div class="text-xs text-(--text-muted)">{{ emp.employee_code }}</div>
              </td>
              <td class="p-3">
                <span v-if="emp.bpjs_ketenagakerjaan_no" class="text-sm">{{ emp.bpjs_ketenagakerjaan_no }}</span>
                <span v-else class="text-xs text-(--text-muted) italic">-</span>
              </td>
              <td class="p-3">
                <span v-if="emp.bpjs_kesehatan_no" class="text-sm">{{ emp.bpjs_kesehatan_no }}</span>
                <span v-else class="text-xs text-(--text-muted) italic">-</span>
              </td>
              <td class="p-3 text-center">
                <input type="checkbox" :checked="emp.has_bpjs_tk"
                  @change="toggleCheckbox(emp, 'has_bpjs_tk', $event)"
                  class="w-4 h-4 rounded accent-(--primary) cursor-pointer" :disabled="isManajemen" />
              </td>
              <td class="p-3 text-center">
                <input type="checkbox" :checked="emp.has_bpjs_ks"
                  @change="toggleCheckbox(emp, 'has_bpjs_ks', $event)"
                  class="w-4 h-4 rounded accent-(--primary) cursor-pointer" :disabled="isManajemen" />
              </td>
              <td class="p-3 text-center">
                <input type="checkbox" :checked="emp.has_bpjs_pen"
                  @change="toggleCheckbox(emp, 'has_bpjs_pen', $event)"
                  class="w-4 h-4 rounded accent-(--primary) cursor-pointer" :disabled="isManajemen" />
              </td>
              <td class="p-3 text-center">
                <div class="flex justify-center gap-1">
                  <button v-if="emp.bpjs_id" @click="openEditModal(emp)"
                    class="p-1.5 rounded hover:bg-(--bg-hover) text-(--text-muted) hover:text-(--primary) disabled:opacity-50 disabled:cursor-not-allowed"
                    title="Edit BPJS" :disabled="isManajemen">
                    ✏️
                  </button>
                  <button v-else @click="openAddForEmployee(emp)"
                    class="p-1.5 rounded hover:bg-(--bg-hover) text-(--text-muted) hover:text-green-500 disabled:opacity-50 disabled:cursor-not-allowed"
                    title="Tambah BPJS" :disabled="isManajemen">
                    ＋
                  </button>
                  <button v-if="emp.bpjs_id" @click="confirmDelete(emp)"
                    class="p-1.5 rounded hover:bg-red-50 text-(--text-muted) hover:text-red-500 disabled:opacity-50 disabled:cursor-not-allowed"
                    title="Hapus BPJS" :disabled="isManajemen">
                    🗑
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="totalPages > 1" class="flex items-center justify-between p-4 border-t border-(--border-soft)">
        <span class="text-xs text-(--text-muted)">
          {{ total }} data &middot; Halaman {{ page }} dari {{ totalPages }}
        </span>
        <div class="flex gap-1">
          <button v-for="p in visiblePages" :key="p" @click="goToPage(p)"
            class="px-3 py-1 rounded text-xs" :class="p === page
              ? 'bg-(--primary) text-white'
              : 'bg-(--bg-input) text-(--text-main) hover:bg-(--border-soft)'">
            {{ p }}
          </button>
        </div>
      </div>
    </BaseCard>

    <!-- Modal Form -->
    <BaseModal :show="showModal" @close="closeModal" :title="formTitle">
      <div class="space-y-4 p-2">
        <!-- Nama (readonly) -->
        <div v-if="formData.employee_name">
          <label class="block text-xs font-medium text-(--text-muted) mb-1">Karyawan</label>
          <div class="text-sm font-medium">{{ formData.employee_name }} ({{ formData.employee_code }})</div>
        </div>

        <!-- Checkbox Keanggotaan -->
        <div>
          <label class="block text-xs font-medium mb-2">Keanggotaan</label>
          <div class="flex gap-6">
            <label class="flex items-center gap-2 cursor-pointer">
              <input type="checkbox" v-model="formData.has_bpjs_tk"
                class="w-4 h-4 rounded accent-(--primary)" />
              <span class="text-sm">BPJS TK</span>
            </label>
            <label class="flex items-center gap-2 cursor-pointer">
              <input type="checkbox" v-model="formData.has_bpjs_ks"
                class="w-4 h-4 rounded accent-(--primary)" />
              <span class="text-sm">BPJS KES</span>
            </label>
            <label class="flex items-center gap-2 cursor-pointer">
              <input type="checkbox" v-model="formData.has_bpjs_pen"
                class="w-4 h-4 rounded accent-(--primary)" />
              <span class="text-sm">BPJS PEN</span>
            </label>
          </div>
        </div>

        <!-- Tunjangan -->
        <div class="border-t border-(--border-soft) pt-4">
          <p class="text-xs font-medium mb-2">Komponen Dasar Perhitungan</p>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-xs mb-1">TJ. Masa Kerja</label>
              <input v-model.number="formData.tj_masa_kerja" type="number" min="0"
                class="w-full bg-(--bg-input) border border-(--border-soft) rounded-lg px-3 py-2 text-sm" />
            </div>
            <div>
              <label class="block text-xs mb-1">Tunjangan</label>
              <input v-model.number="formData.tunjangan" type="number" min="0"
                class="w-full bg-(--bg-input) border border-(--border-soft) rounded-lg px-3 py-2 text-sm" />
            </div>
          </div>
        </div>

        <!-- Notes -->
        <div>
          <label class="block text-xs font-medium mb-1">Catatan</label>
          <textarea v-model="formData.notes" rows="2" placeholder="Opsional"
            class="w-full bg-(--bg-input) border border-(--border-soft) rounded-lg px-3 py-2 text-sm resize-none"></textarea>
        </div>
      </div>

      <template #footer>
        <div class="flex justify-end gap-2 p-4 border-t border-(--border-soft)">
          <BaseButton variant="secondary" @click="closeModal">Batal</BaseButton>
          <BaseButton variant="primary" :loading="saving" @click="save">{{ saving ? 'Menyimpan...' : 'Simpan' }}</BaseButton>
        </div>
      </template>
    </BaseModal>

    <!-- Delete Confirmation -->
    <BaseModal :show="showDelete" @close="showDelete = false" title="Hapus BPJS">
      <p class="p-4 text-sm">Hapus data BPJS untuk <strong>{{ deleteTarget?.name }}</strong>? Data iuran yang sudah di-generate akan tetap ada.</p>
      <template #footer>
        <div class="flex justify-end gap-2 p-4 border-t border-(--border-soft)">
          <BaseButton variant="secondary" @click="showDelete = false">Batal</BaseButton>
          <BaseButton variant="danger" :loading="deleting" @click="doDelete">{{ deleting ? 'Menghapus...' : 'Hapus' }}</BaseButton>
        </div>
      </template>
    </BaseModal>

    <!-- Locked Warning Modal (logic_payroll_baru.md §5/#11) -->
    <BaseModal :show="showLockedModal" @close="showLockedModal = false" title="Payroll Terkunci">
      <div class="p-4 space-y-4">
        <p class="text-sm text-(--text-main)">
          Payroll telah di kunci, anda tidak bisa melakukan perubahan pada periode ini.
          Untuk melakukan perubahan, unlock payroll di halaman Gaji Karyawan terlebih dahulu.
        </p>
        <div class="flex justify-end gap-2">
          <BaseButton variant="secondary" @click="showLockedModal = false">Tutup</BaseButton>
          <BaseButton variant="primary" @click="goToGajiKaryawan">Unlock Payroll</BaseButton>
        </div>
      </div>
    </BaseModal>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useApi } from '@/composables/useApi'
import { useAuth } from '@/composables/useAuth'
import BaseCard from '@/Components/BaseCard.vue'
import BaseButton from '@/Components/BaseButton.vue'
import BaseModal from '@/Components/BaseModal.vue'

const router = useRouter()

const { get, post, put, destroy } = useApi()
const { isManajemen } = useAuth()

const employees = ref([])
const loading = ref(false)
const page = ref(1)
const perPage = 25
const total = ref(0)
const search = ref('')
const payPeriodId = ref('')
const payPeriods = ref([])

const showModal = ref(false)
const isEdit = ref(false)
const selectedEmployee = ref(null)
const saving = ref(false)
const showDelete = ref(false)
const deleteTarget = ref(null)
const deleting = ref(false)
const isGenerating = ref(false)
const showLockedModal = ref(false)

const formData = ref({
  employee_name: '',
  employee_code: '',
  has_bpjs_tk: true,
  has_bpjs_ks: true,
  has_bpjs_pen: true,
  tj_masa_kerja: 0,
  tunjangan: 0,
  notes: '',
})

const formTitle = computed(() => isEdit.value ? 'Edit Data BPJS' : 'Tambah Data BPJS')
const totalPages = computed(() => Math.ceil(total.value / perPage))
const visiblePages = computed(() => {
  const pages = []
  for (let i = 1; i <= totalPages.value; i++) pages.push(i)
  return pages.slice(Math.max(0, page.value - 3), Math.min(totalPages.value, page.value + 2))
})

async function fetchData() {
  loading.value = true
  try {
    const params = { page: page.value, per_page: perPage }
    if (search.value) params.search = search.value
    if (payPeriodId.value) params.pay_period_id = payPeriodId.value
    const res = await get(`/api/v1/bpjs/keanggotaan?${new URLSearchParams(params)}`)
    employees.value = res.data?.data || res.data || []
    total.value = res.data?.total || res.total || 0
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

async function fetchPayPeriods() {
  try {
    const res = await get('/api/v1/payroll/periods')
    payPeriods.value = res.data?.data || res.data || []
  } catch (e) { /* silent */ }
}

function openAddModal() {
  isEdit.value = false
  selectedEmployee.value = null
  resetForm()
  showModal.value = true
}

function openAddForEmployee(emp) {
  isEdit.value = false
  selectedEmployee.value = emp
  formData.value = {
    employee_name: emp.name,
    employee_code: emp.employee_code,
    has_bpjs_tk: true,
    has_bpjs_ks: true,
    has_bpjs_pen: true,
    tj_masa_kerja: 0,
    tunjangan: 0,
    notes: '',
  }
  showModal.value = true
}

function openEditModal(emp) {
  isEdit.value = true
  selectedEmployee.value = emp
  // Load full BPJS data
  get(`/api/v1/bpjs/keanggotaan/${emp.id}?bpjs_id=${emp.bpjs_id}`).then(res => {
    const d = res.data?.data || res.data
    if (d) {
      formData.value = {
        employee_name: emp.name,
        employee_code: emp.employee_code,
        has_bpjs_tk: d.has_bpjs_tk !== undefined ? !!d.has_bpjs_tk : true,
        has_bpjs_ks: d.has_bpjs_ks !== undefined ? !!d.has_bpjs_ks : true,
        has_bpjs_pen: d.has_bpjs_pen !== undefined ? !!d.has_bpjs_pen : true,
        tj_masa_kerja: parseFloat(d.tj_masa_kerja) || 0,
        tunjangan: parseFloat(d.tunjangan) || 0,
        notes: d.notes || '',
      }
    }
    showModal.value = true
  })
}

function resetForm() {
  formData.value = {
    employee_name: '',
    employee_code: '',
    has_bpjs_tk: true,
    has_bpjs_ks: true,
    has_bpjs_pen: true,
    tj_masa_kerja: 0,
    tunjangan: 0,
    notes: '',
  }
}

async function toggleCheckbox(emp, field, event) {
  const newVal = event.target.checked
  
  // Kalau belum ada bpjs_id, auto-create dulu
  if (!emp.bpjs_id) {
    try {
      const payload = {
        has_bpjs_tk: field === 'has_bpjs_tk' ? newVal : true,
        has_bpjs_ks: field === 'has_bpjs_ks' ? newVal : true,
        has_bpjs_pen: field === 'has_bpjs_pen' ? newVal : true,
      }
      // Sertakan pay_period_id kalau user udah pilih periode
      if (payPeriodId.value) payload.pay_period_id = payPeriodId.value
      const res = await post(`/api/v1/bpjs/keanggotaan/${emp.id}`, payload)
      emp.bpjs_id = res.data?.data?.id || res.data?.id
      emp.has_bpjs_tk = true
      emp.has_bpjs_ks = true
      emp.has_bpjs_pen = true
      // Override field yg baru diklik
      emp[field] = newVal
    } catch (e) {
      event.target.checked = !newVal
      alert('Gagal membuat data BPJS: ' + (e.message || 'error'))
    }
    return
  }
  
  // Optimistic update untuk yg sudah ada
  emp[field] = newVal
  try {
    await put(`/api/v1/bpjs/keanggotaan/${emp.id}`, { [field]: newVal, bpjs_id: emp.bpjs_id })
  } catch (e) {
    emp[field] = !newVal
    event.target.checked = !newVal
    alert('Gagal update: ' + (e.message || 'error'))
  }
}

function closeModal() {
  showModal.value = false
  selectedEmployee.value = null
}

async function save() {
  if (!isEdit.value && !selectedEmployee.value) return
  saving.value = true
  try {
    const payload = { ...formData.value }
    delete payload.employee_name
    delete payload.employee_code

    if (isEdit.value) {
      payload.bpjs_id = selectedEmployee.value.bpjs_id
      await put(`/api/v1/bpjs/keanggotaan/${selectedEmployee.value.id}`, payload)
    } else {
      await post(`/api/v1/bpjs/keanggotaan/${selectedEmployee.value.id}`, payload)
    }
    closeModal()
    fetchData()
  } catch (e) {
    alert(e.message || 'Gagal menyimpan')
  } finally {
    saving.value = false
  }
}

function confirmDelete(emp) {
  deleteTarget.value = emp
  showDelete.value = true
}

async function doDelete() {
  if (!deleteTarget.value) return
  deleting.value = true
  try {
    await destroy(`/api/v1/bpjs/keanggotaan/${deleteTarget.value.id}?bpjs_id=${deleteTarget.value.bpjs_id}`)
    showDelete.value = false
    deleteTarget.value = null
    fetchData()
  } catch (e) {
    alert(e.message || 'Gagal menghapus')
  } finally {
    deleting.value = false
  }
}

async function handleGenerate() {
  if (!payPeriodId.value) {
    alert('Pilih periode payroll dulu!')
    return
  }
  if (!confirm('Generate iuran BPJS untuk periode ini?')) return
  isGenerating.value = true
  try {
    const res = await post('/api/v1/bpjs/generate-iuran', { pay_period_id: payPeriodId.value })
    alert(res.message || 'Berhasil generate iuran')
    fetchData()
  } catch (e) {
    if (e.response?.status === 403) {
      showLockedModal.value = true
    } else {
      alert(e.message || 'Gagal generate')
    }
  } finally {
    isGenerating.value = false
  }
}

function goToGajiKaryawan() {
  showLockedModal.value = false
  router.push('/admin/payroll/gaji-karyawan')
}

function goToPage(p) { page.value = p; fetchData() }

onMounted(() => {
  fetchData()
  fetchPayPeriods()
})
</script>
