<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useApi } from '../../../../composables/useApi'
import { useNotificationStore } from '../../../../Stores/notification'
import BaseButton from '../../../../Components/BaseButton.vue'
import BaseCard from '../../../../Components/BaseCard.vue'
import TextInput from '../../../../Components/TextInput.vue'
import SelectInput from '../../../../Components/SelectInput.vue'
import { IconPlus, IconTrash, IconChevronLeft } from '../../../../Components/Icons/index.js'

const router = useRouter()
const notification = useNotificationStore()
const { get, post } = useApi()

const currentStep = ref(1)
const submitting = ref(false)

const form = ref({
  employee_code: '',
  nik: '',
  name: '',
  email: '',
  phone: '',
  date_of_birth: '',
  gender: '',
  place_of_birth: '',
  address: '',
  department_id: '',
  position_id: '',
  employment_status: '',
  join_date: '',
  origin_join_date: '',
  bank_name: '',
  bank_account_number: '',
  bank_account_name: '',
  bank_cabang: '',
  ptkp: '',
})

const familyMembers = ref([])
const departments = ref([])
const positions = ref([])
const loadingDepts = ref(false)
const loadingPositions = ref(false)

// ========== DYNAMIC OPTIONS ==========

const departmentOptions = computed(() =>
  departments.value.map(d => ({ value: d.id, label: d.name }))
)

const positionOptions = computed(() =>
  positions.value.map(p => ({ value: p.id, label: p.name }))
)

const genderOptions = [
  { value: 'L', label: 'Laki-laki' },
  { value: 'P', label: 'Perempuan' },
]

const employmentStatusOptions = [
  { value: 'permanent', label: 'Tetap' },
  { value: 'contract', label: 'Kontrak' },
  { value: 'probation', label: 'Probation' },
  { value: 'outsource', label: 'Outsource' },
  { value: 'freelance', label: 'Freelance' },
]

const ptkpOptions = [
  { value: 'TK/0', label: 'TK/0 - Tidak Kawin, 0 tanggungan' },
  { value: 'TK/1', label: 'TK/1 - Tidak Kawin, 1 tanggungan' },
  { value: 'TK/2', label: 'TK/2 - Tidak Kawin, 2 tanggungan' },
  { value: 'TK/3', label: 'TK/3 - Tidak Kawin, 3 tanggungan' },
  { value: 'K/0',  label: 'K/0 - Kawin, 0 tanggungan' },
  { value: 'K/1',  label: 'K/1 - Kawin, 1 tanggungan' },
  { value: 'K/2',  label: 'K/2 - Kawin, 2 tanggungan' },
  { value: 'K/3',  label: 'K/3 - Kawin, 3 tanggungan' },
]

const familyRelationOptions = [
  { value: 'spouse',  label: 'Pasangan' },
  { value: 'child',   label: 'Anak' },
  { value: 'parent',  label: 'Orang Tua' },
  { value: 'sibling', label: 'Saudara' },
  { value: 'other',   label: 'Lainnya' },
]

const familyGenderOptions = [
  { value: 'L', label: 'Laki-laki' },
  { value: 'P', label: 'Perempuan' },
]

const steps = [
  { number: 1, label: 'Data Pribadi' },
  { number: 2, label: 'Data Kepegawaian' },
  { number: 3, label: 'Data Keluarga' },
]

// ========== LOAD DATA ==========

async function loadDepartments() {
  loadingDepts.value = true
  try {
    const res = await get('/api/organization/departments/options')
    departments.value = res.data || []
  } catch {
    // fallback silent
  } finally {
    loadingDepts.value = false
  }
}

async function loadPositions(deptId) {
  if (!deptId) { positions.value = []; return }
  loadingPositions.value = true
  try {
    const res = await get(`/api/organization/positions/options?department_id=${deptId}`)
    positions.value = res.data || []
  } catch {
    positions.value = []
  } finally {
    loadingPositions.value = false
  }
}

// ========== ACTIONS ==========

function nextStep() {
  if (currentStep.value < 3) currentStep.value++
}

function prevStep() {
  if (currentStep.value > 1) currentStep.value--
}

function addFamilyMember() {
  familyMembers.value.push({ name: '', relation: 'child', gender: 'L', date_of_birth: '', is_dependent: false })
}

function removeFamilyMember(index) {
  familyMembers.value.splice(index, 1)
}

