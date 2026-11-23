<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-(--text-main)">Generate Cuti Tahunan</h1>
        <p class="text-sm text-(--text-muted) mt-1">Buat periode cuti dan generate kuota cuti massal untuk seluruh karyawan aktif.</p>
      </div>
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
          @click="auth.isManajemen ? null : (showConfirmGenerate = true)"
          :loading="generating"
          :disabled="auth.isManajemen || !selectedPeriodId || !selectedPolicyId || generating"
          :class="auth.isManajemen ? 'opacity-50 cursor-not-allowed' : ''"
        >
          <template #icon-left>
            <i class="bx bx-magic-wand text-base"></i>
          </template>
          Proses Generate Kuota
        </BaseButton>
      </div>
    </BaseCard>

    <!-- Peringatan: karyawan sudah genap 1 tahun tapi belum menerima kuota -->
    <div
      v-if="showCandidatesBanner"
      class="mb-6 flex flex-col gap-3 rounded-md border border-(--warning)/40 bg-(--warning)/10 p-4 sm:flex-row sm:items-center sm:justify-between"
    >
      <div class="flex items-start gap-3">
        <i class="bx bx-error-circle text-xl text-(--warning)"></i>
        <div class="text-sm">
          <p class="font-semibold text-(--text-main)">
            {{ candidates.length }} karyawan sudah genap 1 tahun tetapi belum menerima kuota cuti
          </p>
          <p class="mt-1 text-xs text-(--text-muted)">
            Tambahkan ke daftar penerima untuk periode {{ selectedPeriodName }}.
          </p>
        </div>
      </div>
      <BaseButton size="sm" variant="primary" @click="missingModal = true">
        <template #icon-left>
          <i class="bx bx-list-plus text-base"></i>
        </template>
        Lihat &amp; Tambahkan
      </BaseButton>
    </div>

    <!-- Periode Card -->
    <BaseCard>
      <template #title>Daftar Periode Cuti</template>
      <template #actions>
        <BaseButton 
          variant="primary" 
          size="sm" 
          @click="auth.isManajemen ? null : openPeriodForm(null)"
          :disabled="auth.isManajemen"
          :class="auth.isManajemen ? 'opacity-50 cursor-not-allowed' : ''"
        >
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
            <BaseButton 
              variant="ghost" 
              size="sm" 
              @click="auth.isManajemen ? null : openPeriodForm(item)"
              :disabled="auth.isManajemen"
              :class="auth.isManajemen ? 'opacity-50 cursor-not-allowed text-gray-400' : ''"
            >
              <template #icon-left>
                <i class="bx bx-edit text-lg" :class="auth.isManajemen ? '' : 'text-(--primary)'"></i>
              </template>
            </BaseButton>
            <BaseButton 
              variant="ghost" 
              size="sm" 
              @click="auth.isManajemen ? null : confirmDeletePeriod(item)"
              :disabled="auth.isManajemen"
              :class="auth.isManajemen ? 'opacity-50 cursor-not-allowed text-gray-400' : ''"
            >
              <template #icon-left>
                <i class="bx bx-trash text-lg" :class="auth.isManajemen ? '' : 'text-(--danger)'"></i>
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
    <!-- Modal: karyawan sudah genap 1 tahun tapi belum menerima kuota -->
    <BaseModal
      :show="missingModal"
      title="Karyawan Belum Menerima Kuota Cuti"
      size="lg"
      @close="missingModal = false"
    >
      <div class="space-y-4">
        <p class="text-sm text-(--text-main)">
          Karyawan berikut sudah memenuhi masa kerja <strong>≥ 1 tahun</strong> pada periode
          <strong>{{ selectedPeriodName }}</strong>, tetapi belum ada di daftar penerima kuota
          <strong>{{ selectedPolicyName }}</strong>.
        </p>
        <p class="text-xs text-(--text-muted)">
          Setiap karyawan yang ditambahkan akan langsung menerima
          <strong>{{ policyEntitlementDays }} hari</strong> cuti untuk periode ini.
        </p>

        <DataTable
          :headers="candidateHeaders"
          :items="candidates"
          :loading="loadingCandidates"
          emptyText="Semua karyawan yang memenuhi syarat sudah menerima kuota cuti."
        >
          <template #item.masa_kerja="{ item }">
            <span class="font-medium text-(--text-main)">{{ item.masa_kerja }}</span>
            <span class="block text-xs text-(--text-muted)">Sejak {{ item.join_date_label }}</span>
          </template>
          <template #item.actions="{ item }">
            <BaseButton
              size="sm"
              variant="primary"
              :loading="addingId === item.employee_id"
              :disabled="addingId !== null || addingAll || auth.isManajemen"
              :class="auth.isManajemen ? 'opacity-50 cursor-not-allowed' : ''"
              @click="addCandidate(item)"
            >
              <template #icon-left>
                <i class="bx bx-plus text-base"></i>
              </template>
              Tambahkan
            </BaseButton>
          </template>
        </DataTable>
      </div>
      <template #footer>
        <BaseButton variant="ghost" @click="missingModal = false">Tutup</BaseButton>
        <BaseButton
          variant="primary"
          :loading="addingAll"
          :disabled="addingAll || addingId !== null || candidates.length === 0 || auth.isManajemen"
          :class="auth.isManajemen ? 'opacity-50 cursor-not-allowed' : ''"
          @click="addAllCandidates"
        >
          Tambahkan Semua ({{ candidates.length }})
        </BaseButton>
      </template>
    </BaseModal>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue'
