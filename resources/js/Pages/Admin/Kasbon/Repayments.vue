<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-(--text-main)">Pelunasan Kasbon</h1>
        <p class="text-sm text-(--text-muted) mt-1">Kelola cicilan dan pembayaran kasbon karyawan.</p>
      </div>
    </div>

    <!-- Filters -->
    <div class="flex items-center gap-4 mb-4">
      <input
        v-model="search"
        @input="fetchData"
        placeholder="Cari Nama/NIP..."
        class="px-3 py-2 rounded-md border border-(--border-soft) bg-(--bg-card) text-(--text-main) text-sm w-64"
      />
      <select
        v-model="filterStatus"
        @change="fetchData"
        class="px-3 py-2 rounded-md border border-(--border-soft) bg-(--bg-card) text-(--text-main) text-sm"
      >
        <option value="">Semua Status</option>
        <option value="pending">Belum Dibayar</option>
        <option value="paid">Sudah Dibayar</option>
      </select>
    </div>

    <!-- Table -->
    <BaseCard>
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-(--border-soft) text-left text-xs text-(--text-muted) uppercase">
              <th class="px-4 py-3 w-10">
                <input type="checkbox" @change="toggleAll" :checked="allSelected" />
              </th>
              <th class="px-4 py-3">NIP</th>
              <th class="px-4 py-3">Nama</th>
              <th class="px-4 py-3 text-center">Cicilan Ke</th>
              <th class="px-4 py-3 text-right">Nominal</th>
              <th class="px-4 py-3">Periode Payroll</th>
              <th class="px-4 py-3">Status</th>
              <th class="px-4 py-3">Tgl Bayar</th>
              <th class="px-4 py-3 text-center">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loading">
              <td colspan="9" class="px-4 py-8 text-center text-(--text-muted)">Memuat data...</td>
            </tr>
            <tr v-else-if="data.length === 0">
              <td colspan="9" class="px-4 py-8 text-center text-(--text-muted)">Belum ada data cicilan.</td>
            </tr>
            <tr v-for="item in data" :key="item.id" class="border-b border-(--border-soft) hover:bg-(--bg-elevated) transition-colors">
              <td class="px-4 py-3">
                <input
                  type="checkbox"
                  :value="item.id"
                  v-model="selectedIds"
                  :disabled="item.status === 'paid'"
                />
              </td>
              <td class="px-4 py-3 font-mono text-(--text-main)">{{ item.kasbon_request?.employee?.nip }}</td>
              <td class="px-4 py-3 font-medium text-(--text-main)">{{ item.kasbon_request?.employee?.name }}</td>
              <td class="px-4 py-3 text-center">{{ item.installment_number }} / {{ item.kasbon_request?.tenor }}</td>
              <td class="px-4 py-3 text-right font-mono">Rp {{ formatNumber(item.amount) }}</td>
              <td class="px-4 py-3 text-(--text-muted)">{{ item.pay_period?.name || '-' }}</td>
              <td class="px-4 py-3">
                <span :class="item.status === 'paid' ? 'bg-(--success)/10 text-(--success)' : 'bg-(--warning)/10 text-(--warning)'" class="px-2 py-0.5 rounded-full text-xs font-medium">
                  {{ item.status === 'paid' ? 'Lunas' : 'Pending' }}
                </span>
              </td>
              <td class="px-4 py-3 text-(--text-muted) text-xs">{{ item.paid_at ? formatDate(item.paid_at) : '-' }}</td>
              <td class="px-4 py-3 text-center">
                <div class="flex items-center justify-center gap-1">
                  <button
                    v-if="item.status === 'pending'"
                    @click="payItem(item)"
                    class="p-1.5 rounded-md hover:bg-(--success)/10 text-(--text-muted) hover:text-(--success) transition-colors"
                    title="Bayar"
                  >
                    <i class="bx bx-check"></i>
                  </button>
                  <button
                    v-if="item.status === 'pending' && !item.pay_period_id"
                    @click="pushItem(item)"
                    class="p-1.5 rounded-md hover:bg-(--info)/10 text-(--text-muted) hover:text-(--info) transition-colors"
                    title="Push ke Payroll"
                  >
                    <i class="bx bx-send"></i>
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <div v-if="pagination.last_page > 1" class="flex items-center justify-between px-4 py-3 border-t border-(--border-soft)">
        <span class="text-xs text-(--text-muted)">Halaman {{ pagination.current_page }} dari {{ pagination.last_page }}</span>
        <div class="flex gap-1">
          <button :disabled="pagination.current_page === 1" @click="goToPage(pagination.current_page - 1)" class="px-2 py-1 text-xs rounded border border-(--border-soft) disabled:opacity-40">Prev</button>
          <button :disabled="pagination.current_page === pagination.last_page" @click="goToPage(pagination.current_page + 1)" class="px-2 py-1 text-xs rounded border border-(--border-soft) disabled:opacity-40">Next</button>
        </div>
      </div>
    </BaseCard>

    <!-- Push to Payroll Modal -->
    <BaseModal v-if="showPush" :show="showPush" @close="showPush = false" title="Push ke Periode Payroll">
      <div class="p-4">
        <label class="block text-sm font-medium text-(--text-main) mb-2">Pilih Periode Payroll</label>
        <select
          v-model="selectedPeriodId"
          class="w-full px-3 py-2 rounded-md border border-(--border-soft) bg-(--bg-card) text-(--text-main) text-sm"
        >
          <option value="">Pilih periode...</option>
          <option v-for="p in payPeriods" :key="p.id" :value="p.id">{{ p.name }}</option>
        </select>
        <div class="flex justify-end gap-2 mt-4">
          <button @click="showPush = false" class="px-4 py-2 text-sm border border-(--border-soft) rounded-md">Batal</button>
          <button @click="doPush" :disabled="!selectedPeriodId" class="px-4 py-2 text-sm bg-(--primary) text-white rounded-md disabled:opacity-50">Push</button>
        </div>
      </div>
    </BaseModal>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useApi } from '@/composables/useApi'
