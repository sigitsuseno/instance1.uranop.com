<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useApi } from '../../../../composables/useApi'
import { useNotificationStore } from '../../../../Stores/notification'
import BaseCard from '../../../../Components/BaseCard.vue'
import BaseButton from '../../../../Components/BaseButton.vue'

const router = useRouter()
const route = useRoute()
const notification = useNotificationStore()
const { get, put } = useApi()

const salaryId = route.params.id
const loading = ref(true)
const saving = ref(false)
const employee = ref(null)

const form = ref({
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

async function fetchSalary() {
  try {
    const res = await get(`/api/v1/supervisor/employee-data/salaries/${salaryId}`)
    const data = res.data
    employee.value = data.employee
    
    form.value = {
      base_salary: data.base_salary || 0,
      premi: data.premi || 0,
      tunjangan: data.tunjangan || 0,
      allowance_transport: data.allowance_transport || 0,
      allowance_meal: data.allowance_meal || 0,
      allowance_position: data.allowance_position || 0,
      effective_date: data.effective_date || '',
      change_type: data.change_type || 'initial',
      letter_number: data.letter_number || '',
      reason: data.reason || ''
    }
  } catch (e) {
    notification.addNotification('Data tidak ditemukan', 'error')
    router.push('/admin/employees/salaries')
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  fetchSalary()
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
  saving.value = true
  errors.value = {}
  try {
    await put(`/api/v1/supervisor/employee-data/salaries/${salaryId}`, form.value)
    notification.addNotification('Data gaji berhasil diperbarui', 'success')
    router.push('/supervisor/employee-data/gaji-karyawan')
  } catch (err) {
    if (err.response?.data?.errors) {
      errors.value = err.response.data.errors
    } else {
      notification.addNotification('Gagal memperbarui data', 'error')
    }
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div class="space-y-6 max-w-4xl">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-(--text-main)">Edit Data Gaji</h1>
        <p class="text-sm text-(--text-muted) mt-1">Ubah rincian histori komponen gaji</p>
      </div>
      <BaseButton variant="secondary" @click="$router.push('/supervisor/employee-data/gaji-karyawan')">
        <template #icon-left><i class="bx bx-arrow-back text-lg"></i></template>
        Kembali
      </BaseButton>
    </div>

    <div v-if="loading" class="p-12 flex items-center justify-center">
      <div class="w-10 h-10 border-4 border-(--primary)/30 border-t-(--primary) rounded-full animate-spin"></div>
    </div>

    <!-- Form -->
    <form v-else @submit.prevent="handleSubmit" class="space-y-6">
      <BaseCard padding="p-6" class="border-(--border-soft) shadow-sm bg-(--bg-card)">
        <h2 class="text-lg font-semibold text-(--text-main) mb-4 border-b border-(--border-soft) pb-2">Informasi Karyawan</h2>
        <div class="grid grid-cols-1 gap-4">
          <div class="flex gap-4 p-3 bg-(--bg-elevated) border border-(--border-soft) rounded-md">
            <div class="w-12 h-12 bg-(--primary)/10 text-(--primary) rounded-full flex items-center justify-center font-bold text-xl">
              {{ employee?.name ? employee.name.charAt(0) : '?' }}
            </div>
            <div>
              <p class="font-semibold text-(--text-main)">{{ employee?.name }}</p>
              <p class="text-sm text-(--text-muted)">{{ employee?.employee_code }} • {{ employee?.department || 'Tidak ada departemen' }}</p>
            </div>
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
        <BaseButton type="button" variant="secondary" @click="$router.push('/supervisor/employee-data/gaji-karyawan')">Batal</BaseButton>
        <BaseButton variant="primary" type="submit" :loading="saving" class="shadow-lg shadow-(--primary-glow)">
          <template #icon-left><i class="bx bx-save text-lg"></i></template>
          Simpan Perubahan
        </BaseButton>
      </div>
    </form>
  </div>
</template>
