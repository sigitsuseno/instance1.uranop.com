<template>
  <BaseModal :show="show" title="Update Data Lembur & Uang Makan" size="xl" @close="close">
    <div class="space-y-6 text-sm">
      <p class="text-(--text-muted)">
        Data diambil dari <strong>att_prepares</strong> → dihitung per karyawan per tanggal → disimpan ke <strong>employee_overtime</strong>.
      </p>

      <!-- 1. Periode -->
      <div>
        <label class="block font-medium text-(--text-main) mb-1">Periode <span class="text-red-500">*</span></label>
        <select
          v-model="form.period_id"
          class="w-full border border-(--border-soft) bg-(--bg-elevated) text-(--text-main) rounded-lg p-2.5 text-sm"
        >
          <option :value="null" disabled>-- Pilih Periode --</option>
          <option v-for="p in periods" :key="p.id" :value="p.id">
            {{ p.name }} ({{ formatPeriodRange(p.start_date, p.end_date) }})
          </option>
        </select>
      </div>

      <!-- 2. Karyawan Tanpa Sabtu/Minggu/Holiday -->
      <div>
        <label class="block font-medium text-(--text-main) mb-1">
          Karyawan TANPA Uang Lembur Sabtu / Minggu / Holiday
        </label>
        <p class="text-xs text-(--text-muted) mb-2">
          Hanya berlaku untuk <strong>GRP-JKT</strong>. Karyawan yang dipilih tidak akan mendapat uang lembur di hari Sabtu, Minggu, dan Holiday.
        </p>

        <!-- Search -->
        <input
          v-model="employeeSearch"
          type="text"
          placeholder="Cari nama atau ID karyawan..."
          class="w-full border border-(--border-soft) bg-(--bg-elevated) rounded-lg p-2 text-sm mb-2"
        />

        <!-- Selected count -->
        <div class="text-xs text-(--text-muted) mb-2">
          Terpilih: <strong>{{ form.emp_tanpa_sabtu_minggu_holiday.length }}</strong> karyawan
          <button
            v-if="form.emp_tanpa_sabtu_minggu_holiday.length > 0"
            @click="form.emp_tanpa_sabtu_minggu_holiday = []"
            class="ml-2 text-red-500 hover:underline"
          >Hapus semua</button>
        </div>

        <!-- Employee checklist -->
        <div class="border border-(--border-soft) rounded-lg max-h-48 overflow-y-auto">
          <label
            v-for="emp in filteredEmployees"
            :key="emp.id"
            class="flex items-center gap-2 px-3 py-1.5 hover:bg-(--bg-hover) cursor-pointer text-xs"
          >
            <input
              type="checkbox"
              :checked="form.emp_tanpa_sabtu_minggu_holiday.includes(emp.id)"
              @change="toggleEmployee(emp.id)"
              class="rounded"
            />
            <span>{{ emp.name }}</span>
            <span class="text-(--text-muted) ml-auto">ID: {{ emp.id }}</span>
          </label>
          <div v-if="filteredEmployees.length === 0" class="px-3 py-2 text-xs text-(--text-muted)">
            Tidak ada karyawan yang cocok.
          </div>
        </div>
      </div>

      <!-- 3. Aturan Spesifik Berdasarkan Jabatan -->
      <div>
        <label class="block font-medium text-(--text-main) mb-3">
          Aturan Spesifik Berdasarkan Jabatan (Rate Uang Makan)
        </label>

        <div class="overflow-x-auto border border-(--border-soft) rounded-lg">
          <table class="min-w-full text-xs">
            <thead class="bg-(--bg-elevated)">
              <tr>
                <th class="px-3 py-2 text-left font-semibold">Jabatan</th>
                <th class="px-3 py-2 text-center font-semibold">Weekday<br><span class="font-normal text-(--text-muted)">(≥ 2 jam)</span></th>
                <th class="px-3 py-2 text-center font-semibold">Sabtu 2<br><span class="font-normal text-(--text-muted)">(≥ 2 jam)</span></th>
                <th class="px-3 py-2 text-center font-semibold">Sabtu FULL<br><span class="font-normal text-(--text-muted)">(≥ 4 jam)</span></th>
                <th class="px-3 py-2 text-center font-semibold">Minggu HALF<br><span class="font-normal text-(--text-muted)">(≥ 4 jam)</span></th>
                <th class="px-3 py-2 text-center font-semibold">Minggu FULL<br><span class="font-normal text-(--text-muted)">(≥ 8 jam)</span></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="group in positionGroups" :key="group.key" class="border-t border-(--border-soft)">
                <td class="px-3 py-2 font-medium whitespace-nowrap">{{ group.label }}</td>
                <td class="px-2 py-1">
                  <input v-model.number="form.position_rules[group.key].weekday" type="number" class="w-24 text-right border border-(--border-soft) rounded p-1.5 text-xs bg-(--bg-elevated)" />
                </td>
                <td class="px-2 py-1">
                  <input v-model.number="form.position_rules[group.key].sabtu_dua" type="number" class="w-24 text-right border border-(--border-soft) rounded p-1.5 text-xs bg-(--bg-elevated)" />
                </td>
                <td class="px-2 py-1">
                  <input v-model.number="form.position_rules[group.key].sabtu_full" type="number" class="w-24 text-right border border-(--border-soft) rounded p-1.5 text-xs bg-(--bg-elevated)" />
                </td>
                <td class="px-2 py-1">
                  <input v-model.number="form.position_rules[group.key].minggu_half" type="number" class="w-24 text-right border border-(--border-soft) rounded p-1.5 text-xs bg-(--bg-elevated)" />
                </td>
                <td class="px-2 py-1">
                  <input v-model.number="form.position_rules[group.key].minggu_full" type="number" class="w-24 text-right border border-(--border-soft) rounded p-1.5 text-xs bg-(--bg-elevated)" />
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- 4. Aturan Spesifik Teknisi -->
      <div>
        <label class="block font-medium text-(--text-main) mb-3">
          Aturan Spesifik Teknisi (KRY-TKN)
        </label>
        <p class="text-xs text-(--text-muted) mb-2">
          Karyawan dengan flag <strong>KRY-TKN</strong> akan menggunakan aturan ini (overwrite di akhir).
        </p>

        <div class="grid grid-cols-3 gap-3">
          <div>
            <label class="block text-xs text-(--text-muted) mb-1">Weekday (≥ 2 jam)</label>
            <input v-model.number="form.technician_rules.weekday" type="number" class="w-full border border-(--border-soft) rounded-lg p-2.5 text-sm bg-(--bg-elevated)" />
          </div>
          <div>
            <label class="block text-xs text-(--text-muted) mb-1">Sabtu</label>
            <input v-model.number="form.technician_rules.saturday" type="number" class="w-full border border-(--border-soft) rounded-lg p-2.5 text-sm bg-(--bg-elevated)" />
          </div>
          <div>
            <label class="block text-xs text-(--text-muted) mb-1">Minggu / Holiday</label>
            <input v-model.number="form.technician_rules.holiday" type="number" class="w-full border border-(--border-soft) rounded-lg p-2.5 text-sm bg-(--bg-elevated)" />
          </div>
        </div>
      </div>
    </div>

    <template #footer>
      <button
        @click="close"
        class="px-4 py-2 text-sm rounded-lg border border-(--border-soft) hover:bg-(--bg-hover) transition-colors"
      >
        Batal
      </button>
      <button
        @click="submit"
        :disabled="!form.period_id || submitting"
        class="px-4 py-2 text-sm rounded-lg bg-(--primary) text-white hover:opacity-90 transition-colors flex items-center gap-2"
        :class="{ 'opacity-50 cursor-not-allowed': !form.period_id || submitting }"
      >
        <i v-if="submitting" class="bx bx-loader-alt animate-spin"></i>
        {{ submitting ? 'Mengupdate...' : 'Update Data' }}
      </button>
    </template>
  </BaseModal>
