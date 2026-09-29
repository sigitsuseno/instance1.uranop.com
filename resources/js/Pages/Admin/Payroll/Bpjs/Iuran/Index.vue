<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-(--text-main)">Iuran BPJS</h1>
        <p class="text-sm text-(--text-muted) mt-1">
          Nominal iuran BPJS per karyawan — 5 porsi perusahaan + 3 porsi karyawan
        </p>
      </div>
    </div>

    <!-- Filter -->
    <div class="bg-(--bg-card) border border-(--border-soft) rounded-xl p-4 mb-6">
      <div class="flex gap-4">
        <div class="flex-1 relative">
          <input v-model="search" type="text" placeholder="Cari nama atau kode..."
            class="w-full pl-10 pr-4 py-2 bg-(--bg-input) border border-(--border-soft) rounded-lg text-sm"
            @input="fetchData" />
          <span class="absolute left-3 top-2.5 text-(--text-muted)">🔍</span>
        </div>
        <select v-model="payPeriodId" @change="fetchData"
          class="bg-(--bg-input) border border-(--border-soft) rounded-lg px-3 py-2 text-sm">
          <option value="">Semua Periode</option>
          <option v-for="p in payPeriods" :key="p.id" :value="p.id">{{ p.name }}</option>
        </select>
        <button @click="exportExcel"
          :disabled="!payPeriodId || exporting"
          class="px-4 py-2 bg-green-600 text-white rounded-lg text-sm font-medium hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed transition">
          <span v-if="exporting">⏳ Export...</span>
          <span v-else>📥 Export Excel</span>
        </button>
      </div>
    </div>

    <!-- Summary Cards -->
    <div class="grid grid-cols-3 gap-4 mb-6">
      <div class="bg-(--bg-card) border border-(--border-soft) rounded-xl p-4">
        <div class="text-xs text-(--text-muted)">Total Karyawan</div>
        <div class="text-xl font-bold">{{ total }}</div>
      </div>
      <div class="bg-(--bg-card) border border-(--border-soft) rounded-xl p-4 text-right">
        <div class="text-xs text-(--text-muted) text-left">Beban Perusahaan</div>
        <div class="text-lg font-bold text-blue-600">{{ fmt(sumEmployer) }}</div>
      </div>
      <div class="bg-(--bg-card) border border-(--border-soft) rounded-xl p-4 text-right">
        <div class="text-xs text-(--text-muted) text-left">Potongan Karyawan</div>
        <div class="text-lg font-bold text-orange-600">{{ fmt(sumEmployee) }}</div>
      </div>
    </div>

    <!-- Table -->
    <BaseCard>
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="border-b border-(--border-soft)">
            <tr class="text-xs uppercase text-(--text-muted) tracking-wider">
              <th rowspan="2" class="p-3 font-medium text-left sticky left-0 bg-(--bg-card) z-10">Karyawan</th>
              <th rowspan="2" class="p-3 font-medium text-right">Gaji Pokok</th>
              <th rowspan="2" class="p-3 font-medium text-right">TJ MK</th>
              <th rowspan="2" class="p-3 font-medium text-right">Tunjangan</th>
              <th rowspan="2" class="p-3 font-medium text-right">Dasar BPJS</th>
              <th colspan="5" class="p-2 text-center bg-blue-50/30 text-blue-700 text-[10px]">Porsi Perusahaan</th>
              <th colspan="3" class="p-2 text-center bg-orange-50/30 text-orange-700 text-[10px]">Porsi Karyawan</th>
              <th rowspan="2" class="p-2 font-medium text-center w-[110px]">Aksi</th>
            </tr>
            <tr class="text-[10px] text-(--text-soft)">
              <th class="px-2 py-1.5 text-right">JHT</th>
              <th class="px-2 py-1.5 text-right">JKK</th>
              <th class="px-2 py-1.5 text-right">JKM</th>
              <th class="px-2 py-1.5 text-right">KES</th>
              <th class="px-2 py-1.5 text-right">JP</th>
              <th class="px-2 py-1.5 text-right">JHT</th>
              <th class="px-2 py-1.5 text-right">KES</th>
              <th class="px-2 py-1.5 text-right">JP</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-(--border-soft)">
            <tr v-if="loading"><td colspan="14" class="p-8 text-center text-(--text-muted)">Memuat...</td></tr>
            <tr v-else-if="records.length === 0"><td colspan="14" class="p-8 text-center text-(--text-muted)">Belum ada data iuran.</td></tr>
            <tr v-for="r in records" :key="r.id" class="hover:bg-(--bg-hover)">
              <td class="p-3 sticky left-0 bg-(--bg-card) z-10">
                <div class="font-medium">{{ r.employee?.name }}</div>
                <div class="text-[10px] text-(--text-muted)">{{ r.employee?.employee_code }}</div>
              </td>
              <td class="p-3 text-right text-xs">{{ fmt(r.gaji_pokok ?? (r.bpjs_base_salary - (r.tj_masa_kerja + r.tunjangan))) }}</td>
              <td class="p-3 text-right text-xs">{{ fmt(r.tj_masa_kerja) }}</td>
              <td class="p-3 text-right text-xs">{{ fmt(r.tunjangan) }}</td>
              <td class="p-3 text-right font-medium">{{ fmt(r.bpjs_base_salary) }}</td>
              <!-- Employer (5) -->
              <td class="p-2 text-right text-blue-600 bg-blue-50/10 text-xs">{{ fmt(r.employer_jht) }}</td>
              <td class="p-2 text-right text-blue-600 bg-blue-50/10 text-xs">{{ fmt(r.employer_jkk) }}</td>
              <td class="p-2 text-right text-blue-600 bg-blue-50/10 text-xs">{{ fmt(r.employer_jkm) }}</td>
              <td class="p-2 text-right text-blue-600 bg-blue-50/10 text-xs">{{ fmt(r.employer_kesehatan) }}</td>
              <td class="p-2 text-right text-blue-600 bg-blue-50/10 text-xs">{{ fmt(r.employer_jp) }}</td>
              <!-- Employee (3) — inline edit -->
              <td class="p-2 text-right text-orange-600 bg-orange-50/10 text-xs">
                <input v-if="isEditing(r)" v-model.number="editForm.employee_jht" type="number" min="0" step="any"
                  class="w-24 bg-(--bg-input) border border-(--border-soft) rounded px-2 py-1 text-right text-xs" />
                <template v-else>{{ fmt(r.employee_jht) }}</template>
              </td>
              <td class="p-2 text-right text-orange-600 bg-orange-50/10 text-xs">
                <input v-if="isEditing(r)" v-model.number="editForm.employee_kesehatan" type="number" min="0" step="any"
                  class="w-24 bg-(--bg-input) border border-(--border-soft) rounded px-2 py-1 text-right text-xs" />
                <template v-else>{{ fmt(r.employee_kesehatan) }}</template>
              </td>
              <td class="p-2 text-right text-orange-600 bg-orange-50/10 text-xs">
                <input v-if="isEditing(r)" v-model.number="editForm.employee_jp" type="number" min="0" step="any"
                  class="w-24 bg-(--bg-input) border border-(--border-soft) rounded px-2 py-1 text-right text-xs" />
                <template v-else>{{ fmt(r.employee_jp) }}</template>
              </td>
              <!-- Aksi -->
              <td class="p-2 text-center">
                <div v-if="isEditing(r)" class="flex justify-center gap-1">
                  <button @click="saveEdit(r)" :disabled="savingEdit"
                    class="px-2 py-1 rounded text-[10px] bg-(--primary) text-white hover:opacity-90 disabled:opacity-50 disabled:cursor-not-allowed">
                    {{ savingEdit ? '...' : 'Simpan' }}
                  </button>
                  <button @click="cancelEdit" :disabled="savingEdit"
                    class="px-2 py-1 rounded text-[10px] bg-(--bg-input) border border-(--border-soft) text-(--text-main) hover:bg-(--bg-hover) disabled:opacity-50 disabled:cursor-not-allowed">
                    Batal
                  </button>
                </div>
                <button v-else @click="startEdit(r)"
                  class="p-1.5 rounded hover:bg-(--bg-hover) text-(--text-muted) hover:text-(--primary)"
                  title="Edit iuran karyawan (JHT, KES, JP)">
                  ✏️
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <!-- Pagination -->
      <div v-if="totalPages > 1" class="flex items-center justify-between p-4 border-t">
        <span class="text-xs text-(--text-muted)">{{ total }} data &middot; Hal {{ page }} / {{ totalPages }}</span>
        <div class="flex gap-1">
          <button v-for="p in visiblePages" :key="p" @click="goToPage(p)"
            class="px-3 py-1 rounded text-xs" :class="p === page ? 'bg-(--primary) text-white' : 'bg-(--bg-input)'">{{ p }}</button>
        </div>
      </div>
    </BaseCard>

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
import BaseCard from '@/Components/BaseCard.vue'
import BaseButton from '@/Components/BaseButton.vue'
import BaseModal from '@/Components/BaseModal.vue'

