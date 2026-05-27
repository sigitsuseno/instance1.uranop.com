<template>
  <div>
    <div class="mb-6">
      <h1 class="text-xl font-semibold text-(--text-main)">Pengaturan Sistem</h1>
      <p class="text-sm text-(--text-muted) mt-1">Konfigurasi dan pengaturan aplikasi HRIS</p>
    </div>

    <div class="space-y-6">
      <BaseCard>
        <template #title>Informasi Perusahaan</template>
        <template #actions>
          <BaseButton variant="secondary" size="sm" @click="toggleEdit('company')">
            <template #icon-left>
              <IconPencil class="w-4 h-4" />
            </template>
            {{ editingSection === 'company' ? 'Batal' : 'Edit' }}
          </BaseButton>
        </template>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div class="flex items-start gap-4">
            <div v-if="editingSection !== 'company'" class="w-24 h-24 rounded-md bg-(--bg-elevated) border border-(--border-soft) flex items-center justify-center">
              <svg xmlns="http://www.w3.org/2000/svg" class="w-10 h-10 text-(--text-soft)" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="3" width="18" height="18" rx="2" ry="2" />
                <circle cx="8.5" cy="8.5" r="1.5" />
                <polyline points="21 15 16 10 5 21" />
              </svg>
            </div>
            <div v-else class="w-24 h-24 rounded-md bg-(--bg-elevated) border-2 border-dashed border-(--border-soft) flex flex-col items-center justify-center cursor-pointer hover:border-(--primary) transition-colors">
              <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-(--text-muted)" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                <polyline points="17 8 12 3 7 8" />
                <line x1="12" y1="3" x2="12" y2="15" />
              </svg>
              <span class="text-[10px] text-(--text-muted) mt-1">Upload</span>
            </div>
          </div>

          <div class="space-y-4">
            <div v-if="editingSection !== 'company'" class="grid grid-cols-1 gap-3">
              <div>
                <span class="text-xs text-(--text-muted)">Nama Perusahaan</span>
                <p class="text-sm text-(--text-main) font-medium">PT. Perusahaan Kita Sejahtera</p>
              </div>
              <div>
                <span class="text-xs text-(--text-muted)">Alamat</span>
                <p class="text-sm text-(--text-main) font-medium">Jl. Jenderal Sudirman No. 123, Gedung Graha Utama Lt. 15, Jakarta Selatan 12190</p>
              </div>
              <div class="grid grid-cols-2 gap-3">
                <div>
                  <span class="text-xs text-(--text-muted)">Telepon</span>
                  <p class="text-sm text-(--text-main) font-medium">(021) 1234-5678</p>
                </div>
                <div>
                  <span class="text-xs text-(--text-muted)">Email</span>
                  <p class="text-sm text-(--text-main) font-medium">info@perusahaankita.co.id</p>
                </div>
              </div>
            </div>

            <div v-else class="space-y-3">
              <TextInput v-model="companyForm.name" label="Nama Perusahaan" />
              <TextInput v-model="companyForm.address" label="Alamat" />
              <div class="grid grid-cols-2 gap-3">
                <TextInput v-model="companyForm.phone" label="Telepon" />
                <TextInput v-model="companyForm.email" label="Email" type="email" />
              </div>
              <BaseButton variant="primary" size="sm" @click="saveCompany">Simpan</BaseButton>
            </div>
          </div>
        </div>
      </BaseCard>

      <BaseCard>
        <template #title>Pengaturan Umum</template>
        <template #actions>
          <BaseButton variant="secondary" size="sm" @click="toggleEdit('general')">
            <template #icon-left>
              <IconPencil class="w-4 h-4" />
            </template>
            {{ editingSection === 'general' ? 'Batal' : 'Edit' }}
          </BaseButton>
        </template>

        <div v-if="editingSection !== 'general'" class="grid grid-cols-2 md:grid-cols-4 gap-4">
          <div>
            <span class="text-xs text-(--text-muted)">Zona Waktu</span>
            <p class="text-sm text-(--text-main) font-medium">{{ generalSettings.timezone }}</p>
          </div>
          <div>
            <span class="text-xs text-(--text-muted)">Format Tanggal</span>
            <p class="text-sm text-(--text-main) font-medium">{{ generalSettings.date_format }}</p>
          </div>
          <div>
            <span class="text-xs text-(--text-muted)">Bahasa</span>
            <p class="text-sm text-(--text-main) font-medium">{{ generalSettings.language }}</p>
          </div>
          <div>
            <span class="text-xs text-(--text-muted)">Mata Uang</span>
            <p class="text-sm text-(--text-main) font-medium">{{ generalSettings.currency }}</p>
          </div>
        </div>

        <div v-else class="space-y-4">
          <div class="grid grid-cols-2 gap-4">
            <SelectInput
              v-model="generalForm.timezone"
              label="Zona Waktu"
              :options="[
                { value: 'Asia/Jakarta', label: 'Asia/Jakarta (WIB)' },
                { value: 'Asia/Makassar', label: 'Asia/Makassar (WITA)' },
                { value: 'Asia/Jayapura', label: 'Asia/Jayapura (WIT)' },
              ]"
            />
            <SelectInput
              v-model="generalForm.date_format"
              label="Format Tanggal"
              :options="[
                { value: 'DD/MM/YYYY', label: 'DD/MM/YYYY' },
                { value: 'MM/DD/YYYY', label: 'MM/DD/YYYY' },
                { value: 'YYYY-MM-DD', label: 'YYYY-MM-DD' },
              ]"
            />
          </div>
          <div class="grid grid-cols-2 gap-4">
            <SelectInput
              v-model="generalForm.language"
              label="Bahasa"
              :options="[
                { value: 'id', label: 'Bahasa Indonesia' },
                { value: 'en', label: 'English' },
              ]"
            />
            <SelectInput
              v-model="generalForm.currency"
              label="Mata Uang"
              :options="[
                { value: 'IDR', label: 'IDR (Rupiah)' },
                { value: 'USD', label: 'USD (Dollar)' },
              ]"
            />
          </div>
          <BaseButton variant="primary" size="sm" @click="saveGeneral">Simpan</BaseButton>
        </div>
      </BaseCard>

      <BaseCard>
        <template #title>Pengaturan Karyawan</template>
        <template #actions>
          <BaseButton variant="secondary" size="sm" @click="toggleEdit('employee')">
            <template #icon-left>
              <IconPencil class="w-4 h-4" />
            </template>
            {{ editingSection === 'employee' ? 'Batal' : 'Edit' }}
          </BaseButton>
        </template>

        <div v-if="editingSection !== 'employee'" class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <span class="text-xs text-(--text-muted)">Tipe Kontrak Default</span>
            <p class="text-sm text-(--text-main) font-medium">{{ employeeSettings.contract_type === 'permanent' ? 'Karyawan Tetap' : 'Kontrak' }}</p>
          </div>
          <div>
            <span class="text-xs text-(--text-muted)">Masa Percobaan</span>
            <p class="text-sm text-(--text-main) font-medium">{{ employeeSettings.probation_period }} Bulan</p>
          </div>
          <div>
            <span class="text-xs text-(--text-muted)">Periode Notis Pengunduran Diri</span>
            <p class="text-sm text-(--text-main) font-medium">{{ employeeSettings.notice_period }} Hari</p>
          </div>
        </div>

        <div v-else class="space-y-4">
          <div class="grid grid-cols-3 gap-4">
            <SelectInput
              v-model="employeeForm.contract_type"
              label="Tipe Kontrak Default"
              :options="[
                { value: 'permanent', label: 'Karyawan Tetap' },
                { value: 'contract', label: 'Kontrak' },
              ]"
            />
            <TextInput v-model="employeeForm.probation_period" label="Masa Percobaan (Bulan)" type="number" />
            <TextInput v-model="employeeForm.notice_period" label="Periode Notis (Hari)" type="number" />
          </div>
          <BaseButton variant="primary" size="sm" @click="saveEmployee">Simpan</BaseButton>
        </div>
      </BaseCard>

      <BaseCard>
        <template #title>Pengaturan Absensi</template>
        <template #actions>
          <BaseButton variant="secondary" size="sm" @click="toggleEdit('attendance')">
            <template #icon-left>
              <IconPencil class="w-4 h-4" />
            </template>
            {{ editingSection === 'attendance' ? 'Batal' : 'Edit' }}
          </BaseButton>
        </template>

        <div v-if="editingSection !== 'attendance'" class="grid grid-cols-2 md:grid-cols-4 gap-4">
          <div>
            <span class="text-xs text-(--text-muted)">Jam Masuk Kerja</span>
            <p class="text-sm text-(--text-main) font-medium">{{ attendanceSettings.work_start }}</p>
          </div>
          <div>
            <span class="text-xs text-(--text-muted)">Jam Pulang Kerja</span>
            <p class="text-sm text-(--text-main) font-medium">{{ attendanceSettings.work_end }}</p>
          </div>
          <div>
            <span class="text-xs text-(--text-muted)">Toleransi Keterlambatan</span>
            <p class="text-sm text-(--text-main) font-medium">{{ attendanceSettings.tolerance }} Menit</p>
          </div>
          <div>
            <span class="text-xs text-(--text-muted)">Maks. Jam Lembur</span>
            <p class="text-sm text-(--text-main) font-medium">{{ attendanceSettings.max_overtime }} Jam / Hari</p>
          </div>
        </div>

        <div v-else class="space-y-4">
          <div class="grid grid-cols-2 gap-4">
            <TextInput v-model="attendanceForm.work_start" label="Jam Masuk Kerja" type="time" />
            <TextInput v-model="attendanceForm.work_end" label="Jam Pulang Kerja" type="time" />
          </div>
          <div class="grid grid-cols-2 gap-4">
            <TextInput v-model="attendanceForm.tolerance" label="Toleransi Keterlambatan (Menit)" type="number" />
            <TextInput v-model="attendanceForm.max_overtime" label="Maks. Jam Lembur per Hari" type="number" />
          </div>
          <BaseButton variant="primary" size="sm" @click="saveAttendance">Simpan</BaseButton>
        </div>
      </BaseCard>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue'
