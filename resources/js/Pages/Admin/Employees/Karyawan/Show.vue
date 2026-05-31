<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useApi } from '../../../../composables/useApi'
import { useNotificationStore } from '../../../../Stores/notification'
import BaseButton from '../../../../Components/BaseButton.vue'
import BaseCard from '../../../../Components/BaseCard.vue'
import Badge from '../../../../Components/Badge.vue'
import { IconChevronLeft, IconPencil, IconRefresh } from '../../../../Components/Icons/index.js'

const router = useRouter()
const route = useRoute()
const { get } = useApi()
const notification = useNotificationStore()

const employeeId = computed(() => Number(route.params.id))

const loading = ref(true)
const employee = ref(null)
const salaryComponents = ref([])

const activeTab = ref('kontrak')

const tabs = [
  { key: 'kontrak', label: 'Kontrak' },
  { key: 'keluarga', label: 'Keluarga' },
  { key: 'dokumen', label: 'Dokumen' },
  { key: 'riwayat', label: 'Riwayat Jabatan' },
  { key: 'gaji', label: 'Gaji' },
]

async function fetchEmployee() {
  try {
    const res = await get(`/api/v1/employees/${employeeId.value}`)
    employee.value = res.data
  } catch (e) {
    notification.error('Gagal memuat data karyawan')
    router.push('/employees')
  }
}

async function fetchSalaryComponents() {
  try {
    const res = await get(`/api/v1/employees/${employeeId.value}/salary-components`)
    salaryComponents.value = res.data || []
  } catch (e) {
    //
  }
}

function formatRupiah(amount) {
  if (amount == null) return '-'
  return 'Rp ' + Number(amount).toLocaleString('id-ID')
}

function formatDate(date) {
  if (!date) return '-'
  return new Date(date).toLocaleDateString('id-ID', { year: 'numeric', month: 'short', day: 'numeric' })
}

function goBack() {
  if (window.history.length > 1) {
    router.back()
  } else {
    router.push('/employees')
  }
}

