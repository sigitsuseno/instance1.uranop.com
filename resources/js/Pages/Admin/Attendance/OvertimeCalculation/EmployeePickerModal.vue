<template>
  <BaseModal :show="show" title="Pilih Karyawan" size="lg" @close="emit('close')">
    <div class="space-y-3">
      <p class="text-sm text-(--text-muted)">
        <span v-if="periodLabel">{{ periodLabel }} · </span>{{ startDate }} — {{ endDate }}
      </p>

      <div class="relative">
        <i class="bx bx-search absolute left-3 top-1/2 -translate-y-1/2 text-(--text-soft)"></i>
        <input
          v-model="search"
          type="text"
          placeholder="Cari kode atau nama karyawan..."
          class="w-full pl-9 pr-3 py-2 border border-(--border-soft) rounded-lg bg-(--bg-card) text-(--text-main) text-sm focus:outline-none focus:ring-2 focus:ring-(--primary)"
        />
      </div>

      <div v-if="error" class="flex items-center gap-2 text-sm text-(--danger)">
        <span>{{ error }}</span>
        <button type="button" class="underline whitespace-nowrap" @click="emit('retry')">Coba lagi</button>
      </div>

      <div v-if="loading" class="py-8 text-center text-sm text-(--text-muted)">Memuat karyawan...</div>

      <div v-else-if="employees.length === 0 && !error" class="py-8 text-center text-sm text-(--text-muted)">
        Tidak ada karyawan yang punya roster pada rentang ini.
      </div>

      <template v-else-if="employees.length > 0">
        <label class="flex items-center gap-2 px-1 text-sm font-medium text-(--text-main)">
          <input type="checkbox" :checked="allFilteredSelected" @change="toggleSelectAll" />
          Pilih semua ({{ filteredEmployees.length }} tampil)
        </label>

        <div class="max-h-72 overflow-y-auto divide-y divide-(--border-soft) border border-(--border-soft) rounded-lg">
          <label
            v-for="employee in filteredEmployees"
            :key="employee.id"
            class="flex items-center gap-3 px-3 py-2 hover:bg-(--bg-elevated)/50 cursor-pointer"
          >
            <input v-model="selectedIds" type="checkbox" :value="employee.id" />
            <span class="text-sm text-(--text-main)">{{ employee.employee_code }} · {{ employee.name }}</span>
            <span class="text-xs text-(--text-muted) ml-auto">{{ employee.department || '-' }}</span>
          </label>
        </div>
      </template>
    </div>

    <template #footer>
      <div class="flex items-center justify-between w-full">
        <span class="text-sm text-(--text-muted)">
          {{ selectedIds.length }} dari {{ employees.length }} terpilih
        </span>
        <div class="flex gap-3">
          <BaseButton variant="ghost" :disabled="submitting" @click="emit('close')">Batal</BaseButton>
          <BaseButton
            variant="primary"
            :loading="submitting"
            :disabled="selectedIds.length === 0"
            @click="emit('confirm', [...selectedIds])"
          >
            Mulai Kalkulasi Lembur
          </BaseButton>
        </div>
      </div>
    </template>
  </BaseModal>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import BaseModal from '@/Components/BaseModal.vue'
import BaseButton from '@/Components/BaseButton.vue'

const props = defineProps({
  show: { type: Boolean, default: false },
  periodLabel: { type: String, default: '' },
  startDate: { type: String, default: '' },
  endDate: { type: String, default: '' },
  employees: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
  submitting: { type: Boolean, default: false },
  error: { type: String, default: '' },
})
const emit = defineEmits(['close', 'confirm', 'retry'])

const search = ref('')
const selectedIds = ref([])

const filteredEmployees = computed(() => {
  const q = search.value.trim().toLowerCase()
  if (!q) return props.employees
  return props.employees.filter(e =>
    (e.employee_code || '').toLowerCase().includes(q) ||
    (e.name || '').toLowerCase().includes(q)
  )
})

// "Pilih semua" berlaku pada daftar yang sedang tampil (hasil pencarian).
const allFilteredSelected = computed(
  () => filteredEmployees.value.length > 0 &&
    filteredEmployees.value.every(e => selectedIds.value.includes(e.id))
)

function toggleSelectAll() {
  const ids = filteredEmployees.value.map(e => e.id)
  if (allFilteredSelected.value) {
    selectedIds.value = selectedIds.value.filter(id => !ids.includes(id))
  } else {
    selectedIds.value = Array.from(new Set([...selectedIds.value, ...ids]))
  }
}

// Setiap daftar baru dimuat (modal dibuka ulang), semua tercentang.
watch(() => props.employees, (list) => {
  search.value = ''
  selectedIds.value = list.map(e => e.id)
}, { immediate: true })
</script>
