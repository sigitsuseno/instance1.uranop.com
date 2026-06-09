<template>
  <div>
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-(--text-main)">Consecutive Day</h1>
        <p class="text-sm text-(--text-muted) mt-1">
          Input manual hari kerja / absen berturut-turut dari permintaan karyawan
        </p>
      </div>
      <BaseButton variant="primary" @click="openAdd">
        <template #icon-left>
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <line x1="12" y1="5" x2="12" y2="19" />
            <line x1="5" y1="12" x2="19" y2="12" />
          </svg>
        </template>
        Tambah
      </BaseButton>
    </div>

    <!-- Filter Bar -->
    <div class="bg-(--bg-card) border border-(--border-soft) rounded-xl shadow-sm p-4 mb-6">
      <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
        <div class="flex items-center gap-3">
          <label class="text-sm font-medium whitespace-nowrap">Periode</label>
          <select v-model="selectedPeriod"
            class="px-3 py-2 border border-(--border-soft) rounded-lg bg-(--bg-elevated) text-sm min-w-[280px]"
            @change="fetchList" :disabled="isLoading">
            <option v-for="p in payPeriods" :key="p.id" :value="p.id">{{ p.label }}</option>
          </select>
        </div>
        <div class="flex gap-2">
          <select v-model="filterType"
            class="px-3 py-1.5 border border-(--border-soft) rounded-lg bg-(--bg-card) text-sm" @change="fetchList">
            <option value="">Semua Tipe</option>
            <option value="worked">Hadir</option>
            <option value="absent">Absen</option>
          </select>
          <span class="text-xs text-(--text-muted) self-center" v-if="!isLoading">
            {{ streaks.length }} data
          </span>
        </div>
      </div>
    </div>

    <!-- Table -->
    <div class="overflow-auto max-h-[60vh] bg-(--bg-card) border border-(--border-soft) rounded-xl shadow-sm">
      <table class="w-full text-sm">
        <thead class="sticky top-0 z-10 bg-(--bg-elevated)">
          <tr class="border-b border-(--border-soft)">
            <th class="px-4 py-3 text-left text-xs font-medium text-(--text-muted) uppercase">Karyawan</th>
            <th class="px-4 py-3 text-center text-xs font-medium text-(--text-muted) uppercase">Tipe</th>
            <th class="px-4 py-3 text-center text-xs font-medium text-(--text-muted) uppercase">Dari</th>
            <th class="px-4 py-3 text-center text-xs font-medium text-(--text-muted) uppercase">Sampai</th>
            <th class="px-4 py-3 text-center text-xs font-medium text-(--text-muted) uppercase">Hari</th>
            <th class="px-4 py-3 text-center text-xs font-medium text-(--text-muted) uppercase">Catatan</th>
            <th class="px-4 py-3 text-center text-xs font-medium text-(--text-muted) uppercase w-20">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="s in streaks" :key="s.id" class="border-b border-(--border-soft) hover:bg-(--bg-elevated)/30">
            <td class="px-4 py-3">
              <div class="font-semibold">{{ s.employee?.name }}</div>
              <div class="text-xs text-(--text-muted)">{{ s.employee?.employee_code }}</div>
            </td>
            <td class="px-4 py-3 text-center">
              <span class="px-2 py-0.5 text-xs rounded-full font-semibold"
                :class="s.type === 'worked' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'">
                {{ s.type === 'worked' ? 'Hadir' : 'Absen' }}
              </span>
            </td>
            <td class="px-4 py-3 text-center">{{ s.start_date }}</td>
            <td class="px-4 py-3 text-center">{{ s.end_date }}</td>
            <td class="px-4 py-3 text-center font-bold" :class="s.total_days >= 7 ? 'text-red-600' : ''">{{ s.total_days
              }}
            </td>
            <td class="px-4 py-3 text-xs text-(--text-muted) max-w-[200px] truncate">{{ s.notes || '—' }}</td>
            <td class="px-4 py-3 text-center">
              <div class="flex items-center justify-center gap-1">
                <button class="p-1 rounded hover:bg-(--bg-elevated) text-(--text-soft) hover:text-(--primary)"
                  title="Edit" @click="openEdit(s)"><svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="2">
                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" />
                  </svg></button>
                <button class="p-1 rounded hover:bg-red-50 text-(--text-soft) hover:text-red-600" title="Hapus"
                  @click="handleDelete(s)"><svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="2">
                    <polyline points="3 6 5 6 21 6" />
                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />
                  </svg></button>
              </div>
            </td>
          </tr>
          <tr v-if="streaks.length === 0 && !isLoading">
            <td colspan="7" class="px-4 py-12 text-center text-(--text-muted)">Belum ada data.</td>
          </tr>
          <tr v-if="isLoading">
            <td colspan="7" class="px-4 py-12 text-center text-(--text-muted)">Memuat data...</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div class="mt-4 text-sm text-(--text-muted) text-center" v-if="pagination.lastPage > 1">
      <div class="flex items-center justify-center gap-2">
        <button :disabled="pagination.currentPage <= 1" @click="goToPage(pagination.currentPage - 1)"
          class="px-3 py-1 border rounded disabled:opacity-30">←</button>
        <span>Hlm {{ pagination.currentPage }}/{{ pagination.lastPage }}</span>
        <button :disabled="pagination.currentPage >= pagination.lastPage" @click="goToPage(pagination.currentPage + 1)"
          class="px-3 py-1 border rounded disabled:opacity-30">→</button>
      </div>
    </div>

    <!-- Add/Edit Modal -->
    <BaseModal v-if="showModal" :show="showModal" :title="editingId ? 'Edit Consecutive Day' : 'Tambah Consecutive Day'"
      size="md" @close="closeModal">
      <div class="space-y-4">
        <SearchableSelect v-model="form.employee_id" :options="employeeSelectOptions" label="Karyawan"
          placeholder="Cari nama atau kode karyawan..." :disabled="!!editingId" :required="true" />
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-sm font-medium mb-1">Dari Tanggal</label>
            <input v-model="form.start_date" type="date"
              class="w-full px-3 py-2 border border-(--border-soft) rounded-lg bg-(--bg-card) text-sm" required />
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Sampai Tanggal</label>
            <input v-model="form.end_date" type="date"
              class="w-full px-3 py-2 border border-(--border-soft) rounded-lg bg-(--bg-card) text-sm" required />
          </div>
        </div>
        <div v-if="form.start_date && form.end_date" class="text-xs text-(--text-muted)">
          Total: <strong>{{ computedDays }}</strong> hari
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Tipe</label>
          <select v-model="form.type"
            class="w-full px-3 py-2 border border-(--border-soft) rounded-lg bg-(--bg-card) text-sm">
            <option value="worked">Hadir</option>
            <option value="absent">Absen</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Catatan</label>
          <textarea v-model="form.notes" rows="2"
            class="w-full px-3 py-2 border border-(--border-soft) rounded-lg bg-(--bg-card) text-sm"
            placeholder="Opsional..."></textarea>
        </div>
      </div>
      <template #footer>
        <div class="flex gap-3 w-full">
          <BaseButton variant="secondary" @click="closeModal">Batal</BaseButton>
          <span class="flex-1"></span>
          <BaseButton variant="primary" :loading="isSaving" @click="handleSave">{{ editingId ? 'Simpan' : 'Tambah' }}
          </BaseButton>
        </div>
      </template>
    </BaseModal>

    <!-- Confirm Delete -->
    <BaseModal v-if="deletingId" :show="!!deletingId" title="Hapus Consecutive Day" size="sm"
      @close="deletingId = null">
      <p class="text-sm text-(--text-muted)">Yakin hapus data ini?</p>
      <template #footer>
        <BaseButton variant="secondary" @click="deletingId = null">Batal</BaseButton>
        <BaseButton variant="primary" class="!bg-red-600" :loading="isSaving" @click="confirmDelete">Hapus</BaseButton>
      </template>
    </BaseModal>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import BaseButton from '@/Components/BaseButton.vue'
