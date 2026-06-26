<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useApi } from '../../../../composables/useApi'
import { useNotificationStore } from '../../../../Stores/notification'
import { usePermissionStore } from '../../../../Stores/permission'
import BaseCard from '../../../../Components/BaseCard.vue'
import BaseButton from '../../../../Components/BaseButton.vue'

const router = useRouter()
const notification = useNotificationStore()
const { get, post } = useApi()
const permission = usePermissionStore()

// Guard: redirect jika tidak punya permission create
if (!permission.can('create employees')) {
  router.replace('/admin/employees/salaries')
}

const employees = ref([])
const loading = ref(false)

const form = ref({
  employee_id: '',
  base_salary: 0,
  premi: 0,
  tunjangan: 0,
  allowance_transport: 0,
  allowance_meal: 0,
  allowance_position: 0,
  effective_date: '',
  change_type: 'initial',
  letter_number: '',
  reason: ''
})

const errors = ref({})

async function fetchEmployees() {
  try {
    let all = []
    let page = 1
    let lastPage = 1
    do {
      const res = await get(`/api/v1/employees?per_page=100&page=${page}`)
      all = [...all, ...(res.data || [])]
      lastPage = res.meta?.last_page || 1
      page++
    } while (page <= lastPage)
    employees.value = all
  } catch (e) {
    console.error(e)
  }
}

onMounted(() => {
  fetchEmployees()
})

const totalSalary = computed(() => {
  return (Number(form.value.base_salary) || 0) +
         (Number(form.value.premi) || 0) +
         (Number(form.value.tunjangan) || 0) +
         (Number(form.value.allowance_transport) || 0) +
         (Number(form.value.allowance_meal) || 0) +
         (Number(form.value.allowance_position) || 0)
})

const formatCurrency = (val) => {
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(val)
}

