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

        <DataTable :headers="leaveTypeHeaders" :items="leaveTypes">
          <template #item.name="{ value }">{{ value }}</template>
          <template #item.default_days="{ value }">{{ value }} hari</template>
          <template #item.max_days="{ value }">{{ value }} hari</template>
          <template #item.can_carry_forward="{ value }">
            <Badge :variant="value ? 'success' : 'neutral'">{{ value ? 'Ya' : 'Tidak' }}</Badge>
          </template>
          <template #item.status="{ value }">
            <Badge :variant="value === 'active' ? 'success' : 'danger'">{{ value === 'active' ? 'Aktif' : 'Nonaktif' }}</Badge>
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
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <TextInput v-model="policy.maxCarryForward" label="Maksimal Cuti Dibawa ke Tahun Berikutnya (hari)" type="number" />
            <TextInput v-model="policy.minBalance" label="Minimal Saldo untuk Pengajuan (hari)" type="number" />
          </div>
          <div>
            <TextInput v-model="policy.advanceNotice" label="Pemberitahuan Minimal Sebelum Cuti (hari)" type="number" />
          </div>
          <div class="flex justify-end">
            <BaseButton variant="primary" @click="savePolicy">Simpan Kebijakan</BaseButton>
          </div>
        </div>
      </BaseCard>

      <BaseCard>
        <template #title>Periode Cuti</template>

        <div class="space-y-4">
          <p class="text-sm text-(--text-muted)">Tentukan periode tahun cuti yang berlaku untuk perhitungan saldo cuti karyawan.</p>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <SelectInput v-model="leavePeriod.startMonth" label="Bulan Mulai" :options="monthOptions" />
            <SelectInput v-model="leavePeriod.endMonth" label="Bulan Akhir" :options="monthOptions" />
          </div>
          <div class="flex justify-end">
            <BaseButton variant="primary" @click="savePeriod">Simpan Periode</BaseButton>
          </div>
        </div>
      </BaseCard>
    </div>

    <BaseModal :show="showLeaveTypeModal" :title="editingLeaveType ? 'Edit Tipe Cuti' : 'Tambah Tipe Cuti'" size="md" @close="showLeaveTypeModal = false">
      <div class="space-y-4">
        <TextInput v-model="leaveTypeForm.name" label="Nama Tipe Cuti" placeholder="Masukkan nama tipe cuti" />
        <div class="grid grid-cols-2 gap-4">
          <TextInput v-model="leaveTypeForm.defaultDays" label="Default Hari" type="number" />
          <TextInput v-model="leaveTypeForm.maxDays" label="Maksimal Hari" type="number" />
        </div>
        <div class="flex items-center gap-2">
          <input
            id="carryForward"
            v-model="leaveTypeForm.canCarryForward"
            type="checkbox"
            class="rounded border-(--border-soft) text-(--primary) focus:ring-(--primary)"
          />
          <label for="carryForward" class="text-sm text-(--text-main)">Dapat dibawa ke tahun berikutnya</label>
        </div>
        <SelectInput v-model="leaveTypeForm.status" label="Status" :options="statusFormOptions" />
      </div>
      <template #footer>
        <BaseButton variant="ghost" @click="showLeaveTypeModal = false">Batal</BaseButton>
        <BaseButton variant="primary" @click="saveLeaveType">
          {{ editingLeaveType ? 'Simpan' : 'Tambah' }}
        </BaseButton>
      </template>
    </BaseModal>

    <ConfirmDialog
      :show="confirmDelete.show"
      title="Hapus Tipe Cuti"
      :message="'Hapus tipe cuti \'' + confirmDelete.name + '\'? Data tidak dapat dikembalikan.'"
      variant="danger"
      @confirm="confirmDeleteAction"
      @cancel="confirmDelete.show = false"
    />
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue'
import BaseCard from '../../../Components/BaseCard.vue'
import BaseButton from '../../../Components/BaseButton.vue'
import BaseModal from '../../../Components/BaseModal.vue'
import Badge from '../../../Components/Badge.vue'
import TextInput from '../../../Components/TextInput.vue'
import SelectInput from '../../../Components/SelectInput.vue'
import ConfirmDialog from '../../../Components/ConfirmDialog.vue'
import DataTable from '../../../Components/Table/DataTable.vue'
import { IconPlus, IconPencil, IconTrash } from '../../../Components/Icons/index.js'

