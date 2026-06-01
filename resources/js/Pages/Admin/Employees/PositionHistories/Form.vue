<script setup>
import { ref, computed, onMounted } from 'vue'
import { useApi } from '../../../../composables/useApi'
import { useNotificationStore } from '../../../../Stores/notification'

const props = defineProps({
  positionHistory: { type: Object, default: null },
  employee: { type: Object, required: true }
})

const emit = defineEmits(['close', 'success'])

const { get, post, put } = useApi()
const notification = useNotificationStore()

const submitting = ref(false)
const departments = ref([])
const positions = ref([])
const salaryGrades = ref([])
const errors = ref({})

// Filtered positions
const filteredNewPositions = ref([])
const filteredOldPositions = ref([])

// Form data
const form = ref({
  employee_id: props.employee.id,
  old_department_id: props.positionHistory?.old_department_id || props.employee.department_id || '',
  old_position_id: props.positionHistory?.old_position_id || props.employee.position_id || '',
  old_salary_grade_id: props.positionHistory?.old_salary_grade_id || '',
  old_salary: props.positionHistory?.old_salary || '',
  new_department_id: props.positionHistory?.new_department_id || '',
  new_position_id: props.positionHistory?.new_position_id || '',
  new_salary_grade_id: props.positionHistory?.new_salary_grade_id || '',
  new_salary: props.positionHistory?.new_salary || '',
  effective_date: props.positionHistory?.effective_date ? formatDateForInput(props.positionHistory.effective_date) : '',
  change_reason: props.positionHistory?.change_reason || '',
  notes: props.positionHistory?.notes || ''
})

// Hitung selisih gaji
const salaryDifference = computed(() => {
  if (form.value.new_salary && form.value.old_salary) {
    const oldVal = parseFloat(form.value.old_salary) || 0
    const newVal = parseFloat(form.value.new_salary) || 0
    const diff = newVal - oldVal
    const percentage = oldVal > 0 ? ((diff / oldVal) * 100).toFixed(2) : 0
    return {
      amount: diff,
      percentage: percentage,
      isPromotion: diff > 0,
      isDemotion: diff < 0,
      isSame: diff === 0
    }
  }
  return null
})

// Watch old department
function onOldDepartmentChange(deptId) {
  if (deptId) {
    filteredOldPositions.value = positions.value.filter(pos => pos.department_id == deptId)
  } else {
    filteredOldPositions.value = []
  }
}

// Watch new department
function onNewDepartmentChange(deptId) {
  form.value.new_position_id = ''
  if (deptId) {
    filteredNewPositions.value = positions.value.filter(pos => pos.department_id == deptId)
  } else {
    filteredNewPositions.value = []
  }
}

