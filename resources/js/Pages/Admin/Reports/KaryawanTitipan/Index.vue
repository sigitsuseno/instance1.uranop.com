<template>
  <div>
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-(--text-main)">Karyawan Titipan — Roster JAKARTA</h1>
        <p class="text-sm text-(--text-muted) mt-1">Generate & edit roster harian untuk Section A. JAKARTA.</p>
      </div>
      <div class="flex items-center gap-2">
        <button
          @click="showEmployees = true"
          class="px-4 py-2 bg-(--bg-elevated) border border-(--border-soft) text-(--text-main) rounded-md hover:bg-(--bg-card) transition-all text-sm font-medium flex items-center gap-2"
        >
          <i class="bx bx-list-ul"></i> Daftar Karyawan
        </button>
      </div>
    </div>

    <!-- Period Selector + Generate -->
    <div class="bg-(--bg-card) border border-(--border-soft) rounded-md p-4 mb-4">
      <div class="flex flex-wrap gap-4 items-end">
        <div class="min-w-[240px]">
          <label class="block text-xs font-semibold text-(--text-muted) mb-1.5 uppercase tracking-wide">Periode Payroll</label>
          <select
            v-model="selectedPeriodId"
            @change="fetchRoster"
            class="w-full px-3 py-2 bg-(--bg-elevated) border border-(--border-soft) rounded-md text-(--text-main) text-sm focus:ring-2 focus:ring-(--primary) outline-none"
          >
            <option :value="null" disabled>Pilih Periode</option>
            <option v-for="p in periods" :key="p.id" :value="p.id">{{ p.name }} ({{ p.start_date }} → {{ p.end_date }})</option>
          </select>
        </div>
        <button
          :disabled="!selectedPeriodId || generating"
          @click="generateRoster"
          class="px-4 py-2 bg-green-600 hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed text-white rounded-md text-sm font-medium transition-colors flex items-center gap-2"
        >
          <i class="bx bx-refresh"></i> {{ generating ? 'Generating...' : 'Generate' }}
        </button>
        <button
          :disabled="!selectedPeriodId || generating || !hasRoster"
          @click="regenerateRoster"
          class="px-4 py-2 bg-orange-600 hover:bg-orange-700 disabled:opacity-50 disabled:cursor-not-allowed text-white rounded-md text-sm font-medium transition-colors flex items-center gap-2"
        >
          <i class="bx bx-reset"></i> Regenerate
        </button>
        <span v-if="infoMsg" class="text-xs text-(--text-muted) italic">{{ infoMsg }}</span>
      </div>
    </div>

    <!-- Info bar -->
    <div v-if="records.length" class="flex items-center gap-2 text-sm text-(--text-muted) mb-3">
      <span class="text-(--text-main) font-semibold">A. JAKARTA (Karyawan Titipan)</span>
      <span class="text-(--text-soft)">·</span>
      <span>{{ records.length }} karyawan</span>
      <span class="text-(--text-soft)">·</span>
      <span>{{ dates.length }} hari</span>
    </div>

    <!-- Legend -->
    <div v-if="records.length" class="flex flex-wrap gap-4 text-xs text-(--text-muted) mb-3">
      <span><b class="text-green-600">H</b> Hadir</span>
      <span><b class="text-red-600">A</b> Absen</span>
      <span><b class="text-amber-600">C</b> Cuti</span>
      <span><b class="text-purple-600">I</b> Izin</span>
      <span><b class="text-orange-600">S</b> Sakit</span>
      <span><b class="text-(--text-soft)">Off</b> Libur</span>
      <span><b class="text-(--text-muted)">-</b> Belum diisi</span>
      <span class="text-(--text-soft)">|</span>
      <span class="text-(--text-soft) text-[10px]">Klik cell untuk ganti status</span>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="text-center py-12 text-(--text-muted)">⏳ Memuat data...</div>

    <!-- No period -->
    <div v-else-if="!selectedPeriodId" class="bg-(--bg-card) border border-(--border-soft) rounded-md p-12 text-center">
      <p class="text-(--text-muted) text-lg">Pilih periode terlebih dahulu.</p>
    </div>

    <!-- No data -->
    <div v-else-if="!records.length && !loading" class="bg-(--bg-card) border border-(--border-soft) rounded-md p-12 text-center">
      <p class="text-(--text-muted) text-lg">Belum ada roster.</p>
      <p class="text-(--text-soft) text-sm mt-1">Klik tombol <b>Generate</b> untuk membuat roster otomatis.</p>
    </div>

    <!-- Roster Matrix Table -->
    <div v-else class="bg-(--bg-card) border border-(--border-soft) rounded-md shadow-sm overflow-hidden">
      <div class="overflow-x-auto max-h-[55vh]">
        <table class="min-w-max border-collapse">
          <thead class="sticky top-0 z-20">
            <!-- Date row -->
            <tr class="bg-(--bg-elevated)">
              <th class="sticky left-0 z-30 bg-(--bg-elevated) px-3 py-2.5 text-left text-xs font-semibold text-(--text-muted) uppercase border-b border-(--border-soft) shadow-[2px_0_4px_rgba(0,0,0,0.06)] w-24" rowspan="2">Kode</th>
              <th class="sticky z-30 bg-(--bg-elevated) px-3 py-2.5 text-left text-xs font-semibold text-(--text-muted) uppercase border-b border-(--border-soft) shadow-[2px_0_4px_rgba(0,0,0,0.06)]" style="left:110px;min-width:180px;" rowspan="2">Nama</th>
              <th
                v-for="d in dates" :key="d.date"
                class="px-1.5 py-2 text-center text-[10px] font-bold uppercase border-b border-l border-(--border-soft)"
                :class="d.is_weekend ? 'bg-red-50 dark:bg-red-950/30 text-red-600' : 'text-(--text-muted)'"
                :title="d.day_name + ', ' + d.date"
              >
                {{ d.day }}<div class="text-[8px] font-normal mt-0.5 leading-none">{{ d.day_name }}</div>
              </th>
            </tr>
            <!-- Sub-header -->
            <tr class="bg-(--bg-elevated)">
              <th
                v-for="d in dates" :key="'sub-' + d.date"
                class="px-1 py-1 text-center text-[9px] font-semibold uppercase border-b border-l border-(--border-soft) text-(--text-muted)"
                :class="d.is_weekend ? 'bg-red-50 dark:bg-red-950/30' : ''"
              >St</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-(--border-soft)">
            <tr v-for="row in records" :key="row.id" class="hover:bg-(--bg-elevated) transition-colors group">
              <td class="sticky left-0 z-10 bg-(--bg-card) group-hover:bg-(--bg-elevated) px-3 py-2 font-mono text-xs text-(--text-muted) border-b border-(--border-soft) shadow-[2px_0_4px_rgba(0,0,0,0.04)] transition-colors">{{ row.employee_code || '-' }}</td>
              <td class="sticky z-10 bg-(--bg-card) group-hover:bg-(--bg-elevated) px-3 py-2 font-medium text-sm text-(--text-main) border-b border-(--border-soft) shadow-[2px_0_4px_rgba(0,0,0,0.04)] transition-colors truncate" :style="{ left:'110px' }">{{ row.nama }}</td>
              <td
                v-for="d in dates" :key="d.date"
                class="px-2 py-2 text-center text-xs border-b border-l border-(--border-soft) cursor-pointer select-none"
                :class="[d.is_weekend ? 'bg-red-50/30 dark:bg-red-950/15' : '', statusClass(row.attendance[d.date]?.status)]"
                @click="clickCell(row, d)"
                :title="'Klik: ganti status ' + row.nama + ' - ' + d.day_name + ', ' + d.date"
              >
                <span :class="statusTextClass(row.attendance[d.date]?.status)">{{ row.attendance[d.date]?.status || '-' }}</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ======== Modal Daftar Karyawan ======== -->
    <div
      v-if="showEmployees"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/40"
      @click.self="showEmployees = false"
    >
      <div class="bg-(--bg-card) rounded-xl shadow-xl w-full max-w-2xl mx-4 max-h-[80vh] flex flex-col">
        <div class="flex items-center justify-between p-4 border-b border-(--border-soft)">
          <h2 class="text-lg font-bold text-(--text-main)">Daftar Karyawan Titipan</h2>
          <button @click="showEmployees = false" class="p-1 rounded-md hover:bg-(--bg-elevated) text-(--text-muted)">
            <i class="bx bx-x text-xl"></i>
          </button>
        </div>
        <div class="p-4 overflow-y-auto flex-1">
          <!-- Search -->
          <div class="flex gap-3 mb-4">
            <input v-model="empSearch" @input="debouncedEmpSearch" placeholder="Cari nama/kode..."
              class="flex-1 px-3 py-2 bg-(--bg-elevated) border border-(--border-soft) rounded-md text-(--text-main) text-sm outline-none focus:ring-2 focus:ring-(--primary)"
            />
            <button @click="showForm = true; editId = null; form = defaultForm()"
              class="px-3 py-2 bg-(--primary) text-white rounded-md text-sm hover:opacity-90 transition-all flex items-center gap-1.5"
            ><i class="bx bx-plus"></i> Tambah</button>
          </div>

          <!-- Table -->
          <table class="w-full">
            <thead>
              <tr class="bg-(--bg-elevated) text-xs font-semibold text-(--text-muted) uppercase">
                <th class="px-3 py-2 text-left">Nama</th>
                <th class="px-3 py-2 text-left">Kode</th>
                <th class="px-3 py-2 text-center w-20">Status</th>
                <th class="px-3 py-2 text-center w-24">Aksi</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-(--border-soft)">
              <tr v-if="empLoading" class="text-center"><td colspan="4" class="px-3 py-8 text-(--text-muted) text-sm">⏳ Memuat...</td></tr>
              <tr v-else-if="!empData.length" class="text-center"><td colspan="4" class="px-3 py-8 text-(--text-muted) text-sm">Belum ada data.</td></tr>
              <tr v-for="row in empData" :key="row.id" class="hover:bg-(--bg-elevated) transition-colors text-sm">
                <td class="px-3 py-2 text-(--text-main)">{{ row.nama }}</td>
                <td class="px-3 py-2 text-(--text-muted) font-mono">{{ row.employee_code || '-' }}</td>
                <td class="px-3 py-2 text-center">
                  <span class="inline-block px-2 py-0.5 text-xs font-semibold rounded-full"
                    :class="row.status === 'aktif' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'"
                  >{{ row.status }}</span>
                </td>
                <td class="px-3 py-2 text-center">
                  <button @click="editEmployee(row)" class="p-1 text-blue-600 hover:text-blue-800" title="Edit"><i class="bx bx-edit-alt"></i></button>
                  <button @click="confirmDel(row)" class="p-1 text-red-600 hover:text-red-800" title="Hapus"><i class="bx bx-trash"></i></button>
                </td>
              </tr>
            </tbody>
          </table>
          <!-- Pagination -->
          <div v-if="empPagination.last_page > 1" class="flex justify-between items-center mt-3 text-xs text-(--text-muted)">
            <span>{{ empPagination.total }} data</span>
            <div class="flex gap-1">
              <button :disabled="empPage <= 1" @click="empPage--; fetchEmployees()" class="px-2 py-1 border border-(--border-soft) rounded disabled:opacity-40">‹</button>
              <button :disabled="empPage >= empPagination.last_page" @click="empPage++; fetchEmployees()" class="px-2 py-1 border border-(--border-soft) rounded disabled:opacity-40">›</button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal Form Karyawan -->
    <div v-if="showForm" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40" @click.self="showForm = false">
      <div class="bg-(--bg-card) rounded-xl shadow-xl w-full max-w-lg mx-4 p-6">
        <h2 class="text-lg font-bold text-(--text-main) mb-4">{{ editId ? 'Edit' : 'Tambah' }} Karyawan</h2>
        <form @submit.prevent="submitEmployee" class="space-y-3">
          <div>
            <label class="block text-xs font-semibold text-(--text-muted) mb-1 uppercase">Nama <span class="text-red-500">*</span></label>
            <input v-model="form.nama" required class="w-full px-3 py-2 bg-(--bg-elevated) border border-(--border-soft) rounded-md text-sm outline-none focus:ring-2 focus:ring-(--primary)" />
          </div>
          <div>
            <label class="block text-xs font-semibold text-(--text-muted) mb-1 uppercase">Kode</label>
            <input v-model="form.employee_code" class="w-full px-3 py-2 bg-(--bg-elevated) border border-(--border-soft) rounded-md text-sm outline-none focus:ring-2 focus:ring-(--primary)" />
          </div>

          <!-- Period + Uang Makan (only on add) -->
          <template v-if="!editId">
            <div>
              <label class="block text-xs font-semibold text-(--text-muted) mb-1 uppercase">Periode <span class="text-red-500">*</span></label>
              <select v-model="form.period_id" required class="w-full px-3 py-2 bg-(--bg-elevated) border border-(--border-soft) rounded-md text-sm outline-none focus:ring-2 focus:ring-(--primary)">
                <option :value="null" disabled>Pilih Periode</option>
                <option v-for="p in periods" :key="p.id" :value="p.id">{{ p.name }} ({{ p.start_date }} → {{ p.end_date }})</option>
              </select>
              <p class="text-[10px] text-(--text-soft) mt-1">Roster akan otomatis digenerate per tanggal di periode ini.</p>
            </div>
            <div>
              <label class="block text-xs font-semibold text-(--text-muted) mb-1 uppercase">Uang Makan</label>
              <input v-model.number="form.uang_makan" type="number" min="0" step="1" placeholder="0"
                class="w-full px-3 py-2 bg-(--bg-elevated) border border-(--border-soft) rounded-md text-sm outline-none focus:ring-2 focus:ring-(--primary)" />
            </div>
          </template>

          <div>
            <label class="block text-xs font-semibold text-(--text-muted) mb-1 uppercase">Status <span class="text-red-500">*</span></label>
            <select v-model="form.status" required class="w-full px-3 py-2 bg-(--bg-elevated) border border-(--border-soft) rounded-md text-sm outline-none focus:ring-2 focus:ring-(--primary)">
              <option value="aktif">Aktif</option>
              <option value="nonaktif">Nonaktif</option>
            </select>
          </div>
          <div class="flex justify-end gap-2 pt-2">
            <button type="button" @click="showForm = false" class="px-4 py-2 text-sm border border-(--border-soft) rounded-md hover:bg-(--bg-elevated)">Batal</button>
            <button type="submit" :disabled="saving" class="px-4 py-2 text-sm text-white bg-(--primary) rounded-md hover:opacity-90 disabled:opacity-50">{{ saving ? 'Menyimpan...' : 'Simpan' }}</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Confirm Delete -->
    <div v-if="showDel" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40" @click.self="showDel = false">
      <div class="bg-(--bg-card) rounded-xl shadow-xl w-full max-w-sm mx-4 p-6">
        <p class="text-(--text-main) mb-4">Hapus <b>{{ delTarget?.nama }}</b>?</p>
        <div class="flex justify-end gap-2">
          <button @click="showDel = false" class="px-4 py-2 text-sm border border-(--border-soft) rounded-md">Batal</button>
          <button @click="doDelete" class="px-4 py-2 text-sm text-white bg-red-600 rounded-md hover:bg-red-700">Hapus</button>
        </div>
      </div>
    </div>

    <!-- Status Picker Popup -->
    <div
      v-if="showPicker"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/40"
      @click.self="showPicker = false"
    >
      <div class="bg-(--bg-card) rounded-xl shadow-xl w-64 mx-4 p-4">
        <p class="text-xs text-(--text-muted) mb-2">Ganti status — {{ pickerEmployee }}</p>
        <p class="text-xs text-(--text-soft) mb-3">{{ pickerDate }}</p>
        <div class="grid grid-cols-3 gap-2">
          <button
            v-for="s in statusOptions"
            :key="s.value"
            @click="setStatus(s.value)"
            class="px-3 py-2 text-sm font-bold rounded-md border border-(--border-soft) hover:bg-(--bg-elevated) transition-all"
            :class="pickerCurrent === s.value ? 'ring-2 ring-(--primary) ' + s.activeClass : ''"
          >
            {{ s.label }}
          </button>
        </div>
        <button @click="showPicker = false" class="mt-3 w-full py-1.5 text-xs text-(--text-muted) border border-(--border-soft) rounded-md hover:bg-(--bg-elevated)">Batal</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useApi } from '@/composables/useApi'

