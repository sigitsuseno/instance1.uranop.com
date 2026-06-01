<script setup>
import { ref, watch } from 'vue'
import { useApi } from '../../../../composables/useApi'
import { useNotificationStore } from '../../../../Stores/notification'

const props = defineProps({
  document: { type: Object, default: null },
  employee: { type: Object, required: true }
})

const emit = defineEmits(['close', 'success'])

const { post } = useApi()
const notification = useNotificationStore()

const submitting = ref(false)
const errors = ref({})
const filePreview = ref(null)
const fileInput = ref(null)

const form = ref({
  employee_id: props.employee.id,
  document_type: props.document?.document_type || '',
  document_number: props.document?.document_number || '',
  title: props.document?.title || '',
  description: props.document?.description || '',
  issue_date: props.document?.issue_date ? formatDateForInput(props.document.issue_date) : '',
  expiry_date: props.document?.expiry_date ? formatDateForInput(props.document.expiry_date) : '',
  issued_by: props.document?.issued_by || '',
  file: null
})

function formatDateForInput(date) {
  if (!date) return ''
  if (typeof date === 'string') return date.split('T')[0]
  return date
}

const documentTypeOptions = [
  { value: 'ktp', label: 'KTP' },
  { value: 'kk', label: 'Kartu Keluarga' },
  { value: 'npwp', label: 'NPWP' },
  { value: 'bpjs', label: 'BPJS' },
  { value: 'ijazah', label: 'Ijazah' },
  { value: 'transkrip', label: 'Transkrip Nilai' },
  { value: 'sertifikat', label: 'Sertifikat' },
  { value: 'kontrak', label: 'Kontrak Kerja' },
  { value: 'sk', label: 'Surat Keputusan' },
  { value: 'other', label: 'Lainnya' },
]

// Auto-fill title from document type
watch(() => form.value.document_type, (type) => {
  if (type && !form.value.title && !props.document) {
    const labels = {
      'ktp': 'KTP', 'kk': 'Kartu Keluarga', 'npwp': 'NPWP', 'bpjs': 'BPJS',
      'ijazah': 'Ijazah', 'transkrip': 'Transkrip Nilai', 'sertifikat': 'Sertifikat',
      'kontrak': 'Kontrak Kerja', 'sk': 'Surat Keputusan', 'other': 'Dokumen'
    }
    form.value.title = labels[type] || 'Dokumen'
  }
})

function onFileChange(event) {
  const file = event.target.files[0]
  if (file) {
    form.value.file = file
    filePreview.value = file.name
  }
}

function removeFile() {
  form.value.file = null
  filePreview.value = null
  if (fileInput.value) {
    fileInput.value.value = ''
  }
}

