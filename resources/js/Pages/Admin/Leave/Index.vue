<template>
  <div>
    <!-- Header Section -->
    <div class="flex flex-col md:flex-row md:items-center justify-between mb-6 gap-4">
      <div>
        <h1 class="text-2xl font-bold text-(--text-main)">Pengelolaan Cuti</h1>
        <p class="text-sm text-(--text-muted) mt-1">Kelola pengajuan cuti, saldo jatah, generate kuota, dan rekapitulasi periode.</p>
      </div>
      
      <!-- Period Selector -->
      <div class="w-72 flex items-center gap-2">
        <label class="text-sm font-medium text-(--text-main) shrink-0">Periode:</label>
        <div class="relative flex-1">
          <select
            v-model="selectedPeriodId"
            class="w-full px-3 py-2 pr-10 rounded-md border border-(--border-soft) bg-(--bg-card) text-(--text-main) hover:border-(--text-soft) focus:outline-none focus:ring-4 focus:ring-(--primary-glow) focus:border-(--primary) transition-all duration-300 h-10 appearance-none text-sm"
            @change="handlePeriodChange"
          >
            <option v-if="loadingPeriods" value="" disabled>Memuat periode...</option>
            <option v-else-if="periods.length === 0" value="" disabled>Tidak ada periode</option>
            <option
              v-for="period in periods"
              :key="period.id"
              :value="period.id"
            >
              {{ period.name }} ({{ period.status }})
            </option>
          </select>
          <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-(--text-muted)">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <polyline points="6 9 12 15 18 9" />
            </svg>
          </div>
        </div>
      </div>
    </div>

    <!-- Stats Summary Row (Only fetched/shown on Tab 1 & Tab 4) -->
    <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6" v-if="activeTab === 'requests'">
      <BaseCard>
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-md bg-(--primary)/10 flex items-center justify-center">
            <i class="bx bx-list-ol text-xl text-(--primary)"></i>
          </div>
          <div>
            <p class="text-2xl font-bold text-(--text-main)">{{ stats.total }}</p>
            <p class="text-xs text-(--text-muted)">Total Pengajuan</p>
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
          <div class="w-10 h-10 rounded-md bg-(--danger)/10 flex items-center justify-center">
            <i class="bx bx-x-circle text-xl text-(--danger)"></i>
          </div>
          <div>
            <p class="text-2xl font-bold text-(--text-main)">{{ stats.rejected }}</p>
            <p class="text-xs text-(--text-muted)">Ditolak</p>
          </div>
        </div>
      </BaseCard>
      <BaseCard>
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-md bg-(--bg-elevated) flex items-center justify-center">
            <i class="bx bx-x text-xl text-(--text-muted)"></i>
          </div>
          <div>
            <p class="text-2xl font-bold text-(--text-main)">{{ stats.cancelled }}</p>
            <p class="text-xs text-(--text-muted)">Dibatalkan</p>
          </div>
        </div>
      </BaseCard>
    </div>

    <!-- Navigation Tabs -->
    <div class="flex border-b border-(--border-soft) mb-6 gap-2 overflow-x-auto">
      <button
        v-for="tab in tabs"
        :key="tab.id"
        v-show="tab.visible !== false"
        @click="setActiveTab(tab.id)"
        class="flex items-center gap-2 px-4 py-2 text-sm font-medium transition-all border-b-2 h-10 whitespace-nowrap focus:outline-none"
        :class="[
          activeTab === tab.id
            ? 'border-(--primary) text-(--primary) font-semibold bg-(--primary)/5 rounded-t-md'
            : 'border-transparent text-(--text-muted) hover:text-(--text-main) hover:border-(--border-soft)'
        ]"
      >
        <i :class="tab.icon"></i>
        {{ tab.label }}
      </button>
    </div>

    <!-- Tab Contents -->
    <div class="space-y-6">
      
      <!-- TAB 1: DAFTAR PENGAJUAN -->
      <div v-if="activeTab === 'requests'">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-4">
          <!-- Status filter tabs (Sub-tabs) -->
          <div class="flex flex-wrap gap-2">
            <button
              v-for="opt in statusFilterOptions"
              :key="opt.value"
              @click="setStatusFilter(opt.value)"
              class="px-3 py-1.5 text-xs font-medium rounded-full transition-all border h-8 focus:outline-none"
              :class="[
                filters.status === opt.value
                  ? 'bg-(--primary) text-white border-transparent'
                  : 'bg-(--bg-card) text-(--text-muted) border-(--border-soft) hover:text-(--text-main)'
              ]"
            >
              {{ opt.label }}
            </button>
          </div>

          <!-- Create request button -->
          <BaseButton variant="primary" size="sm" @click="openCreateModal">
            <template #icon-left>
              <i class="bx bx-plus text-base"></i>
            </template>
            Ajukan Cuti
          </BaseButton>
        </div>

        <BaseCard>
          <DataTable
            :headers="requestHeaders"
            :items="requests"
            :loading="loadingRequests"
            emptyText="Tidak ada data pengajuan cuti untuk periode ini."
          >
            <template #item.employee_name="{ item }">
              <span class="font-medium text-(--text-main)">{{ item.employee?.name || '-' }}</span>
            </template>
            <template #item.nip="{ item }">
              <span class="font-mono text-xs text-(--text-muted)">{{ item.employee?.nip || '-' }}</span>
            </template>
            <template #item.department="{ item }">
              <span class="text-xs">{{ item.employee?.department?.name || '-' }}</span>
            </template>
            <template #item.leave_type="{ item }">
              <Badge variant="neutral">{{ item.leave_type?.name || '-' }}</Badge>
            </template>
            <template #item.date_range="{ item }">
              <span>{{ formatDate(item.start_date) }} - {{ formatDate(item.end_date) }}</span>
            </template>
            <template #item.days_requested="{ value }">
              <span class="font-semibold">{{ value }} hari</span>
            </template>
            <template #item.status="{ value }">
              <Badge :variant="statusBadgeVariant(value)">{{ statusLabel(value) }}</Badge>
            </template>
            <template #item.actions="{ item }">
              <div class="flex items-center gap-1">
                <BaseButton variant="ghost" size="sm" @click="viewDetail(item)">
                  <template #icon-left>
                    <i class="bx bx-show text-lg"></i>
                  </template>
                </BaseButton>
                <!-- Cancel request button: visible if pending or approved -->
                <BaseButton
                  v-if="['pending', 'approved'].includes(item.status)"
                  variant="ghost"
                  size="sm"
                  @click="handleCancel(item)"
                  title="Batalkan Pengajuan"
                >
                  <template #icon-left>
                    <i class="bx bx-x-circle text-lg text-(--danger)"></i>
                  </template>
                </BaseButton>
              </div>
            </template>
          </DataTable>

          <div class="mt-4">
            <Pagination
              :current-page="pagination.currentPage"
              :total-pages="pagination.totalPages"
              :total="pagination.total"
              :per-page="pagination.perPage"
              @page-change="handlePageChange"
            />
          </div>
        </BaseCard>
      </div>

      <!-- TAB 2: SALDO CUTI -->
      <div v-if="activeTab === 'balances'">
        <BaseCard>
          <div class="mb-4">
            <p class="text-sm text-(--text-muted) mb-4">Informasi sisa jatah cuti karyawan untuk periode terpilih.</p>
          </div>
          <DataTable
            :headers="balanceHeaders"
            :items="balances"
            :loading="loadingBalances"
            :showSearch="true"
            emptyText="Tidak ada data saldo cuti."
          >
            <template #item.entitlement="{ value }">
              <span class="font-medium text-(--primary)">{{ value }} hari</span>
            </template>
            <template #item.used="{ value }">
              <span class="font-medium text-(--warning)">{{ value }} hari</span>
            </template>
            <template #item.balance="{ value }">
              <span class="font-bold" :class="value > 0 ? 'text-(--success)' : 'text-(--text-soft)'">
                {{ value }} hari
              </span>
            </template>
          </DataTable>
        </BaseCard>
      </div>

      <!-- TAB 3: GENERATE CUTI (HR ONLY) -->
      <div v-if="activeTab === 'generate' && isHrOrAdmin">
        <BaseCard>
          <template #title>Generate Kuota Cuti Karyawan</template>
          
          <div class="max-w-2xl space-y-4">
            <p class="text-sm text-(--text-main)">
              Aksi ini digunakan untuk memberikan kuota jatah cuti (addition) ke seluruh karyawan aktif untuk periode cuti terpilih. 
            </p>
            
            <div class="p-4 rounded-md bg-(--primary)/5 border border-(--primary)/20 text-sm text-(--text-main) space-y-2">
              <h4 class="font-semibold flex items-center gap-2">
                <i class="bx bx-info-circle text-lg text-(--primary)"></i>
                Ketentuan & Mekanisme:
              </h4>
              <ul class="list-disc list-inside space-y-1 text-(--text-muted) text-xs">
                <li>Kuota akan didistribusikan sesuai dengan kebijakan cuti (<strong>Leave Policies</strong>) yang berlaku.</li>
                <li>Hanya menyasar karyawan yang memiliki masa kerja <strong>1 tahun atau lebih</strong>.</li>
                <li>Sistem memiliki proteksi bawaan sehingga jatah cuti untuk tipe & periode yang sama tidak akan digenerate ganda bagi karyawan yang sudah memilikinya.</li>
              </ul>
            </div>

            <div class="pt-4 border-t border-(--border-soft) flex flex-col gap-3">
              <div>
                <span class="text-xs text-(--text-soft)">Periode Target:</span>
                <p class="text-sm font-semibold text-(--text-main)">{{ activePeriodName }}</p>
              </div>
              <div>
                <SelectInput
                  v-model="selectedPolicyId"
                  label="Pilih Tipe / Kebijakan Cuti"
                  :options="policies.map(p => ({ value: p.id, label: p.name }))"
                  placeholder="Pilih kebijakan cuti untuk di-generate"
                  :required="true"
                />
              </div>
              <div>
                <BaseButton
                  variant="primary"
                  @click="showConfirmGenerate = true"
                  :loading="generating"
                  :disabled="periods.length === 0 || !selectedPeriodId || !selectedPolicyId || currentPeriodStatus === 'closed' || generating"
                >
                  <template #icon-left>
                    <i class="bx bx-magic-wand text-base"></i>
                  </template>
                  Proses Generate Kuota
                </BaseButton>
                <p v-if="periods.length === 0" class="text-xs text-(--danger) mt-2">
                  * Belum ada data periode cuti. Silakan buat periode cuti terlebih dahulu di menu Pengaturan Cuti.
                </p>
                <p v-else-if="currentPeriodStatus === 'closed'" class="text-xs text-(--danger) mt-2">
                  * Tidak dapat men-generate kuota karena periode terpilih sudah closed/selesai.
                </p>
              </div>
            </div>
          </div>
        </BaseCard>
      </div>

      <!-- TAB 4: REKAP & AKHIRI PERIODE (HR ONLY) -->
      <div v-if="activeTab === 'recap' && isHrOrAdmin">
        <BaseCard>
          <template #title>Rekapitulasi & Penutupan Periode</template>
          
          <div class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div class="space-y-4">
                <p class="text-sm text-(--text-main)">
                  Gunakan menu ini untuk mereview sisa jatah cuti karyawan dan mengakhiri status periode cuti saat ini.
                </p>

                <div class="p-4 rounded-md bg-(--bg-elevated) border border-(--border-soft) space-y-3 text-sm">
                  <div class="flex justify-between border-b border-(--border-soft) pb-2">
                    <span class="text-(--text-muted)">Periode Aktif:</span>
                    <span class="font-bold text-(--text-main)">{{ activePeriodName }}</span>
                  </div>
                  <div class="flex justify-between border-b border-(--border-soft) pb-2">
                    <span class="text-(--text-muted)">Status Saat Ini:</span>
                    <Badge :variant="periodBadgeVariant(currentPeriodStatus)">{{ currentPeriodStatus }}</Badge>
                  </div>
                  <div class="flex justify-between">
                    <span class="text-(--text-muted)">Carry Forward:</span>
                    <span class="font-semibold text-(--text-main)">
                      {{ activePeriodIsCarryForward ? 'Diaktifkan (Jatah Cuti Dibawa)' : 'Dinonaktifkan (Jatah Cuti Hangus)' }}
                    </span>
                  </div>
                </div>

                <div class="flex gap-2">
                  <BaseButton
                    variant="danger"
                    @click="showConfirmRecap = true"
                    :loading="recapping"
                    :disabled="periods.length === 0 || !selectedPeriodId || currentPeriodStatus === 'closed'"
                  >
                    <template #icon-left>
                      <i class="bx bx-archive text-base"></i>
                    </template>
                    Akhiri & Tutup Periode
                  </BaseButton>
                </div>
                <p v-if="periods.length === 0" class="text-xs text-(--danger)">
                  * Belum ada data periode cuti.
                </p>
                <p v-else-if="currentPeriodStatus === 'closed'" class="text-xs text-(--text-soft)">
                  * Periode ini sudah ditutup (closed). Tidak ada aksi penutupan lebih lanjut yang diperlukan.
                </p>
              </div>

              <!-- Carry-forward information warning -->
              <div class="p-4 rounded-md border border-(--warning)/20 bg-(--warning)/5 text-sm space-y-2 h-fit">
                <h4 class="font-semibold flex items-center gap-2 text-(--warning)">
                  <i class="bx bx-error-circle text-lg"></i>
                  Perhatian Sebelum Menutup Periode:
                </h4>
                <p class="text-xs text-(--text-muted) leading-relaxed">
                  Menutup periode cuti akan mengubah status periode terpilih menjadi <strong>closed</strong>.
                </p>
                <p class="text-xs text-(--text-muted) leading-relaxed" v-if="!activePeriodIsCarryForward">
                  Karena kebijakan <strong>Carry Forward Dinonaktifkan</strong> pada periode ini, menekan tombol di atas akan <strong>menghanguskan seluruh sisa saldo cuti</strong> karyawan di database secara otomatis dengan menyuntikkan ledger deduction penyesuaian.
                </p>
                <p class="text-xs text-(--text-muted) leading-relaxed" v-else>
                  Karena kebijakan <strong>Carry Forward Diaktifkan</strong>, sisa jatah cuti karyawan pada periode ini akan tetap dibiarkan utuh di ledger dan dapat digunakan.
                </p>
              </div>
            </div>

            <!-- List of employees and remaining balances for preview -->
            <div class="pt-6 border-t border-(--border-soft)">
              <h3 class="text-base font-semibold text-(--text-main) mb-3">Preview Sisa Jatah Karyawan (Sisa > 0)</h3>
              <DataTable
                :headers="balanceHeaders"
                :items="recapBalances"
                :loading="loadingBalances"
                emptyText="Tidak ada karyawan dengan jatah cuti tersisa."
              >
                <template #item.entitlement="{ value }">{{ value }} hari</template>
                <template #item.used="{ value }">{{ value }} hari</template>
                <template #item.balance="{ value }">
                  <span class="font-bold text-(--danger)">{{ value }} hari</span>
                </template>
              </DataTable>
            </div>
          </div>
        </BaseCard>
      </div>

    </div>

    <!-- MODAL 1: AJUKAN CUTI (CREATE REQUEST) -->
    <BaseModal :show="showCreateModal" title="Ajukan Cuti" size="md" @close="showCreateModal = false">
      <div class="space-y-4">
        <!-- Employee Selector: visible only to HR. For normal user, auto pre-selected and disabled -->
        <div v-if="isHrOrAdmin">
          <SearchableSelect
            v-model="createForm.employee_id"
            label="Karyawan"
            :options="employeeOptions"
            placeholder="Cari nama atau NIP karyawan..."
            :required="true"
            :error="errors.employee_id"
          />
        </div>
        <div v-else class="p-3 bg-(--bg-elevated) rounded-md text-sm text-(--text-main)">
          <span class="text-xs text-(--text-muted)">Mengajukan cuti untuk:</span>
          <p class="font-semibold mt-0.5">{{ auth.userName }}</p>
        </div>

        <SelectInput
          v-model="createForm.leave_type_id"
          label="Tipe Cuti"
          :options="leaveTypeOptions"
          placeholder="Pilih tipe cuti"
          :required="true"
          :error="errors.leave_type_id"
        />

        <div class="grid grid-cols-2 gap-4">
          <TextInput
            v-model="createForm.start_date"
            label="Tanggal Mulai"
            type="date"
            :required="true"
            :error="errors.start_date"
            @change="calculateDays"
          />
          <TextInput
            v-model="createForm.end_date"
            label="Tanggal Selesai"
            type="date"
            :required="true"
            :error="errors.end_date"
            @change="calculateDays"
          />
        </div>

        <TextInput
          v-model="createForm.days_requested"
          label="Durasi Pengajuan (Hari)"
          type="number"
          min="1"
          placeholder="Masukkan jumlah hari"
          :required="true"
          :error="errors.days_requested"
        />

        <TextInput
          v-model="createForm.reason"
          label="Alasan Cuti / Izin"
          placeholder="Tulis detail alasan pengajuan"
          :required="true"
          :error="errors.reason"
        />
      </div>
      <template #footer>
        <BaseButton variant="ghost" @click="showCreateModal = false">Batal</BaseButton>
        <BaseButton variant="primary" @click="submitRequest" :loading="submitting">Ajukan</BaseButton>
      </template>
    </BaseModal>

    <!-- MODAL 2: DETAIL PENGAJUAN -->
    <BaseModal :show="showDetailModal" title="Detail Pengajuan Cuti" size="md" @close="showDetailModal = false">
      <div v-if="detailItem" class="space-y-4 text-sm">
        <div class="grid grid-cols-2 gap-4 border-b border-(--border-soft) pb-4">
          <div>
            <span class="text-xs text-(--text-muted) block">Nama Karyawan:</span>
            <span class="font-medium text-(--text-main)">{{ detailItem.employee?.name || '-' }}</span>
          </div>
          <div>
            <span class="text-xs text-(--text-muted) block">NIP:</span>
            <span class="font-mono text-(--text-main)">{{ detailItem.employee?.nip || '-' }}</span>
          </div>
          <div>
            <span class="text-xs text-(--text-muted) block">Departemen:</span>
            <span class="text-(--text-main)">{{ detailItem.employee?.department?.name || '-' }}</span>
          </div>
          <div>
            <span class="text-xs text-(--text-muted) block">Jenis Cuti:</span>
            <span class="text-(--text-main)">{{ detailItem.leave_type?.name || '-' }}</span>
          </div>
        </div>

        <div class="grid grid-cols-2 gap-4 border-b border-(--border-soft) pb-4">
          <div>
            <span class="text-xs text-(--text-muted) block">Tanggal Mulai:</span>
            <span class="font-medium text-(--text-main)">{{ formatDate(detailItem.start_date) }}</span>
          </div>
          <div>
            <span class="text-xs text-(--text-muted) block">Tanggal Selesai:</span>
            <span class="font-medium text-(--text-main)">{{ formatDate(detailItem.end_date) }}</span>
          </div>
          <div>
            <span class="text-xs text-(--text-muted) block">Total Durasi:</span>
            <span class="font-semibold text-(--text-main)">{{ detailItem.days_requested }} hari</span>
          </div>
          <div>
            <span class="text-xs text-(--text-muted) block">Status:</span>
            <Badge :variant="statusBadgeVariant(detailItem.status)">{{ statusLabel(detailItem.status) }}</Badge>
          </div>
        </div>

        <div class="border-b border-(--border-soft) pb-4" v-if="detailItem.reason">
          <span class="text-xs text-(--text-muted) block mb-1">Alasan Pengajuan:</span>
          <p class="text-(--text-main) bg-(--bg-elevated) p-3 rounded-md italic">
            "{{ detailItem.reason }}"
          </p>
        </div>

        <!-- Rejection/Rejection reason if rejected -->
        <div class="p-3 bg-red-500/10 text-red-700 rounded-md border border-red-500/20" v-if="detailItem.status === 'rejected' && detailItem.rejection_reason">
          <span class="text-xs font-semibold block mb-0.5">Alasan Penolakan HR:</span>
          <p class="text-sm">"{{ detailItem.rejection_reason }}"</p>
        </div>
      </div>
      <template #footer>
        <BaseButton variant="ghost" @click="showDetailModal = false">Tutup</BaseButton>
      </template>
    </BaseModal>

    <!-- CONFIRMATION: CANCEL REQUEST -->
    <ConfirmDialog
      :show="confirmCancel.show"
      title="Batalkan Pengajuan Cuti"
      :message="`Batalkan pengajuan cuti untuk ${confirmCancel.employeeName} (${confirmCancel.days} hari)? Sisa jatah cuti akan dikembalikan.`"
      variant="danger"
      @confirm="confirmCancelAction"
      @cancel="confirmCancel.show = false"
    />

    <!-- CONFIRMATION: GENERATE QUOTA -->
    <ConfirmDialog
      :show="showConfirmGenerate"
      title="Generate Kuota Cuti Massal"
      :message="`Apakah Anda yakin ingin men-generate jatah cuti secara massal untuk seluruh karyawan aktif pada periode '${activePeriodName}'?`"
      variant="primary"
      @confirm="executeGenerateQuota"
      @cancel="showConfirmGenerate = false"
    />

    <!-- CONFIRMATION: RECAP CLOSURE -->
    <ConfirmDialog
      :show="showConfirmRecap"
      title="Akhiri & Tutup Periode Cuti"
      :message="`Apakah Anda yakin ingin MENGAKHIRI & MENUTUP periode '${activePeriodName}'? Tindakan ini akan mengubah status periode menjadi closed, serta menghanguskan sisa jatah cuti jika Carry Forward dinonaktifkan.`"
      variant="danger"
      @confirm="executeRecapPeriod"
      @cancel="showConfirmRecap = false"
    />

  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import BaseCard from '../../../Components/BaseCard.vue'