const { get, post, put, del } = useApi()

// ─── Roster State ──────────────────────────────
const periods = ref([])
const selectedPeriodId = ref(null)
const dates = ref([])
const records = ref([])
const loading = ref(false)
const generating = ref(false)
const infoMsg = ref('')
let searchTimer = null

// ─── Employee Modal State ──────────────────────
const showEmployees = ref(false)
const empData = ref([])
const empLoading = ref(false)
const empSearch = ref('')
const empPage = ref(1)
const empPagination = ref({ current_page: 1, last_page: 1, total: 0 })
const showForm = ref(false)
const editId = ref(null)
const form = ref(defaultForm())
const saving = ref(false)
const showDel = ref(false)
const delTarget = ref(null)

// ─── Status Picker ─────────────────────────────
const showPicker = ref(false)
const pickerEmployee = ref('')
const pickerDate = ref('')
const pickerDateStr = ref('')
const pickerRow = ref(null)
const pickerCurrent = ref('')

const statusOptions = [
  { value: 'H',    label: 'H',    activeClass: 'bg-green-100 text-green-700 border-green-300' },
  { value: 'A',    label: 'A',    activeClass: 'bg-red-100 text-red-700 border-red-300' },
  { value: 'C',    label: 'C',    activeClass: 'bg-amber-100 text-amber-700 border-amber-300' },
  { value: 'I',    label: 'I',    activeClass: 'bg-purple-100 text-purple-700 border-purple-300' },
  { value: 'S',    label: 'S',    activeClass: 'bg-orange-100 text-orange-700 border-orange-300' },
  { value: 'Off',  label: 'Off',  activeClass: 'bg-gray-100 text-gray-500 border-gray-300' },
  { value: '-',    label: '-',    activeClass: 'bg-gray-50 text-gray-300 border-gray-200' },
]

