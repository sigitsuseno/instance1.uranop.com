<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-(--text-main)">Pengajuan Kasbon</h1>
        <p class="text-sm text-(--text-muted) mt-1">Kelola pengajuan pinjaman kasbon karyawan.</p>
      </div>
      <div class="flex items-center gap-2">
        <button
          @click="showSettings = true"
          class="p-2 rounded-md hover:bg-(--bg-elevated) text-(--text-muted) hover:text-(--text-main) transition-colors"
          title="Pengaturan Kasbon"
        >
          <i class="bx bx-cog text-lg"></i>
        </button>
        <button
          @click="showForm = true"
          class="px-4 py-2 bg-(--primary) text-white rounded-md hover:opacity-90 transition-all duration-200 text-sm font-medium flex items-center gap-2"
        >
          <i class="bx bx-plus"></i> Pengajuan Baru
        </button>
      </div>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
      <BaseCard>
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-md bg-(--primary)/10 flex items-center justify-center">
            <i class="bx bx-list-ol text-xl text-(--primary)"></i>
          </div>
          <div>
            <p class="text-2xl font-bold text-(--text-main)">{{ stats.total }}</p>
            <p class="text-xs text-(--text-muted)">Total</p>
          </div>
        </div>
      </BaseCard>
      <BaseCard>
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-md bg-(--warning)/10 flex items-center justify-center">
            <i class="bx bx-time text-xl text-(--warning)"></i>
          </div>
          <div>
            <p class="text-2xl font-bold text-(--text-main)">{{ stats.pending }}</p>
            <p class="text-xs text-(--text-muted)">Pending</p>
          </div>
        </div>
      </BaseCard>
      <BaseCard>
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-md bg-(--success)/10 flex items-center justify-center">
            <i class="bx bx-check-circle text-xl text-(--success)"></i>
          </div>
          <div>
            <p class="text-2xl font-bold text-(--text-main)">{{ stats.approved }}</p>
            <p class="text-xs text-(--text-muted)">Disetujui</p>
          </div>
        </div>
      </BaseCard>
      <BaseCard>
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-md bg-(--info)/10 flex items-center justify-center">
            <i class="bx bx-wallet text-xl text-(--info)"></i>
          </div>
          <div>
            <p class="text-2xl font-bold text-(--text-main)">{{ stats.outstanding }}</p>
            <p class="text-xs text-(--text-muted)">Outstanding</p>
          </div>
        </div>
      </BaseCard>
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
        <option value="pending">Pending</option>
        <option value="approved">Approved</option>
        <option value="rejected">Rejected</option>
        <option value="disbursed">Disbursed</option>
        <option value="completed">Completed</option>
      </select>
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
              <th class="px-4 py-3">Status</th>
              <th class="px-4 py-3 text-center">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loading">
              <td colspan="7" class="px-4 py-8 text-center text-(--text-muted)">Memuat data...</td>
            </tr>
            <tr v-else-if="data.length === 0">
              <td colspan="7" class="px-4 py-8 text-center text-(--text-muted)">Belum ada pengajuan kasbon.</td>
            </tr>
            <tr v-for="item in data" :key="item.id" class="border-b border-(--border-soft) hover:bg-(--bg-elevated) transition-colors">
              <td class="px-4 py-3 font-mono text-(--text-main)">{{ item.employee?.nip }}</td>
              <td class="px-4 py-3 font-medium text-(--text-main)">{{ item.employee?.name }}</td>
              <td class="px-4 py-3 text-(--text-muted)">{{ item.employee?.department?.name }}</td>
              <td class="px-4 py-3 text-right font-mono">Rp {{ formatNumber(item.amount) }}</td>
              <td class="px-4 py-3 text-center">{{ item.tenor }} bln</td>
              <td class="px-4 py-3">
                <span :class="statusBadge(item.status)">{{ statusLabel(item.status) }}</span>
              </td>
              <td class="px-4 py-3 text-center">
                <div class="flex items-center justify-center gap-1">
                  <button
                    v-if="item.status === 'pending'"
                    @click="editItem(item)"
                    class="p-1.5 rounded-md hover:bg-(--primary)/10 text-(--text-muted) hover:text-(--primary) transition-colors"
                    title="Edit"
                  >
                    <i class="bx bx-edit"></i>
                  </button>
                  <button
                    v-if="item.status === 'pending'"
                    @click="confirmDelete(item)"
                    class="p-1.5 rounded-md hover:bg-(--danger)/10 text-(--text-muted) hover:text-(--danger) transition-colors"
                    title="Hapus"
                  >
                    <i class="bx bx-trash"></i>
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <!-- Pagination -->
      <div v-if="pagination.last_page > 1" class="flex items-center justify-between px-4 py-3 border-t border-(--border-soft)">
        <span class="text-xs text-(--text-muted)">Halaman {{ pagination.current_page }} dari {{ pagination.last_page }}</span>
        <div class="flex gap-1">
          <button
            :disabled="pagination.current_page === 1"
            @click="goToPage(pagination.current_page - 1)"
            class="px-2 py-1 text-xs rounded border border-(--border-soft) disabled:opacity-40 hover:bg-(--bg-elevated)"
          >
            Prev
          </button>
          <button
            :disabled="pagination.current_page === pagination.last_page"
            @click="goToPage(pagination.current_page + 1)"
            class="px-2 py-1 text-xs rounded border border-(--border-soft) disabled:opacity-40 hover:bg-(--bg-elevated)"
          >
            Next
          </button>
        </div>
      </div>
    </BaseCard>

    <!-- Form Modal -->
    <BaseModal v-if="showForm" :show="showForm" @close="closeForm" :title="editing ? 'Edit Pengajuan Kasbon' : 'Pengajuan Kasbon Baru'">
      <KasbonForm
        :editing="editing"
        :form="form"
        :errors="formErrors"
        @submit="saveForm"
        @cancel="closeForm"
      />
    </BaseModal>

    <!-- Delete Confirmation -->
    <BaseModal v-if="showDelete" :show="showDelete" @close="showDelete = false" title="Konfirmasi Hapus">
      <div class="p-4">
        <p class="text-(--text-main)">Yakin ingin menghapus pengajuan kasbon ini?</p>
        <div class="flex justify-end gap-2 mt-4">
          <button @click="showDelete = false" class="px-4 py-2 text-sm border border-(--border-soft) rounded-md">Batal</button>
          <button @click="doDelete" class="px-4 py-2 text-sm bg-(--danger) text-white rounded-md">Hapus</button>
        </div>
      </div>
    </BaseModal>

    <!-- Settings Modal -->
    <KasbonSettingsModal
      :show="showSettings"
      @close="showSettings = false"
      @saved="fetchData"
    />
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useApi } from '@/composables/useApi'
import BaseCard from '@/Components/BaseCard.vue'
import BaseModal from '@/Components/BaseModal.vue'
import KasbonForm from './KasbonForm.vue'
import KasbonSettingsModal from './KasbonSettingsModal.vue'