import BaseCard from '../../../Components/BaseCard.vue'
import BaseButton from '../../../Components/BaseButton.vue'
import BaseModal from '../../../Components/BaseModal.vue'
import Badge from '../../../Components/Badge.vue'
import TextInput from '../../../Components/TextInput.vue'
import SelectInput from '../../../Components/SelectInput.vue'
import ConfirmDialog from '../../../Components/ConfirmDialog.vue'
import DataTable from '../../../Components/Table/DataTable.vue'
import { useApi } from '../../../composables/useApi'
import { useAuth } from '../../../composables/useAuth'
import { useNotification } from '../../../composables/useNotification'

const api = useApi()
const auth = useAuth()
const notify = useNotification()

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

// --- State: karyawan sudah 1 tahun tapi belum menerima kuota ---
const candidates = ref([])
const missingModal = ref(false)
const loadingCandidates = ref(false)
const addingId = ref(null)
const addingAll = ref(false)
const pageReady = ref(false)

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

const selectedPeriod = computed(() =>
  periods.value.find((x) => x.id === selectedPeriodId.value) || null
)

const selectedPolicy = computed(() =>
  policies.value.find((x) => x.id === selectedPolicyId.value) || null
)

const selectedPolicyName = computed(() => selectedPolicy.value?.name || '-')

const policyEntitlementDays = computed(() => Number(selectedPolicy.value?.entitlement_days || 0))

// Tampilkan daftar hanya jika periode SUDAH pernah digenerate — kalau belum,
// tindakan yang benar adalah "Proses Generate Kuota". Juga hanya untuk kebijakan
// yang mensyaratkan 1 tahun masa kerja & punya jatah hari.
const showCandidatesBanner = computed(
  () =>
    candidates.value.length > 0 &&
    !!selectedPeriod.value?.is_generated &&
    !!selectedPolicy.value?.requires_one_year_service &&
    policyEntitlementDays.value > 0
)

const candidateHeaders = [
  { key: 'name', label: 'Nama', sortable: false },
  { key: 'masa_kerja', label: 'Masa Kerja', sortable: false },
  { key: 'actions', label: 'Aksi', sortable: false },
]

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
    await loadCandidates()
  } catch (err) {
    notify.error(err.message || 'Gagal generate kuota cuti.')
  } finally {
    generating.value = false
  }
}