async function submit() {
  submitting.value = true
  try {
    const payload = { ...form.value }
    const res = await post('/api/v1/employees', payload)
    const employee = res.data

    // Save family members jika ada
    if (familyMembers.value.length > 0) {
      for (const member of familyMembers.value) {
        if (member.name) {
          try {
            await post(`/api/v1/employees/${employee.id}/families`, member)
          } catch {}
        }
      }
    }

    notification.success(`Karyawan ${employee.name} berhasil ditambahkan.`)
    router.push(`/employees/${employee.id}`)
  } catch (e) {
    notification.error(e.message || 'Gagal menyimpan data karyawan.')
  } finally {
    submitting.value = false
  }
}

// ========== WATCHERS ==========
import { watch } from 'vue'
watch(() => form.value.department_id, (newVal) => {
  form.value.position_id = ''
  loadPositions(newVal)
})

onMounted(() => {
  loadDepartments()
})
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
          <TextInput v-model="form.employee_code" label="Kode Karyawan" placeholder="Kosongkan untuk auto-generate" />
          <TextInput v-model="form.nik" label="NIK" placeholder="16 digit NIK KTP" />
          <TextInput v-model="form.name" label="Nama Lengkap" placeholder="Masukkan nama lengkap" required />
          <SelectInput v-model="form.gender" label="Jenis Kelamin" :options="genderOptions" placeholder="Pilih jenis kelamin" required />
          <TextInput v-model="form.date_of_birth" label="Tanggal Lahir" type="date" />
          <TextInput v-model="form.place_of_birth" label="Tempat Lahir" placeholder="Masukkan tempat lahir" />
          <TextInput v-model="form.email" label="Email" type="email" placeholder="Masukkan email" />
          <TextInput v-model="form.phone" label="No. Telepon" placeholder="Masukkan no. telepon" />
        </div>
        <TextInput v-model="form.address" label="Alamat" placeholder="Masukkan alamat lengkap" />
      </div>

      <div v-if="currentStep === 2" class="space-y-4">
        <h2 class="text-lg font-semibold text-(--text-main) mb-4">Data Kepegawaian</h2>
        <div class="grid grid-cols-2 gap-4">
          <SelectInput v-model="form.department_id" label="Departemen" :options="departmentOptions" placeholder="Pilih departemen" :disabled="loadingDepts" />
          <SelectInput
            v-model="form.position_id"
            label="Jabatan"
            :options="positionOptions"
            placeholder="Pilih jabatan"
            :disabled="!form.department_id || loadingPositions"
          />
          <SelectInput v-model="form.employment_status" label="Status Kepegawaian" :options="employmentStatusOptions" placeholder="Pilih status" required />
          <TextInput v-model="form.join_date" label="Tanggal Masuk" type="date" required />
          <TextInput v-model="form.origin_join_date" label="Tanggal Asal Masuk" type="date" />
          <SelectInput v-model="form.ptkp" label="Status PTKP" :options="ptkpOptions" placeholder="Pilih PTKP" />
        </div>
        <div class="mt-4">
          <h3 class="text-sm font-semibold text-(--text-main) mb-3">Data Bank (Opsional)</h3>
          <div class="grid grid-cols-3 gap-4">
            <TextInput v-model="form.bank_name" label="Nama Bank" placeholder="BCA, BRI, Mandiri..." />
            <TextInput v-model="form.bank_account_number" label="No. Rekening" placeholder="Nomor rekening" />
            <TextInput v-model="form.bank_account_name" label="Nama Pemilik Rekening" placeholder="Nama sesuai rekening" />
            <TextInput v-model="form.bank_cabang" label="Cabang Bank" placeholder="Cabang bank" />
          </div>
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
            <TextInput v-model="member.name" label="Nama" placeholder="Masukkan nama" required />
            <SelectInput v-model="member.relation" label="Hubungan" :options="familyRelationOptions" placeholder="Pilih hubungan" />
            <SelectInput v-model="member.gender" label="Jenis Kelamin" :options="familyGenderOptions" placeholder="Pilih" />
            <TextInput v-model="member.date_of_birth" label="Tanggal Lahir" type="date" />
            <div class="col-span-2 flex items-center gap-2">
              <input type="checkbox" v-model="member.is_dependent" :id="'dep-'+index" class="rounded" />
              <label :for="'dep-'+index" class="text-sm text-(--text-main)">Tanggungan PTKP</label>
            </div>
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
