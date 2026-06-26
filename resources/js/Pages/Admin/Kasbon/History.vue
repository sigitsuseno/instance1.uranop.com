<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-(--text-main)">Riwayat Kasbon</h1>
        <p class="text-sm text-(--text-muted) mt-1">Riwayat semua transaksi kasbon karyawan.</p>
      </div>
    </div>

    <!-- Filters -->
    <div class="flex items-center gap-4 mb-4 flex-wrap">
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
        <option value="pending">Pending</option>
        <option value="approved">Approved</option>
        <option value="rejected">Ditolak</option>
        <option value="disbursed">Dicairkan</option>
        <option value="completed">Lunas</option>
      </select>
      <input
        v-model="dateFrom"
        @change="fetchData"
        type="date"
        class="px-3 py-2 rounded-md border border-(--border-soft) bg-(--bg-card) text-(--text-main) text-sm"
      />
      <span class="text-(--text-muted) text-sm">s/d</span>
      <input
        v-model="dateTo"
        @change="fetchData"
        type="date"
        class="px-3 py-2 rounded-md border border-(--border-soft) bg-(--bg-card) text-(--text-main) text-sm"
      />
    </div>

    <!-- Table -->
    <BaseCard>
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-(--border-soft) text-left text-xs text-(--text-muted) uppercase">
              <th class="px-4 py-3">NIP</th>
              <th class="px-4 py-3">Nama</th>
              <th class="px-4 py-3">Bagian</th>
              <th class="px-4 py-3 text-right">Nominal</th>
              <th class="px-4 py-3 text-center">Tenor</th>
              <th class="px-4 py-3 text-right">Sisa</th>
              <th class="px-4 py-3">Status</th>
              <th class="px-4 py-3">Tgl Pengajuan</th>
              <th class="px-4 py-3">Disetujui Oleh</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loading">
              <td colspan="9" class="px-4 py-8 text-center text-(--text-muted)">Memuat data...</td>
            </tr>
            <tr v-else-if="data.length === 0">
              <td colspan="9" class="px-4 py-8 text-center text-(--text-muted)">Belum ada riwayat kasbon.</td>
            </tr>
            <tr v-for="item in data" :key="item.id" class="border-b border-(--border-soft) hover:bg-(--bg-elevated) transition-colors">
              <td class="px-4 py-3 font-mono text-(--text-main)">{{ item.employee?.nip }}</td>
              <td class="px-4 py-3 font-medium text-(--text-main)">{{ item.employee?.name }}</td>
              <td class="px-4 py-3 text-(--text-muted)">{{ item.employee?.department?.name }}</td>
              <td class="px-4 py-3 text-right font-mono">Rp {{ formatNumber(item.amount) }}</td>
              <td class="px-4 py-3 text-center">{{ item.tenor }} bln</td>
              <td class="px-4 py-3 text-right font-mono">Rp {{ formatNumber(item.remaining_amount) }}</td>
              <td class="px-4 py-3">
                <span :class="statusBadge(item.status)">{{ statusLabel(item.status) }}</span>
              </td>
              <td class="px-4 py-3 text-(--text-muted) text-xs">{{ formatDate(item.created_at) }}</td>
              <td class="px-4 py-3 text-(--text-muted)">{{ item.approved_by?.name || '-' }}</td>
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
import { ref, onMounted } from 'vue'
import { useApi } from '@/composables/useApi'
import BaseCard from '@/Components/BaseCard.vue'

const api = useApi()
const data = ref([])
const pagination = ref({ current_page: 1, last_page: 1 })
const loading = ref(false)
const search = ref('')
const filterStatus = ref('')
const dateFrom = ref('')
const dateTo = ref('')

function statusLabel(status) {
  const map = { pending: 'Pending', approved: 'Disetujui', rejected: 'Ditolak', disbursed: 'Dicairkan', completed: 'Lunas' }
  return map[status] || status
}

function statusBadge(status) {
  const base = 'px-2 py-0.5 rounded-full text-xs font-medium'
  const c = {
    pending: 'bg-(--warning)/10 text-(--warning)',
    approved: 'bg-(--success)/10 text-(--success)',
    rejected: 'bg-(--danger)/10 text-(--danger)',
    disbursed: 'bg-(--info)/10 text-(--info)',
    completed: 'bg-(--text-muted)/10 text-(--text-muted)',
  }
  return `${base} ${c[status] || ''}`
}

function formatNumber(val) { return Number(val || 0).toLocaleString('id-ID') }
function formatDate(d) { return d ? new Date(d).toLocaleDateString('id-ID') : '-' }

async function fetchData(page = 1) {
  loading.value = true
  try {
    const params = { page, per_page: 15 }
    if (search.value) params.search = search.value
    if (filterStatus.value) params.status = filterStatus.value
    if (dateFrom.value) params.date_from = dateFrom.value
    if (dateTo.value) params.date_to = dateTo.value
    const res = await api.get('/api/v1/kasbon/history?' + new URLSearchParams(params))
    data.value = res.data
    pagination.value = res.pagination
  } catch (e) { console.error(e) } finally { loading.value = false }
}

function goToPage(page) { fetchData(page) }

onMounted(() => fetchData())
</script>
