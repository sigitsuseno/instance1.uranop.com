<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useApi } from '../../../../composables/useApi'
import { useNotificationStore } from '../../../../Stores/notification'
import BaseButton from '../../../../Components/BaseButton.vue'
import BaseCard from '../../../../Components/BaseCard.vue'
import TextInput from '../../../../Components/TextInput.vue'
import SelectInput from '../../../../Components/SelectInput.vue'
import { IconChevronLeft, IconRefresh } from '../../../../Components/Icons/index.js'

const router = useRouter()
const route = useRoute()
const { get, put } = useApi()
const notification = useNotificationStore()

const employeeId = computed(() => Number(route.params.id))

const loading = ref(true)
const submitting = ref(false)
const employee = ref(null)

const form = ref({
  employee_code: '',
  nip: '',
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
  bank_name: '',
  bank_account_number: '',
  bank_account_name: '',
  bank_cabang: '',
  ptkp: '',
})

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
  { value: 'resigned', label: 'Resigned' },
  { value: 'terminated', label: 'Terminated' },
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

// ========== LOAD DATA ==========

async function loadDepartments() {
  loadingDepts.value = true
  try {
    const res = await get('/api/organization/departments/options')
    departments.value = res.data || []
  } catch {
    // fallback
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

async function fetchEmployee() {
  try {
    const res = await get(`/api/v1/employees/${employeeId.value}`)
    employee.value = res.data

    // Populate form
    Object.keys(form.value).forEach(key => {
      if (res.data[key] !== undefined) {
        form.value[key] = res.data[key]
      }
    })
    
    // Handle department/position loading specifically so options are ready
    if (form.value.department_id) {
      await loadPositions(form.value.department_id)
      form.value.position_id = res.data.position?.id || ''
    }

  } catch (e) {
    notification.error('Gagal memuat data karyawan')
    router.push('/employees')
  }
}

// ========== ACTIONS ==========

async function submit() {
  submitting.value = true
  try {
    const payload = { ...form.value }
    await put(`/api/v1/employees/${employeeId.value}`, payload)

    notification.success(`Data karyawan berhasil diperbarui.`)
    router.push(`/employees/${employeeId.value}`)
  } catch (e) {
    notification.error(e.message || 'Gagal menyimpan data karyawan.')
  } finally {
    submitting.value = false
  }
}

// ========== WATCHERS ==========
watch(() => form.value.department_id, (newVal, oldVal) => {
  if (oldVal !== undefined && oldVal !== '') {
    form.value.position_id = ''
  }
  loadPositions(newVal)
})

onMounted(async () => {
  loading.value = true
  await Promise.all([
    loadDepartments(),
    fetchEmployee()
  ])
  loading.value = false
})
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center gap-3">
      <BaseButton variant="ghost" @click="router.push(`/employees/${employeeId}`)">
        <template #icon-left>
          <IconChevronLeft class="w-5 h-5" />
        </template>
        Kembali
      </BaseButton>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="flex items-center justify-center py-16 text-(--text-muted)">
      <IconRefresh class="w-8 h-8 animate-spin mr-3" />
      <span>Memuat data...</span>
    </div>

    <template v-else-if="employee">
      <h1 class="text-2xl font-bold text-(--text-main)">
        Edit Karyawan - {{ employee.name }} ({{ employee.employee_code || employee.nik }})
      </h1>

      <div class="space-y-6">
        <BaseCard>
          <template #title>Data Pribadi</template>
          <div class="space-y-4">
            <div class="grid grid-cols-2 gap-4">
              <TextInput v-model="form.employee_code" label="Kode Karyawan" placeholder="Kode unik karyawan" />
              <TextInput v-model="form.nip" label="NIP" placeholder="Nomor Induk Pegawai" />
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
        </BaseCard>

        <BaseCard>
          <template #title>Data Kepegawaian</template>
          <div class="space-y-4">
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
        </BaseCard>

        <div class="flex justify-end gap-3">
          <BaseButton variant="secondary" @click="router.push(`/employees/${employeeId}`)">
            Batal
          </BaseButton>
          <BaseButton :loading="submitting" @click="submit">
            Simpan Perubahan
          </BaseButton>
        </div>
      </div>
    </template>
  </div>
</template>
