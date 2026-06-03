<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-(--text-main)">Pengaturan Cuti</h1>
        <p class="text-sm text-(--text-muted) mt-1">Konfigurasi tipe cuti, kebijakan, dan periode cuti</p>
      </div>
    </div>

    <div class="space-y-6">
      <BaseCard>
        <template #title>Tipe Cuti</template>
        <template #actions>
          <BaseButton variant="primary" size="sm" @click="openLeaveTypeForm(null)">
            <template #icon-left>
              <IconPlus class="w-4 h-4" />
            </template>
            Tambah Tipe
          </BaseButton>
        </template>

        <DataTable :headers="leaveTypeHeaders" :items="leaveTypes" :loading="loadingTypes">
          <template #item.code="{ value }">
            <span class="font-mono text-sm">{{ value }}</span>
          </template>
          <template #item.category="{ value }">
            <Badge :variant="value === 'leave' ? 'primary' : (value === 'permit' ? 'warning' : 'neutral')">
              {{ value }}
            </Badge>
          </template>
          <template #item.balance_type="{ value }">
             <Badge :variant="value === 'decrement' ? 'danger' : 'success'">
              {{ value }}
            </Badge>
          </template>
          <template #item.is_paid="{ value }">
            <Badge :variant="value ? 'success' : 'neutral'">{{ value ? 'Paid' : 'Unpaid' }}</Badge>
          </template>
          <template #item.actions="{ item }">
            <div class="flex items-center gap-1">
              <BaseButton variant="ghost" size="sm" @click="openLeaveTypeForm(item)">
                <IconPencil class="w-4 h-4" />
              </BaseButton>
              <BaseButton variant="ghost" size="sm" @click="deleteLeaveType(item)">
                <IconTrash class="w-4 h-4 text-(--danger)" />
              </BaseButton>
            </div>
          </template>
        </DataTable>
      </BaseCard>

      <BaseCard>
        <template #title>Kebijakan Cuti</template>
        
        <div class="space-y-4">
          <p class="text-sm text-(--text-muted)">Pengaturan kebijakan per tipe cuti.</p>
          <DataTable :headers="policyHeaders" :items="policies" :loading="loadingPolicies">
             <template #item.actions="{ item }">
               <div class="flex items-center gap-1">
                 <BaseButton variant="ghost" size="sm" @click="openPolicyForm(item)">
                   <IconPencil class="w-4 h-4" />
                 </BaseButton>
                 <BaseButton variant="ghost" size="sm" @click="deletePolicy(item)">
                   <IconTrash class="w-4 h-4 text-(--danger)" />
                 </BaseButton>
               </div>
             </template>
          </DataTable>
          <div class="flex justify-start">
             <BaseButton variant="outline" size="sm" @click="openPolicyForm(null)">Tambah Kebijakan</BaseButton>
          </div>
        </div>
      </BaseCard>

      <BaseCard>
        <template #title>Periode Cuti</template>

        <div class="space-y-4">
          <p class="text-sm text-(--text-muted)">Tentukan periode cuti (berbasis Idul Fitri) yang berlaku untuk seluruh karyawan.</p>
          <DataTable :headers="periodHeaders" :items="periods" :loading="loadingPeriods">
             <template #item.status="{ value }">
                <Badge :variant="value === 'active' ? 'success' : (value === 'recap' ? 'warning' : 'neutral')">
                  {{ value }}
                </Badge>
             </template>
             <template #item.actions="{ item }">
               <div class="flex items-center gap-1">
                 <BaseButton variant="ghost" size="sm" @click="openPeriodForm(item)">
                   <IconPencil class="w-4 h-4" />
                 </BaseButton>
                 <BaseButton variant="ghost" size="sm" @click="deletePeriod(item)">
                   <IconTrash class="w-4 h-4 text-(--danger)" />
                 </BaseButton>
               </div>
             </template>
          </DataTable>
          <div class="flex justify-start">
            <BaseButton variant="primary" size="sm" @click="openPeriodForm(null)">Tambah Periode</BaseButton>
          </div>
        </div>
      </BaseCard>
    </div>

    <!-- Modals (Type, Policy, Period) -->
    <BaseModal :show="showLeaveTypeModal" :title="editingLeaveType ? 'Edit Tipe Cuti' : 'Tambah Tipe Cuti'" size="md" @close="showLeaveTypeModal = false">
      <div class="space-y-4">
        <TextInput v-model="leaveTypeForm.code" label="Kode" placeholder="AL" />
        <TextInput v-model="leaveTypeForm.name" label="Nama Tipe" placeholder="Annual Leave" />
        
        <SelectInput v-model="leaveTypeForm.category" label="Kategori" :options="categoryOptions" />
        <SelectInput v-model="leaveTypeForm.balance_type" label="Tipe Saldo" :options="balanceTypeOptions" />
        
        <div class="grid grid-cols-2 gap-4">
          <TextInput v-model="leaveTypeForm.max_days" label="Maks Hari (opsional)" type="number" />
          <div class="flex items-center gap-2 mt-6">
            <input id="isPaid" v-model="leaveTypeForm.is_paid" type="checkbox" class="rounded border-(--border-soft) text-(--primary) focus:ring-(--primary)" />
            <label for="isPaid" class="text-sm text-(--text-main)">Dibayar (Paid Leave)</label>
          </div>
        </div>
      </div>
      <template #footer>
        <BaseButton variant="ghost" @click="showLeaveTypeModal = false">Batal</BaseButton>
        <BaseButton variant="primary" @click="saveLeaveType" :loading="savingType">
          {{ editingLeaveType ? 'Simpan' : 'Tambah' }}
        </BaseButton>
      </template>
    </BaseModal>

    <!-- Modal Policy -->
    <BaseModal :show="showPolicyModal" :title="editingPolicy ? 'Edit Kebijakan' : 'Tambah Kebijakan'" size="md" @close="showPolicyModal = false">
      <div class="space-y-4">
        <SelectInput v-model="policyForm.leave_type_id" label="Tipe Cuti" :options="leaveTypeOptions" />
        <TextInput v-model="policyForm.name" label="Nama Kebijakan" placeholder="Kebijakan Staff IT" />
        <TextInput v-model="policyForm.entitlement_days" label="Kuota (Hari)" type="number" />
        
        <div class="flex flex-col gap-2 mt-4">
          <div class="flex items-center gap-2">
            <input id="req1Y" v-model="policyForm.requires_one_year_service" type="checkbox" class="rounded border-(--border-soft) text-(--primary) focus:ring-(--primary)" />
            <label for="req1Y" class="text-sm text-(--text-main)">Syarat masa kerja 1 tahun</label>
          </div>
          <div class="flex items-center gap-2">
            <input id="ccf" v-model="policyForm.can_carry_forward" type="checkbox" class="rounded border-(--border-soft) text-(--primary) focus:ring-(--primary)" />
            <label for="ccf" class="text-sm text-(--text-main)">Sisa bisa dibawa ke tahun depan</label>
          </div>
        </div>
        <TextInput v-if="policyForm.can_carry_forward" v-model="policyForm.max_carry_forward_days" label="Maksimal Bawa (Hari)" type="number" />
      </div>
      <template #footer>
        <BaseButton variant="ghost" @click="showPolicyModal = false">Batal</BaseButton>
        <BaseButton variant="primary" @click="savePolicy" :loading="savingPolicy">
          {{ editingPolicy ? 'Simpan' : 'Tambah' }}
        </BaseButton>
      </template>
    </BaseModal>

    <!-- Modal Period -->
    <BaseModal :show="showPeriodModal" :title="editingPeriod ? 'Edit Periode' : 'Tambah Periode'" size="md" @close="showPeriodModal = false">
      <div class="space-y-4">
        <TextInput v-model="periodForm.name" label="Nama Periode" placeholder="Idul Fitri 2026-2027" />
        <div class="grid grid-cols-2 gap-4">
          <TextInput v-model="periodForm.start_date" label="Tanggal Mulai" type="date" />
          <TextInput v-model="periodForm.end_date" label="Tanggal Akhir" type="date" />
        </div>
        <SelectInput v-model="periodForm.status" label="Status" :options="statusOptions" />
      </div>
      <template #footer>
        <BaseButton variant="ghost" @click="showPeriodModal = false">Batal</BaseButton>
        <BaseButton variant="primary" @click="savePeriod" :loading="savingPeriod">
          {{ editingPeriod ? 'Simpan' : 'Tambah' }}
        </BaseButton>
      </template>
    </BaseModal>

    <ConfirmDialog
      :show="confirmDelete.show"
      title="Konfirmasi Hapus"
      :message="`Hapus data '${confirmDelete.name}'? Data tidak dapat dikembalikan.`"
      variant="danger"
      @confirm="confirmDeleteAction"
      @cancel="confirmDelete.show = false"
    />
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue'
import BaseCard from '../../../Components/BaseCard.vue'
import BaseButton from '../../../Components/BaseButton.vue'
import BaseModal from '../../../Components/BaseModal.vue'
import Badge from '../../../Components/Badge.vue'
import TextInput from '../../../Components/TextInput.vue'
import SelectInput from '../../../Components/SelectInput.vue'
import ConfirmDialog from '../../../Components/ConfirmDialog.vue'
import DataTable from '../../../Components/Table/DataTable.vue'
import { IconPlus, IconPencil, IconTrash } from '../../../Components/Icons/index.js'
import { useApi } from '../../../composables/useApi'
import { useNotification } from '../../../composables/useNotification'

