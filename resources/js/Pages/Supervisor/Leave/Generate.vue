<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-(--text-main)">Generate Cuti Tahunan</h1>
        <p class="text-sm text-(--text-muted) mt-1">Buat periode cuti dan generate kuota cuti massal untuk seluruh karyawan aktif.</p>
      </div>
      <BaseButton
        v-if="auth.isSuperadmin"
        variant="secondary"
        size="sm"
        title="Ambil Data Cuti"
        @click="openImportPanel"
      >
        <template #icon-left>
          <i class="bx bx-cog text-base"></i>
        </template>
      </BaseButton>
    </div>

    <!-- Generate Quota Card -->
    <BaseCard class="mb-6">
      <template #title>Generate Kuota Cuti</template>
      <div class="space-y-4">
        <p class="text-sm text-(--text-main)">
          Berikan kuota jatah cuti (addition) ke seluruh karyawan aktif untuk periode cuti tertentu.
        </p>

        <div class="p-4 rounded-md bg-(--primary)/5 border border-(--primary)/20 text-sm space-y-2">
          <h4 class="font-semibold flex items-center gap-2">
            <i class="bx bx-info-circle text-lg text-(--primary)"></i>
            Ketentuan:
          </h4>
          <ul class="list-disc list-inside space-y-1 text-(--text-muted) text-xs">
            <li>Kuota didistribusikan sesuai kebijakan cuti (<strong>Leave Policies</strong>) yang berlaku.</li>
            <li>Hanya menyasar karyawan dengan masa kerja <strong>≥ 1 tahun</strong>.</li>
            <li>Sistem memproteksi jatah cuti ganda — karyawan yang sudah punya jatah di tipe & periode yang sama tidak akan digenerate ulang.</li>
          </ul>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <SelectInput
              v-model="selectedPeriodId"
              label="Periode Target"
              :options="periodOptions"
              placeholder="Pilih periode cuti"
              :required="true"
            />
          </div>
          <div>
            <SelectInput
              v-model="selectedPolicyId"
              label="Kebijakan Cuti"
              :options="policyOptions"
              placeholder="Pilih kebijakan cuti untuk di-generate"
              :required="true"
            />
          </div>
        </div>

        <BaseButton
          variant="primary"
          @click="showConfirmGenerate = true"
          :loading="generating"
          :disabled="!selectedPeriodId || !selectedPolicyId || generating"
        >
          <template #icon-left>
            <i class="bx bx-magic-wand text-base"></i>
          </template>
          Proses Generate Kuota
        </BaseButton>
      </div>
    </BaseCard>

    <!-- Periode Card -->
    <BaseCard>
      <template #title>Daftar Periode Cuti</template>
      <template #actions>
        <BaseButton variant="primary" size="sm" @click="openPeriodForm(null)">
          <template #icon-left>
            <i class="bx bx-plus text-base"></i>
          </template>
          Tambah Periode
        </BaseButton>
      </template>

      <DataTable
        :headers="periodHeaders"
        :items="periods"
        :loading="loadingPeriods"
        emptyText="Belum ada periode cuti. Silakan tambah periode terlebih dahulu."
      >
        <template #item.status="{ value }">
          <Badge :variant="periodBadgeVariant(value)">{{ value }}</Badge>
        </template>
        <template #item.is_carry_forward="{ value }">
          <Badge :variant="value ? 'success' : 'neutral'">{{ value ? 'Ya' : 'Tidak' }}</Badge>
        </template>
        <template #item.is_generated="{ value }">
          <Badge :variant="value ? 'success' : 'warning'">{{ value ? 'Sudah' : 'Belum' }}</Badge>
        </template>
        <template #item.actions="{ item }">
          <div class="flex items-center gap-1">
            <BaseButton variant="ghost" size="sm" @click="openPeriodForm(item)">
              <template #icon-left>
                <i class="bx bx-edit text-lg text-(--primary)"></i>
              </template>
            </BaseButton>
            <BaseButton variant="ghost" size="sm" @click="confirmDeletePeriod(item)">
              <template #icon-left>
                <i class="bx bx-trash text-lg text-(--danger)"></i>
              </template>
            </BaseButton>
          </div>
        </template>
      </DataTable>
    </BaseCard>

    <!-- Period Form Modal -->
    <BaseModal :show="showPeriodModal" :title="isEditingPeriod ? 'Edit Periode' : 'Tambah Periode'" size="sm" @close="showPeriodModal = false">
      <div class="space-y-4">
        <TextInput
          v-model="periodForm.name"
          label="Nama Periode"
          placeholder="Contoh: Periode Cuti 2026"
          :required="true"
          :error="periodErrors.name"
        />
        <div class="grid grid-cols-2 gap-4">
          <TextInput
            v-model="periodForm.start_date"
            label="Tanggal Mulai"
            type="date"
            :required="true"
            :error="periodErrors.start_date"
          />
          <TextInput
            v-model="periodForm.end_date"
            label="Tanggal Selesai"
            type="date"
            :required="true"
            :error="periodErrors.end_date"
          />
        </div>
        <div class="flex items-center gap-2">
          <input
            id="carryForward"
            v-model="periodForm.is_carry_forward"
            type="checkbox"
            class="rounded border-(--border-soft) text-(--primary) focus:ring-(--primary)"
          />
          <label for="carryForward" class="text-sm text-(--text-main)">Carry Forward (sisa jatah dibawa ke periode berikutnya)</label>
        </div>
      </div>
      <template #footer>
        <BaseButton variant="ghost" @click="showPeriodModal = false">Batal</BaseButton>
        <BaseButton variant="primary" @click="submitPeriod" :loading="submittingPeriod">{{ isEditingPeriod ? 'Simpan' : 'Tambah' }}</BaseButton>
      </template>
    </BaseModal>

    <!-- Confirm Delete Period -->
    <ConfirmDialog
      :show="confirmDelete.show"
      title="Hapus Periode"
      :message="`Hapus periode '${confirmDelete.name}'? Data jatah cuti terkait juga akan terhapus.`"
      variant="danger"
      @confirm="executeDeletePeriod"
      @cancel="confirmDelete.show = false"
    />

    <!-- Confirm Generate -->
    <ConfirmDialog
      :show="showConfirmGenerate"
      title="Generate Kuota Cuti Massal"
      :message="`Generate jatah cuti untuk seluruh karyawan aktif pada periode '${selectedPeriodName}'?`"
      variant="primary"
      @confirm="executeGenerate"
      @cancel="showConfirmGenerate = false"
    />

    <!-- Offcanvas: Ambil Data Cuti (khusus superadmin) -->
    <Teleport to="body">
      <transition name="spv-fade">
        <div
          v-if="showImportPanel"
          class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm z-40"
          @click="closeImportPanel"
        ></div>
      </transition>

      <transition name="spv-slide">
        <aside
          v-if="showImportPanel"
          class="fixed top-0 right-0 h-full w-full sm:w-[480px] z-50 flex flex-col bg-(--bg-card) border-l border-(--border-soft) shadow-xl"
        >
          <div class="shrink-0 flex items-start justify-between gap-4 px-6 py-4 border-b border-(--border-soft)">
            <div>
              <h2 class="text-base font-semibold text-(--text-main)">Ambil Data Cuti</h2>
              <p class="text-xs text-(--text-muted) mt-1">
                Salin ledger cuti karyawan terpilih pada periode yang dipilih ke tabel supervisor.
              </p>
            </div>
            <button
              type="button"
              class="w-8 h-8 flex items-center justify-center rounded-md text-(--text-muted) hover:bg-(--bg-elevated) hover:text-(--text-main) transition-colors"
              @click="closeImportPanel"
            >
              <i class="bx bx-x text-xl"></i>
            </button>
          </div>

          <div class="shrink-0 px-6 py-3 border-b border-(--border-soft) space-y-3">
            <div class="relative">
              <input
                v-model="employeeSearch"
                type="text"
                placeholder="Cari NIP atau nama karyawan..."
                class="w-full pl-9 pr-3 py-2 bg-(--bg-card) border border-(--border-soft) rounded-md text-(--text-main) text-sm focus:outline-none focus:ring-2 focus:ring-(--primary) focus:border-transparent transition-all h-10"
                @input="handleEmployeeSearch"
              />
              <i class="bx bx-search absolute left-2.5 top-1/2 -translate-y-1/2 text-(--text-muted)"></i>
            </div>
            <div class="flex items-center justify-between gap-3">
              <label class="flex items-center gap-2 text-sm text-(--text-main)">
                <input
                  type="checkbox"
                  class="rounded border-(--border-soft) text-(--primary) focus:ring-(--primary)"
                  :checked="allVisibleSelected"
                  :disabled="employees.length === 0 || loadingEmployees"
                  @change="toggleSelectAllVisible"
                />
                Pilih semua
              </label>
              <p class="text-xs text-(--text-muted)">{{ selectedEmployeeIds.length }} dipilih</p>
            </div>
          </div>

          <div class="flex-1 min-h-0 overflow-y-auto divide-y divide-(--border-soft)">
            <div v-if="loadingEmployees" class="py-12 flex flex-col items-center justify-center">
              <div class="w-8 h-8 border-4 border-(--primary)/30 border-t-(--primary) rounded-full animate-spin mb-3"></div>
              <p class="text-sm text-(--text-muted)">Memuat karyawan...</p>
            </div>
            <p v-else-if="employees.length === 0" class="px-6 py-12 text-center text-sm text-(--text-muted)">
              Tidak ada karyawan ditemukan.
            </p>
            <label
              v-for="employee in employees"
              :key="employee.id"
              class="flex items-start gap-3 px-6 py-3 cursor-pointer hover:bg-(--bg-elevated)/50 transition-colors"
            >
              <input
                v-model="selectedEmployeeIds"
                type="checkbox"
                :value="employee.id"
                class="mt-0.5 rounded border-(--border-soft) text-(--primary) focus:ring-(--primary)"
              />
              <div class="min-w-0">
                <p class="text-sm font-medium text-(--text-main) truncate">{{ employee.name }}</p>
                <p class="text-xs text-(--text-muted) mt-0.5">
                  {{ employee.employee_code || '-' }}<span v-if="employee.department"> · {{ employee.department.name }}</span>
                </p>
              </div>
            </label>
          </div>

          <div class="shrink-0 px-6 py-4 border-t border-(--border-soft) space-y-2">
            <p v-if="!selectedPeriodId" class="text-xs text-(--warning) flex items-center gap-1.5">
              <i class="bx bx-info-circle text-base"></i>
              Pilih Periode Target di halaman terlebih dahulu.
            </p>
            <div class="flex items-center justify-end gap-2">
              <BaseButton variant="ghost" :disabled="importing" @click="closeImportPanel">Batal</BaseButton>
              <BaseButton
                variant="primary"
                :loading="importing"
                :disabled="selectedEmployeeIds.length === 0 || !selectedPeriodId || importing"
                @click="executeImportLedger"
              >
                <template #icon-left>
                  <i class="bx bx-download text-base"></i>
                </template>
                Get Data
              </BaseButton>
            </div>
          </div>
        </aside>
      </transition>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, onBeforeUnmount } from 'vue'