onMounted(async () => {
  loading.value = true
  await Promise.all([
    fetchEmployee(),
    fetchSalaryComponents()
  ])
  loading.value = false
})
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center gap-3">
      <BaseButton variant="ghost" @click="goBack">
        <template #icon-left>
          <IconChevronLeft class="w-5 h-5" />
        </template>
        Kembali
      </BaseButton>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="flex items-center justify-center py-16 text-(--text-muted)">
      <IconRefresh class="w-8 h-8 animate-spin mr-3" />
      <span>Memuat data...</span>
    </div>

    <template v-else-if="employee">
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-4">
          <div>
            <h1 class="text-2xl font-bold text-(--text-main)">{{ employee.name }}</h1>
            <p class="text-sm text-(--text-muted)">{{ employee.employee_code || employee.nik || '-' }}</p>
          </div>
          <div class="flex items-center gap-2">
            <Badge :variant="employee.is_active ? 'success' : 'danger'">
              {{ employee.is_active ? 'Aktif' : 'Nonaktif' }}
            </Badge>
            <Badge
              :variant="employee.employment_status === 'permanent' ? 'primary' : employee.employment_status === 'contract' ? 'warning' : 'info'"
            >
              {{ employee.employment_status_label || employee.employment_status }}
            </Badge>
          </div>
        </div>
        <BaseButton @click="router.push(`/employees/${employee.id}/edit`)">
          <template #icon-left>
            <IconPencil class="w-4 h-4" />
          </template>
          Edit Karyawan
        </BaseButton>
      </div>

      <div class="grid grid-cols-2 gap-6">
        <BaseCard>
          <template #title>Data Pribadi</template>
          <dl class="space-y-3">
            <div class="flex justify-between">
              <dt class="text-sm text-(--text-muted)">Kode Karyawan</dt>
              <dd class="text-sm font-medium text-(--text-main)">{{ employee.employee_code || '-' }}</dd>
            </div>
            <div class="flex justify-between">
              <dt class="text-sm text-(--text-muted)">NIK</dt>
              <dd class="text-sm font-medium text-(--text-main)">{{ employee.nik || '-' }}</dd>
            </div>
            <div class="flex justify-between">
              <dt class="text-sm text-(--text-muted)">Nama Lengkap</dt>
              <dd class="text-sm font-medium text-(--text-main)">{{ employee.name }}</dd>
            </div>
            <div class="flex justify-between">
              <dt class="text-sm text-(--text-muted)">Email</dt>
              <dd class="text-sm font-medium text-(--text-main)">{{ employee.email || '-' }}</dd>
            </div>
            <div class="flex justify-between">
              <dt class="text-sm text-(--text-muted)">No. Telepon</dt>
              <dd class="text-sm font-medium text-(--text-main)">{{ employee.phone || '-' }}</dd>
            </div>
            <div class="flex justify-between">
              <dt class="text-sm text-(--text-muted)">Tempat, Tanggal Lahir</dt>
              <dd class="text-sm font-medium text-(--text-main)">
                {{ employee.place_of_birth ? employee.place_of_birth + ', ' : '' }}{{ formatDate(employee.date_of_birth) }}
              </dd>
            </div>
            <div class="flex justify-between">
              <dt class="text-sm text-(--text-muted)">Jenis Kelamin</dt>
              <dd class="text-sm font-medium text-(--text-main)">{{ employee.gender === 'L' ? 'Laki-laki' : 'Perempuan' }}</dd>
            </div>
            <div class="flex justify-between">
              <dt class="text-sm text-(--text-muted)">Alamat</dt>
              <dd class="text-sm font-medium text-(--text-main) text-right max-w-[60%]">{{ employee.address || '-' }}</dd>
            </div>
          </dl>
        </BaseCard>

        <BaseCard>
          <template #title>Data Kepegawaian</template>
          <dl class="space-y-3">
            <div class="flex justify-between">
              <dt class="text-sm text-(--text-muted)">Departemen</dt>
              <dd class="text-sm font-medium text-(--text-main)">{{ employee.department?.name || '-' }}</dd>
            </div>
            <div class="flex justify-between">
              <dt class="text-sm text-(--text-muted)">Jabatan</dt>
              <dd class="text-sm font-medium text-(--text-main)">{{ employee.position?.name || '-' }}</dd>
            </div>
            <div class="flex justify-between">
              <dt class="text-sm text-(--text-muted)">Status Kepegawaian</dt>
              <dd class="text-sm font-medium text-(--text-main)">{{ employee.employment_status_label || employee.employment_status }}</dd>
            </div>
            <div class="flex justify-between">
              <dt class="text-sm text-(--text-muted)">Tanggal Masuk</dt>
              <dd class="text-sm font-medium text-(--text-main)">{{ formatDate(employee.join_date) }}</dd>
            </div>
            <div class="flex justify-between">
              <dt class="text-sm text-(--text-muted)">Masa Kerja</dt>
              <dd class="text-sm font-medium text-(--text-main)">{{ employee.years_of_service }} Tahun {{ employee.months_of_service }} Bulan</dd>
            </div>
            <div class="flex justify-between" v-if="employee.employment_status === 'contract' && employee.latest_contract">
              <dt class="text-sm text-(--text-muted)">Akhir Kontrak</dt>
              <dd class="text-sm font-medium text-(--text-main)">
                {{ formatDate(employee.latest_contract.end_date) }} 
                <span class="text-xs ml-1 text-(--warning)">({{ employee.latest_contract.days_left }} hari lagi)</span>
              </dd>
            </div>
            <div class="flex justify-between pt-2 border-t border-(--border-soft)">
              <dt class="text-sm text-(--text-muted)">Status PTKP</dt>
              <dd class="text-sm font-medium text-(--text-main)">{{ employee.ptkp || '-' }}</dd>
            </div>
            <div class="flex justify-between">
              <dt class="text-sm text-(--text-muted)">Bank</dt>
              <dd class="text-sm font-medium text-(--text-main)">{{ employee.bank_name || '-' }} - {{ employee.bank_account_number || '-' }}</dd>
            </div>
            <div class="flex flex-col gap-2 pt-2 border-t border-(--border-soft)">
              <dt class="text-sm text-(--text-muted)">Kelompok / Shift</dt>
              <dd class="text-sm font-medium text-(--text-main)">
                <div v-if="employee.groups?.length" class="flex flex-wrap gap-2 mt-1">
                  <Badge v-for="grp in employee.groups" :key="grp.reference_code" variant="neutral">
                    {{ grp.master_name || grp.reference_code }} <span class="text-xs opacity-75" v-if="grp.group_label">({{ grp.group_label }})</span>
                  </Badge>
                </div>
                <span v-else class="text-(--text-muted)">-</span>
              </dd>
            </div>
          </dl>
        </BaseCard>
      </div>

      <BaseCard :padding="'p-0'">
        <div class="border-b border-(--border-soft) overflow-x-auto">
          <nav class="flex min-w-max">
            <button
              v-for="tab in tabs"
              :key="tab.key"
              @click="activeTab = tab.key"
              :class="[
                'px-6 py-3 text-sm font-medium transition-colors border-b-2 whitespace-nowrap',
                activeTab === tab.key
                  ? 'text-(--primary) border-(--primary)'
                  : 'text-(--text-muted) border-transparent hover:text-(--text-main)',
              ]"
            >
              {{ tab.label }}
            </button>
          </nav>
        </div>

        <div class="p-6 overflow-x-auto">
          <div v-if="activeTab === 'kontrak'">
            <table class="w-full border-collapse min-w-[600px]">
              <thead>
                <tr class="bg-(--bg-elevated)">
                  <th class="px-4 py-3 text-left text-xs font-semibold text-(--text-muted) uppercase tracking-wider">No. Kontrak</th>
                  <th class="px-4 py-3 text-left text-xs font-semibold text-(--text-muted) uppercase tracking-wider">Tipe</th>
                  <th class="px-4 py-3 text-left text-xs font-semibold text-(--text-muted) uppercase tracking-wider">Tanggal Mulai</th>
                  <th class="px-4 py-3 text-left text-xs font-semibold text-(--text-muted) uppercase tracking-wider">Tanggal Berakhir</th>
                  <th class="px-4 py-3 text-left text-xs font-semibold text-(--text-muted) uppercase tracking-wider">Status</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="c in employee.contracts || []" :key="c.id" class="border-b border-(--border-soft)">
                  <td class="px-4 py-3 text-sm text-(--text-main)">{{ c.contract_number || '-' }}</td>
                  <td class="px-4 py-3 text-sm text-(--text-main)">{{ c.contract_type === 'pkwt' ? 'PKWT' : c.contract_type === 'pkwtt' ? 'PKWTT' : 'Magang' }}</td>
                  <td class="px-4 py-3 text-sm text-(--text-main)">{{ formatDate(c.start_date) }}</td>
                  <td class="px-4 py-3 text-sm text-(--text-main)">{{ formatDate(c.end_date) }}</td>
                  <td class="px-4 py-3">
                    <Badge :variant="c.status === 'active' ? 'success' : 'neutral'">
                      {{ c.status === 'active' ? 'Aktif' : 'Berakhir' }}
                    </Badge>
                  </td>
                </tr>
                <tr v-if="!employee.contracts?.length">
                  <td colspan="5" class="px-4 py-8 text-center text-sm text-(--text-muted)">Belum ada data kontrak.</td>
                </tr>
              </tbody>
            </table>
          </div>

          <div v-if="activeTab === 'keluarga'">
            <table class="w-full border-collapse min-w-[600px]">
              <thead>
                <tr class="bg-(--bg-elevated)">
                  <th class="px-4 py-3 text-left text-xs font-semibold text-(--text-muted) uppercase tracking-wider">Nama</th>
                  <th class="px-4 py-3 text-left text-xs font-semibold text-(--text-muted) uppercase tracking-wider">Hubungan</th>
                  <th class="px-4 py-3 text-left text-xs font-semibold text-(--text-muted) uppercase tracking-wider">Jenis Kelamin</th>
                  <th class="px-4 py-3 text-left text-xs font-semibold text-(--text-muted) uppercase tracking-wider">Tanggal Lahir</th>
                  <th class="px-4 py-3 text-left text-xs font-semibold text-(--text-muted) uppercase tracking-wider">Tanggungan</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="f in employee.families || []" :key="f.id" class="border-b border-(--border-soft)">
                  <td class="px-4 py-3 text-sm text-(--text-main)">{{ f.name }}</td>
                  <td class="px-4 py-3 text-sm text-(--text-main) capitalize">{{ f.relation }}</td>
                  <td class="px-4 py-3 text-sm text-(--text-main)">{{ f.gender === 'L' ? 'Laki-laki' : 'Perempuan' }}</td>
                  <td class="px-4 py-3 text-sm text-(--text-main)">{{ formatDate(f.date_of_birth) }}</td>
                  <td class="px-4 py-3">
                    <Badge :variant="f.is_dependent ? 'success' : 'neutral'">
                      {{ f.is_dependent ? 'Ya' : 'Tidak' }}
                    </Badge>
                  </td>
                </tr>
                <tr v-if="!employee.families?.length">
                  <td colspan="5" class="px-4 py-8 text-center text-sm text-(--text-muted)">Belum ada data keluarga.</td>
                </tr>
              </tbody>
            </table>
          </div>

          <div v-if="activeTab === 'dokumen'">
            <table class="w-full border-collapse min-w-[600px]">
              <thead>
                <tr class="bg-(--bg-elevated)">
                  <th class="px-4 py-3 text-left text-xs font-semibold text-(--text-muted) uppercase tracking-wider">Nama Dokumen</th>
                  <th class="px-4 py-3 text-left text-xs font-semibold text-(--text-muted) uppercase tracking-wider">Tipe</th>
                  <th class="px-4 py-3 text-left text-xs font-semibold text-(--text-muted) uppercase tracking-wider">Tgl Upload</th>
                  <th class="px-4 py-3 text-left text-xs font-semibold text-(--text-muted) uppercase tracking-wider">Aksi</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="d in employee.documents || []" :key="d.id" class="border-b border-(--border-soft)">
                  <td class="px-4 py-3 text-sm text-(--text-main)">{{ d.name }}</td>
                  <td class="px-4 py-3 text-sm text-(--text-main) capitalize">{{ d.document_type }}</td>
                  <td class="px-4 py-3 text-sm text-(--text-main)">{{ formatDate(d.created_at) }}</td>
                  <td class="px-4 py-3">
                    <a v-if="d.file_url" :href="d.file_url" target="_blank" class="text-(--primary) hover:underline text-sm">Download</a>
                  </td>
                </tr>
                <tr v-if="!employee.documents?.length">
                  <td colspan="4" class="px-4 py-8 text-center text-sm text-(--text-muted)">Belum ada data dokumen.</td>
                </tr>
              </tbody>
            </table>
          </div>

          <div v-if="activeTab === 'riwayat'">
            <table class="w-full border-collapse min-w-[600px]">
              <thead>
                <tr class="bg-(--bg-elevated)">
                  <th class="px-4 py-3 text-left text-xs font-semibold text-(--text-muted) uppercase tracking-wider">Tanggal Efektif</th>
                  <th class="px-4 py-3 text-left text-xs font-semibold text-(--text-muted) uppercase tracking-wider">Jenis Perubahan</th>
                  <th class="px-4 py-3 text-left text-xs font-semibold text-(--text-muted) uppercase tracking-wider">Dari</th>
                  <th class="px-4 py-3 text-left text-xs font-semibold text-(--text-muted) uppercase tracking-wider">Menjadi</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="p in employee.position_histories || []" :key="p.id" class="border-b border-(--border-soft)">
                  <td class="px-4 py-3 text-sm text-(--text-main)">{{ formatDate(p.effective_date) }}</td>
                  <td class="px-4 py-3 text-sm text-(--text-main) capitalize">{{ p.change_type }}</td>
                  <td class="px-4 py-3 text-sm text-(--text-main)">
                    {{ p.old_department?.name }} - {{ p.old_position?.name }}
                  </td>
                  <td class="px-4 py-3 text-sm text-(--text-main)">
                    {{ p.new_department?.name }} - {{ p.new_position?.name }}
                  </td>
                </tr>
                <tr v-if="!employee.position_histories?.length">
                  <td colspan="4" class="px-4 py-8 text-center text-sm text-(--text-muted)">Belum ada data riwayat jabatan.</td>
                </tr>
              </tbody>
            </table>
          </div>

          <div v-if="activeTab === 'gaji'">
            <div class="mb-4 flex gap-4">
              <div class="p-3 bg-(--bg-elevated) rounded-md">
                <p class="text-xs text-(--text-muted)">Total Gaji Saat Ini</p>
                <p class="text-lg font-bold text-(--text-main)">{{ formatRupiah(employee.total_gaji) }}</p>
              </div>
            </div>
            <table class="w-full border-collapse min-w-[600px]">
              <thead>
                <tr class="bg-(--bg-elevated)">
                  <th class="px-4 py-3 text-left text-xs font-semibold text-(--text-muted) uppercase tracking-wider">Tanggal Efektif</th>
                  <th class="px-4 py-3 text-left text-xs font-semibold text-(--text-muted) uppercase tracking-wider">Gaji Pokok</th>
                  <th class="px-4 py-3 text-left text-xs font-semibold text-(--text-muted) uppercase tracking-wider">Premi</th>
                  <th class="px-4 py-3 text-left text-xs font-semibold text-(--text-muted) uppercase tracking-wider">Tunjangan</th>
                  <th class="px-4 py-3 text-left text-xs font-semibold text-(--text-muted) uppercase tracking-wider">Total</th>
                  <th class="px-4 py-3 text-left text-xs font-semibold text-(--text-muted) uppercase tracking-wider">Status</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="s in salaryComponents" :key="s.id" class="border-b border-(--border-soft)">
                  <td class="px-4 py-3 text-sm text-(--text-main)">{{ formatDate(s.effective_date) }}</td>
                  <td class="px-4 py-3 text-sm text-(--text-main)">{{ formatRupiah(s.gaji_pokok) }}</td>
                  <td class="px-4 py-3 text-sm text-(--text-main)">{{ formatRupiah(s.premi) }}</td>
                  <td class="px-4 py-3 text-sm text-(--text-main)">{{ formatRupiah((Number(s.tunjangan_masa_kerja)||0) + (Number(s.tunjangan)||0) + (Number(s.tunjangan_lain)||0)) }}</td>
                  <td class="px-4 py-3 text-sm text-(--text-main font-semibold)">
                    {{ formatRupiah(Number(s.gaji_pokok) + Number(s.premi) + Number(s.tunjangan_masa_kerja) + Number(s.tunjangan) + Number(s.tunjangan_lain)) }}
                  </td>
                  <td class="px-4 py-3">
                    <Badge :variant="s.is_active ? 'success' : 'neutral'">
                      {{ s.is_active ? 'Aktif' : 'History' }}
                    </Badge>
                  </td>
                </tr>
                <tr v-if="!salaryComponents?.length">
                  <td colspan="6" class="px-4 py-8 text-center text-sm text-(--text-muted)">Belum ada data komponen gaji.</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </BaseCard>
    </template>
  </div>
</template>
