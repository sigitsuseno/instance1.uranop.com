<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-semibold text-(--text-main)">Pajak Karyawan</h1>
        <p class="text-sm text-(--text-muted) mt-1">
          Pengaturan Nomor Pajak (NIK/NPWP) dan status PTKP per karyawan
        </p>
      </div>
      <div class="flex items-center gap-2">
        <BaseButton variant="outline" size="sm" @click="fetchData">
          <IconRefresh class="w-4 h-4" />
          Refresh
        </BaseButton>
      </div>
    </div>

    <!-- Filters -->
    <BaseCard>
      <div class="flex flex-col sm:flex-row gap-4 items-end">
        <div class="flex-1 w-full max-w-xs">
          <label class="block text-sm font-medium text-(--text-main) mb-1">Periode Penggajian</label>
          <select 
            v-model="filters.period_id" 
            class="w-full h-10 px-3 py-2 bg-(--bg-main) border border-(--border-soft) rounded-lg focus:outline-none focus:ring-2 focus:ring-(--primary)/50"
            @change="fetchData"
          >
            <option value="">Semua Karyawan Aktif</option>
            <option v-for="period in periods" :key="period.id" :value="period.id">
              {{ period.name }}
            </option>
          </select>
        </div>
        <div class="flex-1 w-full max-w-sm">
          <TextInput
            v-model="filters.search"
            type="search"
            placeholder="Cari nama atau NIK..."
            @keyup.enter="fetchData"
          />
        </div>
        <BaseButton variant="primary" @click="fetchData">Terapkan Filter</BaseButton>
      </div>
    </BaseCard>

    <!-- Table -->
    <BaseCard class="p-0 overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-left text-sm whitespace-nowrap">
          <thead class="bg-(--bg-sub) border-b border-(--border-soft)">
            <tr>
              <th class="px-4 py-3 font-medium text-(--text-muted)">Karyawan</th>
              <th class="px-4 py-3 font-medium text-(--text-muted)">Kode</th>
              <th class="px-4 py-3 font-medium text-(--text-muted)">NIK / NPWP</th>
              <th class="px-4 py-3 font-medium text-(--text-muted)">Status PTKP</th>
              <th class="px-4 py-3 font-medium text-(--text-muted)">NPWP Valid?</th>
              <th class="px-4 py-3 font-medium text-(--text-muted) w-20">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-(--border-soft)">
            <tr v-if="loading" class="animate-pulse">
              <td colspan="6" class="px-4 py-8 text-center text-(--text-muted)">Memuat data...</td>
            </tr>
            <tr v-else-if="!employees.data || employees.data.length === 0">
              <td colspan="6" class="px-4 py-8 text-center text-(--text-muted)">Tidak ada data ditemukan</td>
            </tr>
            <tr v-else v-for="employee in employees.data" :key="employee.id" class="hover:bg-(--bg-sub)/50">
              <td class="px-4 py-3">
                <div class="font-medium text-(--text-main)">{{ employee.name }}</div>
              </td>
              <td class="px-4 py-3 text-(--text-muted)">{{ employee.employee_code }}</td>
              <td class="px-4 py-3">
                <div class="text-(--text-main)">{{ employee.nik || employee.npwp || '-' }}</div>
                <div class="text-xs text-(--text-muted)">
                  <span v-if="employee.npwp && employee.npwp !== employee.nik">NPWP Lama: {{ employee.npwp }}</span>
                  <span v-else>Format NIK 16-Digit</span>
                </div>
              </td>
              <td class="px-4 py-3">
                <Badge variant="primary" v-if="employee.ptkp">{{ employee.ptkp }}</Badge>
                <span v-else class="text-(--text-muted)">-</span>
              </td>
              <td class="px-4 py-3">
                <button 
                  @click="toggleNpwp(employee)"
                  class="relative inline-flex h-5 w-9 shrink-0 cursor-pointer items-center rounded-full transition-colors duration-200 ease-in-out focus:outline-none"
                  :class="employee.has_npwp ? 'bg-(--primary)' : 'bg-(--border-soft)'"
                >
                  <span class="sr-only">Toggle NPWP</span>
                  <span 
                    class="pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                    :class="employee.has_npwp ? 'translate-x-4.5 ml-0.5' : 'translate-x-0.5'"
                  ></span>
                </button>
              </td>
              <td class="px-4 py-3">
                <button
                  class="p-1.5 rounded-md text-(--text-muted) hover:text-(--primary) hover:bg-(--primary)/10 transition-colors"
                  @click="openEditModal(employee)"
                  title="Edit Pengaturan Pajak"
                >
                  <IconPencil class="w-4 h-4" />
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination Placeholder -->
      <div v-if="employees.total > 0" class="px-4 py-3 border-t border-(--border-soft) flex items-center justify-between">
        <span class="text-sm text-(--text-muted)">
          Menampilkan {{ employees.from }} - {{ employees.to }} dari {{ employees.total }} data
        </span>
        <div class="flex gap-2">
          <BaseButton 
            variant="outline" 
            size="sm" 
            :disabled="!employees.prev_page_url"
            @click="changePage(employees.current_page - 1)"
          >
            Sebelumnnya
          </BaseButton>
          <BaseButton 
            variant="outline" 
            size="sm" 
            :disabled="!employees.next_page_url"
            @click="changePage(employees.current_page + 1)"
          >
            Selanjutnya
          </BaseButton>
        </div>
      </div>
    </BaseCard>

    <!-- Edit Modal -->
    <BaseModal :show="!!editingEmployee" title="Edit Pengaturan Pajak Karyawan" @close="editingEmployee = null">
      <div class="space-y-4" v-if="editingEmployee">
        <div class="bg-(--bg-sub) p-3 rounded-md mb-4 border border-(--border-soft)">
          <p class="font-medium text-(--text-main)">{{ editingEmployee.name }}</p>
          <p class="text-xs text-(--text-muted)">{{ editingEmployee.employee_code }}</p>
          <div class="mt-2 text-sm text-(--text-main) p-2 bg-(--primary)/5 rounded">
            <strong>Nomor NIK/NPWP:</strong> {{ editingEmployee.nik || 'Belum diisi' }}
          </div>
        </div>

        <TextInput 
          v-model="form.npwp" 
          label="NPWP Format Lama (Opsional)" 
          placeholder="Isi jika masih menggunakan NPWP 15 digit" 
        />
        
        <div>
          <label class="block text-sm font-medium text-(--text-main) mb-1">Status PTKP</label>
          <select 
            v-model="form.ptkp" 
            class="w-full px-3 py-2 bg-(--bg-main) border border-(--border-soft) rounded-lg focus:outline-none focus:ring-2 focus:ring-(--primary)/50"
          >
            <option value="">-- Pilih PTKP --</option>
            <option value="TK/0">TK/0 - Tidak Kawin, Tanpa Tanggungan</option>
            <option value="TK/1">TK/1 - Tidak Kawin, 1 Tanggungan</option>
            <option value="TK/2">TK/2 - Tidak Kawin, 2 Tanggungan</option>
            <option value="TK/3">TK/3 - Tidak Kawin, 3 Tanggungan</option>
            <option value="K/0">K/0 - Kawin, Tanpa Tanggungan</option>
            <option value="K/1">K/1 - Kawin, 1 Tanggungan</option>
            <option value="K/2">K/2 - Kawin, 2 Tanggungan</option>
            <option value="K/3">K/3 - Kawin, 3 Tanggungan</option>
          </select>
        </div>

        <label class="flex items-center gap-2 text-sm text-(--text-main) mt-2 p-3 bg-(--bg-sub) rounded-md border border-(--border-soft)">
          <input type="checkbox" v-model="form.has_npwp" class="rounded border-(--border-soft) text-(--primary)" />
          <span>
            <strong>NPWP Valid</strong><br>
            <span class="text-xs text-(--text-muted)">Karyawan memiliki NIK/NPWP valid dan terdaftar. Jika tidak dicentang, akan dikenakan tambahan potongan 20%.</span>
          </span>
        </label>
      </div>

      <template #footer>
        <BaseButton variant="ghost" @click="editingEmployee = null">Batal</BaseButton>
        <BaseButton variant="primary" @click="saveSettings">Simpan Perubahan</BaseButton>
      </template>
    </BaseModal>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import BaseCard from '../../../../Components/BaseCard.vue'