import BaseCard from '../../../Components/BaseCard.vue'
import BaseButton from '../../../Components/BaseButton.vue'
import BaseModal from '../../../Components/BaseModal.vue'
import Badge from '../../../Components/Badge.vue'
import TextInput from '../../../Components/TextInput.vue'
import SelectInput from '../../../Components/SelectInput.vue'
import ConfirmDialog from '../../../Components/ConfirmDialog.vue'
import DataTable from '../../../Components/Table/DataTable.vue'
import { useApi } from '../../../composables/useApi'
import { useNotification } from '../../../composables/useNotification'
import { useAuthStore } from '../../../Stores/auth'

const api = useApi()
const notify = useNotification()
const auth = useAuthStore()

// --- State ---
const periods = ref([])
const policies = ref([])
const selectedPeriodId = ref('')
const selectedPolicyId = ref('')
const loadingPeriods = ref(false)
const generating = ref(false)
const submittingPeriod = ref(false)

const showPeriodModal = ref(false)
const showConfirmGenerate = ref(false)
const isEditingPeriod = ref(false)

const periodForm = reactive({
  id: null,
  name: '',
  start_date: '',
  end_date: '',
  is_carry_forward: false,
})

const periodErrors = ref({})

const confirmDelete = reactive({
  show: false,
  id: null,
  name: '',
})

