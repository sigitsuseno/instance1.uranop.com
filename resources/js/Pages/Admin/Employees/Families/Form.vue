<script setup>
import { ref } from 'vue'
import { useApi } from '../../../../composables/useApi'
import { useNotificationStore } from '../../../../Stores/notification'

const props = defineProps({
  family: { type: Object, default: null },
  employee: { type: Object, required: true }
})

const emit = defineEmits(['close', 'success'])

const { post, put } = useApi()
const notification = useNotificationStore()

const submitting = ref(false)
const errors = ref({})

// Form data
const form = ref({
  employee_id: props.employee.id,
  relation: props.family?.relation || '',
  name: props.family?.name || '',
  gender: props.family?.gender || 'L',
  nik: props.family?.nik || '',
  date_of_birth: props.family?.date_of_birth ? formatDateForInput(props.family.date_of_birth) : '',
  education: props.family?.education || '',
  occupation: props.family?.occupation || '',
  is_dependent: props.family?.is_dependent || false,
  is_emergency_contact: props.family?.is_emergency_contact || false,
  emergency_phone: props.family?.emergency_phone || '',
})

function formatDateForInput(date) {
  if (!date) return ''
  if (typeof date === 'string') return date.split('T')[0]
  return date
}

const relationOptions = [
  { value: 'spouse', label: 'Suami/Istri' },
  { value: 'child', label: 'Anak' },
  { value: 'parent', label: 'Orang Tua' },
  { value: 'sibling', label: 'Saudara' },
  { value: 'other', label: 'Lainnya' },
]

