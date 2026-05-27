<script setup>
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useNotificationStore } from '../../../Stores/notification'
import BaseButton from '../../../Components/BaseButton.vue'
import BaseCard from '../../../Components/BaseCard.vue'
import TextInput from '../../../Components/TextInput.vue'
import SelectInput from '../../../Components/SelectInput.vue'
import { IconPlus, IconTrash, IconChevronLeft } from '../../../Components/Icons/index.js'

const router = useRouter()
const notification = useNotificationStore()

const currentStep = ref(1)
const submitting = ref(false)

const form = ref({
  nip: '',
  name: '',
  email: '',
  phone: '',
  birth_date: '',
  gender: '',
  address: '',
  department: '',
  position: '',
  employment_status: '',
  join_date: '',
})

const familyMembers = ref([])

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

const steps = [
  { number: 1, label: 'Data Pribadi' },
  { number: 2, label: 'Data Kepegawaian' },
  { number: 3, label: 'Data Keluarga' },
]

function nextStep() {
  if (currentStep.value < 3) currentStep.value++
}

function prevStep() {
  if (currentStep.value > 1) currentStep.value--
}

function addFamilyMember() {
  familyMembers.value.push({ name: '', relation: '', phone: '', birth_date: '' })
}

function removeFamilyMember(index) {
  familyMembers.value.splice(index, 1)
}

async function submit() {
  submitting.value = true

  console.log('Form submitted:', { ...form.value, family_members: familyMembers.value })

  await new Promise((resolve) => setTimeout(resolve, 500))

  notification.success('Karyawan berhasil ditambahkan')
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

    <h1 class="text-2xl font-bold text-(--text-main)">Tambah Karyawan</h1>

    <BaseCard>
      <div class="flex items-center justify-center mb-8">
        <div class="flex items-center">
          <template v-for="(step, index) in steps" :key="step.number">
            <div class="flex items-center">
              <div
                :class="[
                  'flex items-center justify-center w-8 h-8 rounded-full text-sm font-bold transition-colors',
                  step.number < currentStep
                    ? 'bg-(--success) text-white'
                    : step.number === currentStep
                      ? 'bg-(--primary) text-white'
                      : 'bg-(--bg-elevated) text-(--text-muted)',
                ]"
              >
                {{ step.number < currentStep ? '&#10003;' : step.number }}
              </div>
              <span
                :class="[
                  'ml-2 text-sm font-medium',
                  step.number === currentStep ? 'text-(--primary)' : 'text-(--text-muted)',
                ]"
              >
                {{ step.label }}
              </span>
            </div>
            <div
              v-if="index < steps.length - 1"
              :class="[
                'w-16 h-0.5 mx-2',
                step.number < currentStep ? 'bg-(--success)' : 'bg-(--border-soft)',
              ]"
            />
          </template>
        </div>
      </div>

      <div v-if="currentStep === 1" class="space-y-4">
        <h2 class="text-lg font-semibold text-(--text-main) mb-4">Data Pribadi</h2>
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

      <div v-if="currentStep === 2" class="space-y-4">
        <h2 class="text-lg font-semibold text-(--text-main) mb-4">Data Kepegawaian</h2>
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
      </div>

      <div v-if="currentStep === 3" class="space-y-4">
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-lg font-semibold text-(--text-main)">Data Keluarga</h2>
          <BaseButton variant="secondary" size="sm" @click="addFamilyMember">
            <template #icon-left>
              <IconPlus class="w-4 h-4" />
            </template>
            Tambah Anggota
          </BaseButton>
        </div>
        <p class="text-sm text-(--text-muted) mb-4">Data keluarga bersifat opsional dan dapat ditambahkan nanti.</p>

        <div v-if="familyMembers.length === 0" class="text-center py-8 text-(--text-muted)">
          <p class="text-sm">Belum ada data anggota keluarga.</p>
        </div>

        <div v-for="(member, index) in familyMembers" :key="index" class="border border-(--border-soft) rounded-md p-4 space-y-3 relative">
          <div class="absolute top-3 right-3">
            <button
              class="p-1.5 rounded-md text-(--text-muted) hover:text-(--danger) hover:bg-(--danger)/10 transition-colors"
              @click="removeFamilyMember(index)"
            >
              <IconTrash class="w-4 h-4" />
            </button>
          </div>
          <p class="text-sm font-medium text-(--text-main)">Anggota Keluarga {{ index + 1 }}</p>
          <div class="grid grid-cols-2 gap-4">
            <TextInput v-model="member.name" label="Nama" placeholder="Masukkan nama" />
            <TextInput v-model="member.relation" label="Hubungan" placeholder="Contoh: Istri, Anak" />
            <TextInput v-model="member.phone" label="No. Telepon" placeholder="Masukkan no. telepon" />
            <TextInput v-model="member.birth_date" label="Tanggal Lahir" type="date" />
          </div>
        </div>
      </div>

      <div class="flex justify-between mt-8 pt-6 border-t border-(--border-soft)">
        <BaseButton
          v-if="currentStep > 1"
          variant="secondary"
          @click="prevStep"
        >
          Sebelumnya
        </BaseButton>
        <div v-else />

        <div class="flex gap-3">
          <BaseButton
            v-if="currentStep < 3"
            @click="nextStep"
          >
            Selanjutnya
          </BaseButton>
          <BaseButton
            v-if="currentStep === 3"
            :loading="submitting"
            @click="submit"
          >
            Simpan Karyawan
          </BaseButton>
        </div>
      </div>
    </BaseCard>
  </div>
</template>