import BaseModal from '@/Components/BaseModal.vue'
import SearchableSelect from '@/Components/SearchableSelect.vue'
import { useApi } from '@/composables/useApi'

const { get, post, put, destroy } = useApi()

// ── State ──
const isLoading = ref(true)
const isSaving = ref(false)
const selectedPeriod = ref('current')
const filterType = ref('')
const streaks = ref([])
const pagination = ref({ currentPage: 1, lastPage: 1 })
const payPeriods = ref([])
const employeeOptions = ref([])

// ── Modal ──
const showModal = ref(false)
const editingId = ref(null)
const deletingId = ref(null)
const form = ref({ employee_id: '', start_date: '', end_date: '', type: 'worked', notes: '' })

const computedDays = computed(() => {
  if (!form.value.start_date || !form.value.end_date) return 0
  const s = new Date(form.value.start_date), e = new Date(form.value.end_date)
  return Math.round((e - s) / 86400000) + 1
})

// Format employeeOptions untuk SearchableSelect: [{value, label}]
const employeeSelectOptions = computed(() =>
  employeeOptions.value.map(e => ({
    value: e.id,
    label: `${e.id} — ${e.name}`
  }))
)

// ── Fetch Pay Periods ──
async function fetchPayPeriods() {
  try {
    const res = await get('/api/v1/payroll/periods')
    payPeriods.value = (res.data || []).map(p => ({ id: p.id, start: p.start_date, end: p.end_date, label: `${p.name} (${p.start_date} — ${p.end_date})` }))
    if (payPeriods.value.length > 0 && selectedPeriod.value === 'current') selectedPeriod.value = payPeriods.value[0].id
  } catch (e) { console.error(e) }
}

