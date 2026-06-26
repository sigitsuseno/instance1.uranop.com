<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-(--text-main)">Persetujuan Kasbon</h1>
        <p class="text-sm text-(--text-muted) mt-1">Approve atau tolak pengajuan kasbon karyawan.</p>
      </div>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-2 gap-4 mb-6">
      <BaseCard>
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-md bg-(--warning)/10 flex items-center justify-center">
            <i class="bx bx-time text-xl text-(--warning)"></i>
          </div>
          <div>
            <p class="text-2xl font-bold text-(--text-main)">{{ stats.total_pending }}</p>
            <p class="text-xs text-(--text-muted)">Total Pending</p>
          </div>
        </div>
      </BaseCard>
      <BaseCard>
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-md bg-(--info)/10 flex items-center justify-center">
            <i class="bx bx-money text-xl text-(--info)"></i>
          </div>
          <div>
            <p class="text-2xl font-bold text-(--text-main)">Rp {{ formatNumber(stats.total_nominal) }}</p>
            <p class="text-xs text-(--text-muted)">Total Nominal</p>
          </div>
        </div>
      </BaseCard>
    </div>

    <!-- Actions -->
    <div class="flex items-center gap-3 mb-4">
      <input
        v-model="search"
        @input="fetchData"
        placeholder="Cari Nama/NIP..."
        class="px-3 py-2 rounded-md border border-(--border-soft) bg-(--bg-card) text-(--text-main) text-sm w-64"
      />
      <button
        v-if="selectedIds.length > 0"
        @click="bulkApprove"
        class="px-3 py-2 text-xs bg-(--success) text-white rounded-md hover:opacity-90"
      >
        Setujui Terpilih ({{ selectedIds.length }})
      </button>
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
              <th class="px-4 py-3">Bagian</th>
              <th class="px-4 py-3 text-right">Nominal</th>
              <th class="px-4 py-3 text-center">Tenor</th>
              <th class="px-4 py-3">Alasan</th>
              <th class="px-4 py-3">Tgl Pengajuan</th>
              <th class="px-4 py-3 text-center">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loading">
              <td colspan="9" class="px-4 py-8 text-center text-(--text-muted)">Memuat data...</td>
            </tr>
            <tr v-else-if="data.length === 0">
              <td colspan="9" class="px-4 py-8 text-center text-(--text-muted)">Tidak ada pengajuan pending.</td>
            </tr>
            <tr v-for="item in data" :key="item.id" class="border-b border-(--border-soft) hover:bg-(--bg-elevated) transition-colors">
              <td class="px-4 py-3">
                <input type="checkbox" :value="item.id" v-model="selectedIds" />
              </td>
              <td class="px-4 py-3 font-mono text-(--text-main)">{{ item.employee?.nip }}</td>
              <td class="px-4 py-3 font-medium text-(--text-main)">{{ item.employee?.name }}</td>
              <td class="px-4 py-3 text-(--text-muted)">{{ item.employee?.department?.name }}</td>
              <td class="px-4 py-3 text-right font-mono">Rp {{ formatNumber(item.amount) }}</td>
              <td class="px-4 py-3 text-center">{{ item.tenor }} bln</td>
              <td class="px-4 py-3 text-(--text-muted) max-w-xs truncate">{{ item.reason || '-' }}</td>
              <td class="px-4 py-3 text-(--text-muted) text-xs">{{ formatDate(item.created_at) }}</td>
              <td class="px-4 py-3 text-center">
                <div class="flex items-center justify-center gap-1">
                  <button
                    @click="approveItem(item)"
                    class="p-1.5 rounded-md hover:bg-(--success)/10 text-(--text-muted) hover:text-(--success) transition-colors"
                    title="Setujui"
                  >
                    <i class="bx bx-check"></i>
                  </button>
                  <button
                    @click="rejectItem(item)"
                    class="p-1.5 rounded-md hover:bg-(--danger)/10 text-(--text-muted) hover:text-(--danger) transition-colors"
                    title="Tolak"
                  >
                    <i class="bx bx-x"></i>
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
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useApi } from '@/composables/useApi'
import BaseCard from '@/Components/BaseCard.vue'

const api = useApi()
const data = ref([])
const stats = ref({ total_pending: 0, total_nominal: 0 })
const pagination = ref({ current_page: 1, last_page: 1 })
const loading = ref(false)
const search = ref('')
const selectedIds = ref([])

const allSelected = computed(() => data.value.length > 0 && selectedIds.value.length === data.value.length)

function formatNumber(val) { return Number(val || 0).toLocaleString('id-ID') }
function formatDate(d) { return d ? new Date(d).toLocaleDateString('id-ID') : '-' }

async function fetchData(page = 1) {
  loading.value = true
  try {
    const params = { page, per_page: 15 }
    if (search.value) params.search = search.value
    const res = await api.get('/api/v1/kasbon/approvals?' + new URLSearchParams(params))
    data.value = res.data
    stats.value = res.stats
    pagination.value = res.pagination
  } catch (e) { console.error(e) } finally { loading.value = false }
}

function goToPage(page) { fetchData(page) }

function toggleAll(e) {
  selectedIds.value = e.target.checked ? data.value.map(d => d.id) : []
}

async function approveItem(item) {
  try {
    await api.post(`/api/v1/kasbon/requests/${item.id}/approve`)
    fetchData()
  } catch (e) { console.error(e) }
}

async function bulkApprove() {
  try {
    await api.post('/api/v1/kasbon/requests/bulk-approve', { ids: selectedIds.value })
    selectedIds.value = []
    fetchData()
  } catch (e) { console.error(e) }
}

async function rejectItem(item) {
  try {
    await api.post(`/api/v1/kasbon/requests/${item.id}/reject`, { rejection_note: 'Ditolak' })
    fetchData()
  } catch (e) { console.error(e) }
}

onMounted(() => fetchData())
</script>