import BaseButton from '../../../../Components/BaseButton.vue'
import BaseModal from '../../../../Components/BaseModal.vue'
import TextInput from '../../../../Components/TextInput.vue'
import Badge from '../../../../Components/Badge.vue'
import { IconRefresh, IconPencil } from '../../../../Components/Icons/index.js'
import { useApi } from '../../../../composables/useApi'
import { useNotification } from '../../../../composables/useNotification'

const api = useApi()
const notification = useNotification()

const loading = ref(false)
const periods = ref([])
const employees = ref({ data: [] })

const filters = reactive({
  period_id: '',
  search: '',
  page: 1
})

const editingEmployee = ref(null)
const form = reactive({
  npwp: '',
  has_npwp: false,
  ptkp: ''
})

async function fetchPeriods() {
  try {
    const res = await api.get('/api/v1/payroll/periods')
    periods.value = res.data || []
    if (periods.value.length > 0) {
      filters.period_id = periods.value[0].id
    }
  } catch (err) {
    console.error('Failed to fetch periods', err)
  }
}

async function fetchData() {
  loading.value = true
  try {
    const query = new URLSearchParams()
    if (filters.period_id) query.append('period_id', filters.period_id)
    if (filters.search) query.append('search', filters.search)
    query.append('page', filters.page)

    const res = await api.get(`/api/v1/payroll/pph/employees?${query.toString()}`)
    employees.value = res
  } catch (err) {
    notification.error('Gagal mengambil data karyawan')
  } finally {
    loading.value = false
  }
}

function changePage(page) {
  filters.page = page
  fetchData()
}

onMounted(async () => {
  await fetchPeriods()
  await fetchData()
})

function openEditModal(emp) {
  editingEmployee.value = emp
  form.npwp = emp.npwp || ''
  form.has_npwp = !!emp.has_npwp
  form.ptkp = emp.ptkp || ''
}

async function toggleNpwp(emp) {
  try {
    const newStatus = !emp.has_npwp;
    // Optimistic UI update
    emp.has_npwp = newStatus;
    
    const payload = {
      npwp: emp.npwp || '',
      ptkp: emp.ptkp || '',
      has_npwp: newStatus
    };
    
    await api.put(`/api/v1/payroll/pph/employees/${emp.id}`, payload);
    notification.success(`Status NPWP ${emp.name} berhasil diubah`);
  } catch (err) {
    // Revert on fail
    emp.has_npwp = !emp.has_npwp;
    notification.error('Gagal mengubah status NPWP');
  }
}

async function saveSettings() {
  if (!editingEmployee.value) return
  
  try {
    const res = await api.put(`/api/v1/payroll/pph/employees/${editingEmployee.value.id}`, form)
    notification.success(res.message || 'Pengaturan pajak berhasil disimpan')
    
    // Update local data
    Object.assign(editingEmployee.value, res.data)
    editingEmployee.value = null
  } catch (err) {
    notification.error('Gagal menyimpan pengaturan pajak')
  }
}
</script>
