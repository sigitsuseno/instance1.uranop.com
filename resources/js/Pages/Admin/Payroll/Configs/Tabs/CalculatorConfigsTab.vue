<template>
  <div class="space-y-6">
    <div class="flex justify-between items-center">
      <div>
        <h3 class="text-lg font-medium text-(--text-main)">Konfigurasi Kalkulasi Lembur</h3>
        <p class="text-sm text-(--text-muted)">Atur jam normal, toleransi keterlambatan, potongan, dan rounding</p>
      </div>
      <BaseButton v-if="!editing" variant="primary" size="sm" @click="createNew">
        Tambah Konfigurasi
      </BaseButton>
    </div>

    <!-- List Mode -->
    <div v-if="!editing" class="grid gap-4">
      <BaseCard v-for="cfg in configs" :key="cfg.id">
        <div class="flex justify-between items-start mb-4">
          <div>
            <div class="flex items-center gap-2">
              <h4 class="font-semibold text-(--text-main)">{{ cfg.name }}</h4>
              <Badge v-if="!cfg.work_pattern_id" variant="secondary">Global</Badge>
              <Badge v-else variant="primary">{{ cfg.work_pattern?.name }}</Badge>
            </div>
            <p class="text-xs text-(--text-muted) mt-1">{{ cfg.description || '-' }}</p>
          </div>
          <div class="flex gap-2">
            <BaseButton variant="ghost" size="sm" @click="editConfig(cfg)">Edit</BaseButton>
            <BaseButton variant="danger" size="sm" @click="deleteConfig(cfg.id)">Hapus</BaseButton>
          </div>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
          <div class="bg-(--bg-elevated) rounded-lg p-3 text-center">
            <div class="text-2xl font-bold text-(--text-main)">{{ cfg.normal_work_minutes / 60 }}</div>
            <div class="text-[10px] text-(--text-muted) uppercase">Jam Normal</div>
          </div>
          <div class="bg-(--bg-elevated) rounded-lg p-3 text-center">
            <div class="text-2xl font-bold text-(--text-main)">{{ cfg.saturday_work_minutes / 60 }}</div>
            <div class="text-[10px] text-(--text-muted) uppercase">Jam Sabtu</div>
          </div>
          <div class="bg-(--bg-elevated) rounded-lg p-3 text-center">
            <div class="text-2xl font-bold text-(--text-main)">{{ cfg.holiday_max_minutes / 60 }}</div>
            <div class="text-[10px] text-(--text-muted) uppercase">Max Holiday</div>
          </div>
          <div class="bg-(--bg-elevated) rounded-lg p-3 text-center">
            <div class="text-2xl font-bold" :class="cfg.late_deducts_overtime ? 'text-amber-500' : 'text-(--text-soft)'">
              {{ cfg.late_deducts_overtime ? 'ON' : 'OFF' }}
            </div>
            <div class="text-[10px] text-(--text-muted) uppercase">Telat→OT</div>
          </div>
          <div class="bg-(--bg-elevated) rounded-lg p-3 text-center">
            <div class="text-2xl font-bold text-(--text-main)">{{ cfg.late_tolerance }}</div>
            <div class="text-[10px] text-(--text-muted) uppercase">Toleransi (m)</div>
          </div>
        </div>
      </BaseCard>

      <div v-if="configs.length === 0" class="text-center py-8 text-(--text-muted)">
        Belum ada konfigurasi. Silakan tambah.
      </div>
    </div>

    <!-- Edit/Create Mode -->
    <BaseCard v-else>
      <template #title>{{ form.id ? 'Edit Konfigurasi' : 'Tambah Konfigurasi' }}</template>
      
      <div class="space-y-4">
        <!-- Name + Work Pattern -->
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-(--text-main) mb-1">Nama</label>
            <input type="text" v-model="form.name" class="w-full px-3 py-2 text-sm border border-(--border-soft) rounded focus:outline-none focus:border-(--primary)" placeholder="Contoh: Default Global" />
          </div>
          <div>
            <label class="block text-sm font-medium text-(--text-main) mb-1">Work Pattern</label>
            <select v-model="form.work_pattern_id" class="w-full px-3 py-2 text-sm border border-(--border-soft) rounded focus:outline-none focus:border-(--primary)">
              <option :value="null">Global (Semua)</option>
              <option v-for="wp in workPatterns" :key="wp.id" :value="wp.id">{{ wp.name }}</option>
            </select>
          </div>
        </div>

        <!-- Jam Kerja Normal -->
        <div>
          <h4 class="text-sm font-semibold text-(--text-main) mb-3 border-b border-(--border-soft) pb-1">Jam Kerja Normal</h4>
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <div>
              <label class="block text-xs text-(--text-muted) mb-1">Weekday (menit)</label>
              <input type="number" min="0" v-model.number="form.normal_work_minutes" class="w-full px-3 py-2 text-sm border border-(--border-soft) rounded focus:outline-none focus:border-(--primary)" />
            </div>
            <div>
              <label class="block text-xs text-(--text-muted) mb-1">Sabtu (menit)</label>
              <input type="number" min="0" v-model.number="form.saturday_work_minutes" class="w-full px-3 py-2 text-sm border border-(--border-soft) rounded focus:outline-none focus:border-(--primary)" />
            </div>
            <div>
              <label class="block text-xs text-(--text-muted) mb-1">Max Holiday (menit)</label>
              <input type="number" min="0" v-model.number="form.holiday_max_minutes" class="w-full px-3 py-2 text-sm border border-(--border-soft) rounded focus:outline-none focus:border-(--primary)" />
            </div>
            <div>
              <label class="block text-xs text-(--text-muted) mb-1">Flat SHIFT Sabtu</label>
              <input type="number" min="0" v-model.number="form.shift_saturday_flat" class="w-full px-3 py-2 text-sm border border-(--border-soft) rounded focus:outline-none focus:border-(--primary)" />
            </div>
          </div>
        </div>

        <!-- Perilaku Keterlambatan -->
        <div>
          <h4 class="text-sm font-semibold text-(--text-main) mb-3 border-b border-(--border-soft) pb-1">Perilaku Keterlambatan</h4>
          <div class="grid grid-cols-2 gap-4">
            <div class="flex items-center gap-3">
              <input type="checkbox" id="late_deducts" v-model="form.late_deducts_overtime" class="rounded border-(--border-soft) text-(--primary) focus:ring-(--primary)" />
              <label for="late_deducts" class="text-sm text-(--text-main)">Keterlambatan Mengurangi Lembur</label>
            </div>
            <div>
              <label class="block text-xs text-(--text-muted) mb-1">Toleransi Keterlambatan (menit)</label>
              <input type="number" min="0" v-model.number="form.late_tolerance" class="w-full px-3 py-2 text-sm border border-(--border-soft) rounded focus:outline-none focus:border-(--primary)" />
              <p class="text-[10px] text-(--text-soft) mt-1">Telat ≤ nilai ini → tidak mengurangi lembur</p>
            </div>
          </div>
        </div>

        <!-- Potongan & Rounding -->
        <div>
          <h4 class="text-sm font-semibold text-(--text-main) mb-3 border-b border-(--border-soft) pb-1">Potongan & Rounding</h4>
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <div>
              <label class="block text-xs text-(--text-muted) mb-1">Potongan Istirahat LM</label>
              <input type="number" min="0" v-model.number="form.lm_rest_deduction" class="w-full px-3 py-2 text-sm border border-(--border-soft) rounded focus:outline-none focus:border-(--primary)" />
            </div>
            <div>
              <label class="block text-xs text-(--text-muted) mb-1">Interval Rounding</label>
              <input type="number" min="1" v-model.number="form.rounding_interval" class="w-full px-3 py-2 text-sm border border-(--border-soft) rounded focus:outline-none focus:border-(--primary)" />
            </div>
            <div>
              <label class="block text-xs text-(--text-muted) mb-1">Threshold</label>
              <input type="number" min="0" v-model.number="form.rounding_threshold" class="w-full px-3 py-2 text-sm border border-(--border-soft) rounded focus:outline-none focus:border-(--primary)" />
            </div>
            <div>
              <label class="block text-xs text-(--text-muted) mb-1">Pembagi Upah/Jam</label>
              <input type="number" min="1" v-model.number="form.hourly_divisor" class="w-full px-3 py-2 text-sm border border-(--border-soft) rounded focus:outline-none focus:border-(--primary)" />
            </div>
          </div>
        </div>

        <!-- Description -->
        <div>
          <label class="block text-sm font-medium text-(--text-main) mb-1">Deskripsi</label>
          <input type="text" v-model="form.description" class="w-full px-3 py-2 text-sm border border-(--border-soft) rounded focus:outline-none focus:border-(--primary)" placeholder="Opsional" />
        </div>
      </div>
      
      <template #footer>
        <div class="flex justify-end gap-2">
          <BaseButton variant="ghost" size="sm" @click="cancelEdit">Batal</BaseButton>
          <BaseButton variant="primary" size="sm" @click="save">Simpan</BaseButton>
        </div>
      </template>
    </BaseCard>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import BaseButton from '../../../../../Components/BaseButton.vue'