// --- Karyawan sudah 1 tahun tapi belum menerima kuota ---
// Pasangan periode+kebijakan yang terakhir dimuat (mencegah request ganda)
const lastLoaded = { period: null, policy: null }

async function loadCandidates({ autoOpen = false } = {}) {
  if (!selectedPeriodId.value || !selectedPolicyId.value) {
    candidates.value = []
    return
  }

  lastLoaded.period = selectedPeriodId.value
  lastLoaded.policy = selectedPolicyId.value
  loadingCandidates.value = true
  try {
    const query =
      `?leave_period_id=${encodeURIComponent(selectedPeriodId.value)}` +
      `&leave_policy_id=${encodeURIComponent(selectedPolicyId.value)}`
    const res = await api.get(`/api/v1/leave/generate-candidates${query}`)
    candidates.value = (res.data || []).map((c) => ({ ...c, id: c.employee_id }))

    if (autoOpen && showCandidatesBanner.value) {
      missingModal.value = true
    }
  } catch (err) {
    // Tidak punya akses / periode belum siap — sembunyikan daftar
    candidates.value = []
  } finally {
    loadingCandidates.value = false
  }
}

async function addCandidate(item) {
  if (auth.isManajemen) return

  addingId.value = item.employee_id
  try {
    const res = await api.post('/api/v1/leave/generate-quota-employee', {
      employee_id: item.employee_id,
      leave_period_id: selectedPeriodId.value,
      leave_policy_id: selectedPolicyId.value,
    })
    notify.success(res.message || `${item.name} berhasil ditambahkan.`)
    candidates.value = candidates.value.filter((c) => c.employee_id !== item.employee_id)
    if (candidates.value.length === 0) {
      missingModal.value = false
    }
    await fetchPeriods()
  } catch (err) {
    notify.error(err.message || 'Gagal menambahkan kuota cuti.')
  } finally {
    addingId.value = null
  }
}

async function addAllCandidates() {
  if (auth.isManajemen || candidates.value.length === 0 || addingAll.value) return

  addingAll.value = true
  const targets = [...candidates.value]
  let success = 0
  const failed = []

  for (const item of targets) {
    try {
      await api.post('/api/v1/leave/generate-quota-employee', {
        employee_id: item.employee_id,
        leave_period_id: selectedPeriodId.value,
        leave_policy_id: selectedPolicyId.value,
      })
      success++
      candidates.value = candidates.value.filter((c) => c.employee_id !== item.employee_id)
    } catch (err) {
      failed.push(item.name)
    }
  }

  addingAll.value = false
  if (success > 0) {
    notify.success(`${success} karyawan berhasil ditambahkan sebagai penerima kuota cuti.`)
  }
  if (failed.length > 0) {
    notify.error(`Gagal menambahkan: ${failed.join(', ')}`)
  }
  if (candidates.value.length === 0) {
    missingModal.value = false
  }
  await fetchPeriods()
}

// --- Helpers ---
function periodBadgeVariant(status) {
  const map = { active: 'success', recap: 'warning', closed: 'neutral' }
  return map[status] || 'neutral'
}

// --- Init ---
onMounted(async () => {
  await fetchPeriods()
  await fetchPolicies()

  // Default-kan kebijakan cuti tahunan supaya generate & tambah kuota bisa langsung dipakai
  if (!selectedPolicyId.value && policies.value.length > 0) {
    const annualPolicy =
      policies.value.find((p) => (p.leaveType?.code || p.leave_type?.code) === 'CT') ||
      policies.value.find((p) => p.requires_one_year_service)
    if (annualPolicy) {
      selectedPolicyId.value = annualPolicy.id
    }
  }

  pageReady.value = true
  await loadCandidates({ autoOpen: true })
})

// Muat ulang daftar calon penerima saat periode / kebijakan diganti
watch([selectedPeriodId, selectedPolicyId], () => {
  if (!pageReady.value) return
  if (selectedPeriodId.value === lastLoaded.period && selectedPolicyId.value === lastLoaded.policy) return
  loadCandidates()
})
</script>