import BaseButton from '../../../Components/BaseButton.vue'
import BaseCard from '../../../Components/BaseCard.vue'
import TextInput from '../../../Components/TextInput.vue'
import SelectInput from '../../../Components/SelectInput.vue'
import { IconPencil } from '../../../Components/Icons/index.js'

const editingSection = ref(null)

const generalSettings = reactive({
  timezone: 'Asia/Jakarta (WIB)',
  date_format: 'DD/MM/YYYY',
  language: 'Bahasa Indonesia',
  currency: 'IDR',
})

const generalForm = reactive({
  timezone: 'Asia/Jakarta',
  date_format: 'DD/MM/YYYY',
  language: 'id',
  currency: 'IDR',
})

const employeeSettings = reactive({
  contract_type: 'permanent',
  probation_period: 3,
  notice_period: 30,
})

const employeeForm = reactive({
  contract_type: 'permanent',
  probation_period: 3,
  notice_period: 30,
})

const attendanceSettings = reactive({
  work_start: '08:00',
  work_end: '17:00',
  tolerance: 30,
  max_overtime: 4,
})

const attendanceForm = reactive({
  work_start: '08:00',
  work_end: '17:00',
  tolerance: 30,
  max_overtime: 4,
})

const companyForm = reactive({
  name: 'PT. Perusahaan Kita Sejahtera',
  address: 'Jl. Jenderal Sudirman No. 123, Gedung Graha Utama Lt. 15, Jakarta Selatan 12190',
  phone: '(021) 1234-5678',
  email: 'info@perusahaankita.co.id',
})

