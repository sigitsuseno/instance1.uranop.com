<template>
  <BaseModal :show="show" @close="$emit('close')" title="Pengaturan Kasbon">
    <div class="p-4 space-y-4">
      <!-- Limit Type -->
      <div>
        <label class="block text-sm font-medium text-(--text-main) mb-1">Jenis Limit</label>
        <select
          v-model="form.limit_type"
          class="w-full px-3 py-2 rounded-md border border-(--border-soft) bg-(--bg-card) text-(--text-main) text-sm"
        >
          <option value="salary_multiplier">Kelipatan Gaji Pokok</option>
          <option value="fixed">Nominal Tetap</option>
          <option value="unlimited">Tidak Terbatas</option>
        </select>
      </div>

      <!-- Limit Value -->
      <div v-if="form.limit_type !== 'unlimited'">
        <label class="block text-sm font-medium text-(--text-main) mb-1">
          {{ form.limit_type === 'salary_multiplier' ? 'Kelipatan Gaji (x)' : 'Nominal Maksimal (Rp)' }}
        </label>
        <input
          v-model.number="form.limit_value"
          type="number"
          min="1"
          class="w-full px-3 py-2 rounded-md border border-(--border-soft) bg-(--bg-card) text-(--text-main) text-sm"
        />
      </div>

      <!-- Max Tenor -->
      <div>
        <label class="block text-sm font-medium text-(--text-main) mb-1">Maksimal Tenor (Bulan)</label>
        <input
          v-model.number="form.max_tenor"
          type="number"
          min="1"
          max="60"
          class="w-full px-3 py-2 rounded-md border border-(--border-soft) bg-(--bg-card) text-(--text-main) text-sm"
        />
      </div>

      <!-- Interest Rate -->
      <div>
        <label class="block text-sm font-medium text-(--text-main) mb-1">Bunga (%)</label>
        <input
          v-model.number="form.interest_rate"
          type="number"
          min="0"
          step="0.1"
          class="w-full px-3 py-2 rounded-md border border-(--border-soft) bg-(--bg-card) text-(--text-main) text-sm"
        />
      </div>

      <!-- Allow Multi -->
      <div class="flex items-center justify-between">
        <div>
          <label class="text-sm font-medium text-(--text-main)">Multi Kasbon</label>
          <p class="text-xs text-(--text-muted)">Izinkan karyawan punya lebih dari 1 kasbon aktif</p>
        </div>
        <label class="relative inline-flex items-center cursor-pointer">
          <input type="checkbox" v-model="form.allow_multi" class="sr-only peer" />
          <div class="w-9 h-5 bg-gray-300 rounded-full peer peer-checked:bg-(--primary) peer-focus:ring-2 peer-focus:ring-(--primary-glow) transition-colors"></div>
          <div class="absolute left-0.5 top-0.5 w-4 h-4 bg-white rounded-full peer-checked:translate-x-4 transition-transform"></div>
        </label>
      </div>

      <!-- Auto Deduct -->
      <div class="flex items-center justify-between">
        <div>
          <label class="text-sm font-medium text-(--text-main)">Auto Potong Gaji</label>
          <p class="text-xs text-(--text-muted)">Otomatis potong gaji di payroll setiap periode</p>
        </div>
        <label class="relative inline-flex items-center cursor-pointer">
          <input type="checkbox" v-model="form.auto_deduct" class="sr-only peer" />
          <div class="w-9 h-5 bg-gray-300 rounded-full peer peer-checked:bg-(--primary) peer-focus:ring-2 peer-focus:ring-(--primary-glow) transition-colors"></div>
          <div class="absolute left-0.5 top-0.5 w-4 h-4 bg-white rounded-full peer-checked:translate-x-4 transition-transform"></div>
        </label>
      </div>

      <!-- Approval Roles -->
      <div>
        <label class="block text-sm font-medium text-(--text-main) mb-1">Role yang Bisa Approve</label>
        <div class="grid grid-cols-2 gap-2">
          <label v-for="role in availableRoles" :key="role.value" class="flex items-center gap-2 text-sm text-(--text-main)">
            <input type="checkbox" :value="role.value" v-model="form.approval_roles" class="rounded" />
            {{ role.label }}
          </label>
        </div>
      </div>

      <!-- Last Updated -->
      <div v-if="lastUpdated" class="text-xs text-(--text-muted) pt-2">
        Terakhir diupdate oleh {{ lastUpdated.by }} pada {{ formatDate(lastUpdated.at) }}
      </div>
    </div>

    <!-- Footer -->
    <div class="flex justify-end gap-2 p-4 border-t border-(--border-soft)">
      <button @click="$emit('close')" class="px-4 py-2 text-sm border border-(--border-soft) rounded-md hover:bg-(--bg-elevated)">Batal</button>
      <button @click="save" :disabled="saving" class="px-4 py-2 text-sm bg-(--primary) text-white rounded-md hover:opacity-90 disabled:opacity-50">
        {{ saving ? 'Menyimpan...' : 'Simpan' }}
      </button>
    </div>
  </BaseModal>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useApi } from '@/composables/useApi'
import BaseModal from '@/Components/BaseModal.vue'

const api = useApi()

defineProps({
  show: { type: Boolean, default: false },
})

const emit = defineEmits(['close', 'saved'])

const saving = ref(false)
const lastUpdated = ref(null)

const availableRoles = [
  { value: 'superadmin', label: 'Superadmin' },
  { value: 'hrmanager', label: 'HR Manager' },
  { value: 'adm_manager', label: 'Admin Manager' },
]

const form = ref({
  limit_type: 'salary_multiplier',
  limit_value: 3,
  max_tenor: 12,
  interest_rate: 0,
  allow_multi: false,
  auto_deduct: true,
  approval_roles: ['superadmin', 'hrmanager'],
})

function formatDate(d) {
  return d ? new Date(d).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '-'
}

async function fetchConfig() {
  try {
    const res = await api.get('/api/v1/payroll/configs/kasbon')
    if (res.config) {
      form.value = { ...form.value, ...res.config }
    }
    if (res.updated_at) {
      lastUpdated.value = { by: res.updated_by || 'System', at: res.updated_at }
    }
  } catch (e) { console.error(e) }
}

async function save() {
  saving.value = true
  try {
    await api.put('/api/v1/payroll/configs/kasbon', { config: form.value })
    emit('saved', form.value)
    emit('close')
  } catch (e) { console.error(e) } finally { saving.value = false }
}

onMounted(() => fetchConfig())
</script>