import BaseButton from '../../../Components/BaseButton.vue'
import BaseModal from '../../../Components/BaseModal.vue'
import Badge from '../../../Components/Badge.vue'
import TextInput from '../../../Components/TextInput.vue'
import SelectInput from '../../../Components/SelectInput.vue'
import SearchableSelect from '../../../Components/SearchableSelect.vue'
import ConfirmDialog from '../../../Components/ConfirmDialog.vue'
import DataTable from '../../../Components/Table/DataTable.vue'
import Pagination from '../../../Components/Table/Pagination.vue'
import { useApi } from '../../../composables/useApi'
import { useNotification } from '../../../composables/useNotification'
import { useAuth } from '../../../composables/useAuth'

const api = useApi()
const notify = useNotification()
const auth = useAuth()

// State management
const activeTab = ref('requests')
const periods = ref([])
const selectedPeriodId = ref('')
const leaveTypes = ref([])
const policies = ref([])
const selectedPolicyId = ref('')
const employeeOptions = ref([])

// Loaders
const loadingPeriods = ref(false)
const loadingRequests = ref(false)
const loadingBalances = ref(false)
const generating = ref(false)
const recapping = ref(false)
const submitting = ref(false)

// Tabs config
const tabs = [
  { id: 'requests', label: 'Daftar Pengajuan', icon: 'bx bx-list-ul' },
  { id: 'balances', label: 'Saldo Cuti', icon: 'bx bx-bar-chart-square' },
  { id: 'generate', label: 'Generate Cuti', icon: 'bx bx-magic-wand', visible: computed(() => isHrOrAdmin.value) },
  { id: 'recap', label: 'Rekap Cuti', icon: 'bx bx-archive', visible: computed(() => isHrOrAdmin.value) },
]

