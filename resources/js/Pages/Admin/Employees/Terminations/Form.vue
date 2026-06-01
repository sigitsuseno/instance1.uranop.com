<script setup>
import { ref } from 'vue'
import { useApi } from '../../../../composables/useApi'
import { useNotificationStore } from '../../../../Stores/notification'

const props = defineProps({
  termination: { type: Object, default: null },
  employee: { type: Object, required: true }
})

const emit = defineEmits(['close', 'success'])

const { post, put } = useApi()
const notification = useNotificationStore()

const submitting = ref(false)
const errors = ref({})

const form = ref({
  employee_id: props.employee.id,
  termination_type: props.termination?.termination_type || '',
  termination_date: props.termination?.termination_date ? formatDateForInput(props.termination.termination_date) : '',
  effective_date: props.termination?.effective_date ? formatDateForInput(props.termination.effective_date) : '',
  reason: props.termination?.reason || '',
  settlement_amount: props.termination?.settlement_amount || '',
  settlement_notes: props.termination?.settlement_notes || '',
  is_eligible_for_rehire: props.termination?.is_eligible_for_rehire || false,
  clearance_asset: props.termination?.clearance_asset || false,
  clearance_finance: props.termination?.clearance_finance || false,
  clearance_it: props.termination?.clearance_it || false,
  clearance_notes: props.termination?.clearance_notes || '',
})

function formatDateForInput(date) {
  if (!date) return ''
  if (typeof date === 'string') return date.split('T')[0]
  return date
}

function formatCurrency(value) {
  if (!value && value !== 0) return ''
  const num = typeof value === 'string' ? parseInt(value.replace(/[^\d]/g, '')) : value
  if (isNaN(num)) return ''
  return new Intl.NumberFormat('id-ID').format(num)
}

function parseCurrency(value) {
  if (!value) return ''
  const raw = value.toString().replace(/[^\d]/g, '')
  return raw ? parseInt(raw) : ''
}

const terminationTypeOptions = [
  { value: 'resign', label: 'Resign (Mengundurkan Diri)' },
  { value: 'retirement', label: 'Pensiun' },
  { value: 'fired', label: 'PHK (Pemutusan Hubungan Kerja)' },
  { value: 'contract_end', label: 'Kontrak Habis' },
  { value: 'death', label: 'Meninggal Dunia' },
  { value: 'other', label: 'Lainnya' },
]