import BaseCard from '@/Components/BaseCard.vue'
import BaseModal from '@/Components/BaseModal.vue'

const api = useApi()
const data = ref([])
const pagination = ref({ current_page: 1, last_page: 1 })
const loading = ref(false)
const search = ref('')
const filterStatus = ref('')
const selectedIds = ref([])
const showPush = ref(false)
const selectedPeriodId = ref('')
const pushTarget = ref(null)
const payPeriods = ref([])

const allSelected = computed(() => data.value.filter(d => d.status === 'pending').length > 0 && data.value.filter(d => d.status === 'pending').every(d => selectedIds.value.includes(d.id)))

function formatNumber(val) { return Number(val || 0).toLocaleString('id-ID') }
function formatDate(d) { return d ? new Date(d).toLocaleDateString('id-ID') : '-' }

async function fetchData(page = 1) {
  loading.value = true
  try {
    const params = { page, per_page: 15 }
    if (search.value) params.search = search.value
    if (filterStatus.value) params.status = filterStatus.value
    const res = await api.get('/api/v1/kasbon/installments?' + new URLSearchParams(params))
    data.value = res.data
    pagination.value = res.pagination
  } catch (e) { console.error(e) } finally { loading.value = false }
}

async function fetchPayPeriods() {
  try {
    const res = await api.get('/api/v1/kasbon/pay-periods')
    payPeriods.value = res.data
  } catch (e) { console.error(e) }
}

function goToPage(page) { fetchData(page) }

function toggleAll(e) {
  selectedIds.value = e.target.checked ? data.value.filter(d => d.status === 'pending').map(d => d.id) : []
}

async function payItem(item) {
  try {
    await api.post(`/api/v1/kasbon/installments/${item.id}/pay`)
    fetchData()
  } catch (e) { console.error(e) }
}

function pushItem(item) {
  pushTarget.value = item
  showPush.value = true
  selectedPeriodId.value = ''
}

async function doPush() {
  try {
    await api.post(`/api/v1/kasbon/installments/${pushTarget.value.id}/push`, { pay_period_id: selectedPeriodId.value })
    showPush.value = false
    fetchData()
  } catch (e) { console.error(e) }
}

onMounted(() => {
  fetchData()
  fetchPayPeriods()
})
</script>