// Filters
const filters = reactive({
  status: '',
})

// Tables lists
const requests = ref([])
const balances = ref([])
const stats = reactive({
  total: 0,
  pending: 0,
  approved: 0,
  rejected: 0,
  cancelled: 0,
})

// Pagination
const pagination = reactive({
  currentPage: 1,
  totalPages: 1,
  total: 0,
  perPage: 15,
})

// Modals / Dialogs states
const showCreateModal = ref(false)
const showDetailModal = ref(false)
const detailItem = ref(null)
const showConfirmGenerate = ref(false)
const showConfirmRecap = ref(false)

const createForm = reactive({
  employee_id: '',
  leave_type_id: '',
  start_date: '',
  end_date: '',
  days_requested: '',
  reason: '',
})

const errors = ref({})

const confirmCancel = reactive({
  show: false,
  id: null,
  employeeName: '',
  days: 0,
})

// Role check helper
const isHrOrAdmin = computed(() => {
  const allowed = ['superadmin', 'hrmanager', 'hr_manager', 'hr']
  return allowed.includes(auth.userRole) || auth.isSuperadmin || auth.isHrmanager
})

// Computed headers/selectors based on active period info
const activePeriodName = computed(() => {
  const p = periods.value.find((x) => x.id === selectedPeriodId.value)
  return p ? p.name : '-'
})