function getPeriodDates() {
  const p = payPeriods.value.find(p => p.id == selectedPeriod.value)
  if (p) return p
  if (payPeriods.value.length > 0) return payPeriods.value[0]
  const now = new Date()
  return { start: new Date(now.getFullYear(), now.getMonth(), 1).toISOString().split('T')[0], end: now.toISOString().split('T')[0] }
}

// ── Fetch Employee Options ──
async function fetchEmployeeOptions() {
  try {
    // Pakai endpoint options — tanpa pagination, ambil semua karyawan aktif
    const res = await get('/api/v1/employees/options?active_only=1')
    employeeOptions.value = (res.data || []).map(e => ({ id: e.id, name: e.name, employee_code: e.employee_code }))
  } catch (e) {
    console.error('Gagal fetch employee options:', e)
  }
}

// ── Fetch List ──
async function fetchList(page = 1) {
  isLoading.value = true
  try {
    const { start, end } = getPeriodDates()
    const params = new URLSearchParams({ start_date: start, end_date: end, per_page: '50', page: String(page) })
    if (filterType.value) params.set('type', filterType.value)
    const res = await get(`/api/v1/attendance/consecutive?${params}`)
    const p = res.data || {}
    streaks.value = p.data || []
    pagination.value = { currentPage: p.current_page || 1, lastPage: p.last_page || 1 }
  } catch (e) { console.error(e) }
  finally { isLoading.value = false }
}

function goToPage(page) { fetchList(page) }

// ── Modal Actions ──
function openAdd() {
  editingId.value = null
  form.value = { employee_id: '', start_date: '', end_date: '', type: 'worked', notes: '' }
  showModal.value = true
}
function openEdit(s) {
  editingId.value = s.id
  form.value = { employee_id: s.employee_id, start_date: s.start_date, end_date: s.end_date, type: s.type, notes: s.notes || '' }
  showModal.value = true
}
function closeModal() { showModal.value = false }

async function handleSave() {
  isSaving.value = true
  try {
    const payload = { ...form.value }
    if (editingId.value) {
      await put(`/api/v1/attendance/consecutive/${editingId.value}`, payload)
    } else {
      await post('/api/v1/attendance/consecutive', payload)
    }
    closeModal()
    await fetchEmployeeOptions()
    await fetchList()
  } catch (e) { console.error(e) }
  finally { isSaving.value = false }
}

function handleDelete(s) { deletingId.value = s.id }
async function confirmDelete() {
  isSaving.value = true
  try { await destroy(`/api/v1/attendance/consecutive/${deletingId.value}`); deletingId.value = null; await fetchList() }
  catch (e) { console.error(e) }
  finally { isSaving.value = false }
}

// ── Init ──
; (async () => {
  await fetchPayPeriods()
  await Promise.all([fetchList(), fetchEmployeeOptions()])
})()
</script>