import BaseCard from '../../../../../Components/BaseCard.vue'
import Badge from '../../../../../Components/Badge.vue'
import { useApi } from '../../../../../composables/useApi'
import { useNotification } from '../../../../../composables/useNotification'

const api = useApi()
const notification = useNotification()

const configs = ref([])
const workPatterns = ref([])
const editing = ref(false)

const emptyForm = {
  id: null,
  name: '',
  work_pattern_id: null,
  normal_work_minutes: 480,
  saturday_work_minutes: 360,
  holiday_max_minutes: 480,
  shift_saturday_flat: 120,
  late_deducts_overtime: false,
  late_tolerance: 0,
  lm_rest_deduction: 60,
  rounding_interval: 30,
  rounding_threshold: 5,
  hourly_divisor: 173,
  description: '',
}

const form = ref({ ...emptyForm })

async function fetchData() {
  try {
    const [configsRes, wpRes] = await Promise.all([
      api.get('/api/v1/settings/payroll-configs/calculator'),
      api.get('/api/v1/settings/payroll-configs/work-patterns')
    ])
    configs.value = configsRes.data || []
    workPatterns.value = wpRes.data || []
  } catch (err) {
    notification.error('Gagal mengambil data konfigurasi')
  }
}

onMounted(() => fetchData())