const currentPeriodStatus = computed(() => {
  const p = periods.value.find((x) => x.id === selectedPeriodId.value)
  return p ? p.status : 'active'
})

const activePeriodIsCarryForward = computed(() => {
  const p = periods.value.find((x) => x.id === selectedPeriodId.value)
  return p ? !!p.is_carry_forward : false
})

const recapBalances = computed(() => {
  return balances.value.filter((x) => x.balance > 0)
})

// Table Headers Definitions
const requestHeaders = [
  { key: 'employee_name', label: 'Karyawan' },
  { key: 'nip', label: 'NIP' },
  { key: 'department', label: 'Departemen' },
  { key: 'leave_type', label: 'Tipe' },
  { key: 'date_range', label: 'Tanggal Cuti' },
  { key: 'days_requested', label: 'Total Durasi' },
  { key: 'status', label: 'Status' },
  { key: 'actions', label: 'Aksi', sortable: false },
]

const balanceHeaders = [
  { key: 'nip', label: 'NIP' },
  { key: 'employee_name', label: 'Nama Karyawan' },
  { key: 'department_name', label: 'Departemen' },
  { key: 'leave_type_name', label: 'Jenis Cuti' },
  { key: 'entitlement', label: 'Jatah Kuota' },
  { key: 'used', label: 'Terpakai' },
  { key: 'balance', label: 'Sisa Saldo' },
]