async function handleSubmit() {
  loading.value = true
  errors.value = {}
  try {
    await post('/api/v1/employees/salaries', form.value)
    notification.addNotification('Gaji karyawan berhasil ditambahkan', 'success')
    router.push('/admin/employees/salaries')
  } catch (err) {
    if (err.response?.data?.errors) {
      errors.value = err.response.data.errors
    } else {
      notification.addNotification('Gagal menambahkan gaji karyawan', 'error')
    }
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="space-y-6 max-w-4xl">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-(--text-main)">Tambah Gaji Karyawan</h1>
        <p class="text-sm text-(--text-muted) mt-1">Tambahkan riwayat gaji atau komponen baru</p>
      </div>
      <BaseButton variant="secondary" @click="$router.push('/admin/employees/salaries')">
        <template #icon-left><i class="bx bx-arrow-back text-lg"></i></template>
        Kembali
      </BaseButton>
    </div>

    <!-- Form -->
    <form @submit.prevent="handleSubmit" class="space-y-6">
      <BaseCard padding="p-6" class="border-(--border-soft) shadow-sm bg-(--bg-card)">
        <h2 class="text-lg font-semibold text-(--text-main) mb-4 border-b border-(--border-soft) pb-2">Informasi Karyawan</h2>
        <div class="grid grid-cols-1 gap-4">
          <div>
            <label class="block text-sm font-medium text-(--text-main) mb-1">Pilih Karyawan <span class="text-red-500">*</span></label>
            <select v-model="form.employee_id" class="w-full h-10 px-3 rounded-md bg-(--bg-elevated) border text-(--text-main) focus:ring-2 focus:ring-(--primary) outline-none transition-all" :class="errors.employee_id ? 'border-red-500 focus:border-red-500' : 'border-(--border-soft) focus:border-(--primary)'">
              <option value="" disabled>Pilih Karyawan...</option>
              <option v-for="emp in employees" :key="emp.id" :value="emp.id">{{ emp.name }} ({{ emp.employee_code }})</option>
            </select>
            <p v-if="errors.employee_id" class="text-xs text-red-500 mt-1">{{ errors.employee_id[0] }}</p>
          </div>
        </div>
      </BaseCard>

      <BaseCard padding="p-6" class="border-(--border-soft) shadow-sm bg-(--bg-card)">
        <h2 class="text-lg font-semibold text-(--text-main) mb-4 border-b border-(--border-soft) pb-2">Komponen Gaji</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-(--text-main) mb-1">Gaji Pokok <span class="text-red-500">*</span></label>
            <input type="number" step="0.01" v-model="form.base_salary" class="w-full h-10 px-3 rounded-md bg-(--bg-elevated) border text-(--text-main) outline-none transition-all" :class="errors.base_salary ? 'border-red-500 focus:ring-red-500 focus:border-red-500' : 'border-(--border-soft) focus:ring-2 focus:ring-(--primary) focus:border-(--primary)'">
            <p v-if="errors.base_salary" class="text-xs text-red-500 mt-1">{{ errors.base_salary[0] }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-(--text-main) mb-1">Premi / Bonus Tetap</label>
            <input type="number" step="0.01" v-model="form.premi" class="w-full h-10 px-3 rounded-md bg-(--bg-elevated) border border-(--border-soft) focus:ring-2 focus:ring-(--primary) text-(--text-main) outline-none transition-all">
          </div>
          <div>
            <label class="block text-sm font-medium text-(--text-main) mb-1">Tunjangan Lainnya</label>
            <input type="number" step="0.01" v-model="form.tunjangan" class="w-full h-10 px-3 rounded-md bg-(--bg-elevated) border border-(--border-soft) focus:ring-2 focus:ring-(--primary) text-(--text-main) outline-none transition-all">
          </div>
          <div>
            <label class="block text-sm font-medium text-(--text-main) mb-1">Tunjangan Transport</label>
            <input type="number" step="0.01" v-model="form.allowance_transport" class="w-full h-10 px-3 rounded-md bg-(--bg-elevated) border border-(--border-soft) focus:ring-2 focus:ring-(--primary) text-(--text-main) outline-none transition-all">
          </div>
          <div>
            <label class="block text-sm font-medium text-(--text-main) mb-1">Tunjangan Makan</label>
            <input type="number" step="0.01" v-model="form.allowance_meal" class="w-full h-10 px-3 rounded-md bg-(--bg-elevated) border border-(--border-soft) focus:ring-2 focus:ring-(--primary) text-(--text-main) outline-none transition-all">
          </div>
          <div>
            <label class="block text-sm font-medium text-(--text-main) mb-1">Tunjangan Jabatan</label>
            <input type="number" step="0.01" v-model="form.allowance_position" class="w-full h-10 px-3 rounded-md bg-(--bg-elevated) border border-(--border-soft) focus:ring-2 focus:ring-(--primary) text-(--text-main) outline-none transition-all">
          </div>
          
          <div class="sm:col-span-2 mt-2">
            <div class="bg-(--primary)/5 border border-(--primary)/20 rounded-md p-4 flex items-center justify-between">
              <span class="font-medium text-(--text-main)">Estimasi Total Gaji (Gross)</span>
              <span class="text-xl font-bold text-(--primary)">{{ formatCurrency(totalSalary) }}</span>
            </div>
          </div>
        </div>
      </BaseCard>

      <BaseCard padding="p-6" class="border-(--border-soft) shadow-sm bg-(--bg-card)">
        <h2 class="text-lg font-semibold text-(--text-main) mb-4 border-b border-(--border-soft) pb-2">Informasi Perubahan</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-(--text-main) mb-1">Tipe Perubahan <span class="text-red-500">*</span></label>
            <select v-model="form.change_type" class="w-full h-10 px-3 rounded-md bg-(--bg-elevated) border text-(--text-main) focus:ring-2 focus:ring-(--primary) outline-none transition-all border-(--border-soft)">
              <option value="initial">Gaji Awal</option>
              <option value="increase">Kenaikan Gaji</option>
              <option value="decrease">Penurunan Gaji</option>
              <option value="promotion">Kenaikan Jabatan</option>
              <option value="demotion">Penurunan Jabatan</option>
              <option value="adjustment">Penyesuaian</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-(--text-main) mb-1">Tanggal Efektif</label>
            <input type="date" v-model="form.effective_date" class="w-full h-10 px-3 rounded-md bg-(--bg-elevated) border text-(--text-main) focus:ring-2 focus:ring-(--primary) outline-none transition-all border-(--border-soft)">
          </div>
          <div>
            <label class="block text-sm font-medium text-(--text-main) mb-1">Nomor Surat / SK</label>
            <input type="text" v-model="form.letter_number" class="w-full h-10 px-3 rounded-md bg-(--bg-elevated) border text-(--text-main) focus:ring-2 focus:ring-(--primary) outline-none transition-all border-(--border-soft)">
          </div>
          <div class="sm:col-span-2">
            <label class="block text-sm font-medium text-(--text-main) mb-1">Alasan / Keterangan</label>
            <textarea v-model="form.reason" rows="3" class="w-full px-3 py-2 rounded-md bg-(--bg-elevated) border text-(--text-main) focus:ring-2 focus:ring-(--primary) outline-none transition-all border-(--border-soft) resize-none"></textarea>
          </div>
        </div>
      </BaseCard>

      <div class="flex items-center justify-end gap-3 pt-4">
        <BaseButton variant="ghost" type="button" @click="$router.push('/admin/employees/salaries')">Batal</BaseButton>
        <BaseButton variant="primary" type="submit" :loading="loading" class="shadow-lg shadow-(--primary-glow)">
          <template #icon-left><i class="bx bx-save text-lg"></i></template>
          Simpan Gaji
        </BaseButton>
      </div>
    </form>
  </div>
</template>