async function submit() {
  errors.value = {}
  
  if (!form.value.termination_type) { errors.value.termination_type = 'Jenis terminasi harus dipilih'; return }
  if (!form.value.termination_date) { errors.value.termination_date = 'Tanggal terminasi harus diisi'; return }
  if (!form.value.effective_date) { errors.value.effective_date = 'Tanggal efektif harus diisi'; return }
  if (!form.value.reason) { errors.value.reason = 'Alasan harus diisi'; return }

  submitting.value = true
  try {
    const payload = {
      ...form.value,
      settlement_amount: form.value.settlement_amount ? String(form.value.settlement_amount) : null
    }
    if (props.termination) {
      await put(`/api/v1/employees/${props.employee.id}/terminations/${props.termination.id}`, payload)
      notification.success('Terminasi berhasil diperbarui')
    } else {
      await post(`/api/v1/employees/${props.employee.id}/terminations`, payload)
      notification.success('Terminasi berhasil dibuat')
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
        {{ termination ? 'Edit Terminasi' : 'Buat Terminasi Baru' }}
      </h2>
      <button @click="emit('close')" class="text-(--text-muted) hover:text-(--text-main) transition-colors">
        <i class="bx bx-x text-2xl"></i>
      </button>
    </div>

    <div class="mb-4 p-3 bg-blue-500/10 border border-blue-500/20 rounded-md">
      <p class="text-sm text-blue-700 dark:text-blue-400 font-medium">
        <i class="bx bx-info-circle mr-1"></i>
        Karyawan: {{ employee.name }} ({{ employee.code }})
      </p>
      <p class="text-xs text-blue-600 dark:text-blue-300 mt-1">
        Status saat ini: {{ employee.employment_status_label || employee.employment_status }} - {{ employee.position?.name }}
      </p>
    </div>

    <form @submit.prevent="submit" class="space-y-4">
      <div>
        <label class="block text-sm font-medium text-(--text-main) mb-1">Jenis Terminasi <span class="text-red-600 dark:text-red-400">*</span></label>
        <select v-model="form.termination_type"
          class="w-full px-3 py-2 rounded-md bg-(--bg-elevated) border border-(--border-strong) text-(--text-main) focus:ring-2 focus:ring-(--primary-glow) focus:border-(--primary) transition-all outline-none"
          :class="{ 'border-red-600 dark:border-red-400': errors.termination_type }">
          <option value="">Pilih Jenis Terminasi</option>
          <option v-for="opt in terminationTypeOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
        </select>
        <p v-if="errors.termination_type" class="mt-1 text-sm text-red-600 dark:text-red-400 font-medium">{{ errors.termination_type }}</p>
      </div>

      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-(--text-main) mb-1">Tanggal Terminasi <span class="text-red-600 dark:text-red-400">*</span></label>
          <input v-model="form.termination_date" type="date"
            class="w-full px-3 py-2 rounded-md bg-(--bg-elevated) border border-(--border-strong) text-(--text-main) focus:ring-2 focus:ring-(--primary-glow) focus:border-(--primary) transition-all outline-none"
            :class="{ 'border-red-600 dark:border-red-400': errors.termination_date }">
          <p v-if="errors.termination_date" class="mt-1 text-sm text-red-600 dark:text-red-400 font-medium">{{ errors.termination_date }}</p>
        </div>
        <div>
          <label class="block text-sm font-medium text-(--text-main) mb-1">Tanggal Efektif <span class="text-red-600 dark:text-red-400">*</span></label>
          <input v-model="form.effective_date" type="date"
            class="w-full px-3 py-2 rounded-md bg-(--bg-elevated) border border-(--border-strong) text-(--text-main) focus:ring-2 focus:ring-(--primary-glow) focus:border-(--primary) transition-all outline-none"
            :class="{ 'border-red-600 dark:border-red-400': errors.effective_date }" :min="form.termination_date">
          <p v-if="errors.effective_date" class="mt-1 text-sm text-red-600 dark:text-red-400 font-medium">{{ errors.effective_date }}</p>
        </div>
      </div>

      <div>
        <label class="block text-sm font-medium text-(--text-main) mb-1">Alasan <span class="text-red-600 dark:text-red-400">*</span></label>
        <textarea v-model="form.reason" rows="3"
          class="w-full px-3 py-2 rounded-md bg-(--bg-elevated) border border-(--border-strong) text-(--text-main) focus:ring-2 focus:ring-(--primary-glow) focus:border-(--primary) transition-all outline-none resize-none"
          :class="{ 'border-red-600 dark:border-red-400': errors.reason }" placeholder="Jelaskan alasan resign/PHK..."></textarea>
        <p v-if="errors.reason" class="mt-1 text-sm text-red-600 dark:text-red-400 font-medium">{{ errors.reason }}</p>
      </div>

      <div class="bg-(--bg-elevated)/30 p-4 border border-(--border-soft) rounded-md">
        <h3 class="text-md font-semibold text-(--text-main) mb-3">Settlement / Uang Pisah</h3>
        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-(--text-main) mb-1">Jumlah Settlement</label>
            <div class="relative">
              <span class="absolute left-3 top-1/2 -translate-y-1/2 text-(--text-muted)">Rp</span>
              <input type="text" :value="formatCurrency(form.settlement_amount)"
                @input="(e) => form.settlement_amount = parseCurrency(e.target.value)"
                class="w-full pl-10 pr-4 py-2 rounded-md bg-(--bg-elevated) border border-(--border-strong) text-(--text-main) focus:ring-2 focus:ring-(--primary-glow) focus:border-(--primary) transition-all outline-none"
                placeholder="0">
            </div>
          </div>
          <div>
            <label class="block text-sm font-medium text-(--text-main) mb-1">Keterangan Settlement</label>
            <textarea v-model="form.settlement_notes" rows="2"
              class="w-full px-3 py-2 rounded-md bg-(--bg-elevated) border border-(--border-strong) text-(--text-main) focus:ring-2 focus:ring-(--primary-glow) focus:border-(--primary) transition-all outline-none resize-none"
              placeholder="Catatan tentang perhitungan settlement..."></textarea>
          </div>
        </div>
      </div>

      <div class="bg-(--bg-elevated)/30 p-4 border border-(--border-soft) rounded-md">
        <h3 class="text-md font-semibold text-(--text-main) mb-3">Exit Clearance</h3>
        <div class="space-y-3">
          <label class="flex items-center space-x-2 cursor-pointer group">
            <input type="checkbox" v-model="form.clearance_asset"
              class="w-4 h-4 rounded-md border-(--border-strong) text-(--primary) focus:ring-(--primary-glow) transition-all">
            <span class="text-sm text-(--text-main) group-hover:text-(--primary) transition-colors">Pengembalian aset perusahaan (laptop, id card, dll)</span>
          </label>
          <label class="flex items-center space-x-2 cursor-pointer group">
            <input type="checkbox" v-model="form.clearance_finance"
              class="w-4 h-4 rounded-md border-(--border-strong) text-(--primary) focus:ring-(--primary-glow) transition-all">
            <span class="text-sm text-(--text-main) group-hover:text-(--primary) transition-colors">Clearance keuangan (pinjaman, kasbon, dll)</span>
          </label>
          <label class="flex items-center space-x-2 cursor-pointer group">
            <input type="checkbox" v-model="form.clearance_it"
              class="w-4 h-4 rounded-md border-(--border-strong) text-(--primary) focus:ring-(--primary-glow) transition-all">
            <span class="text-sm text-(--text-main) group-hover:text-(--primary) transition-colors">Clearance IT (email, akun, akses)</span>
          </label>
          <div class="mt-2">
            <label class="block text-sm font-medium text-(--text-main) mb-1">Catatan Clearance</label>
            <textarea v-model="form.clearance_notes" rows="2"
              class="w-full px-3 py-2 rounded-md bg-(--bg-elevated) border border-(--border-strong) text-(--text-main) focus:ring-2 focus:ring-(--primary-glow) focus:border-(--primary) transition-all outline-none resize-none"
              placeholder="Catatan tambahan tentang clearance..."></textarea>
          </div>
        </div>
      </div>

      <div>
        <label class="flex items-center space-x-2 cursor-pointer group">
          <input type="checkbox" v-model="form.is_eligible_for_rehire"
            class="w-4 h-4 rounded-md border-(--border-strong) text-(--primary) focus:ring-(--primary-glow) transition-all">
          <span class="text-sm text-(--text-main) group-hover:text-(--primary) transition-colors">Bisa dipekerjakan kembali di masa depan</span>
        </label>
      </div>

      <div class="flex justify-end space-x-3 pt-4 border-t border-(--border-soft)">
        <button type="button" @click="emit('close')"
          class="px-4 py-2 border border-(--border-strong) rounded-md text-(--text-main) hover:bg-(--bg-elevated) transition-all outline-none">
          Batal
        </button>
        <button type="submit" :disabled="submitting"
          class="px-4 py-2 bg-(--primary) hover:bg-(--primary-hover) text-white rounded-md shadow-lg shadow-(--primary-glow) transition-all outline-none disabled:opacity-50 flex items-center space-x-2">
          <i v-if="submitting" class="bx bx-loader-alt bx-spin"></i>
          <i v-else :class="termination ? 'bx bx-save' : 'bx bx-plus-circle'"></i>
          <span>{{ termination ? 'Update' : 'Simpan' }}</span>
        </button>
      </div>
    </form>
  </div>
</template>