const api = useApi()

const data = ref([])
const stats = ref({ total: 0, pending: 0, approved: 0, outstanding: 0 })
const pagination = ref({ current_page: 1, last_page: 1 })
const loading = ref(false)
const search = ref('')
const filterStatus = ref('')

const showForm = ref(false)
const showSettings = ref(false)
const showDelete = ref(false)
const editing = ref(false)
const form = ref({ employee_id: null, amount: 0, tenor: 3, reason: '' })
const formErrors = ref({})
const deleteTarget = ref(null)

function statusLabel(status) {
  const map = { pending: 'Pending', approved: 'Disetujui', rejected: 'Ditolak', disbursed: 'Dicairkan', completed: 'Lunas' }
  return map[status] || status
}

function statusBadge(status) {
  const base = 'px-2 py-0.5 rounded-full text-xs font-medium'
  const colors = {
    pending: 'bg-(--warning)/10 text-(--warning)',
    approved: 'bg-(--success)/10 text-(--success)',
    rejected: 'bg-(--danger)/10 text-(--danger)',
    disbursed: 'bg-(--info)/10 text-(--info)',
    completed: 'bg-(--text-muted)/10 text-(--text-muted)',
  }
  return `${base} ${colors[status] || ''}`
}

function formatNumber(val) {
  return Number(val || 0).toLocaleString('id-ID')
}

async function fetchData(page = 1) {
  loading.value = true
  try {
    const params = { page, per_page: 15 }
    if (search.value) params.search = search.value
    if (filterStatus.value) params.status = filterStatus.value
    const res = await api.get('/api/v1/kasbon/requests?' + new URLSearchParams(params))
    data.value = res.data
    stats.value = res.stats
    pagination.value = res.pagination
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

function goToPage(page) {
  fetchData(page)
}

function editItem(item) {
  editing.value = true
  form.value = {
    id: item.id,
    employee_id: item.employee_id,
    amount: Number(item.amount),
    tenor: item.tenor,
    reason: item.reason || '',
  }
  formErrors.value = {}
  showForm.value = true
}

async function saveForm() {
  formErrors.value = {}
  try {
    if (editing.value) {
      await api.put(`/api/v1/kasbon/requests/${form.value.id}`, form.value)
    } else {
      await api.post('/api/v1/kasbon/requests', form.value)
    }
    showForm.value = false
    fetchData()
  } catch (e) {
    if (e.response?.status === 422) {
      formErrors.value = e.response.data.errors || {}
    }
  }
}

function closeForm() {
  showForm.value = false
  editing.value = false
  form.value = { employee_id: null, amount: 0, tenor: 3, reason: '' }
  formErrors.value = {}
}

function confirmDelete(item) {
  deleteTarget.value = item
  showDelete.value = true
}

async function doDelete() {
  try {
    await api.destroy(`/api/v1/kasbon/requests/${deleteTarget.value.id}`)
    showDelete.value = false
    fetchData()
  } catch (e) {
    console.error(e)
  }
}

onMounted(() => {
  fetchData()
})
</script>