const statusFilterOptions = [
  { value: '', label: 'Semua Status' },
  { value: 'pending', label: 'Pending' },
  { value: 'approved', label: 'Disetujui' },
  { value: 'rejected', label: 'Ditolak' },
  { value: 'cancelled', label: 'Dibatalkan' },
]

const leaveTypeOptions = computed(() => {
  return leaveTypes.value.map((t) => ({ value: t.id, label: t.name }))
})

// Actions
function setActiveTab(tabId) {
  activeTab.value = tabId
  fetchTabSpecificData()
}

function setStatusFilter(status) {
  filters.status = status
  pagination.currentPage = 1
  fetchRequests()
}

function handlePeriodChange() {
  pagination.currentPage = 1
  fetchTabSpecificData()
}

function fetchTabSpecificData() {
  if (activeTab.value === 'requests') {
    fetchRequests()
  } else if (activeTab.value === 'balances' || activeTab.value === 'recap') {
    fetchBalances()
  }
}

// APIs
async function fetchPeriods() {
  loadingPeriods.value = true
  try {
    const res = await api.get('/api/v1/leave/periods')
    periods.value = res.data || []
    
    // Auto-select active period, fallback to first
    const active = periods.value.find((p) => p.status === 'active')
    if (active) {
      selectedPeriodId.value = active.id
    } else if (periods.value.length > 0) {
      selectedPeriodId.value = periods.value[0].id
    }
  } catch (err) {
    notify.error('Gagal memuat daftar periode cuti.')
  } finally {
    loadingPeriods.value = false
  }
}