const router = useRouter()
const { get, put } = useApi()

const records = ref([])
const loading = ref(false)
const page = ref(1)
const perPage = 25
const total = ref(0)
const search = ref('')
const payPeriodId = ref('')
const payPeriods = ref([])
const exporting = ref(false)

// Inline edit — 3 porsi karyawan
const editingId = ref(null)
const editForm = ref({ employee_jht: 0, employee_kesehatan: 0, employee_jp: 0 })
const savingEdit = ref(false)
const showLockedModal = ref(false)

function isEditing(r) {
  return editingId.value === r.id
}

function startEdit(r) {
  editingId.value = r.id
  editForm.value = {
    employee_jht: parseFloat(r.employee_jht) || 0,
    employee_kesehatan: parseFloat(r.employee_kesehatan) || 0,
    employee_jp: parseFloat(r.employee_jp) || 0,
  }
}

function cancelEdit() {
  editingId.value = null
}

async function saveEdit(r) {
  savingEdit.value = true
  try {
    const res = await put(`/api/v1/bpjs/iuran/${r.id}`, editForm.value)
    const d = res.data?.data || res.data
    if (d) {
      r.employee_jht = d.employee_jht
      r.employee_kesehatan = d.employee_kesehatan
      r.employee_jp = d.employee_jp
    }
    editingId.value = null
  } catch (e) {
    if (e.response?.status === 403) {
      showLockedModal.value = true
    } else {
      alert(e.message || 'Gagal menyimpan iuran')
    }
  } finally {
    savingEdit.value = false
  }
}