async function submit() {
  errors.value = {}
  
  if (!form.value.document_type) { errors.value.document_type = 'Tipe dokumen harus dipilih'; return }
  if (!form.value.title) { errors.value.title = 'Judul dokumen harus diisi'; return }
  if (!props.document && !form.value.file) { errors.value.file = 'File harus diupload'; return }

  submitting.value = true
  try {
    const formData = new FormData()
    formData.append('employee_id', form.value.employee_id)
    formData.append('document_type', form.value.document_type)
    formData.append('title', form.value.title)
    if (form.value.document_number) formData.append('document_number', form.value.document_number)
    if (form.value.description) formData.append('description', form.value.description)
    if (form.value.issue_date) formData.append('issue_date', form.value.issue_date)
    if (form.value.expiry_date) formData.append('expiry_date', form.value.expiry_date)
    if (form.value.issued_by) formData.append('issued_by', form.value.issued_by)
    if (form.value.file) formData.append('file', form.value.file)

    if (props.document) {
      formData.append('_method', 'PUT')
    }

    await post(`/api/v1/employees/${props.employee.id}/documents${props.document ? `/${props.document.id}` : ''}`, formData)
    notification.success(props.document ? 'Dokumen berhasil diperbarui' : 'Dokumen berhasil diupload')
    emit('success')
  } catch (e) {
    if (e.response?.data?.errors) {
      errors.value = e.response.data.errors
    } else {
      notification.error(e.message || 'Gagal menyimpan dokumen')
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
        {{ document ? 'Edit Dokumen' : 'Upload Dokumen Baru' }}
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

    <form @submit.prevent="submit" class="space-y-4" enctype="multipart/form-data">
      <div>
        <label class="block text-sm font-medium text-(--text-main) mb-1">Tipe Dokumen <span class="text-red-600 dark:text-red-400">*</span></label>
        <select v-model="form.document_type"
          class="w-full px-3 py-2 rounded-md bg-(--bg-elevated) border border-(--border-strong) text-(--text-main) focus:ring-2 focus:ring-(--primary-glow) focus:border-(--primary) outline-none transition-all"
          :class="{ 'border-red-600 dark:border-red-400': errors.document_type }">
          <option value="">Pilih Tipe Dokumen</option>
          <option v-for="opt in documentTypeOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
        </select>
        <p v-if="errors.document_type" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ errors.document_type }}</p>
      </div>

      <div>
        <label class="block text-sm font-medium text-(--text-main) mb-1">Nomor Dokumen</label>
        <input v-model="form.document_number" type="text"
          class="w-full px-3 py-2 rounded-md bg-(--bg-elevated) border border-(--border-strong) text-(--text-main) focus:ring-2 focus:ring-(--primary-glow) focus:border-(--primary) outline-none transition-all"
          placeholder="Contoh: 1234567890">
      </div>

      <div>
        <label class="block text-sm font-medium text-(--text-main) mb-1">Judul Dokumen <span class="text-red-600 dark:text-red-400">*</span></label>
        <input v-model="form.title" type="text"
          class="w-full px-3 py-2 rounded-md bg-(--bg-elevated) border border-(--border-strong) text-(--text-main) focus:ring-2 focus:ring-(--primary-glow) focus:border-(--primary) outline-none transition-all"
          :class="{ 'border-red-600 dark:border-red-400': errors.title }" placeholder="Contoh: KTP, Ijazah S1, dll">
        <p v-if="errors.title" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ errors.title }}</p>
      </div>

      <div>
        <label class="block text-sm font-medium text-(--text-main) mb-1">Keterangan</label>
        <textarea v-model="form.description" rows="2"
          class="w-full px-3 py-2 rounded-md bg-(--bg-elevated) border border-(--border-strong) text-(--text-main) focus:ring-2 focus:ring-(--primary-glow) focus:border-(--primary) outline-none transition-all resize-none"
          placeholder="Tambahkan keterangan jika perlu..."></textarea>
      </div>

      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-(--text-main) mb-1">Tanggal Terbit</label>
          <input v-model="form.issue_date" type="date"
            class="w-full px-3 py-2 rounded-md bg-(--bg-elevated) border border-(--border-strong) text-(--text-main) focus:ring-2 focus:ring-(--primary-glow) focus:border-(--primary) outline-none transition-all">
        </div>
        <div>
          <label class="block text-sm font-medium text-(--text-main) mb-1">Diterbitkan Oleh</label>
          <input v-model="form.issued_by" type="text"
            class="w-full px-3 py-2 rounded-md bg-(--bg-elevated) border border-(--border-strong) text-(--text-main) focus:ring-2 focus:ring-(--primary-glow) focus:border-(--primary) outline-none transition-all"
            placeholder="Instansi/Instansi">
        </div>
      </div>

      <div>
        <label class="block text-sm font-medium text-(--text-main) mb-1">Tanggal Kadaluarsa</label>
        <input v-model="form.expiry_date" type="date"
          class="w-full px-3 py-2 rounded-md bg-(--bg-elevated) border border-(--border-strong) text-(--text-main) focus:ring-2 focus:ring-(--primary-glow) focus:border-(--primary) outline-none transition-all"
          :min="form.issue_date">
        <p class="text-xs text-(--text-muted) mt-1">Kosongi jika tidak ada masa berlaku</p>
      </div>

      <div>
        <label class="block text-sm font-medium text-(--text-main) mb-1">
          File Dokumen <span v-if="!document" class="text-red-600 dark:text-red-400">*</span>
        </label>

        <div v-if="document && document.file_path"
          class="mb-2 p-2 bg-blue-500/10 border border-blue-500/20 rounded-md flex items-center justify-between">
          <div class="flex items-center">
            <i class="bx bx-file text-blue-500 mr-2"></i>
            <span class="text-sm text-blue-600 dark:text-blue-400">{{ document.file_name }}</span>
          </div>
          <a :href="`/api/v1/employees/${employee.id}/documents/${document.id}/download`" target="_blank"
            class="text-blue-600 dark:text-blue-400 hover:underline text-sm">
            <i class="bx bx-download"></i>
          </a>
        </div>

        <div class="flex items-center space-x-3">
          <div class="flex-1">
            <input ref="fileInput" type="file" @change="onFileChange" accept=".pdf,.jpg,.jpeg,.png"
              class="w-full px-3 py-2 rounded-md bg-(--bg-elevated) border border-(--border-strong) text-(--text-main) file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-medium file:bg-(--primary) file:text-white hover:file:bg-(--primary-hover) outline-none transition-all"
              :class="{ 'border-red-600 dark:border-red-400': errors.file }">
          </div>
          <button v-if="filePreview || form.file" type="button" @click="removeFile"
            class="p-2 text-red-600 dark:text-red-400 hover:bg-red-500/10 rounded-md transition-all">
            <i class="bx bx-x text-xl"></i>
          </button>
        </div>
        <p v-if="errors.file" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ errors.file }}</p>
        <p class="text-xs text-(--text-muted) mt-1">Format: PDF, JPG, PNG. Maksimal 2MB</p>
        <p v-if="filePreview" class="text-xs text-emerald-600 dark:text-emerald-400 mt-1">File baru: {{ filePreview }}</p>
      </div>

      <div class="flex justify-end space-x-3 pt-4 border-t border-(--border-soft)">
        <button type="button" @click="emit('close')"
          class="px-4 py-2 border border-(--border-strong) rounded-md text-(--text-main) hover:bg-(--bg-elevated) transition-all">
          Batal
        </button>
        <button type="submit" :disabled="submitting"
          class="px-4 py-2 bg-(--primary) hover:bg-(--primary-hover) text-white rounded-md shadow-lg shadow-(--primary-glow) transition-all disabled:opacity-50 flex items-center space-x-2">
          <i v-if="submitting" class="bx bx-loader-alt bx-spin"></i>
          <i v-else :class="document ? 'bx bx-save' : 'bx bx-upload'"></i>
          <span>{{ document ? 'Update' : 'Upload' }}</span>
        </button>
      </div>
    </form>
  </div>
</template>