// Format helpers
function formatDateForInput(date) {
  if (!date) return ''
  if (typeof date === 'string') return date.split('T')[0]
  if (date instanceof Date) return date.toISOString().split('T')[0]
  return ''
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

const reasonOptions = [
  { value: 'promotion', label: 'Promosi' },
  { value: 'demotion', label: 'Demosi' },
  { value: 'transfer', label: 'Mutasi' },
  { value: 'rotation', label: 'Rotasi' },
  { value: 'upgrade', label: 'Upgrade' },
  { value: 'restructuring', label: 'Restrukturisasi' },
]

async function fetchOptions() {
  try {
    const [deptRes, posRes] = await Promise.all([
      get('/api/organization/departments/options'),
      get('/api/organization/positions/options')
    ])
    departments.value = deptRes.data || []
    positions.value = posRes.data || []
    
    // Initial filter
    onOldDepartmentChange(form.value.old_department_id)
    if (form.value.new_department_id) {
      onNewDepartmentChange(form.value.new_department_id)
    }
  } catch (e) {
    notification.error('Gagal memuat data opsi')
  }
}

async function submit() {
  errors.value = {}
  
  // Validasi
  if (!form.value.new_department_id) {
    errors.value.new_department_id = 'Departemen baru harus dipilih'
    return
  }
  if (!form.value.new_position_id) {
    errors.value.new_position_id = 'Posisi baru harus dipilih'
    return
  }
  if (!form.value.effective_date) {
    errors.value.effective_date = 'Tanggal efektif harus diisi'
    return
  }
  if (!form.value.change_reason) {
    errors.value.change_reason = 'Alasan perubahan harus dipilih'
    return
  }

  submitting.value = true
  try {
    const payload = { ...form.value }
    if (props.positionHistory) {
      // Try PUT, fallback to POST approach
      try {
        await put(`/api/v1/employees/${props.employee.id}/position-histories/${props.positionHistory.id}`, payload)
      } catch (putErr) {
        // If PUT doesn't exist, let user know
        throw putErr
      }
    } else {
      await post(`/api/v1/employees/${props.employee.id}/position-histories`, payload)
    }
    notification.success(props.positionHistory ? 'Riwayat berhasil diperbarui' : 'Riwayat berhasil ditambahkan')
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

onMounted(() => {
  fetchOptions()
})
</script>

<template>
  <div>
    <!-- Header -->
    <div class="flex items-center justify-between mb-4">
      <h2 class="text-lg font-semibold text-(--text-main)">
        {{ positionHistory ? 'Edit Riwayat Pekerjaan' : 'Tambah Riwayat Pekerjaan' }}
      </h2>
      <button @click="emit('close')" class="text-(--text-muted) hover:text-(--text-main)">
        <i class="bx bx-x text-2xl"></i>
      </button>
    </div>

    <!-- Info Karyawan -->
    <div class="mb-4 p-3 bg-blue-500/10 border border-blue-500/20 rounded-md">
      <p class="text-sm text-blue-600 dark:text-blue-400">
        <i class="bx bx-info-circle mr-1"></i>
        Karyawan: {{ employee.name }} ({{ employee.code }})
      </p>
      <p class="text-xs text-blue-500/80 mt-1">
        Posisi saat ini: {{ employee.position?.name || '-' }} - {{ employee.department?.name || '-' }}
      </p>
    </div>

    <form @submit.prevent="submit" class="space-y-4">
      <!-- Posisi Lama -->
      <div class="bg-(--bg-elevated)/30 border border-(--border-soft) p-4 rounded-md">
        <h3 class="text-md font-semibold text-(--text-main) mb-3">Posisi Lama</h3>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-(--text-main) mb-1">Departemen</label>
            <select v-model="form.old_department_id" @change="onOldDepartmentChange(form.old_department_id)"
              class="w-full px-3 py-2 rounded-md bg-(--bg-elevated) border border-(--border-strong) text-(--text-main) focus:ring-2 focus:ring-(--primary-glow) focus:border-(--primary) outline-none transition-all">
              <option value="">Pilih Departemen</option>
              <option v-for="dept in departments" :key="dept.id" :value="dept.id">{{ dept.name }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-(--text-main) mb-1">Posisi</label>
            <select v-model="form.old_position_id"
              class="w-full px-3 py-2 rounded-md bg-(--bg-elevated) border border-(--border-strong) text-(--text-main) focus:ring-2 focus:ring-(--primary-glow) focus:border-(--primary) outline-none transition-all">
              <option value="">Pilih Posisi</option>
              <option v-for="pos in filteredOldPositions" :key="pos.id" :value="pos.id">{{ pos.name }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-(--text-main) mb-1">Gaji Lama</label>
            <div class="relative">
              <span class="absolute left-3 top-1/2 -translate-y-1/2 text-(--text-muted)">Rp</span>
              <input type="text" :value="formatCurrency(form.old_salary)"
                @input="(e) => form.old_salary = parseCurrency(e.target.value)"
                class="w-full pl-10 pr-4 py-2 rounded-md bg-(--bg-elevated) border border-(--border-strong) text-(--text-main) focus:ring-2 focus:ring-(--primary-glow) focus:border-(--primary) outline-none transition-all"
                placeholder="0">
            </div>
          </div>
        </div>
      </div>

      <!-- Posisi Baru -->
      <div class="bg-(--bg-elevated)/30 border border-(--border-soft) p-4 rounded-md">
        <h3 class="text-md font-semibold text-(--text-main) mb-3">Posisi Baru <span class="text-red-600 dark:text-red-400">*</span></h3>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-(--text-main) mb-1">Departemen <span class="text-red-600 dark:text-red-400">*</span></label>
            <select v-model="form.new_department_id" @change="onNewDepartmentChange(form.new_department_id)"
              class="w-full px-3 py-2 rounded-md bg-(--bg-elevated) border border-(--border-strong) text-(--text-main) focus:ring-2 focus:ring-(--primary-glow) focus:border-(--primary) outline-none transition-all"
              :class="{ 'border-red-600 dark:border-red-400': errors.new_department_id }">
              <option value="">Pilih Departemen</option>
              <option v-for="dept in departments" :key="dept.id" :value="dept.id">{{ dept.name }}</option>
            </select>
            <p v-if="errors.new_department_id" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ errors.new_department_id }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-(--text-main) mb-1">Posisi <span class="text-red-600 dark:text-red-400">*</span></label>
            <select v-model="form.new_position_id"
              class="w-full px-3 py-2 rounded-md bg-(--bg-elevated) border border-(--border-strong) text-(--text-main) focus:ring-2 focus:ring-(--primary-glow) focus:border-(--primary) outline-none transition-all"
              :class="{ 'border-red-600 dark:border-red-400': errors.new_position_id }">
              <option value="">Pilih Posisi</option>
              <option v-for="pos in filteredNewPositions" :key="pos.id" :value="pos.id">{{ pos.name }}</option>
            </select>
            <p v-if="errors.new_position_id" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ errors.new_position_id }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-(--text-main) mb-1">Gaji Baru</label>
            <div class="relative">
              <span class="absolute left-3 top-1/2 -translate-y-1/2 text-(--text-muted)">Rp</span>
              <input type="text" :value="formatCurrency(form.new_salary)"
                @input="(e) => form.new_salary = parseCurrency(e.target.value)"
                class="w-full pl-10 pr-4 py-2 rounded-md bg-(--bg-elevated) border border-(--border-strong) text-(--text-main) focus:ring-2 focus:ring-(--primary-glow) focus:border-(--primary) outline-none transition-all"
                placeholder="0">
            </div>
          </div>
        </div>
      </div>

      <!-- Salary Difference -->
      <div v-if="salaryDifference" class="p-3 border rounded-md transition-all"
        :class="{
          'bg-emerald-500/10 border-emerald-500/20': salaryDifference.isPromotion,
          'bg-red-500/10 border-red-500/20': salaryDifference.isDemotion,
          'bg-slate-500/10 border-slate-500/20': salaryDifference.isSame
        }">
        <div class="flex items-center">
          <i class="bx text-xl mr-2"
            :class="{
              'bx-trending-up text-emerald-600 dark:text-emerald-400': salaryDifference.isPromotion,
              'bx-trending-down text-red-600 dark:text-red-400': salaryDifference.isDemotion,
              'bx-minus-circle text-slate-600 dark:text-slate-400': salaryDifference.isSame
            }"></i>
          <div>
            <p class="text-sm font-semibold"
              :class="{
                'text-emerald-700 dark:text-emerald-300': salaryDifference.isPromotion,
                'text-red-700 dark:text-red-300': salaryDifference.isDemotion,
                'text-slate-700 dark:text-slate-300': salaryDifference.isSame
              }">
              {{ salaryDifference.isPromotion ? 'Kenaikan Gaji' :
                 salaryDifference.isDemotion ? 'Penurunan Gaji' : 'Tidak Ada Perubahan Gaji' }}
            </p>
            <p class="text-xs"
              :class="{
                'text-emerald-600 dark:text-emerald-400': salaryDifference.isPromotion,
                'text-red-600 dark:text-red-400': salaryDifference.isDemotion,
                'text-slate-600 dark:text-slate-400': salaryDifference.isSame
              }">
              <span v-if="!salaryDifference.isSame">
                Rp {{ Math.abs(salaryDifference.amount).toLocaleString('id-ID') }}
                ({{ Math.abs(salaryDifference.percentage) }}%)
              </span>
              <span v-else>Gaji tetap sama</span>
            </p>
          </div>
        </div>
      </div>

      <!-- Informasi Perubahan -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-(--text-main) mb-1">Tanggal Efektif <span class="text-red-600 dark:text-red-400">*</span></label>
          <input v-model="form.effective_date" type="date"
            class="w-full px-3 py-2 rounded-md bg-(--bg-elevated) border border-(--border-strong) text-(--text-main) focus:ring-2 focus:ring-(--primary-glow) focus:border-(--primary) outline-none transition-all"
            :class="{ 'border-red-600 dark:border-red-400': errors.effective_date }">
          <p v-if="errors.effective_date" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ errors.effective_date }}</p>
        </div>
        <div>
          <label class="block text-sm font-medium text-(--text-main) mb-1">Alasan Perubahan <span class="text-red-600 dark:text-red-400">*</span></label>
          <select v-model="form.change_reason"
            class="w-full px-3 py-2 rounded-md bg-(--bg-elevated) border border-(--border-strong) text-(--text-main) focus:ring-2 focus:ring-(--primary-glow) focus:border-(--primary) outline-none transition-all"
            :class="{ 'border-red-600 dark:border-red-400': errors.change_reason }">
            <option value="">Pilih Alasan</option>
            <option v-for="reason in reasonOptions" :key="reason.value" :value="reason.value">{{ reason.label }}</option>
          </select>
          <p v-if="errors.change_reason" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ errors.change_reason }}</p>
        </div>
        <div class="md:col-span-2">
          <label class="block text-sm font-medium text-(--text-main) mb-1">Catatan</label>
          <textarea v-model="form.notes" rows="3"
            class="w-full px-3 py-2 rounded-md bg-(--bg-elevated) border border-(--border-strong) text-(--text-main) focus:ring-2 focus:ring-(--primary-glow) focus:border-(--primary) outline-none transition-all resize-none"
            placeholder="Tambahkan keterangan jika diperlukan..."></textarea>
        </div>
      </div>

      <!-- Action Buttons -->
      <div class="flex justify-end space-x-3 pt-4 border-t border-(--border-soft)">
        <button type="button" @click="emit('close')"
          class="px-4 py-2 border border-(--border-strong) rounded-md text-(--text-main) hover:bg-(--bg-elevated) transition-all">
          Batal
        </button>
        <button type="submit" :disabled="submitting"
          class="px-4 py-2 bg-(--primary) hover:bg-(--primary-hover) text-white rounded-md shadow-lg shadow-(--primary-glow) transition-all disabled:opacity-50 flex items-center space-x-2">
          <i v-if="submitting" class="bx bx-loader-alt bx-spin"></i>
          <i v-else :class="positionHistory ? 'bx bx-save' : 'bx bx-plus-circle'"></i>
          <span>{{ positionHistory ? 'Update' : 'Simpan' }}</span>
        </button>
      </div>
    </form>
  </div>
</template>