// --- Ambil Data Cuti (superadmin) ---
const showImportPanel = ref(false)
const employees = ref([])
const employeeSearch = ref('')
const selectedEmployeeIds = ref([])
const loadingEmployees = ref(false)
const importing = ref(false)
let employeeSearchTimer = null

// --- Computed ---
const periodOptions = computed(() =>
  periods.value.map((p) => ({ value: p.id, label: `${p.name} (${p.status})` }))
)

const policyOptions = computed(() =>
  policies.value.map((p) => ({ value: p.id, label: p.name }))
)

const selectedPeriodName = computed(() => {
  const p = periods.value.find((x) => x.id === selectedPeriodId.value)
  return p ? p.name : '-'
})

const allVisibleSelected = computed(
  () =>
    employees.value.length > 0 &&
    employees.value.every((employee) => selectedEmployeeIds.value.includes(employee.id))
)

const periodHeaders = [
  { key: 'name', label: 'Nama Periode' },
  { key: 'start_date', label: 'Tgl Mulai' },
  { key: 'end_date', label: 'Tgl Selesai' },
  { key: 'status', label: 'Status' },
  { key: 'is_carry_forward', label: 'Carry Forward' },
  { key: 'is_generated', label: 'Generated' },
  { key: 'actions', label: 'Aksi', sortable: false },
]