function toggleEdit(section) {
  if (editingSection.value === section) {
    editingSection.value = null
  } else {
    editingSection.value = section
    if (section === 'general') {
      generalForm.timezone = generalSettings.timezone.includes('Jakarta') ? 'Asia/Jakarta' : generalSettings.timezone
      generalForm.date_format = generalSettings.date_format
      generalForm.language = generalSettings.language === 'Bahasa Indonesia' ? 'id' : 'en'
      generalForm.currency = generalSettings.currency
    }
    if (section === 'employee') {
      employeeForm.contract_type = employeeSettings.contract_type
      employeeForm.probation_period = employeeSettings.probation_period
      employeeForm.notice_period = employeeSettings.notice_period
    }
    if (section === 'attendance') {
      attendanceForm.work_start = attendanceSettings.work_start
      attendanceForm.work_end = attendanceSettings.work_end
      attendanceForm.tolerance = attendanceSettings.tolerance
      attendanceForm.max_overtime = attendanceSettings.max_overtime
    }
  }
}

function saveCompany() {
  editingSection.value = null
  alert('Informasi perusahaan berhasil disimpan')
}

function saveGeneral() {
  generalSettings.timezone = generalForm.timezone + (generalForm.timezone === 'Asia/Jakarta' ? ' (WIB)' : generalForm.timezone === 'Asia/Makassar' ? ' (WITA)' : ' (WIT)')
  generalSettings.date_format = generalForm.date_format
  generalSettings.language = generalForm.language === 'id' ? 'Bahasa Indonesia' : 'English'
  generalSettings.currency = generalForm.currency
  editingSection.value = null
}

function saveEmployee() {
  employeeSettings.contract_type = employeeForm.contract_type
  employeeSettings.probation_period = employeeForm.probation_period
  employeeSettings.notice_period = employeeForm.notice_period
  editingSection.value = null
}

function saveAttendance() {
  attendanceSettings.work_start = attendanceForm.work_start
  attendanceSettings.work_end = attendanceForm.work_end
  attendanceSettings.tolerance = attendanceForm.tolerance
  attendanceSettings.max_overtime = attendanceForm.max_overtime
  editingSection.value = null
}
</script>
