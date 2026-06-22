<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useApi } from '../../../../composables/useApi'
import { useNotificationStore } from '../../../../Stores/notification'
import BaseCard from '../../../../Components/BaseCard.vue'
import BaseButton from '../../../../Components/BaseButton.vue'
import Badge from '../../../../Components/Badge.vue'

const route = useRoute()
const router = useRouter()
const { get } = useApi()
const notification = useNotificationStore()

const salaryId = route.params.id
const loading = ref(true)
const salary = ref(null)

async function fetchSalary() {
  try {
    const res = await get(`/api/v1/supervisor/employee-data/salaries/${salaryId}`)
    salary.value = res.data
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

const formatCurrency = (val) => {
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(val || 0)
}

const formatDate = (date) => {
  if (!date) return '-'
  return new Date(date).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' })
}

const getChangeTypeBadge = (type) => {
  const classes = {
    initial: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
    increase: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
    decrease: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
    promotion: 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-400',
    demotion: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
    adjustment: 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-400',
  }
  return classes[type] || 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-400'
}
</script>

<template>
  <div class="space-y-6 max-w-4xl">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-(--text-main)">Detail Gaji Karyawan</h1>
        <p class="text-sm text-(--text-muted) mt-1">Melihat rincian histori komponen gaji</p>
      </div>
      <div class="flex gap-2">
        <BaseButton variant="secondary" @click="$router.push('/supervisor/employee-data/gaji-karyawan')">
          <template #icon-left><i class="bx bx-arrow-back text-lg"></i></template>
          Kembali
        </BaseButton>
        <BaseButton v-if="salary" variant="primary" @click="$router.push(`/admin/employees/salaries/${salary.id}/edit`)">
          <template #icon-left><i class="bx bx-edit text-lg"></i></template>
          Edit
        </BaseButton>
      </div>
    </div>

    <div v-if="loading" class="p-12 flex items-center justify-center">
      <div class="w-10 h-10 border-4 border-(--primary)/30 border-t-(--primary) rounded-full animate-spin"></div>
    </div>

    <div v-else-if="salary" class="space-y-6">
      <BaseCard padding="p-6" class="border-(--border-soft) shadow-sm bg-(--bg-card)">
        <h2 class="text-lg font-semibold text-(--text-main) mb-4 border-b border-(--border-soft) pb-2">Informasi Karyawan</h2>
        <div class="flex gap-4 items-center">
          <div class="w-16 h-16 bg-(--primary)/10 text-(--primary) rounded-full flex items-center justify-center font-bold text-2xl">
            {{ salary.employee?.name ? salary.employee.name.charAt(0) : '?' }}
          </div>
          <div>
            <h3 class="font-bold text-xl text-(--text-main)">{{ salary.employee?.name }}</h3>
            <p class="text-(--text-muted)">{{ salary.employee?.employee_code }} • {{ salary.employee?.department || '-' }}</p>
            <p class="text-sm text-(--text-soft) mt-1">{{ salary.employee?.position || '-' }}</p>
          </div>
        </div>
      </BaseCard>

      <BaseCard padding="p-6" class="border-(--border-soft) shadow-sm bg-(--bg-card)">
        <div class="flex items-center justify-between mb-4 border-b border-(--border-soft) pb-2">
          <h2 class="text-lg font-semibold text-(--text-main)">Rincian Komponen Gaji</h2>
          <Badge :variant="salary.is_active ? 'success' : 'secondary'">
            {{ salary.is_active ? 'AKTIF' : 'TIDAK AKTIF' }}
          </Badge>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div class="space-y-4">
            <div>
              <p class="text-sm text-(--text-muted) mb-1">Gaji Pokok</p>
              <p class="text-lg font-semibold text-(--text-main)">{{ formatCurrency(salary.base_salary) }}</p>
            </div>
            <div>
              <p class="text-sm text-(--text-muted) mb-1">Premi / Bonus Tetap</p>
              <p class="text-base text-(--text-main)">{{ formatCurrency(salary.premi) }}</p>
            </div>
            <div>
              <p class="text-sm text-(--text-muted) mb-1">Tunjangan Lainnya</p>
              <p class="text-base text-(--text-main)">{{ formatCurrency(salary.tunjangan) }}</p>
            </div>
            <div>
              <p class="text-sm text-(--text-muted) mb-1">Tunjangan Transport</p>
              <p class="text-base text-(--text-main)">{{ formatCurrency(salary.allowance_transport) }}</p>
            </div>
          </div>
          <div class="space-y-4">
            <div>
              <p class="text-sm text-(--text-muted) mb-1">Tunjangan Makan</p>
              <p class="text-base text-(--text-main)">{{ formatCurrency(salary.allowance_meal) }}</p>
            </div>
            <div>
              <p class="text-sm text-(--text-muted) mb-1">Tunjangan Jabatan</p>
              <p class="text-base text-(--text-main)">{{ formatCurrency(salary.allowance_position) }}</p>
            </div>
            
            <div class="bg-(--primary)/5 border border-(--primary)/20 rounded-md p-4 mt-2">
              <p class="text-sm font-medium text-(--text-main) mb-1">Total Gaji (Gross)</p>
              <p class="text-2xl font-bold text-(--primary)">{{ formatCurrency(salary.total_salary) }}</p>
            </div>
          </div>
        </div>
      </BaseCard>

      <BaseCard padding="p-6" class="border-(--border-soft) shadow-sm bg-(--bg-card)">
        <h2 class="text-lg font-semibold text-(--text-main) mb-4 border-b border-(--border-soft) pb-2">Informasi Perubahan</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <p class="text-sm text-(--text-muted) mb-1">Tipe Perubahan</p>
            <span :class="['px-2 py-1 text-xs font-medium rounded-md uppercase tracking-wider', getChangeTypeBadge(salary.change_type)]">
              {{ salary.change_type_label }}
            </span>
          </div>
          <div>
            <p class="text-sm text-(--text-muted) mb-1">Tanggal Efektif</p>
            <p class="font-medium text-(--text-main)">{{ formatDate(salary.effective_date) }}</p>
          </div>
          <div>
            <p class="text-sm text-(--text-muted) mb-1">Nomor Surat / SK</p>
            <p class="font-medium text-(--text-main)">{{ salary.letter_number || '-' }}</p>
          </div>
          <div>
            <p class="text-sm text-(--text-muted) mb-1">Diinput Oleh</p>
            <p class="font-medium text-(--text-main)">{{ salary.created_by?.name || '-' }} pada {{ formatDate(salary.created_at) }}</p>
          </div>
          <div class="md:col-span-2 mt-2">
            <p class="text-sm text-(--text-muted) mb-1">Alasan / Keterangan</p>
            <div class="p-3 bg-(--bg-elevated) rounded-md text-(--text-main) border border-(--border-soft)">
              {{ salary.reason || 'Tidak ada keterangan' }}
            </div>
          </div>
        </div>
      </BaseCard>
    </div>
  </div>
</template>