function createNew() {
  form.value = { ...emptyForm }
  editing.value = true
}

function editConfig(cfg) {
  form.value = {
    id: cfg.id,
    name: cfg.name,
    work_pattern_id: cfg.work_pattern_id,
    normal_work_minutes: cfg.normal_work_minutes,
    saturday_work_minutes: cfg.saturday_work_minutes,
    holiday_max_minutes: cfg.holiday_max_minutes,
    shift_saturday_flat: cfg.shift_saturday_flat,
    late_deducts_overtime: cfg.late_deducts_overtime,
    late_tolerance: cfg.late_tolerance,
    lm_rest_deduction: cfg.lm_rest_deduction,
    rounding_interval: cfg.rounding_interval,
    rounding_threshold: cfg.rounding_threshold,
    hourly_divisor: cfg.hourly_divisor,
    description: cfg.description || '',
  }
  editing.value = true
}

function cancelEdit() {
  editing.value = false
}

async function save() {
  try {
    if (form.value.id) {
      await api.put(`/api/v1/settings/payroll-configs/calculator/${form.value.id}`, form.value)
      notification.success('Konfigurasi berhasil diupdate')
    } else {
      await api.post('/api/v1/settings/payroll-configs/calculator', form.value)
      notification.success('Konfigurasi berhasil ditambahkan')
    }
    editing.value = false
    fetchData()
  } catch (err) {
    notification.error(err.response?.data?.message || 'Gagal menyimpan konfigurasi')
  }
}

async function deleteConfig(id) {
  if (confirm('Yakin ingin menghapus konfigurasi ini?')) {
    try {
      await api.destroy(`/api/v1/settings/payroll-configs/calculator/${id}`)
      notification.success('Konfigurasi dihapus')
      fetchData()
    } catch (err) {
      notification.error('Gagal menghapus konfigurasi')
    }
  }
}
</script>