const hasRoster = computed(() => records.value.length > 0 && dates.value.length > 0)

function defaultForm() {
  return {
    nama: '',
    employee_code: '',
    period_id: null,
    uang_makan: 0,
    status: 'aktif',
  }
}

// ─── Status helpers ────────────────────────────
function statusClass(status) {
  if (!status) return ''
  const map = { H: 'hover:bg-green-100', A: 'hover:bg-red-100', C: 'hover:bg-amber-100', I: 'hover:bg-purple-100', S: 'hover:bg-orange-100', Off: 'hover:bg-gray-100' }
  return map[status] || 'hover:bg-(--bg-elevated)'
}

function statusTextClass(status) {
  const map = { H: 'text-green-600 font-bold', A: 'text-red-600 font-bold', C: 'text-amber-600 font-bold', I: 'text-purple-600 font-bold', S: 'text-orange-600 font-bold', Off: 'text-(--text-soft)' }
  return map[status] || 'text-(--text-muted)'
}

// ─── Roster API ────────────────────────────────
async function fetchPeriods() {
  try {
    const res = await get('/api/v1/karyawan-titipan/periods')
    periods.value = res.data || []
  } catch (e) { console.error(e) }
}

async function fetchRoster() {
  if (!selectedPeriodId.value) return
  loading.value = true
  infoMsg.value = ''
  try {
    const res = await get(`/api/v1/karyawan-titipan/roster?period_id=${selectedPeriodId.value}`)
    dates.value = res.dates || []
    records.value = res.records || []
    if (!periods.value.length) periods.value = res.periods || []
  } catch (e) { console.error(e) }
  finally { loading.value = false }
}

