<template>
  <ReportPageLayout
    title="Laporan BPJS"
    description="Rekapitulasi iuran BPJS Ketenagakerjaan & Kesehatan per periode"
    @openSettings="showSettings = true"
  >
    <!-- Filter Area -->
    <template #filter>
      <div class="flex items-center gap-4 flex-wrap">
        <div class="flex-1 min-w-[200px] max-w-xs">
          <label class="block text-xs font-medium text-(--text-muted) mb-1">Periode Penggajian</label>
          <select
            v-model="payPeriodId"
            @change="fetchData"
            class="w-full bg-(--bg-input) border border-(--border-soft) rounded-lg px-3 py-2 text-sm"
          >
            <option value="">Pilih Periode...</option>
            <option v-for="p in payPeriods" :key="p.id" :value="p.id">{{ p.name }}</option>
          </select>
        </div>
        <div class="flex-1 min-w-[200px] max-w-xs">
          <label class="block text-xs font-medium text-(--text-muted) mb-1">Cari Karyawan</label>
          <div class="relative">
            <input
              v-model="search"
              type="text"
              placeholder="Nama atau kode..."
              class="w-full pl-10 pr-4 py-2 bg-(--bg-input) border border-(--border-soft) rounded-lg text-sm"
              @input="fetchData"
            />
            <span class="absolute left-3 top-2.5 text-(--text-muted)">🔍</span>
          </div>
        </div>
      </div>
    </template>

    <!-- Actions -->
    <template #actions>
      <button
        class="px-3 py-2 text-sm rounded-lg border border-(--border-soft) hover:bg-(--bg-hover) flex items-center gap-1.5 disabled:opacity-40"
        @click="exportExcel"
        :disabled="records.length === 0 || exporting"
      >
        <i class="bx" :class="exporting ? 'bx-loader-alt animate-spin' : 'bx-export'" ></i>
        <span class="hidden sm:inline">{{ exporting ? 'Mengekspor...' : 'Export Excel' }}</span>
      </button>
    </template>

    <!-- Pilih Periode Dulu -->
    <div v-if="!payPeriodId" class="text-center py-16 text-(--text-muted)">
      <i class="bx bx-calendar text-4xl block mb-3"></i>
      <p>Pilih periode penggajian terlebih dahulu</p>
    </div>

    <!-- Content -->
    <template v-else>
      <!-- Summary Cards -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="bg-(--bg-card) border border-(--border-soft) rounded-xl p-4">
          <div class="text-xs text-(--text-muted) mb-1">Total Karyawan</div>
          <div class="text-xl font-bold text-(--text-main)">{{ records.length }}</div>
        </div>
        <div class="bg-(--bg-card) border border-(--border-soft) rounded-xl p-4">
          <div class="text-xs text-(--text-muted) mb-1">Beban Perusahaan</div>
          <div class="text-xl font-bold text-blue-600">{{ fmt(totals.employerGrandTotal) }}</div>
        </div>
        <div class="bg-(--bg-card) border border-(--border-soft) rounded-xl p-4">
          <div class="text-xs text-(--text-muted) mb-1">Potongan Karyawan</div>
          <div class="text-xl font-bold text-orange-600">{{ fmt(totals.employeeGrandTotal) }}</div>
        </div>
        <div class="bg-(--bg-card) border border-(--border-soft) rounded-xl p-4">
          <div class="text-xs text-(--text-muted) mb-1">Total Iuran BPJS</div>
          <div class="text-xl font-bold text-green-600">{{ fmt(totals.grandTotal) }}</div>
        </div>
      </div>

      <!-- Loading -->
      <div v-if="loading" class="text-center py-8 text-(--text-muted)">
        <i class="bx bx-loader-alt animate-spin text-2xl"></i>
        <p class="mt-2">Memuat data...</p>
      </div>

      <!-- Empty State -->
      <div v-else-if="records.length === 0" class="text-center py-16 text-(--text-muted)">
        <i class="bx bx-shield-quarter text-4xl block mb-3"></i>
        <p>Belum ada data iuran untuk periode ini.</p>
      </div>

      <!-- Grouped Sections -->
      <template v-else>
        <div v-for="(group, gKey) in groupedRecords" :key="gKey" class="mb-8">
          <!-- Group Header -->
          <div class="flex items-center gap-3 mb-2">
            <div class="h-8 w-1.5 rounded-full bg-(--primary)"></div>
            <h3 class="text-sm font-bold text-(--text-main) uppercase tracking-wide">{{ group.name }}</h3>
            <span class="text-xs text-(--text-muted) bg-(--bg-elevated) px-2 py-0.5 rounded-full">
              {{ group.records.length }} karyawan
            </span>
          </div>

          <!-- Per-Group Table -->
          <BaseCard class="p-0">
            <div :class="['overflow-x-auto', { 'max-h-[55vh] overflow-y-auto': group.code === 'BPJS-PROD' }]">
              <table class="w-full text-xs border-collapse">
                <thead>
                  <!-- Row 1: Grouped Headers -->
                  <tr class="border-b border-(--border-soft) bg-(--bg-elevated)">
                    <th class="px-2 py-1.5 text-center font-semibold text-(--text-muted) border-r border-(--border-soft)" rowspan="2">No</th>
                    <th class="px-2 py-1.5 text-left font-semibold text-(--text-muted) border-r border-(--border-soft)" rowspan="2">NAMA KARYAWAN</th>
                    <th class="px-2 py-1.5 text-center font-semibold text-(--text-muted) border-r border-(--border-soft)" rowspan="2">THN<br/>MASUK</th>
                    <th class="px-2 py-1.5 text-center font-semibold text-(--text-muted) border-r border-(--border-soft)" rowspan="2">MASA<br/>KERJA<br/><span class="text-[9px]">(bln)</span></th>
                    <th class="px-2 py-1.5 text-right font-semibold text-(--text-muted) border-r border-(--border-soft)" rowspan="2">GAJI<br/>POKOK</th>
                    <th class="px-2 py-1.5 text-right font-semibold text-(--text-muted) border-r border-(--border-soft)" rowspan="2">TUNJ.<br/>MK</th>
                    <th class="px-2 py-1.5 text-right font-semibold text-(--text-muted) border-r border-(--border-soft)" rowspan="2">TUNJANGAN</th>
                    <th class="px-2 py-1.5 text-right font-semibold text-(--text-muted) border-r border-(--border-soft)" rowspan="2">DASAR<br/>BPJS</th>
                    <th class="px-2 py-1.5 text-center font-semibold text-(--text-muted) border-r border-(--border-soft)" rowspan="2">NO. KPJ<br/>TK</th>
                    <th class="px-2 py-1.5 text-center font-semibold text-(--text-muted) border-r border-(--border-soft)" rowspan="2">NO. KPJ<br/>KES</th>
                    <!-- Perusahaan group header -->
                    <th colspan="6" class="px-2 py-1.5 text-center font-semibold text-blue-700 bg-blue-50/60 border-r border-blue-200">
                      BPJS DIBAYAR PERUSAHAAN
                    </th>
                    <!-- Karyawan group header -->
                    <th colspan="3" class="px-2 py-1.5 text-center font-semibold text-orange-700 bg-orange-50/60 border-r border-orange-200">
                      BPJS DIBAYAR KARYAWAN
                    </th>
                    <!-- Totals -->
                    <th class="px-2 py-1.5 text-center font-semibold text-(--text-muted) border-r border-(--border-soft)" rowspan="2">TOTAL<br/>BPJS TK</th>
                    <th class="px-2 py-1.5 text-center font-semibold text-(--text-muted)" rowspan="2">BPJS<br/>KES</th>
                  </tr>
                  <!-- Row 2: Sub-headers -->
                  <tr class="border-b-2 border-(--border-soft) bg-(--bg-elevated) text-[10px]">
                    <!-- Perusahaan sub -->
                    <th class="px-2 py-1 text-right font-medium text-blue-600 bg-blue-50/40 border-r border-blue-200/50 whitespace-nowrap">JHT<br/><span class="text-[9px] text-blue-400">3.7%</span></th>
                    <th class="px-2 py-1 text-right font-medium text-blue-600 bg-blue-50/40 border-r border-blue-200/50 whitespace-nowrap">JKM<br/><span class="text-[9px] text-blue-400">0.24%</span></th>
                    <th class="px-2 py-1 text-right font-medium text-blue-600 bg-blue-50/40 border-r border-blue-200/50 whitespace-nowrap">JKK<br/><span class="text-[9px] text-blue-400">0.3%</span></th>
                    <th class="px-2 py-1 text-right font-semibold text-blue-700 bg-blue-50/60 border-r border-blue-200/50">TOTAL TK</th>
                    <th class="px-2 py-1 text-right font-medium text-blue-600 bg-blue-50/40 border-r border-blue-200/50 whitespace-nowrap">PENSIUN<br/><span class="text-[9px] text-blue-400">2%</span></th>
                    <th class="px-2 py-1 text-right font-medium text-blue-600 bg-blue-50/40 border-r border-blue-200">KESEHATAN<br/><span class="text-[9px] text-blue-400">4%</span></th>
                    <!-- Karyawan sub -->
                    <th class="px-2 py-1 text-right font-medium text-orange-600 bg-orange-50/40 border-r border-orange-200/50 whitespace-nowrap">JHT<br/><span class="text-[9px] text-orange-400">2%</span></th>
                    <th class="px-2 py-1 text-right font-medium text-orange-600 bg-orange-50/40 border-r border-orange-200/50 whitespace-nowrap">PENSIUN<br/><span class="text-[9px] text-orange-400">1%</span></th>
                    <th class="px-2 py-1 text-right font-medium text-orange-600 bg-orange-50/40 border-r border-orange-200">KESEHATAN<br/><span class="text-[9px] text-orange-400">1%</span></th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-(--border-soft)">
                  <tr v-for="(r, i) in group.records" :key="r.id" class="hover:bg-(--bg-hover)/50 transition-colors">
                    <td class="px-2 py-1.5 text-center text-(--text-muted) border-r border-(--border-soft)">{{ i + 1 }}</td>
                    <td class="px-2 py-1.5 border-r border-(--border-soft)">
                      <div class="font-medium text-(--text-main)">{{ r.employee?.name }}</div>
                      <div class="text-[10px] text-(--text-muted)">{{ r.employee?.nip || r.employee?.employee_code }}</div>
                    </td>
                    <td class="px-2 py-1.5 text-center border-r border-(--border-soft) text-(--text-muted)">{{ r.join_year || '-' }}</td>
                    <td class="px-2 py-1.5 text-center border-r border-(--border-soft) text-(--text-muted)">{{ fmtMasaKerja(r.masa_kerja) }}</td>
                    <td class="px-2 py-1.5 text-right border-r border-(--border-soft)">{{ fmtNum(r.gaji_pokok) }}</td>
                    <td class="px-2 py-1.5 text-right border-r border-(--border-soft)">{{ fmtNum(r.tj_masa_kerja) }}</td>
                    <td class="px-2 py-1.5 text-right border-r border-(--border-soft)">{{ fmtNum(r.tunjangan) }}</td>
                    <td class="px-2 py-1.5 text-right font-semibold border-r border-(--border-soft)">{{ fmtNum(r.bpjs_base_salary) }}</td>
                    <td class="px-2 py-1.5 text-center border-r border-(--border-soft) text-(--text-muted) text-[10px]">{{ r.kpj_tk || '-' }}</td>
                    <td class="px-2 py-1.5 text-center border-r border-(--border-soft) text-(--text-muted) text-[10px]">{{ r.kpj_ks || '-' }}</td>
                    <!-- Perusahaan -->
                    <td class="px-2 py-1.5 text-right text-blue-700 bg-blue-50/10 border-r border-blue-100">{{ fmtNum(r.employer_jht) }}</td>
                    <td class="px-2 py-1.5 text-right text-blue-700 bg-blue-50/10 border-r border-blue-100">{{ fmtNum(r.employer_jkm) }}</td>
                    <td class="px-2 py-1.5 text-right text-blue-700 bg-blue-50/10 border-r border-blue-100">{{ fmtNum(r.employer_jkk) }}</td>
                    <td class="px-2 py-1.5 text-right font-semibold text-blue-800 bg-blue-50/20 border-r border-blue-200">{{ fmtNum(r.employer_tk_total) }}</td>
                    <td class="px-2 py-1.5 text-right text-blue-700 bg-blue-50/10 border-r border-blue-100">{{ fmtNum(r.employer_jp) }}</td>
                    <td class="px-2 py-1.5 text-right text-blue-700 bg-blue-50/10 border-r border-blue-200">{{ fmtNum(r.employer_kesehatan) }}</td>
                    <!-- Karyawan -->
                    <td class="px-2 py-1.5 text-right text-orange-700 bg-orange-50/10 border-r border-orange-100">{{ fmtNum(r.employee_jht) }}</td>
                    <td class="px-2 py-1.5 text-right text-orange-700 bg-orange-50/10 border-r border-orange-100">{{ fmtNum(r.employee_jp) }}</td>
                    <td class="px-2 py-1.5 text-right text-orange-700 bg-orange-50/10 border-r border-orange-200">{{ fmtNum(r.employee_kesehatan) }}</td>
                    <!-- Totals -->
                    <td class="px-2 py-1.5 text-right font-semibold border-r border-(--border-soft)">{{ fmtNum(totalBpjsTk(r)) }}</td>
                    <td class="px-2 py-1.5 text-right font-semibold">{{ fmtNum(totalBpjsKs(r)) }}</td>
                  </tr>
                </tbody>
                <!-- Group Subtotal -->
                <tfoot>
                  <tr class="border-t-2 border-gray-300 bg-(--bg-elevated) font-semibold text-[11px]">
                    <td class="px-2 py-2 border-r border-(--border-soft)" colspan="4">
                      <span class="text-(--primary) uppercase">Total {{ group.name }}</span>
                    </td>
                    <td class="px-2 py-2 text-right border-r border-(--border-soft)">{{ fmtNum(sumField(group.records, 'gaji_pokok')) }}</td>
                    <td class="px-2 py-2 text-right border-r border-(--border-soft)">{{ fmtNum(sumField(group.records, 'tj_masa_kerja')) }}</td>
                    <td class="px-2 py-2 text-right border-r border-(--border-soft)">{{ fmtNum(sumField(group.records, 'tunjangan')) }}</td>
                    <td class="px-2 py-2 text-right border-r border-(--border-soft)">{{ fmtNum(sumField(group.records, 'bpjs_base_salary')) }}</td>
                    <td class="px-2 py-2 border-r border-(--border-soft)"></td>
                    <td class="px-2 py-2 border-r border-(--border-soft)"></td>
                    <!-- Perusahaan subtotals -->
                    <td class="px-2 py-2 text-right text-blue-800 bg-blue-50/20 border-r border-blue-100">{{ fmtNum(sumField(group.records, 'employer_jht')) }}</td>
                    <td class="px-2 py-2 text-right text-blue-800 bg-blue-50/20 border-r border-blue-100">{{ fmtNum(sumField(group.records, 'employer_jkm')) }}</td>
                    <td class="px-2 py-2 text-right text-blue-800 bg-blue-50/20 border-r border-blue-100">{{ fmtNum(sumField(group.records, 'employer_jkk')) }}</td>
                    <td class="px-2 py-2 text-right font-bold text-blue-900 bg-blue-50/30 border-r border-blue-200">{{ fmtNum(sumField(group.records, 'employer_tk_total')) }}</td>
                    <td class="px-2 py-2 text-right text-blue-800 bg-blue-50/20 border-r border-blue-100">{{ fmtNum(sumField(group.records, 'employer_jp')) }}</td>
                    <td class="px-2 py-2 text-right text-blue-800 bg-blue-50/20 border-r border-blue-200">{{ fmtNum(sumField(group.records, 'employer_kesehatan')) }}</td>
                    <!-- Karyawan subtotals -->
                    <td class="px-2 py-2 text-right text-orange-800 bg-orange-50/20 border-r border-orange-100">{{ fmtNum(sumField(group.records, 'employee_jht')) }}</td>
                    <td class="px-2 py-2 text-right text-orange-800 bg-orange-50/20 border-r border-orange-100">{{ fmtNum(sumField(group.records, 'employee_jp')) }}</td>
                    <td class="px-2 py-2 text-right text-orange-800 bg-orange-50/20 border-r border-orange-200">{{ fmtNum(sumField(group.records, 'employee_kesehatan')) }}</td>
                    <!-- Group totals -->
                    <td class="px-2 py-2 text-right font-bold border-r border-(--border-soft)">{{ fmtNum(sumTotalTk(group.records)) }}</td>
                    <td class="px-2 py-2 text-right font-bold">{{ fmtNum(sumTotalKs(group.records)) }}</td>
                  </tr>
                </tfoot>
              </table>
            </div>
          </BaseCard>
        </div>

        <!-- Grand Total -->
        <div class="mt-6 bg-(--bg-card) border-2 border-(--primary)/30 rounded-xl overflow-hidden">
          <div class="bg-(--primary)/10 px-4 py-3 flex items-center gap-3">
            <i class="bx bx-calculator text-(--primary) text-lg"></i>
            <h3 class="text-sm font-bold text-(--primary) uppercase">Grand Total</h3>
          </div>
          <div class="overflow-x-auto p-3">
            <table class="w-full text-xs border-collapse">
              <thead>
                <tr class="border-b border-(--border-soft)">
                  <th class="px-2 py-1.5 text-left font-semibold text-(--text-muted) w-40">Keterangan</th>
                  <th class="px-2 py-1.5 text-right font-semibold text-(--text-muted)">Gaji Pokok</th>
                  <th class="px-2 py-1.5 text-right font-semibold text-(--text-muted)">Tunj. MK</th>
                  <th class="px-2 py-1.5 text-right font-semibold text-(--text-muted)">Tunjangan</th>
                  <th class="px-2 py-1.5 text-right font-semibold text-(--text-muted)">Dasar BPJS</th>
                  <th colspan="3" class="px-2 py-1.5 text-center font-semibold text-blue-700 bg-blue-50/40">BPJS TK Perusahaan</th>
                  <th class="px-2 py-1.5 text-right font-semibold text-blue-700 bg-blue-50/40">Total TK</th>
                  <th class="px-2 py-1.5 text-right font-semibold text-blue-700 bg-blue-50/40">Pensiun</th>
                  <th class="px-2 py-1.5 text-right font-semibold text-blue-700 bg-blue-50/40">KES Prsh.</th>
                  <th colspan="3" class="px-2 py-1.5 text-center font-semibold text-orange-700 bg-orange-50/40">BPJS TK Karyawan</th>
                  <th class="px-2 py-1.5 text-right font-semibold text-orange-700 bg-orange-50/40">KES Kary.</th>
                  <th class="px-2 py-1.5 text-right font-semibold text-(--text-main)">Total TK</th>
                  <th class="px-2 py-1.5 text-right font-semibold text-(--text-main)">Total KES</th>
                </tr>
                <tr class="border-b-2 border-(--border-soft) text-[10px] text-(--text-muted)">
                  <th></th><th></th><th></th><th></th><th></th>
                  <th class="px-2 py-1 text-right bg-blue-50/20 text-blue-600">JHT 3.7%</th>
                  <th class="px-2 py-1 text-right bg-blue-50/20 text-blue-600">JKM 0.24%</th>
                  <th class="px-2 py-1 text-right bg-blue-50/20 text-blue-600">JKK 0.3%</th>
                  <th class="px-2 py-1 text-right bg-blue-50/20"></th>
                  <th class="px-2 py-1 text-right bg-blue-50/20 text-blue-600">2%</th>
                  <th class="px-2 py-1 text-right bg-blue-50/20 text-blue-600">4%</th>
                  <th class="px-2 py-1 text-right bg-orange-50/20 text-orange-600">JHT 2%</th>
                  <th class="px-2 py-1 text-right bg-orange-50/20 text-orange-600">Pensiun 1%</th>
                  <th class="px-2 py-1 text-right bg-orange-50/20 text-orange-600">KES 1%</th>
                  <th class="px-2 py-1 text-right bg-orange-50/20"></th>
                  <th></th><th></th>
                </tr>
              </thead>
              <tbody>
                <tr class="bg-(--primary)/5 font-bold">
                  <td class="px-2 py-2 text-(--primary)">TOTAL KESELURUHAN</td>
                  <td class="px-2 py-2 text-right">{{ fmtNum(totals.gaji_pokok) }}</td>
                  <td class="px-2 py-2 text-right">{{ fmtNum(totals.tj_masa_kerja) }}</td>
                  <td class="px-2 py-2 text-right">{{ fmtNum(totals.tunjangan) }}</td>
                  <td class="px-2 py-2 text-right">{{ fmtNum(totals.bpjs_base_salary) }}</td>
                  <td class="px-2 py-2 text-right text-blue-800 bg-blue-50/10">{{ fmtNum(totals.employer_jht) }}</td>
                  <td class="px-2 py-2 text-right text-blue-800 bg-blue-50/10">{{ fmtNum(totals.employer_jkm) }}</td>
                  <td class="px-2 py-2 text-right text-blue-800 bg-blue-50/10">{{ fmtNum(totals.employer_jkk) }}</td>
                  <td class="px-2 py-2 text-right text-blue-900 bg-blue-50/20">{{ fmtNum(totals.employer_tk_total) }}</td>
                  <td class="px-2 py-2 text-right text-blue-800 bg-blue-50/10">{{ fmtNum(totals.employer_jp) }}</td>
                  <td class="px-2 py-2 text-right text-blue-800 bg-blue-50/10">{{ fmtNum(totals.employer_kesehatan) }}</td>
                  <td class="px-2 py-2 text-right text-orange-800 bg-orange-50/10">{{ fmtNum(totals.employee_jht) }}</td>
                  <td class="px-2 py-2 text-right text-orange-800 bg-orange-50/10">{{ fmtNum(totals.employee_jp) }}</td>
                  <td class="px-2 py-2 text-right text-orange-800 bg-orange-50/10">{{ fmtNum(totals.employee_kesehatan) }}</td>
                  <td class="px-2 py-2 text-right text-orange-800 bg-orange-50/10">{{ fmtNum(totals.total_ks_ee) }}</td>
                  <td class="px-2 py-2 text-right text-(--primary)">{{ fmtNum(totals.total_tk_combined) }}</td>
                  <td class="px-2 py-2 text-right text-(--primary)">{{ fmtNum(totals.total_ks_combined) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </template>
    </template>

    <!-- Settings Modal -->
    <ReportSettingsModal
      v-if="showSettings"
      report-type="bpjs"
      report-label="Laporan BPJS"
      :available-groups="groupCodes"
      @close="showSettings = false"
      @saved="onSettingsSaved"
    >
      <template #config>
        <BpjsSettings />
      </template>
    </ReportSettingsModal>
  </ReportPageLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useApi } from '@/composables/useApi'
import ReportPageLayout from '@/Components/ReportPage/ReportPageLayout.vue'
import ReportSettingsModal from '@/Components/ReportPage/ReportSettingsModal.vue'
import BpjsSettings from '@/Components/ReportPage/settings/BpjsSettings.vue'
import BaseCard from '@/Components/BaseCard.vue'

const { get } = useApi()

// State
const showSettings = ref(false)
const loading = ref(false)
const exporting = ref(false)
const payPeriodId = ref('')
const payPeriods = ref([])
const search = ref('')
const records = ref([])
const selectedGroups = ref([])
const availableGroups = ref([])

// Computed
const groupCodes = computed(() => availableGroups.value.map(g => g.code))

// Group records by group_code
const groupedRecords = computed(() => {
  const groups = {}
  for (const r of records.value) {
    const key = r.group_code || '_other'
    if (!groups[key]) {
      groups[key] = { name: r.group || key, code: key, records: [] }
    }
    groups[key].records.push(r)
  }
  return groups
})

// Totals
const totals = computed(() => {
  const r = records.value
  const sum = (key) => r.reduce((s, x) => s + toNum(x[key]), 0)

  const emp_jht   = sum('employer_jht')
  const emp_jkm   = sum('employer_jkm')
  const emp_jkk   = sum('employer_jkk')
  const emp_jp    = sum('employer_jp')
  const emp_ks    = sum('employer_kesehatan')
  const emp_tk    = emp_jht + emp_jkm + emp_jkk

  const ee_jht    = sum('employee_jht')
  const ee_jp     = sum('employee_jp')
  const ee_ks     = sum('employee_kesehatan')

  return {
    gaji_pokok:         sum('gaji_pokok'),
    tj_masa_kerja:      sum('tj_masa_kerja'),
    tunjangan:          sum('tunjangan'),
    bpjs_base_salary:   sum('bpjs_base_salary'),
    employer_jht:       emp_jht,
    employer_jkm:       emp_jkm,
    employer_jkk:       emp_jkk,
    employer_tk_total:  emp_tk,
    employer_jp:        emp_jp,
    employer_kesehatan: emp_ks,
    employee_jht:       ee_jht,
    employee_jp:        ee_jp,
    employee_kesehatan: ee_ks,
    total_ks_ee:        ee_ks,
    // TK combined = TK prsh + TK kary (JHT+JP both sides)
    total_tk_combined:  emp_tk + emp_jp + ee_jht + ee_jp,
    // KES combined = KES prsh + KES kary
    total_ks_combined:  emp_ks + ee_ks,
    employerGrandTotal: emp_tk + emp_jp + emp_ks,
    employeeGrandTotal: ee_jht + ee_jp + ee_ks,
    grandTotal:         emp_tk + emp_jp + emp_ks + ee_jht + ee_jp + ee_ks,
  }
})

// Helper functions
function totalBpjsTk(r) {
  return toNum(r.employer_jht) + toNum(r.employer_jkm) + toNum(r.employer_jkk) + toNum(r.employer_jp)
       + toNum(r.employee_jht) + toNum(r.employee_jp)
}

function totalBpjsKs(r) {
  return toNum(r.employer_kesehatan) + toNum(r.employee_kesehatan)
}

function sumField(recs, key) {
  return recs.reduce((s, x) => s + toNum(x[key]), 0)
}

function sumTotalTk(recs) {
  return recs.reduce((s, r) => s + totalBpjsTk(r), 0)
}

function sumTotalKs(recs) {
  return recs.reduce((s, r) => s + totalBpjsKs(r), 0)
}

function fmtNum(v) {
  if (v === null || v === undefined || v === 0) return '-'
  return new Intl.NumberFormat('id-ID').format(Math.round(v))
}

function fmtMasaKerja(v) {
  if (v === null || v === undefined || v === '') return '0'
  return Math.round(Number(v))
}

function fmt(v) {
  if (v === null || v === undefined || v === 0) return '-'
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(v)
}

function toNum(v) {
  return parseFloat(v) || 0
}

// API Calls
async function fetchData() {
  if (!payPeriodId.value) {
    records.value = []
    return
  }
  loading.value = true
  try {
    const params = new URLSearchParams({ pay_period_id: payPeriodId.value })
    if (search.value) params.set('search', search.value)
    if (selectedGroups.value.length > 0) {
      params.set('groups', selectedGroups.value.join(','))
    }
    const res = await get(`/api/v1/bpjs/reports?${params}`)
    records.value = res.data?.details || res.details || []
  } catch (e) {
    console.error('Gagal fetch data BPJS:', e)
    records.value = []
  } finally {
    loading.value = false
  }
}

async function fetchPayPeriods() {
  try {
    const res = await get('/api/v1/payroll/periods')
    payPeriods.value = res.data?.data || res.data || []
  } catch (e) {
    console.error('Gagal fetch periods:', e)
  }
}

async function fetchGroups() {
  try {
    const res = await get('/api/v1/settings/employee-data/groups')
    const allGroups = res.data || []
    availableGroups.value = allGroups
      .filter(g => g.group_label === 'BPJS GROUP')
      .map(g => ({ code: g.code, name: g.name }))
  } catch (err) {
    console.error('Gagal fetch groups:', err)
  }
}

async function fetchSavedConfig() {
  try {
    const res = await get('/api/v1/settings/report-configs/bpjs')
    const saved = res.data?.employee_groups || res.employee_groups || []
    if (saved.length > 0) selectedGroups.value = saved
  } catch (e) { /* no config yet */ }
}

function onSettingsSaved({ employee_groups }) {
  showSettings.value = false
  selectedGroups.value = employee_groups
  fetchData()
}

async function exportExcel() {
  if (!payPeriodId.value || records.value.length === 0) return
  exporting.value = true
  try {
    const token = localStorage.getItem('token')
    const params = new URLSearchParams({ pay_period_id: payPeriodId.value })
    if (selectedGroups.value.length > 0) {
      params.set('groups', selectedGroups.value.join(','))
    }

    const url = `/api/v1/bpjs/reports/export?${params}`

    const response = await fetch(url, {
      headers: { 
        'Authorization': `Bearer ${token}`,
        'Accept': 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
      }
    })

    if (!response.ok) {
      const text = await response.text()
      throw new Error(text || `HTTP ${response.status}`)
    }

    const blob = await response.blob()
    const blobUrl = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = blobUrl
    link.download = `Laporan_BPJS_${payPeriods.value.find(p => p.id == payPeriodId.value)?.name || 'export'}.xlsx`
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
    URL.revokeObjectURL(blobUrl)
  } catch (e) {
    console.error('Gagal export:', e)
    alert('Gagal export Excel: ' + (e.message || 'Unknown error'))
  } finally {
    exporting.value = false
  }
}

onMounted(() => {
  fetchPayPeriods()
  fetchGroups()
  fetchSavedConfig()
})
</script>