async function fetchLeaveTypes() {
  try {
    const res = await api.get('/api/v1/leave/types')
    leaveTypes.value = res.data || []
  } catch (err) {
    notify.error('Gagal memuat tipe cuti.')
  }
}

async function fetchPolicies() {
  try {
    const res = await api.get('/api/v1/leave/policies')
    policies.value = res.data || []
  } catch (err) {
    notify.error('Gagal memuat kebijakan cuti.')
  }
}

async function fetchEmployees() {
  if (!isHrOrAdmin.value) return
  try {
    const res = await api.get('/api/v1/employees/options')
    const list = res.data || []
    employeeOptions.value = list.map((e) => ({ value: e.id, label: `${e.name} (${e.nip})` }))
  } catch (err) {
    notify.error('Gagal memuat opsi karyawan.')
  }
}

async function fetchRequests() {
  if (!selectedPeriodId.value) return
  loadingRequests.value = true
  try {
    const url = `/api/v1/leave/requests?leave_period_id=${selectedPeriodId.value}&status=${filters.status}&page=${pagination.currentPage}`
    const res = await api.get(url)
    
    // API returns Stats envelope
    const dataEnvelope = res.paginated || res
    requests.value = dataEnvelope.data || []
    
    pagination.currentPage = dataEnvelope.current_page || 1
    pagination.totalPages = dataEnvelope.last_page || 1
    pagination.total = dataEnvelope.total || 0
    pagination.perPage = dataEnvelope.per_page || 15

    if (res.stats) {
      Object.assign(stats, res.stats)
    }
  } catch (err) {
    notify.error('Gagal memuat riwayat pengajuan cuti.')
  } finally {
    loadingRequests.value = false
  }
}

