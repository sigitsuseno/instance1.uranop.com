<script setup>
import { ref, reactive, onMounted } from 'vue'
import BaseButton from '../../../../../Components/BaseButton.vue'
import BaseCard from '../../../../../Components/BaseCard.vue'
import SelectInput from '../../../../../Components/SelectInput.vue'
import TextInput from '../../../../../Components/TextInput.vue'
import { IconPencil } from '../../../../../Components/Icons/index.js'
import { useNotification } from '../../../../../composables/useNotification'
import { useApi } from '../../../../../composables/useApi'

const notification = useNotification()
const api = useApi()
const isEditing = ref(false)

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

async function fetchSettings() {
  try {
    const response = await api.get('/api/v1/settings/employee')
    if (response.data) {
      Object.assign(employeeSettings, response.data)
    }
  } catch (error) {
    notification.error('Gagal mengambil pengaturan karyawan')
  }
}

onMounted(() => {
  fetchSettings()
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

async function save() {
  try {
    await api.post('/api/v1/settings/employee', form)
    employeeSettings.contract_type = form.contract_type
    employeeSettings.probation_period = form.probation_period
    employeeSettings.notice_period = form.notice_period
    isEditing.value = false
    notification.success('Pengaturan karyawan berhasil disimpan')
  } catch (error) {
    notification.error('Gagal menyimpan pengaturan karyawan')
  }
}
</script>

<template>
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
</template>