async function generateRoster() {
  if (!selectedPeriodId.value) return
  generating.value = true
  infoMsg.value = ''
  try {
    const res = await post('/api/v1/karyawan-titipan/roster/generate', { period_id: selectedPeriodId.value })
    infoMsg.value = res.message || 'Roster berhasil digenerate.'
    await fetchRoster()
  } catch (e) {
    infoMsg.value = e.response?.data?.message || e.message || 'Gagal generate'
  } finally { generating.value = false }
}

async function regenerateRoster() {
  if (!selectedPeriodId.value) return
  generating.value = true
  infoMsg.value = ''
  try {
    const res = await post('/api/v1/karyawan-titipan/roster/regenerate', { period_id: selectedPeriodId.value })
    infoMsg.value = res.message || 'Roster berhasil diregenerate.'
    await fetchRoster()
  } catch (e) {
    infoMsg.value = e.response?.data?.message || e.message || 'Gagal regenerate'
  } finally { generating.value = false }
}

// ─── Click Cell → Status Picker ────────────────
function clickCell(row, d) {
  const att = row.attendance[d.date]
  if (!att?.roster_id) return // Belum generate, skip

  pickerRow.value = row
  pickerDateStr.value = d.date
  pickerDate.value = d.day_name + ', ' + d.date
  pickerEmployee.value = row.nama
  pickerCurrent.value = att.status
  showPicker.value = true
}