async function fetchBalances() {
  if (!selectedPeriodId.value) return
  loadingBalances.value = true
  try {
    const res = await api.get(`/api/v1/leave/balances?leave_period_id=${selectedPeriodId.value}`)
    balances.value = res.data || []
  } catch (err) {
    notify.error('Gagal memuat daftar saldo cuti karyawan.')
  } finally {
    loadingBalances.value = false
  }
}

// Calculate days between two dates
function calculateDays() {
  if (createForm.start_date && createForm.end_date) {
    const start = new Date(createForm.start_date)
    const end = new Date(createForm.end_date)
    if (end >= start) {
      const diffTime = Math.abs(end - start)
      const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1
      createForm.days_requested = diffDays
    } else {
      createForm.days_requested = ''
    }
  }
}

// Open modals
function openCreateModal() {
  errors.value = {}
  
  // Reset form
  createForm.employee_id = isHrOrAdmin.value ? '' : (auth.user?.employee_id || '')
  createForm.leave_type_id = ''
  createForm.start_date = ''
  createForm.end_date = ''
  createForm.days_requested = ''
  createForm.reason = ''
  
  showCreateModal.value = true
}

async function submitRequest() {
  errors.value = {}
  
  // Validations
  if (isHrOrAdmin.value && !createForm.employee_id) {
    errors.value.employee_id = 'Karyawan wajib dipilih.'
  }
  if (!createForm.leave_type_id) {
    errors.value.leave_type_id = 'Tipe cuti wajib dipilih.'
  }
  if (!createForm.start_date) {
    errors.value.start_date = 'Tanggal mulai wajib dipilih.'
  }
  if (!createForm.end_date) {
    errors.value.end_date = 'Tanggal selesai wajib dipilih.'
  }
  if (!createForm.days_requested || createForm.days_requested < 1) {
    errors.value.days_requested = 'Jumlah hari harus minimal 1 hari.'
  }
  if (!createForm.reason) {
    errors.value.reason = 'Alasan pengajuan wajib diisi.'
  }

  if (Object.keys(errors.value).length > 0) {
    return
  }

  submitting.value = true
  try {
    const payload = {
      employee_id: createForm.employee_id,
      leave_type_id: createForm.leave_type_id,
      start_date: createForm.start_date,
      end_date: createForm.end_date,
      days_requested: parseInt(createForm.days_requested),
      reason: createForm.reason,
    }
    
    await api.post('/api/v1/leave/requests', payload)
    notify.success('Pengajuan cuti berhasil disubmit.')
    showCreateModal.value = false
    fetchRequests()
  } catch (err) {
    notify.error(err.message || 'Gagal menyimpan pengajuan cuti.')
  } finally {
    submitting.value = false
  }
}

