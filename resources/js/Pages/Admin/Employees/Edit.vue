<script setup>
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useNotificationStore } from '../../../Stores/notification'
import BaseButton from '../../../Components/BaseButton.vue'
import BaseCard from '../../../Components/BaseCard.vue'
import TextInput from '../../../Components/TextInput.vue'
import SelectInput from '../../../Components/SelectInput.vue'
import { IconChevronLeft } from '../../../Components/Icons/index.js'

const router = useRouter()
const notification = useNotificationStore()

const submitting = ref(false)

const form = ref({
  nip: 'EMP001',
  name: 'Budi Santoso',
  email: 'budi@example.com',
  phone: '081234567890',
  birth_date: '1990-03-15',
  gender: 'Laki-laki',
  address: 'Jl. Merdeka No. 123, Jakarta Selatan, DKI Jakarta',
  department: 'IT',
  position: 'Senior Developer',
  employment_status: 'Tetap',
  join_date: '2023-01-15',
})

const departmentOptions = [
  { value: 'IT', label: 'IT' },
  { value: 'HR', label: 'HR' },
  { value: 'Finance', label: 'Finance' },
  { value: 'Marketing', label: 'Marketing' },
  { value: 'Operations', label: 'Operations' },
]

const genderOptions = [
  { value: 'Laki-laki', label: 'Laki-laki' },
  { value: 'Perempuan', label: 'Perempuan' },
]

const employmentStatusOptions = [
  { value: 'Tetap', label: 'Tetap' },
  { value: 'Kontrak', label: 'Kontrak' },
  { value: 'Probation', label: 'Probation' },
]

const positionOptionsByDept = {
  IT: [
    { value: 'Junior Developer', label: 'Junior Developer' },
    { value: 'Senior Developer', label: 'Senior Developer' },
    { value: 'Tech Lead', label: 'Tech Lead' },
    { value: 'DevOps Engineer', label: 'DevOps Engineer' },
    { value: 'QA Engineer', label: 'QA Engineer' },
  ],
  HR: [
    { value: 'HR Staff', label: 'HR Staff' },
    { value: 'HR Supervisor', label: 'HR Supervisor' },
    { value: 'Recruitment Officer', label: 'Recruitment Officer' },
    { value: 'Training Coordinator', label: 'Training Coordinator' },
    { value: 'HR Manager', label: 'HR Manager' },
  ],
  Finance: [
    { value: 'Finance Staff', label: 'Finance Staff' },
    { value: 'Accountant', label: 'Accountant' },
    { value: 'Finance Analyst', label: 'Finance Analyst' },
    { value: 'Tax Officer', label: 'Tax Officer' },
    { value: 'Finance Manager', label: 'Finance Manager' },
  ],
  Marketing: [
    { value: 'Marketing Staff', label: 'Marketing Staff' },
    { value: 'Content Writer', label: 'Content Writer' },
    { value: 'Graphic Designer', label: 'Graphic Designer' },
    { value: 'SEO Specialist', label: 'SEO Specialist' },
    { value: 'Marketing Manager', label: 'Marketing Manager' },
  ],
  Operations: [
    { value: 'Operations Staff', label: 'Operations Staff' },
    { value: 'Admin Officer', label: 'Admin Officer' },
    { value: 'Logistics Coordinator', label: 'Logistics Coordinator' },
    { value: 'Procurement Officer', label: 'Procurement Officer' },
    { value: 'Ops Manager', label: 'Ops Manager' },
  ],
}

const positionOptions = computed(() => {
  if (!form.value.department) return []
  return positionOptionsByDept[form.value.department] || []
})

async function submit() {
  submitting.value = true

  console.log('Form updated:', { ...form.value })

  await new Promise((resolve) => setTimeout(resolve, 500))

  notification.success('Data karyawan berhasil diperbarui')
  submitting.value = false
  router.push('/employees')
}
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center gap-3">
      <BaseButton variant="ghost" @click="router.push('/employees')">
        <template #icon-left>
          <IconChevronLeft class="w-5 h-5" />
        </template>
        Kembali
      </BaseButton>
    </div>

    <h1 class="text-2xl font-bold text-(--text-main)">
      Edit Karyawan - Budi Santoso (EMP001)
    </h1>

    <div class="space-y-6">
      <BaseCard>
        <template #title>Data Pribadi</template>
        <div class="space-y-4">
          <div class="grid grid-cols-2 gap-4">
            <TextInput v-model="form.nip" label="NIP" placeholder="Masukkan NIP" required />
            <TextInput v-model="form.name" label="Nama Lengkap" placeholder="Masukkan nama lengkap" required />
            <TextInput v-model="form.email" label="Email" type="email" placeholder="Masukkan email" required />
            <TextInput v-model="form.phone" label="No. Telepon" placeholder="Masukkan no. telepon" />
            <TextInput v-model="form.birth_date" label="Tanggal Lahir" type="date" />
            <SelectInput v-model="form.gender" label="Jenis Kelamin" :options="genderOptions" placeholder="Pilih jenis kelamin" />
          </div>
          <TextInput v-model="form.address" label="Alamat" placeholder="Masukkan alamat lengkap" />
        </div>
      </BaseCard>

      <BaseCard>
        <template #title>Data Kepegawaian</template>
        <div class="grid grid-cols-2 gap-4">
          <SelectInput v-model="form.department" label="Departemen" :options="departmentOptions" placeholder="Pilih departemen" required />
          <SelectInput
            v-model="form.position"
            label="Jabatan"
            :options="positionOptions"
            placeholder="Pilih jabatan"
            required
            :disabled="!form.department"
          />
          <SelectInput v-model="form.employment_status" label="Status Kepegawaian" :options="employmentStatusOptions" placeholder="Pilih status" required />
          <TextInput v-model="form.join_date" label="Tanggal Masuk" type="date" required />
        </div>
      </BaseCard>

      <div class="flex justify-end gap-3">
        <BaseButton variant="secondary" @click="router.push('/employees')">
          Batal
        </BaseButton>
        <BaseButton :loading="submitting" @click="submit">
          Simpan Perubahan
        </BaseButton>
      </div>
    </div>
  </div>
</template>