// --- API ---
async function fetchPeriods() {
  loadingPeriods.value = true
  try {
    const res = await api.get('/api/v1/leave/periods')
    periods.value = res.data || []
    // Auto-select first if none selected
    if (!selectedPeriodId.value && periods.value.length > 0) {
      selectedPeriodId.value = periods.value[0].id
    }
  } catch (err) {
    notify.error('Gagal memuat daftar periode.')
  } finally {
    loadingPeriods.value = false
  }
}

async function fetchPolicies() {
  try {
    const res = await api.get('/api/v1/leave/policies')
    policies.value = res.data || []
  } catch (err) {
    notify.error('Gagal memuat kebijakan cuti.')
  }
}

// --- Period CRUD ---
function openPeriodForm(item) {
  periodErrors.value = {}
  if (item) {
    isEditingPeriod.value = true
    periodForm.id = item.id
    periodForm.name = item.name
    periodForm.start_date = item.start_date
    periodForm.end_date = item.end_date
    periodForm.is_carry_forward = !!item.is_carry_forward
  } else {
    isEditingPeriod.value = false
    periodForm.id = null
    periodForm.name = ''
    periodForm.start_date = ''
    periodForm.end_date = ''
    periodForm.is_carry_forward = false
  }
  showPeriodModal.value = true
}

async function submitPeriod() {
  periodErrors.value = {}
  if (!periodForm.name) periodErrors.value.name = 'Nama periode wajib diisi.'
  if (!periodForm.start_date) periodErrors.value.start_date = 'Tanggal mulai wajib dipilih.'
  if (!periodForm.end_date) periodErrors.value.end_date = 'Tanggal selesai wajib dipilih.'
  if (Object.keys(periodErrors.value).length > 0) return

  submittingPeriod.value = true
  try {
    const payload = {
      name: periodForm.name,
      start_date: periodForm.start_date,
      end_date: periodForm.end_date,
      is_carry_forward: periodForm.is_carry_forward,
    }

    if (isEditingPeriod.value) {
      await api.put(`/api/v1/leave/periods/${periodForm.id}`, payload)
      notify.success('Periode berhasil diupdate.')
    } else {
      await api.post('/api/v1/leave/periods', payload)
      notify.success('Periode berhasil ditambahkan.')
    }
    showPeriodModal.value = false
    await fetchPeriods()
  } catch (err) {
    notify.error(err.message || 'Gagal menyimpan periode.')
  } finally {
    submittingPeriod.value = false
  }
}