async function setStatus(status) {
  const row = pickerRow.value
  const att = row.attendance[pickerDateStr.value]
  if (!att?.roster_id) return

  try {
    await put(`/api/v1/karyawan-titipan/roster/${att.roster_id}`, { status })
    att.status = status
    showPicker.value = false
  } catch (e) {
    alert('Gagal update status')
  }
}

// ─── Employees CRUD (Modal) ────────────────────
async function fetchEmployees() {
  empLoading.value = true
  try {
    const params = new URLSearchParams()
    params.set('page', empPage.value)
    params.set('per_page', 10)
    if (empSearch.value) params.set('search', empSearch.value)
    const res = await get(`/api/v1/karyawan-titipan?${params}`)
    empData.value = res.data || []
    empPagination.value = res.pagination || empPagination.value
  } catch (e) { console.error(e) }
  finally { empLoading.value = false }
}

function debouncedEmpSearch() {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => { empPage.value = 1; fetchEmployees() }, 400)
}

function editEmployee(row) {
  editId.value = row.id
  form.value = {
    nama: row.nama,
    employee_code: row.employee_code || '',
    period_id: null,
    uang_makan: row.component?.uang_makan || 0,
    status: row.status,
  }
  showForm.value = true
}

async function submitEmployee() {
  saving.value = true
  try {
    if (editId.value) {
      await put(`/api/v1/karyawan-titipan/${editId.value}`, {
        nama: form.value.nama,
        employee_code: form.value.employee_code,
        status: form.value.status,
        component: JSON.stringify({ uang_makan: form.value.uang_makan }),
      })
    } else {
      await post('/api/v1/karyawan-titipan', form.value)
    }
    showForm.value = false
    fetchEmployees()
    if (selectedPeriodId.value) fetchRoster()
  } catch (e) {
    alert('Gagal: ' + (e.response?.data?.message || e.message))
  } finally { saving.value = false }
}

function confirmDel(row) {
  delTarget.value = row
  showDel.value = true
}

async function doDelete() {
  try {
    await del(`/api/v1/karyawan-titipan/${delTarget.value.id}`)
    showDel.value = false
    fetchEmployees()
    if (selectedPeriodId.value) fetchRoster()
  } catch (e) {
    alert('Gagal hapus: ' + (e.response?.data?.message || e.message))
  }
}

// ─── Init ──────────────────────────────────────
onMounted(() => {
  fetchPeriods()

  // Auto-select latest period
  const params = new URLSearchParams(window.location.search)
  if (params.get('period_id')) {
    selectedPeriodId.value = Number(params.get('period_id'))
    fetchRoster()
  }
})
</script>