const api = useApi()
const notify = useNotification()

// State Data
const leaveTypes = ref([])
const policies = ref([])
const periods = ref([])

const loadingTypes = ref(false)
const loadingPolicies = ref(false)
const loadingPeriods = ref(false)

// Modals State
const showLeaveTypeModal = ref(false)
const editingLeaveType = ref(null)
const savingType = ref(false)

const showPolicyModal = ref(false)
const editingPolicy = ref(null)
const savingPolicy = ref(false)

const showPeriodModal = ref(false)
const editingPeriod = ref(null)
const savingPeriod = ref(false)

const confirmDelete = reactive({ show: false, id: null, name: '', type: '' })

// Dropdown Options
const categoryOptions = [
  { value: 'leave', label: 'Cuti (Leave)' },
  { value: 'permit', label: 'Izin (Permit)' },
  { value: 'sick', label: 'Sakit (Sick)' },
  { value: 'special', label: 'Khusus (Special)' }
]
const balanceTypeOptions = [
  { value: 'decrement', label: 'Decrement (Kuota)' },
  { value: 'increment', label: 'Increment (Akumulasi)' },
  { value: 'none', label: 'None (Tidak dicatat)' }
]
const statusOptions = [
  { value: 'active', label: 'Active' },
  { value: 'recap', label: 'Recap' },
  { value: 'closed', label: 'Closed' }
]