function confirmDeletePeriod(item) {
  confirmDelete.id = item.id
  confirmDelete.name = item.name
  confirmDelete.show = true
}

async function executeDeletePeriod() {
  confirmDelete.show = false
  try {
    await api.delete(`/api/v1/leave/periods/${confirmDelete.id}`)
    notify.success('Periode berhasil dihapus.')
    await fetchPeriods()
  } catch (err) {
    notify.error(err.message || 'Gagal menghapus periode.')
  }
}

// --- Generate ---
async function executeGenerate() {
  showConfirmGenerate.value = false
  generating.value = true
  try {
    const res = await api.post('/api/v1/leave/generate-quota', {
      leave_period_id: selectedPeriodId.value,
      leave_policy_id: selectedPolicyId.value,
    })
    notify.success(res.message || 'Kuota cuti berhasil digenerate.')
    await fetchPeriods()
  } catch (err) {
    notify.error(err.message || 'Gagal generate kuota cuti.')
  } finally {
    generating.value = false
  }
}

// --- Helpers ---
function periodBadgeVariant(status) {
  const map = { active: 'success', recap: 'warning', closed: 'neutral' }
  return map[status] || 'neutral'
}

// --- Ambil Data Cuti ---
async function openImportPanel() {
  showImportPanel.value = true
  employeeSearch.value = ''
  selectedEmployeeIds.value = []
  await fetchEmployees()
}

function closeImportPanel() {
  showImportPanel.value = false
}

function handleEmployeeSearch() {
  clearTimeout(employeeSearchTimer)
  employeeSearchTimer = setTimeout(fetchEmployees, 300)
}

async function fetchEmployees() {
  loadingEmployees.value = true
  try {
    const params = new URLSearchParams({ per_page: '100', is_active: '1' })
    if (employeeSearch.value.trim()) params.set('search', employeeSearch.value.trim())

    const res = await api.get(`/api/v1/supervisor/employee-data/karyawan?${params.toString()}`)
    employees.value = res.data || []
  } catch (err) {
    notify.error(err.message || 'Gagal memuat karyawan.')
  } finally {
    loadingEmployees.value = false
  }
}

function toggleSelectAllVisible(event) {
  if (event.target.checked) {
    const ids = new Set(selectedEmployeeIds.value)
    employees.value.forEach((employee) => ids.add(employee.id))
    selectedEmployeeIds.value = [...ids]
  } else {
    const visibleIds = new Set(employees.value.map((employee) => employee.id))
    selectedEmployeeIds.value = selectedEmployeeIds.value.filter((id) => !visibleIds.has(id))
  }
}

async function executeImportLedger() {
  if (!selectedEmployeeIds.value.length || !selectedPeriodId.value) return

  importing.value = true
  try {
    const res = await api.post('/api/v1/supervisor/leave/spv-ledger-cuti/import', {
      employee_ids: selectedEmployeeIds.value,
      leave_period_id: selectedPeriodId.value,
    })
    notify.success(res.message || 'Data cuti berhasil diambil.')
    closeImportPanel()
  } catch (err) {
    notify.error(err.message || 'Gagal mengambil data cuti.')
  } finally {
    importing.value = false
  }
}

// --- Init ---
onMounted(async () => {
  await fetchPeriods()
  await fetchPolicies()
})

onBeforeUnmount(() => {
  clearTimeout(employeeSearchTimer)
})
</script>

<style scoped>
.spv-fade-enter-active,
.spv-fade-leave-active {
  transition: opacity 0.2s ease;
}

.spv-fade-enter-from,
.spv-fade-leave-to {
  opacity: 0;
}

.spv-slide-enter-active,
.spv-slide-leave-active {
  transition: transform 0.25s ease;
}

.spv-slide-enter-from,
.spv-slide-leave-to {
  transform: translateX(100%);
}
</style>