function goToGajiKaryawan() {
  showLockedModal.value = false
  router.push('/admin/payroll/gaji-karyawan')
}

const totalPages = computed(() => Math.ceil(total.value / perPage))
const visiblePages = computed(() => {
  const pages = []
  for (let i = 1; i <= totalPages.value; i++) pages.push(i)
  return pages.slice(Math.max(0, page.value - 3), Math.min(totalPages.value, page.value + 2))
})
const sumEmployer = computed(() => records.value.reduce((s, r) => s + (parseFloat(r.employer_jht)||0) + (parseFloat(r.employer_jkk)||0) + (parseFloat(r.employer_jkm)||0) + (parseFloat(r.employer_kesehatan)||0) + (parseFloat(r.employer_jp)||0), 0))
const sumEmployee = computed(() => records.value.reduce((s, r) => s + (parseFloat(r.employee_jht)||0) + (parseFloat(r.employee_kesehatan)||0) + (parseFloat(r.employee_jp)||0), 0))

function fmt(v) {
  if (v === null || v === undefined || v === 0) return '-'
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(v)
}

async function fetchData() {
  loading.value = true
  try {
    const params = new URLSearchParams({ page: page.value, per_page: perPage })
    if (search.value) params.set('search', search.value)
    if (payPeriodId.value) params.set('pay_period_id', payPeriodId.value)
    const res = await get(`/api/v1/bpjs/iuran?${params}`)
    records.value = res.data?.data || res.data || []
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
  } catch (e) {}
}

async function exportExcel() {
  exporting.value = true
  try {
    const token = localStorage.getItem('token')
    const url = `/api/v1/bpjs/iuran/export?pay_period_id=${payPeriodId.value}`
    const res = await fetch(url, { headers: { 'Authorization': `Bearer ${token}` } })
    if (!res.ok) {
      const err = await res.json()
      alert(err.message || 'Gagal export Excel.')
      return
    }
    const blob = await res.blob()
    const downloadUrl = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = downloadUrl
    link.setAttribute('download', 'Iuran_BPJS.xlsx')
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
    URL.revokeObjectURL(downloadUrl)
  } catch (e) {
    alert('Gagal export Excel.')
  } finally {
    exporting.value = false
  }
}

function goToPage(p) { page.value = p; fetchData() }

onMounted(() => { fetchData(); fetchPayPeriods() })
</script>
