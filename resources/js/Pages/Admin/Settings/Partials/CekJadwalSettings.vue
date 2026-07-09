<script setup>
import { ref, watch, computed } from 'vue'
import { useApi } from '../../../../composables/useApi'
import { useNotificationStore } from '../../../../Stores/notification'

const { get, put } = useApi()
const notification = useNotificationStore()

// State
const periods = ref([])
const selectedPeriodId = ref(null)
const workPatterns = ref([])
const rosters = ref([])
const loading = ref(false)
const loadingPeriods = ref(true)
const searchQuery = ref('')
const currentPage = ref(1)
const lastPage = ref(1)
const perPage = ref(50)
const totalRecords = ref(0)

let searchTimeout = null

// Fetch pay periods
const fetchPeriods = async () => {
  loadingPeriods.value = true
  try {
    const res = await get('/api/v1/settings/employee-data/pay-periods')
    periods.value = res.data || []
    if (periods.value.length > 0) {
      selectedPeriodId.value = periods.value[0].id
    }
  } catch (e) {
    notification.addNotification('Gagal memuat periode', 'error')
  } finally {
    loadingPeriods.value = false
  }
}

// Fetch roster data
const fetchData = async () => {
  if (!selectedPeriodId.value) return
  loading.value = true
  try {
    const params = new URLSearchParams({
      period_id: selectedPeriodId.value,
      page: currentPage.value,
      per_page: perPage.value,
    })
    if (searchQuery.value) {
      params.set('search', searchQuery.value)
    }
    const res = await get(`/api/v1/settings/cek-jadwal?${params}`)
    workPatterns.value = res.data.work_patterns || []
    rosters.value = res.data.rosters || []
    if (res.meta) {
      currentPage.value = res.meta.current_page
      lastPage.value = res.meta.last_page
      totalRecords.value = res.meta.total
    }
  } catch (e) {
    notification.addNotification('Gagal memuat data jadwal', 'error')
  } finally {
    loading.value = false
  }
}

// Watch period change
watch(selectedPeriodId, () => {
  currentPage.value = 1
  fetchData()
})

// Debounced search
watch(searchQuery, () => {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    currentPage.value = 1
    fetchData()
  }, 400)
})

// Page change
const goToPage = (page) => {
  if (page < 1 || page > lastPage.value) return
  currentPage.value = page
  fetchData()
  // Scroll to top of table
  window.scrollTo({ top: 300, behavior: 'smooth' })
}

// Update Work Pattern
const updatingWp = ref({})
const updateWorkPattern = async (roster, newWpId) => {
  const oldWpId = roster.work_pattern_id
  roster.work_pattern_id = newWpId
  const wp = workPatterns.value.find(w => w.id === newWpId)
  roster.work_pattern_code = wp?.code || '-'

  updatingWp.value[roster.id] = true
  try {
    const res = await put(`/api/v1/settings/cek-jadwal/${roster.id}/work-pattern`, {
      work_pattern_id: newWpId,
    })
    const updated = res.data
    roster.work_pattern_id = updated.work_pattern_id
    roster.work_pattern_code = updated.work_pattern_code
    roster.shift_id = updated.shift_id
    roster.shift_code = updated.shift_code
    roster.external_code = updated.external_code
    roster.kode = updated.kode
    roster.shift_options = updated.shift_options
    roster.jadwal = updated.kode === 'None' ? '-' : updated.kode
    roster.jadwal_is_red = updated.kode === 'None'
    notification.addNotification('WP berhasil diupdate', 'success')
  } catch (e) {
    roster.work_pattern_id = oldWpId
    notification.addNotification('Gagal update WP', 'error')
  } finally {
    delete updatingWp.value[roster.id]
  }
}

// Update Kode
const updatingKode = ref({})
const updateKode = async (roster, newKode) => {
  const oldKode = roster.kode
  const oldJadwal = roster.jadwal
  const oldJadwalRed = roster.jadwal_is_red

  roster.kode = newKode
  roster.external_code = newKode === 'None' ? null : newKode
  roster.jadwal = newKode === 'None' ? roster.jadwal : newKode
  roster.jadwal_is_red = newKode === 'None'

  updatingKode.value[roster.id] = true
  try {
    const res = await put(`/api/v1/settings/cek-jadwal/${roster.id}/kode`, { kode: newKode })
    const updated = res.data
    roster.kode = updated.kode
    roster.external_code = updated.external_code
    roster.shift_code = updated.shift_code
    roster.shift_id = updated.shift_id
    roster.jadwal = updated.kode === 'None' ? '-' : updated.kode
    roster.jadwal_is_red = updated.kode === 'None'
    notification.addNotification('Kode berhasil diupdate', 'success')
  } catch (e) {
    roster.kode = oldKode
    roster.jadwal = oldJadwal
    roster.jadwal_is_red = oldJadwalRed
    notification.addNotification('Gagal update kode', 'error')
  } finally {
    delete updatingKode.value[roster.id]
  }
}