</template>

<script setup>
import { ref, reactive, watch, computed } from 'vue'
import { useApi } from '@/composables/useApi'
import { useNotificationStore } from '@/Stores/notification'
import BaseModal from '@/Components/BaseModal.vue'

const props = defineProps({
  show: { type: Boolean, default: false },
  periods: { type: Array, default: () => [] },
  allEmployees: { type: Array, default: () => [] },
  initialEmpTanpa: { type: Array, default: () => [] },
  initialPositionRules: { type: Object, default: null },
  initialTechnicianRules: { type: Object, default: null },
})

const emit = defineEmits(['close', 'saved'])

const { post } = useApi()
const notification = useNotificationStore()
const submitting = ref(false)
const employeeSearch = ref('')

const positionGroups = [
  { key: 'KABAG',   label: 'KABAG' },
  { key: 'KASHIFT', label: 'KASHIFT / KEPALA SHIFT' },
  { key: 'ALLIN',   label: 'ALL IN / Lainnya' },
]

const defaultPositionRules = {
  KABAG:   { weekday: 15000, sabtu_dua: 55000, sabtu_full: 110000, minggu_half: 110000, minggu_full: 220000 },
  KASHIFT: { weekday: 15000, sabtu_dua: 52522, sabtu_full: 105000, minggu_half: 105000, minggu_full: 210000 },
  ALLIN:   { weekday: 15000, sabtu_dua: 50000, sabtu_full: 100000, minggu_half: 100000, minggu_full: 200000 },
}