async function submit() {
  errors.value = {}
  
  // Validasi
  if (!form.value.relation) { errors.value.relation = 'Hubungan keluarga harus dipilih'; return }
  if (!form.value.name) { errors.value.name = 'Nama harus diisi'; return }
  if (form.value.is_emergency_contact && !form.value.emergency_phone) {
    errors.value.emergency_phone = 'Nomor telepon darurat harus diisi'; return
  }

  submitting.value = true
  try {
    const payload = { ...form.value }
    if (props.family) {
      await put(`/api/v1/employees/${props.employee.id}/families/${props.family.id}`, payload)
      notification.success('Data keluarga berhasil diperbarui')
    } else {
      await post(`/api/v1/employees/${props.employee.id}/families`, payload)
      notification.success('Data keluarga berhasil ditambahkan')
    }
    emit('success')
  } catch (e) {
    if (e.response?.data?.errors) {
      errors.value = e.response.data.errors
    } else {
      notification.error(e.message || 'Gagal menyimpan data')
    }
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <div>
    <div class="flex items-center justify-between mb-4">
      <h2 class="text-lg font-semibold text-(--text-main)">
        {{ family ? 'Edit Anggota Keluarga' : 'Tambah Anggota Keluarga' }}
      </h2>
      <button @click="emit('close')" class="text-(--text-muted) hover:text-(--text-main)">
        <i class="bx bx-x text-2xl"></i>
      </button>
    </div>

    <div class="mb-4 p-3 bg-blue-500/10 border border-blue-500/20 rounded-md">
      <p class="text-sm text-blue-600 dark:text-blue-400">
        <i class="bx bx-info-circle mr-1"></i>
        Karyawan: {{ employee.name }} ({{ employee.code }})
      </p>
    </div>

    <form @submit.prevent="submit" class="space-y-4">
      <div>
        <label class="block text-sm font-medium text-(--text-main) mb-1">Hubungan <span class="text-red-600 dark:text-red-400">*</span></label>
        <select v-model="form.relation"
          class="w-full px-3 py-2 rounded-md bg-(--bg-elevated) border border-(--border-strong) text-(--text-main) focus:ring-2 focus:ring-(--primary-glow) focus:border-(--primary) outline-none transition-all"
          :class="{ 'border-red-600 dark:border-red-400': errors.relation }">
          <option value="">Pilih Hubungan</option>
          <option v-for="rel in relationOptions" :key="rel.value" :value="rel.value">{{ rel.label }}</option>
        </select>
        <p v-if="errors.relation" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ errors.relation }}</p>
      </div>

      <div>
        <label class="block text-sm font-medium text-(--text-main) mb-1">Nama Lengkap <span class="text-red-600 dark:text-red-400">*</span></label>
        <input v-model="form.name" type="text"
          class="w-full px-3 py-2 rounded-md bg-(--bg-elevated) border border-(--border-strong) text-(--text-main) focus:ring-2 focus:ring-(--primary-glow) focus:border-(--primary) outline-none transition-all"
          :class="{ 'border-red-600 dark:border-red-400': errors.name }">
        <p v-if="errors.name" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ errors.name }}</p>
      </div>

      <div>
        <label class="block text-sm font-medium text-(--text-main) mb-1">Jenis Kelamin <span class="text-red-600 dark:text-red-400">*</span></label>
        <div class="flex space-x-4">
          <label class="flex items-center cursor-pointer group">
            <input type="radio" v-model="form.gender" value="L"
              class="w-4 h-4 text-(--primary) border-(--border-strong) focus:ring-(--primary)">
            <span class="ml-2 text-sm text-(--text-main) group-hover:text-(--primary) transition-colors">Laki-laki</span>
          </label>
          <label class="flex items-center cursor-pointer group">
            <input type="radio" v-model="form.gender" value="P"
              class="w-4 h-4 text-(--primary) border-(--border-strong) focus:ring-(--primary)">
            <span class="ml-2 text-sm text-(--text-main) group-hover:text-(--primary) transition-colors">Perempuan</span>
          </label>
        </div>
      </div>

      <div>
        <label class="block text-sm font-medium text-(--text-main) mb-1">NIK</label>
        <input v-model="form.nik" type="text" maxlength="50"
          class="w-full px-3 py-2 rounded-md bg-(--bg-elevated) border border-(--border-strong) text-(--text-main) focus:ring-2 focus:ring-(--primary-glow) focus:border-(--primary) outline-none transition-all">
      </div>

      <div>
        <label class="block text-sm font-medium text-(--text-main) mb-1">Tanggal Lahir</label>
        <input v-model="form.date_of_birth" type="date"
          class="w-full px-3 py-2 rounded-md bg-(--bg-elevated) border border-(--border-strong) text-(--text-main) focus:ring-2 focus:ring-(--primary-glow) focus:border-(--primary) outline-none transition-all">
      </div>

      <div>
        <label class="block text-sm font-medium text-(--text-main) mb-1">Pendidikan</label>
        <input v-model="form.education" type="text"
          class="w-full px-3 py-2 rounded-md bg-(--bg-elevated) border border-(--border-strong) text-(--text-main) focus:ring-2 focus:ring-(--primary-glow) focus:border-(--primary) outline-none transition-all"
          placeholder="SD, SMP, SMA, S1, dll">
      </div>

      <div>
        <label class="block text-sm font-medium text-(--text-main) mb-1">Pekerjaan</label>
        <input v-model="form.occupation" type="text"
          class="w-full px-3 py-2 rounded-md bg-(--bg-elevated) border border-(--border-strong) text-(--text-main) focus:ring-2 focus:ring-(--primary-glow) focus:border-(--primary) outline-none transition-all"
          placeholder="Karyawan Swasta, Wirausaha, dll">
      </div>

      <div class="space-y-3">
        <label class="flex items-center space-x-2">
          <input type="checkbox" v-model="form.is_dependent"
            class="w-4 h-4 rounded border-(--border-strong) text-(--primary) focus:ring-(--primary)">
          <span class="text-sm text-(--text-main)">Tanggungan (untuk PTKP)</span>
        </label>
        <label class="flex items-center space-x-2">
          <input type="checkbox" v-model="form.is_emergency_contact"
            class="w-4 h-4 rounded border-(--border-strong) text-(--primary) focus:ring-(--primary)">
          <span class="text-sm text-(--text-main)">Kontak Darurat</span>
        </label>
      </div>

      <div v-if="form.is_emergency_contact">
        <label class="block text-sm font-medium text-(--text-main) mb-1">Nomor Telepon Darurat <span class="text-red-600 dark:text-red-400">*</span></label>
        <input v-model="form.emergency_phone" type="text"
          class="w-full px-3 py-2 rounded-md bg-(--bg-elevated) border border-(--border-strong) text-(--text-main) focus:ring-2 focus:ring-(--primary-glow) focus:border-(--primary) outline-none transition-all"
          :class="{ 'border-red-600 dark:border-red-400': errors.emergency_phone }" placeholder="081234567890">
        <p v-if="errors.emergency_phone" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ errors.emergency_phone }}</p>
        <p class="text-xs text-(--text-muted) mt-1">Contoh: 081234567890 atau 021-1234567</p>
      </div>

      <div class="flex justify-end space-x-3 pt-4 border-t border-(--border-soft)">
        <button type="button" @click="emit('close')"
          class="px-4 py-2 border border-(--border-strong) rounded-md text-(--text-main) hover:bg-(--bg-elevated) transition-all">
          Batal
        </button>
        <button type="submit" :disabled="submitting"
          class="px-4 py-2 bg-(--primary) hover:bg-(--primary-hover) text-white rounded-md shadow-lg shadow-(--primary-glow) transition-all disabled:opacity-50 flex items-center space-x-2">
          <i v-if="submitting" class="bx bx-loader-alt bx-spin"></i>
          <i v-else :class="family ? 'bx bx-save' : 'bx bx-plus-circle'"></i>
          <span>{{ family ? 'Update' : 'Simpan' }}</span>
        </button>
      </div>
    </form>
  </div>
</template>
