<script setup>
import { ref, reactive } from 'vue'
import BaseButton from '../../../../Components/BaseButton.vue'
import BaseCard from '../../../../Components/BaseCard.vue'
import SelectInput from '../../../../Components/SelectInput.vue'
import TextInput from '../../../../Components/TextInput.vue'
import { IconPencil } from '../../../../Components/Icons/index.js'
import { useNotification } from '../../../../composables/useNotification'

const notification = useNotification()
const isEditing = ref(false)

// Mock Data
const employeeSettings = reactive({
  contract_type: 'permanent',
  probation_period: 3,
  notice_period: 30,
})

const form = reactive({
  contract_type: 'permanent',
  probation_period: 3,
  notice_period: 30,
})

function toggleEdit() {
  if (isEditing.value) {
    isEditing.value = false
  } else {
    isEditing.value = true
    form.contract_type = employeeSettings.contract_type
    form.probation_period = employeeSettings.probation_period
    form.notice_period = employeeSettings.notice_period
  }
}

function save() {
  employeeSettings.contract_type = form.contract_type
  employeeSettings.probation_period = form.probation_period
  employeeSettings.notice_period = form.notice_period
  isEditing.value = false
  notification.success('Pengaturan karyawan berhasil disimpan')
}
</script>

<template>
  <div class="space-y-6">
    <div>
      <h2 class="text-lg font-medium text-(--text-main)">Pengaturan Karyawan</h2>
      <p class="text-sm text-(--text-muted)">Atur nilai default untuk masa kontrak, masa percobaan, dan notifikasi pengunduran diri.</p>
    </div>

    <!-- Default Settings -->
    <BaseCard>
      <template #title>Nilai Default Karyawan Baru</template>
      <template #actions>
        <BaseButton variant="secondary" size="sm" @click="toggleEdit">
          <template #icon-left>
            <IconPencil class="w-4 h-4" />
          </template>
          {{ isEditing ? 'Batal' : 'Edit' }}
        </BaseButton>
      </template>

      <div v-if="!isEditing" class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
          <span class="text-xs text-(--text-muted)">Tipe Kontrak Default</span>
          <p class="text-sm text-(--text-main) font-medium">{{ employeeSettings.contract_type === 'permanent' ? 'Karyawan Tetap' : 'Kontrak (PKWT)' }}</p>
        </div>
        <div>
          <span class="text-xs text-(--text-muted)">Masa Percobaan Default</span>
          <p class="text-sm text-(--text-main) font-medium">{{ employeeSettings.probation_period }} Bulan</p>
        </div>
        <div>
          <span class="text-xs text-(--text-muted)">Periode Notis (Resign)</span>
          <p class="text-sm text-(--text-main) font-medium">{{ employeeSettings.notice_period }} Hari</p>
        </div>
      </div>

      <div v-else class="space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <SelectInput
            v-model="form.contract_type"
            label="Tipe Kontrak Default"
            :options="[
              { value: 'permanent', label: 'Karyawan Tetap (PKWTT)' },
              { value: 'contract', label: 'Kontrak (PKWT)' },
            ]"
          />
          <TextInput v-model="form.probation_period" label="Masa Percobaan (Bulan)" type="number" />
          <TextInput v-model="form.notice_period" label="Periode Notis (Hari)" type="number" />
        </div>
        <div class="pt-2">
          <BaseButton variant="primary" size="sm" @click="save" icon="bx bx-save">Simpan Perubahan</BaseButton>
        </div>
      </div>
    </BaseCard>

    <!-- Master Data Enums -->
    <BaseCard>
      <template #title>Data Master (Tabel Referensi)</template>
      <div class="space-y-4">
        <p class="text-sm text-(--text-muted)">Data master seperti Tipe Kontrak, Status Karyawan, Grup, dan Jabatan dikelola langsung di database agar lebih fleksibel.</p>
        
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div class="p-4 border border-(--border-soft) rounded-md bg-(--bg-main) flex items-center justify-between">
            <div>
              <h4 class="text-sm font-medium text-(--text-main)">Tipe Kontrak</h4>
              <p class="text-xs text-(--text-muted)">PKWT, PKWTT, Internship, dll.</p>
            </div>
            <BaseButton variant="ghost" size="sm" icon="bx bx-right-arrow-alt">Kelola</BaseButton>
          </div>
          
          <div class="p-4 border border-(--border-soft) rounded-md bg-(--bg-main) flex items-center justify-between">
            <div>
              <h4 class="text-sm font-medium text-(--text-main)">Status Karyawan</h4>
              <p class="text-xs text-(--text-muted)">Aktif, Resign, PHK, Cuti di Luar Tanggungan.</p>
            </div>
            <BaseButton variant="ghost" size="sm" icon="bx bx-right-arrow-alt">Kelola</BaseButton>
          </div>

          <div class="p-4 border border-(--border-soft) rounded-md bg-(--bg-main) flex items-center justify-between">
            <div>
              <h4 class="text-sm font-medium text-(--text-main)">Grup Karyawan (Employee Groups)</h4>
              <p class="text-xs text-(--text-muted)">Staff, Non-Staff, Management.</p>
            </div>
            <BaseButton variant="ghost" size="sm" icon="bx bx-right-arrow-alt">Kelola</BaseButton>
          </div>

          <div class="p-4 border border-(--border-soft) rounded-md bg-(--bg-main) flex items-center justify-between">
            <div>
              <h4 class="text-sm font-medium text-(--text-main)">Titel (Employee Titles)</h4>
              <p class="text-xs text-(--text-muted)">Dr., S.Kom, Prof., dll.</p>
            </div>
            <BaseButton variant="ghost" size="sm" icon="bx bx-right-arrow-alt">Kelola</BaseButton>
          </div>
        </div>
      </div>
    </BaseCard>
  </div>
</template>