// Build kode dropdown options
const getKodeOptions = (roster) => {
  const options = [{ value: 'None', label: 'None' }]
  if (roster.shift_options) {
    roster.shift_options.forEach(s => {
      if (s.external_code) {
        options.push({ value: s.external_code, label: s.external_code })
      }
    })
  }
  if (roster.is_sunday) {
    options.push({ value: 'M', label: 'M' })
  }
  return options
}

// Format date
const formatDate = (dateStr) => {
  if (!dateStr) return '-'
  const d = new Date(dateStr)
  return d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })
}

// Pagination helpers
const pageNumbers = computed(() => {
  const pages = []
  const maxVisible = 5
  let start = Math.max(1, currentPage.value - Math.floor(maxVisible / 2))
  let end = Math.min(lastPage.value, start + maxVisible - 1)
  if (end - start < maxVisible - 1) {
    start = Math.max(1, end - maxVisible + 1)
  }
  for (let i = start; i <= end; i++) pages.push(i)
  return pages
})

// Init
fetchPeriods()
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h2 class="text-lg font-semibold text-(--text-main)">Cek Jadwal</h2>
        <p class="text-sm text-(--text-muted) mt-1">Verifikasi jadwal & kode karyawan per periode</p>
      </div>
    </div>

    <!-- Controls -->
    <div class="flex items-center gap-4 flex-wrap">
      <div class="flex items-center gap-2">
        <label class="text-sm font-medium text-(--text-muted) whitespace-nowrap">Periode:</label>
        <select
          v-model="selectedPeriodId"
          :disabled="loadingPeriods"
          class="px-3 py-2 rounded-lg border border-(--border-soft) bg-(--bg-card) text-(--text-main) text-sm focus:outline-none focus:ring-2 focus:ring-(--primary-glow) focus:border-(--primary) min-w-[180px]"
        >
          <option v-for="p in periods" :key="p.id" :value="p.id">
            {{ p.name || `${p.period_month}/${p.period_year}` }}
          </option>
        </select>
      </div>

      <div class="flex items-center gap-2">
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Cari nama/NIP..."
          class="px-3 py-2 rounded-lg border border-(--border-soft) bg-(--bg-card) text-(--text-main) text-sm focus:outline-none focus:ring-2 focus:ring-(--primary-glow) focus:border-(--primary) w-48"
        />
      </div>

      <span v-if="totalRecords > 0" class="text-sm text-(--text-muted)">
        {{ totalRecords.toLocaleString() }} data
      </span>
    </div>

    <!-- Table -->
    <div class="rounded-xl border border-(--border-soft) overflow-hidden bg-(--bg-card)">
      <div v-if="loading" class="p-12 flex flex-col items-center justify-center">
        <div class="w-10 h-10 border-4 border-(--primary)/30 border-t-(--primary) rounded-full animate-spin mb-4"></div>
        <p class="text-(--text-muted)">Memuat data jadwal...</p>
      </div>

      <div v-else-if="rosters.length === 0" class="p-12 text-center text-(--text-muted)">
        <i class="bx bx-calendar-x text-4xl mb-2 block"></i>
        <p>Tidak ada data roster untuk periode ini</p>
      </div>

      <div v-else>
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-(--border-soft) bg-(--bg-elevated)">
                <th class="px-4 py-3 text-left font-semibold text-(--text-muted) whitespace-nowrap">Tanggal</th>
                <th class="px-4 py-3 text-left font-semibold text-(--text-muted) whitespace-nowrap">NIP/PIN</th>
                <th class="px-4 py-3 text-left font-semibold text-(--text-muted) whitespace-nowrap">Nama</th>
                <th class="px-4 py-3 text-left font-semibold text-(--text-muted) whitespace-nowrap">Scan Log</th>
                <th class="px-4 py-3 text-left font-semibold text-(--text-muted) whitespace-nowrap">WP</th>
                <th class="px-4 py-3 text-center font-semibold text-(--text-muted) whitespace-nowrap">Kode</th>
                <th class="px-4 py-3 text-center font-semibold text-(--text-muted) whitespace-nowrap">Jadwal</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="roster in rosters"
                :key="roster.id"
                class="border-b border-(--border-soft) hover:bg-(--bg-elevated)/50 transition-colors"
                :class="{ 'bg-(--bg-elevated)/30': roster.is_sunday }"
              >
                <td class="px-4 py-2.5 whitespace-nowrap text-(--text-main)">
                  <div class="flex items-center gap-1.5">
                    {{ formatDate(roster.date) }}
                    <span v-if="roster.is_sunday" class="text-xs px-1.5 py-0.5 rounded bg-red-100 text-red-600 font-medium">Min</span>
                  </div>
                </td>
                <td class="px-4 py-2.5 whitespace-nowrap font-mono text-(--text-muted)">{{ roster.nip }}</td>
                <td class="px-4 py-2.5 whitespace-nowrap text-(--text-main) font-medium">{{ roster.nama }}</td>
                <td class="px-4 py-2.5 text-(--text-muted) max-w-[200px] truncate" :title="roster.scan_log">{{ roster.scan_log }}</td>
                <td class="px-4 py-2.5 whitespace-nowrap">
                  <select
                    :value="roster.work_pattern_id"
                    :disabled="updatingWp[roster.id]"
                    @change="updateWorkPattern(roster, Number($event.target.value))"
                    class="px-2 py-1.5 rounded-md border border-(--border-soft) bg-(--bg-card) text-(--text-main) text-xs focus:outline-none focus:ring-2 focus:ring-(--primary-glow) focus:border-(--primary) min-w-[80px]"
                  >
                    <option v-for="wp in workPatterns" :key="wp.id" :value="wp.id">{{ wp.code }}</option>
                  </select>
                  <span v-if="updatingWp[roster.id]" class="ml-1.5 inline-block w-3 h-3 border-2 border-(--primary)/30 border-t-(--primary) rounded-full animate-spin"></span>
                </td>
                <td class="px-4 py-2.5 whitespace-nowrap text-center">
                  <select
                    :value="roster.kode"
                    :disabled="updatingKode[roster.id]"
                    @change="updateKode(roster, $event.target.value)"
                    class="px-2 py-1.5 rounded-md border border-(--border-soft) bg-(--bg-card) text-(--text-main) text-xs focus:outline-none focus:ring-2 focus:ring-(--primary-glow) focus:border-(--primary) min-w-[80px] text-center"
                  >
                    <option v-for="opt in getKodeOptions(roster)" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                  </select>
                  <span v-if="updatingKode[roster.id]" class="ml-1.5 inline-block w-3 h-3 border-2 border-(--primary)/30 border-t-(--primary) rounded-full animate-spin"></span>
                </td>
                <td class="px-4 py-2.5 whitespace-nowrap text-center font-semibold" :class="roster.jadwal_is_red ? 'text-red-500' : 'text-(--text-main)'">
                  {{ roster.jadwal }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div v-if="lastPage > 1" class="flex items-center justify-between px-4 py-3 border-t border-(--border-soft) bg-(--bg-elevated)/30">
          <span class="text-xs text-(--text-muted)">
            Halaman {{ currentPage }} dari {{ lastPage }} ({{ totalRecords.toLocaleString() }} data)
          </span>
          <div class="flex items-center gap-1">
            <button
              @click="goToPage(1)"
              :disabled="currentPage === 1"
              class="px-2 py-1 rounded text-xs transition-colors"
              :class="currentPage === 1 ? 'text-(--text-soft) cursor-not-allowed' : 'text-(--text-muted) hover:bg-(--bg-elevated)'"
            >
              ««
            </button>
            <button
              @click="goToPage(currentPage - 1)"
              :disabled="currentPage === 1"
              class="px-2 py-1 rounded text-xs transition-colors"
              :class="currentPage === 1 ? 'text-(--text-soft) cursor-not-allowed' : 'text-(--text-muted) hover:bg-(--bg-elevated)'"
            >
              «
            </button>
            <button
              v-for="p in pageNumbers"
              :key="p"
              @click="goToPage(p)"
              class="px-2.5 py-1 rounded text-xs font-medium transition-colors"
              :class="p === currentPage ? 'bg-(--primary) text-white' : 'text-(--text-muted) hover:bg-(--bg-elevated)'"
            >
              {{ p }}
            </button>
            <button
              @click="goToPage(currentPage + 1)"
              :disabled="currentPage === lastPage"
              class="px-2 py-1 rounded text-xs transition-colors"
              :class="currentPage === lastPage ? 'text-(--text-soft) cursor-not-allowed' : 'text-(--text-muted) hover:bg-(--bg-elevated)'"
            >
              »
            </button>
            <button
              @click="goToPage(lastPage)"
              :disabled="currentPage === lastPage"
              class="px-2 py-1 rounded text-xs transition-colors"
              :class="currentPage === lastPage ? 'text-(--text-soft) cursor-not-allowed' : 'text-(--text-muted) hover:bg-(--bg-elevated)'"
            >
              »»
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