const leaveTypeOptions = computed(() => {
  return leaveTypes.value.map(t => ({ value: t.id, label: `${t.code} - ${t.name}` }))
})

// Forms
const leaveTypeForm = reactive({ code: '', name: '', category: 'leave', balance_type: 'decrement', is_paid: true, max_days: null })
const policyForm = reactive({ leave_type_id: '', name: '', description: '', requires_one_year_service: true, can_carry_forward: false, max_carry_forward_days: null, entitlement_days: 12 })
const periodForm = reactive({ name: '', start_date: '', end_date: '', status: 'active' })

// Headers
const leaveTypeHeaders = [
  { key: 'code', label: 'Kode' },
  { key: 'name', label: 'Nama' },
  { key: 'category', label: 'Kategori' },
  { key: 'balance_type', label: 'Tipe Saldo' },
  { key: 'is_paid', label: 'Dibayar' },
  { key: 'actions', label: 'Aksi' }
]
const policyHeaders = [
  { key: 'name', label: 'Kebijakan' },
  { key: 'leave_type.name', label: 'Tipe Cuti' },
  { key: 'entitlement_days', label: 'Kuota' },
  { key: 'actions', label: 'Aksi' }
]
const periodHeaders = [
  { key: 'name', label: 'Periode' },
  { key: 'start_date', label: 'Mulai' },
  { key: 'end_date', label: 'Selesai' },
  { key: 'status', label: 'Status' },
  { key: 'actions', label: 'Aksi' }
]

// Fetch Data
const fetchData = async () => {
  loadingTypes.value = true
  loadingPolicies.value = true
  loadingPeriods.value = true
  
  try {
    const [resTypes, resPolicies, resPeriods] = await Promise.all([
      api.get('/api/v1/leave/types'),
      api.get('/api/v1/leave/policies'),
      api.get('/api/v1/leave/periods')
    ])
    leaveTypes.value = resTypes.data || []
    policies.value = resPolicies.data || []
    periods.value = resPeriods.data || []
  } catch (error) {
    notify.error('Gagal memuat data pengaturan cuti.')
  } finally {
    loadingTypes.value = false
    loadingPolicies.value = false
    loadingPeriods.value = false
  }
}

onMounted(() => {
  fetchData()
})