function viewDetail(item) {
  detailItem.value = item
  showDetailModal.value = true
}

function handleCancel(item) {
  confirmCancel.show = true
  confirmCancel.id = item.id
  confirmCancel.employeeName = item.employee?.name || '-'
  confirmCancel.days = item.days_requested
}

async function confirmCancelAction() {
  confirmCancel.show = false
  try {
    await api.post(`/api/v1/leave/requests/${confirmCancel.id}/cancel`, {})
    notify.success('Pengajuan cuti berhasil dibatalkan dan saldo dikembalikan.')
    fetchRequests()
  } catch (err) {
    notify.error(err.message || 'Gagal membatalkan pengajuan.')
  }
}

// Bulk generate quota action
async function executeGenerateQuota() {
  showConfirmGenerate.value = false
  generating.value = true
  try {
    const res = await api.post('/api/v1/leave/generate-quota', {
      leave_period_id: selectedPeriodId.value,
      leave_policy_id: selectedPolicyId.value,
    })
    notify.success(res.message || 'Kuota cuti massal berhasil digenerate.')
    // Reload if balances or requests are showing
    fetchTabSpecificData()
  } catch (err) {
    notify.error(err.message || 'Gagal generate kuota cuti.')
  } finally {
    generating.value = false
  }
}

// Recap and close period action
async function executeRecapPeriod() {
  showConfirmRecap.value = false
  recapping.value = true
  try {
    const res = await api.post('/api/v1/leave/recap-period', {
      leave_period_id: selectedPeriodId.value,
    })
    notify.success(res.message || 'Periode berhasil ditutup dan direkap.')
    
    // Reload periods structure to update their status labels
    await fetchPeriods()
    fetchTabSpecificData()
  } catch (err) {
    notify.error(err.message || 'Gagal merekap periode cuti.')
  } finally {
    recapping.value = false
  }
}

// Helpers
function handlePageChange(page) {
  pagination.currentPage = page
  fetchRequests()
}

function statusBadgeVariant(status) {
  const map = { pending: 'warning', approved: 'success', rejected: 'danger', cancelled: 'neutral' }
  return map[status] || 'neutral'
}

function statusLabel(status) {
  const map = { pending: 'Pending', approved: 'Disetujui', rejected: 'Ditolak', cancelled: 'Dibatalkan' }
  return map[status] || status
}

function periodBadgeVariant(status) {
  const map = { active: 'success', recap: 'warning', closed: 'neutral' }
  return map[status] || 'neutral'
}

function formatDate(dateStr) {
  if (!dateStr) return '-'
  const d = new Date(dateStr)
  if (isNaN(d.getTime())) return dateStr
  return d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })
}

// Initialise page data
onMounted(async () => {
  await fetchPeriods()
  await fetchLeaveTypes()
  await fetchPolicies()
  await fetchEmployees()
  fetchTabSpecificData()
})
</script>