const showLeaveTypeModal = ref(false)
const editingLeaveType = ref(null)

const confirmDelete = reactive({ show: false, id: null, name: '' })

const policy = reactive({
  maxCarryForward: 6,
  minBalance: 2,
  advanceNotice: 3,
})

const leavePeriod = reactive({
  startMonth: '1',
  endMonth: '12',
})

const monthOptions = [
  { value: '1', label: 'Januari' },
  { value: '2', label: 'Februari' },
  { value: '3', label: 'Maret' },
  { value: '4', label: 'April' },
  { value: '5', label: 'Mei' },
  { value: '6', label: 'Juni' },
  { value: '7', label: 'Juli' },
  { value: '8', label: 'Agustus' },
  { value: '9', label: 'September' },
  { value: '10', label: 'Oktober' },
  { value: '11', label: 'November' },
  { value: '12', label: 'Desember' },
]

const statusFormOptions = [
  { value: 'active', label: 'Aktif' },
  { value: 'inactive', label: 'Nonaktif' },
]

const leaveTypeForm = reactive({
  name: '',
  defaultDays: 12,
  maxDays: 12,
  canCarryForward: true,
  status: 'active',
})

const leaveTypeHeaders = [
  { key: 'name', label: 'Nama Tipe Cuti' },
  { key: 'default_days', label: 'Default Hari' },
  { key: 'max_days', label: 'Maks. Hari' },
  { key: 'can_carry_forward', label: 'Dibawa Tahun Depan' },
  { key: 'status', label: 'Status' },
  { key: 'actions', label: 'Aksi' },
]

const leaveTypes = ref([
  { id: 1, name: 'Cuti Tahunan', default_days: 12, max_days: 12, can_carry_forward: true, status: 'active' },
  { id: 2, name: 'Cuti Sakit', default_days: 14, max_days: 14, can_carry_forward: false, status: 'active' },
  { id: 3, name: 'Cuti Melahirkan', default_days: 90, max_days: 90, can_carry_forward: false, status: 'active' },
  { id: 4, name: 'Cuti Besar', default_days: 30, max_days: 30, can_carry_forward: false, status: 'active' },
  { id: 5, name: 'Cuti Alasan Penting', default_days: 5, max_days: 5, can_carry_forward: false, status: 'active' },
])

function openLeaveTypeForm(item) {
  if (item) {
    editingLeaveType.value = item
    leaveTypeForm.name = item.name
    leaveTypeForm.defaultDays = item.default_days
    leaveTypeForm.maxDays = item.max_days
    leaveTypeForm.canCarryForward = item.can_carry_forward
    leaveTypeForm.status = item.status
  } else {
    editingLeaveType.value = null
    leaveTypeForm.name = ''
    leaveTypeForm.defaultDays = 12
    leaveTypeForm.maxDays = 12
    leaveTypeForm.canCarryForward = true
    leaveTypeForm.status = 'active'
  }
  showLeaveTypeModal.value = true
}

function saveLeaveType() {
  if (editingLeaveType.value) {
    const item = leaveTypes.value.find((i) => i.id === editingLeaveType.value.id)
    if (item) {
      item.name = leaveTypeForm.name
      item.default_days = leaveTypeForm.defaultDays
      item.max_days = leaveTypeForm.maxDays
      item.can_carry_forward = leaveTypeForm.canCarryForward
      item.status = leaveTypeForm.status
    }
  } else {
    leaveTypes.value.push({
      id: Date.now(),
      name: leaveTypeForm.name,
      default_days: leaveTypeForm.defaultDays,
      max_days: leaveTypeForm.maxDays,
      can_carry_forward: leaveTypeForm.canCarryForward,
      status: leaveTypeForm.status,
    })
  }
  showLeaveTypeModal.value = false
}

function deleteLeaveType(item) {
  confirmDelete.id = item.id
  confirmDelete.name = item.name
  confirmDelete.show = true
}

function confirmDeleteAction() {
  leaveTypes.value = leaveTypes.value.filter((i) => i.id !== confirmDelete.id)
  confirmDelete.show = false
}

function savePolicy() {
  alert('Kebijakan cuti berhasil disimpan.')
}

function savePeriod() {
  alert('Periode cuti berhasil disimpan.')
}
</script>