const defaultTechnicianRules = { weekday: 15000, saturday: 100000, holiday: 200000 }

const form = reactive({
  period_id: null,
  emp_tanpa_sabtu_minggu_holiday: [],
  position_rules: { ...JSON.parse(JSON.stringify(defaultPositionRules)) },
  technician_rules: { ...defaultTechnicianRules },
})

const filteredEmployees = computed(() => {
  const search = employeeSearch.value.toLowerCase().trim()
  if (!search) return props.allEmployees
  return props.allEmployees.filter(e => {
    const name = (e.name || e.nama || '').toLowerCase()
    const id = String(e.id)
    return name.includes(search) || id.includes(search)
  })
})

function toggleEmployee(id) {
  const idx = form.emp_tanpa_sabtu_minggu_holiday.indexOf(id)
  if (idx >= 0) {
    form.emp_tanpa_sabtu_minggu_holiday.splice(idx, 1)
  } else {
    form.emp_tanpa_sabtu_minggu_holiday.push(id)
  }
}

function formatPeriodRange(start, end) {
  if (!start || !end) return ''
  const fmt = new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: 'short', year: 'numeric' })
  return fmt.format(new Date(start)) + ' - ' + fmt.format(new Date(end))
}

function initForm() {
  employeeSearch.value = ''
  form.period_id = null
  form.emp_tanpa_sabtu_minggu_holiday = [...props.initialEmpTanpa]
  form.position_rules = props.initialPositionRules
    ? { ...JSON.parse(JSON.stringify(props.initialPositionRules)) }
    : { ...JSON.parse(JSON.stringify(defaultPositionRules)) }
  form.technician_rules = props.initialTechnicianRules
    ? { ...props.initialTechnicianRules }
    : { ...defaultTechnicianRules }
}

watch(() => props.show, (val) => {
  if (val) initForm()
})

async function submit() {
  if (!form.period_id) {
    notification.addNotification('Pilih periode terlebih dahulu', 'warning')
    return
  }

  const selectedPeriod = props.periods.find(p => p.id === form.period_id)
  const periodName = selectedPeriod?.name || 'periode ini'

  if (!confirm(`Update data lembur & uang makan untuk "${periodName}"?\n\nData lama akan dihapus dan diganti dengan data baru.\n\nLanjutkan?`)) return

  submitting.value = true
  try {
    const res = await post('/api/v1/reports/lembur/update-data', {
      period_id: form.period_id,
      emp_tanpa_sabtu_minggu_holiday: form.emp_tanpa_sabtu_minggu_holiday,
      position_rules: form.position_rules,
      technician_rules: form.technician_rules,
    })
    notification.addNotification(res.message || 'Data berhasil diupdate', 'success')
    emit('saved', res)
    emit('close')
  } catch (err) {
    console.error('Gagal update data:', err)
    const msg = err?.response?.data?.message || 'Gagal update data lembur & uang makan'
    notification.addNotification(msg, 'error')
  } finally {
    submitting.value = false
  }
}

function close() {
  if (!submitting.value) {
    emit('close')
  }
}
</script>