// --- Leave Types Methods ---
function openLeaveTypeForm(item) {
  if (item) {
    editingLeaveType.value = item
    Object.assign(leaveTypeForm, item)
  } else {
    editingLeaveType.value = null
    Object.assign(leaveTypeForm, { code: '', name: '', category: 'leave', balance_type: 'decrement', is_paid: true, max_days: null })
  }
  showLeaveTypeModal.value = true
}

async function saveLeaveType() {
  savingType.value = true
  try {
    if (editingLeaveType.value) {
      await api.put(`/api/v1/leave/types/${editingLeaveType.value.id}`, leaveTypeForm)
      notify.success('Tipe cuti diperbarui')
    } else {
      await api.post('/api/v1/leave/types', leaveTypeForm)
      notify.success('Tipe cuti ditambahkan')
    }
    showLeaveTypeModal.value = false
    fetchData()
  } catch (error) {
    notify.error('Gagal menyimpan tipe cuti')
  } finally {
    savingType.value = false
  }
}

function deleteLeaveType(item) {
  confirmDelete.id = item.id
  confirmDelete.name = item.name
  confirmDelete.type = 'leaveType'
  confirmDelete.show = true
}

// --- Policies Methods ---
function openPolicyForm(item) {
  if (item) {
    editingPolicy.value = item
    Object.assign(policyForm, item)
  } else {
    editingPolicy.value = null
    Object.assign(policyForm, { leave_type_id: '', name: '', description: '', requires_one_year_service: true, can_carry_forward: false, max_carry_forward_days: null, entitlement_days: 12 })
  }
  showPolicyModal.value = true
}

async function savePolicy() {
  savingPolicy.value = true
  try {
    if (editingPolicy.value) {
      await api.put(`/api/v1/leave/policies/${editingPolicy.value.id}`, policyForm)
      notify.success('Kebijakan diperbarui')
    } else {
      await api.post('/api/v1/leave/policies', policyForm)
      notify.success('Kebijakan ditambahkan')
    }
    showPolicyModal.value = false
    fetchData()
  } catch (error) {
    notify.error('Gagal menyimpan kebijakan')
  } finally {
    savingPolicy.value = false
  }
}

function deletePolicy(item) {
  confirmDelete.id = item.id
  confirmDelete.name = item.name
  confirmDelete.type = 'policy'
  confirmDelete.show = true
}

// --- Periods Methods ---
function formatDateForInput(date) {
  if (!date) return ''
  // API returns ISO 8601 (e.g. 2026-06-02T00:00:00.000000Z), input[type=date] needs YYYY-MM-DD
  return date.split('T')[0]
}

function openPeriodForm(item) {
  if (item) {
    editingPeriod.value = item
    Object.assign(periodForm, {
      name: item.name,
      start_date: formatDateForInput(item.start_date),
      end_date: formatDateForInput(item.end_date),
      status: item.status,
    })
  } else {
    editingPeriod.value = null
    Object.assign(periodForm, { name: '', start_date: '', end_date: '', status: 'active' })
  }
  showPeriodModal.value = true
}

async function savePeriod() {
  savingPeriod.value = true
  try {
    if (editingPeriod.value) {
      await api.put(`/api/v1/leave/periods/${editingPeriod.value.id}`, periodForm)
      notify.success('Periode diperbarui')
    } else {
      await api.post('/api/v1/leave/periods', periodForm)
      notify.success('Periode ditambahkan')
    }
    showPeriodModal.value = false
    fetchData()
  } catch (error) {
    notify.error('Gagal menyimpan periode')
  } finally {
    savingPeriod.value = false
  }
}

function deletePeriod(item) {
  confirmDelete.id = item.id
  confirmDelete.name = item.name
  confirmDelete.type = 'period'
  confirmDelete.show = true
}

// --- Delete Action ---
async function confirmDeleteAction() {
  try {
    if (confirmDelete.type === 'leaveType') {
      await api.delete(`/api/v1/leave/types/${confirmDelete.id}`)
    } else if (confirmDelete.type === 'policy') {
      await api.delete(`/api/v1/leave/policies/${confirmDelete.id}`)
    } else if (confirmDelete.type === 'period') {
      await api.delete(`/api/v1/leave/periods/${confirmDelete.id}`)
    }
    notify.success('Data berhasil dihapus')
    fetchData()
  } catch (error) {
    notify.error('Gagal menghapus data')
  } finally {
    confirmDelete.show = false
  }
}
</script>
