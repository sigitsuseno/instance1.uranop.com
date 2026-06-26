<template>
  <div>
    <div v-if="editing" class="mb-4 px-4 py-3 bg-(--primary)/10 rounded-md border border-(--primary)/20">
      <span class="text-sm font-medium text-(--primary)">Edit Pengajuan Kasbon</span>
    </div>

    <!-- Employee Select -->
    <div class="mb-4">
      <SearchableSelect
        v-model="form.employee_id"
        :options="employeeOptions"
        label="Karyawan"
        placeholder="Cari nama atau NIP karyawan..."
        :disabled="editing"
        :error="errors.employee_id"
        required
      />
    </div>

    <!-- Amount -->
    <div class="mb-4">
      <label class="block text-sm font-medium text-(--text-main) mb-1">Nominal (Rp)</label>
      <input
        v-model.number="form.amount"
        type="number"
        min="1"
        class="w-full px-3 py-2 rounded-md border border-(--border-soft) bg-(--bg-card) text-(--text-main) text-sm"
        placeholder="Masukkan nominal"
      />
      <p v-if="errors.amount" class="text-xs text-(--danger) mt-1">{{ errors.amount }}</p>
    </div>

    <!-- Tenor -->
    <div class="mb-4">
      <label class="block text-sm font-medium text-(--text-main) mb-1">Tenor (Bulan)</label>
      <select
        v-model.number="form.tenor"
        class="w-full px-3 py-2 rounded-md border border-(--border-soft) bg-(--bg-card) text-(--text-main) text-sm"
      >
        <option v-for="n in 12" :key="n" :value="n">{{ n }} bulan</option>
      </select>
      <p v-if="errors.tenor" class="text-xs text-(--danger) mt-1">{{ errors.tenor }}</p>
    </div>

    <!-- Reason -->
    <div class="mb-4">
      <label class="block text-sm font-medium text-(--text-main) mb-1">Alasan</label>
      <textarea
        v-model="form.reason"
        rows="3"
        class="w-full px-3 py-2 rounded-md border border-(--border-soft) bg-(--bg-card) text-(--text-main) text-sm"
        placeholder="Alasan pengajuan kasbon..."
      ></textarea>
      <p v-if="errors.reason" class="text-xs text-(--danger) mt-1">{{ errors.reason }}</p>
    </div>

    <!-- Server-side errors -->
    <div v-if="serverErrors.length" class="mb-4 px-3 py-2 bg-(--danger)/10 border border-(--danger)/20 rounded-md">
      <p v-for="(err, i) in serverErrors" :key="i" class="text-xs text-(--danger)">{{ err }}</p>
    </div>

    <!-- Actions -->
    <div class="flex justify-end gap-2 mt-6">
      <button @click="$emit('cancel')" class="px-4 py-2 text-sm border border-(--border-soft) rounded-md hover:bg-(--bg-elevated)">
        Batal
      </button>
      <button @click="$emit('submit')" class="px-4 py-2 text-sm bg-(--primary) text-white rounded-md hover:opacity-90">
        {{ editing ? 'Simpan' : 'Ajukan' }}
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useApi } from '@/composables/useApi'
import SearchableSelect from '@/Components/SearchableSelect.vue'

const api = useApi()
const employees = ref([])

const props = defineProps({
  editing: { type: Boolean, default: false },
  form: { type: Object, required: true },
  errors: { type: [Object, Array], default: () => ({}) },
})

defineEmits(['submit', 'cancel'])

const employeeOptions = computed(() =>
  employees.value.map(emp => ({
    value: emp.id,
    label: `${emp.nip} - ${emp.name} (${emp.department?.name || '—'})`,
  }))
)

const serverErrors = computed(() => {
  if (Array.isArray(props.errors)) return props.errors
  if (typeof props.errors === 'string') return [props.errors]
  return []
})

async function fetchEmployees() {
  try {
    const res = await api.get('/api/v1/employees?per_page=100')
    employees.value = res.data || []
  } catch (e) { console.error(e) }
}

onMounted(() => fetchEmployees())
</script>
