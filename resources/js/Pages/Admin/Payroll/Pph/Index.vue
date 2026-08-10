<template>
  <div>
    <div class="mb-6">
      <h1 class="text-xl font-semibold text-(--text-main)">{{ activeTab === 'data' ? 'PPH 21 Bulanan' : 'Pengelolaan PPh 21' }}</h1>
      <p class="text-sm text-(--text-muted) mt-1">{{ activeTab === 'data' ? 'Perhitungan pajak PPh 21 bulanan per karyawan per periode' : 'Konfigurasi Pengaturan Pajak, PTKP, TER, dan Tarif Progresif' }}</p>
    </div>

    <div v-if="activeTab !== 'data'" class="border-b border-(--border-soft) mb-6">
      <nav class="flex gap-0 -mb-px">
        <button
          v-for="tab in tabs"
          :key="tab.key"
          :class="[
            'px-4 py-2.5 text-sm font-medium transition-colors border-b-2 rounded-t-md',
            activeTab === tab.key
              ? 'text-(--primary) border-(--primary)'
              : 'text-(--text-muted) border-transparent hover:text-(--text-main) hover:border-(--border-soft)',
          ]"
          @click="activeTab = tab.key"
        >
          {{ tab.label }}
        </button>
      </nav>
    </div>


    <div class="mt-4 space-y-6">
      <!-- General Config -->
      <BaseCard v-if="activeTab === 'general'">
        <template #title>Pengaturan Umum PPh 21</template>
        <template #subtitle>Metode perhitungan pajak dan penalti Non-NPWP</template>
        
        <div v-if="loading.general" class="text-center py-4 text-(--text-muted)">Memuat...</div>
        <div v-else class="space-y-4 max-w-2xl">
          <SelectInput
            v-model="pphConfig.calculation_method"
            label="Metode Perhitungan"
            :options="[
              { value: 'ter', label: 'Tarif Efektif Rata-rata (TER)' },
              { value: 'progressive', label: 'Tarif Progresif (Pasal 17)' }
            ]"
          />
          <SelectInput
            v-model="pphConfig.pph_method"
            label="Metode PPh (Tunjangan/Potongan)"
            :options="[
              { value: 'gross', label: 'Gross (Pajak Ditanggung Karyawan)' },
              { value: 'gross_up', label: 'Gross Up (Pajak Ditanggung Perusahaan)' },
              { value: 'net', label: 'Net (Pajak Ditanggung Perusahaan tanpa Tunjangan)' }
            ]"
          />
          
          <div class="pt-2 border-t border-(--border-soft)">
            <label class="flex items-center gap-2 text-sm text-(--text-main) font-medium mb-3">
              <input type="checkbox" v-model="pphConfig.non_npwp_penalty" class="rounded border-(--border-soft)" />
              Aktifkan Penalti Non-NPWP
            </label>
            <TextInput
              v-if="pphConfig.non_npwp_penalty"
              v-model="pphConfig.non_npwp_multiplier"
              type="number"
              step="0.01"
              label="Faktor Pengali Non-NPWP"
              placeholder="Contoh: 1.2 (20% lebih tinggi)"
            />
          </div>

          <!-- NIK sebagai NPWP -->
          <div class="pt-2 border-t border-(--border-soft)">
            <label class="flex items-start gap-3 text-sm">
              <input type="checkbox" v-model="pphConfig.nik_as_npwp" class="rounded border-(--border-soft) mt-0.5" />
              <div>
                <span class="font-medium text-(--text-main)">NIK = NPWP</span>
                <p class="text-xs text-(--text-muted) mt-0.5">
                  Setiap karyawan dianggap sudah terdaftar sebagai wajib pajak.
                  Nomor NPWP otomatis menggunakan NIK (16 digit).
                  Jika OFF, karyawan wajib input NPWP secara terpisah.
                </p>
              </div>
            </label>
          </div>

          <!-- DTP Global -->
          <div class="pt-2 border-t border-(--border-soft)">
            <label class="flex items-start gap-3 text-sm">
              <input type="checkbox" v-model="pphConfig.is_dtp" class="rounded border-(--border-soft) mt-0.5" />
              <div>
                <span class="font-medium text-(--text-main)">DTP (Ditanggung Pemerintah)</span>
                <p class="text-xs text-(--text-muted) mt-0.5">
                  PPh 21 ditanggung oleh pemerintah. Pajak tetap dihitung dan dilaporkan ke DJP,
                  tetapi tidak dipotong dari gaji karyawan. Hanya untuk karyawan yang eligible
                  sesuai PMK yang berlaku.
                </p>
              </div>
            </label>
          </div>
          
          <div class="pt-4 flex justify-end">
            <BaseButton variant="primary" @click="savePphConfig" :disabled="saving">
              {{ saving ? 'Menyimpan...' : 'Simpan Pengaturan' }}
            </BaseButton>
          </div>
        </div>
      </BaseCard>

      <!-- PTKP -->
      <BaseCard v-if="activeTab === 'ptkp'">
        <template #title>Penghasilan Tidak Kena Pajak (PTKP)</template>
        <template #subtitle>Daftar tarif PTKP berdasarkan status perkawinan dan tanggungan</template>
        <template #actions>
          <BaseButton variant="primary" size="sm" @click="savePtkp" :disabled="saving">
            {{ saving ? 'Menyimpan...' : 'Simpan Perubahan' }}
          </BaseButton>
        </template>
        
        <div v-if="loading.ptkp" class="text-center py-4 text-(--text-muted)">Memuat...</div>
        <div v-else class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-(--border-soft) text-left text-(--text-muted)">
                <th class="py-2 px-3 font-medium">Status</th>
                <th class="py-2 px-3 font-medium">Keterangan</th>
                <th class="py-2 px-3 font-medium w-1/3">Nilai (Rp)</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="rate in ptkpRates" :key="rate.id" class="border-b border-(--border-soft)">
                <td class="py-2 px-3 font-medium text-(--text-main)">{{ rate.status_code }}</td>
                <td class="py-2 px-3 text-(--text-muted)">{{ rate.status_name }}</td>
                <td class="py-2 px-3">
                  <input
                    v-model="rate.rate"
                    type="number"
                    class="w-full px-3 py-1.5 bg-(--bg-elevated) border border-(--border-soft) rounded-md text-(--text-main) focus:ring-1 focus:ring-(--primary)"
                  />
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </BaseCard>

      <!-- TER -->
      <BaseCard v-if="activeTab === 'ter'">
        <template #title>Tarif Efektif Rata-rata (TER) Bulanan</template>
        <template #subtitle>Daftar lapisan tarif TER Kategori A, B, dan C</template>
        <template #actions>
          <BaseButton variant="primary" size="sm" @click="saveTer" :disabled="saving">
            {{ saving ? 'Menyimpan...' : 'Simpan Perubahan' }}
          </BaseButton>
        </template>
        
        <div v-if="loading.ter" class="text-center py-4 text-(--text-muted)">Memuat...</div>
        <div v-else class="space-y-6">
          <div v-for="category in ['A', 'B', 'C']" :key="category">
            <h3 class="font-semibold text-(--text-main) mb-3">Kategori TER {{ category }}</h3>
            <div class="overflow-x-auto">
              <table class="w-full text-sm">
                <thead>
                  <tr class="border-b border-(--border-soft) text-left text-(--text-muted)">
                    <th class="py-2 px-3 font-medium">Batas Bawah (Rp)</th>
                    <th class="py-2 px-3 font-medium">Batas Atas (Rp)</th>
                    <th class="py-2 px-3 font-medium w-1/4">Tarif (%)</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="rate in terRates.filter(r => r.category === category)" :key="rate.id" class="border-b border-(--border-soft)">
                    <td class="py-2 px-3">
                      <input v-model="rate.min_income" type="number" class="w-full px-3 py-1 bg-(--bg-elevated) border border-(--border-soft) rounded-md" />
                    </td>
                    <td class="py-2 px-3">
                      <input v-model="rate.max_income" type="number" placeholder="Tak terhingga" class="w-full px-3 py-1 bg-(--bg-elevated) border border-(--border-soft) rounded-md" />
                    </td>
                    <td class="py-2 px-3">
                      <input v-model="rate.rate" type="number" step="0.01" class="w-full px-3 py-1 bg-(--bg-elevated) border border-(--border-soft) rounded-md" />
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </BaseCard>

      <!-- Progressive -->
      <BaseCard v-if="activeTab === 'progressive'">
        <template #title>Tarif Pajak Progresif</template>
        <template #subtitle>Berdasarkan Pasal 17 UU HPP</template>
        <template #actions>
          <BaseButton variant="primary" size="sm" @click="saveProgressive" :disabled="saving">
            {{ saving ? 'Menyimpan...' : 'Simpan Perubahan' }}
          </BaseButton>
        </template>
        
        <div v-if="loading.progressive" class="text-center py-4 text-(--text-muted)">Memuat...</div>
        <div v-else class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-(--border-soft) text-left text-(--text-muted)">
                <th class="py-2 px-3 font-medium w-16">Lapisan</th>
                <th class="py-2 px-3 font-medium">Batas Bawah (Rp)</th>
                <th class="py-2 px-3 font-medium">Batas Atas (Rp)</th>
                <th class="py-2 px-3 font-medium w-1/4">Tarif (%)</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(rate, idx) in progressiveRates" :key="rate.id" class="border-b border-(--border-soft)">
                <td class="py-2 px-3 font-medium text-center">{{ idx + 1 }}</td>
                <td class="py-2 px-3">
                  <input v-model="rate.min_income" type="number" class="w-full px-3 py-1 bg-(--bg-elevated) border border-(--border-soft) rounded-md" />
                </td>
                <td class="py-2 px-3">
                  <input v-model="rate.max_income" type="number" placeholder="Tak terhingga" class="w-full px-3 py-1 bg-(--bg-elevated) border border-(--border-soft) rounded-md" />
                </td>
                <td class="py-2 px-3">
                  <input v-model="rate.rate" type="number" step="1" class="w-full px-3 py-1 bg-(--bg-elevated) border border-(--border-soft) rounded-md" />
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </BaseCard>

      <!-- Data PPh -->
      <BaseCard v-if="activeTab === 'data'">
        <template #title>PPH 21 Bulanan</template>
        <template #subtitle>Generate data PPh per periode dari sumber data (salary, BPJS, kehadiran)</template>
        <template #actions>
          <BaseButton variant="primary" size="sm" @click="generatePph()" :disabled="generating || !pphPeriods.length">
            <i class="bx bx-cog mr-1"></i>
            {{ generating ? 'Generating...' : 'Generate PPh' }}
          </BaseButton>
          <BaseButton v-if="pphRecords.length > 0" variant="outline" size="sm" @click="regeneratePph" :disabled="generating">
            <i class="bx bx-refresh mr-1"></i> Regenerate (Hapus & Buat Ulang)
          </BaseButton>
        </template>

        <!-- Period selector -->
        <div class="mb-4 flex flex-wrap gap-3 items-end">
          <div class="min-w-[200px]">
            <label class="block text-xs text-(--text-muted) mb-1 font-medium">Periode</label>
            <select v-model="pphPeriodId" @change="fetchPphData" class="w-full px-3 py-2 bg-(--bg-elevated) border border-(--border-soft) rounded-md text-(--text-main) text-sm">
              <option value="">-- Pilih Periode --</option>
              <option v-for="p in pphPeriods" :key="p.id" :value="p.id">{{ p.name }}</option>
            </select>
          </div>
          <BaseButton variant="outline" size="sm" @click="fetchPphData" :disabled="!pphPeriodId">
            <i class="bx bx-refresh mr-1"></i> Refresh
          </BaseButton>
        </div>

        <div v-if="loading.data" class="text-center py-8 text-(--text-muted)">Memuat data...</div>

        <template v-else-if="pphRecords.length > 0">
          <!-- Stats mini -->
          <div class="grid grid-cols-2 md:grid-cols-5 gap-2 mb-4">
            <div class="bg-(--bg-elevated) rounded p-3 text-center">
              <div class="text-lg font-bold text-(--text-main)">{{ pphStats.total_karyawan }}</div>
              <div class="text-xs text-(--text-muted)">Karyawan</div>
            </div>
            <div class="bg-(--bg-elevated) rounded p-3 text-center">
              <div class="text-lg font-bold text-(--primary)">{{ fmtShort(pphStats.total_pph) }}</div>
              <div class="text-xs text-(--text-muted)">PPh Laporan</div>
            </div>
            <div class="bg-(--bg-elevated) rounded p-3 text-center">
              <div class="text-lg font-bold text-(--danger)">{{ fmtShort(pphStats.total_pph_deducted) }}</div>
              <div class="text-xs text-(--text-muted)">Potong Gaji</div>
            </div>
            <div class="bg-(--bg-elevated) rounded p-3 text-center">
              <div class="text-lg font-bold text-green-600">{{ pphStats.total_dtp }}</div>
              <div class="text-xs text-(--text-muted)">DTP</div>
            </div>
            <div class="bg-(--bg-elevated) rounded p-3 text-center">
              <div class="text-lg font-bold text-yellow-600">{{ pphStats.total_non_npwp }}</div>
              <div class="text-xs text-(--text-muted)">Non-NPWP</div>
            </div>
          </div>

          <!-- Table -->
          <div class="overflow-x-auto max-h-[55vh]">
            <table class="w-full text-sm">
              <thead class="bg-(--bg-elevated) sticky top-0 z-10">
                <tr class="text-xs text-(--text-muted)">
                  <th class="px-3 py-2 text-left">Karyawan</th>
                  <th class="px-2 py-2 text-right">Gaji Pokok</th>
                  <th class="px-2 py-2 text-right">Premi</th>
                  <th class="px-2 py-2 text-right">Tunjangan</th>
                  <th class="px-2 py-2 text-right">Lembur</th>
                  <th class="px-2 py-2 text-right">Gross</th>
                  <th class="px-2 py-2 text-right">Netto</th>
                  <th class="px-2 py-2 text-right">Tarif</th>
                  <th class="px-2 py-2 text-right">PPh</th>
                  <th class="px-2 py-2 text-center w-16">DTP</th>
                  <th class="px-2 py-2 text-center w-16">Aksi</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-(--border-soft)">
                <tr v-for="r in pphRecords" :key="r.id" class="hover:bg-(--bg-elevated)">
                  <td class="px-3 py-2">
                    <div class="font-medium text-(--text-main)">{{ r.employee_name }}</div>
                    <div class="text-xs text-(--text-muted)">{{ r.employee_code }} · {{ r.ptkp_status }}</div>
                  </td>
                  <td class="px-2 py-2 text-right">{{ fmtShort(r.gaji_pokok) }}</td>
                  <td class="px-2 py-2 text-right">{{ fmtShort(r.premi) }}</td>
                  <td class="px-2 py-2 text-right">{{ fmtShort(r.tunjangan) }}</td>
                  <td class="px-2 py-2 text-right">{{ fmtShort(r.lembur_bonus_thr) }}</td>
                  <td class="px-2 py-2 text-right font-medium">{{ fmtShort(r.gross_income) }}</td>
                  <td class="px-2 py-2 text-right">{{ fmtShort(r.netto_income) }}</td>
                  <td class="px-2 py-2 text-right text-(--text-muted)">{{ r.pph_rate }}%</td>
                  <td class="px-2 py-2 text-right font-semibold" :class="r.is_dtp ? 'text-green-600' : 'text-(--danger)'">
                    {{ fmtShort(r.pph_deducted) }}
                  </td>
                  <td class="px-2 py-2 text-center">
                    <span v-if="r.is_dtp" class="text-xs px-1.5 py-0.5 bg-green-100 text-green-700 rounded">DTP</span>
                    <span v-else class="text-xs text-(--text-muted)">-</span>
                  </td>
                  <td class="px-2 py-2 text-center">
                    <button @click="openPphEdit(r)" class="p-1.5 rounded text-(--text-muted) hover:text-(--primary) hover:bg-(--primary)/10" title="Edit">
                      <i class="bx bx-pencil"></i>
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </template>

        <div v-else-if="pphPeriodId" class="text-center py-12 text-(--text-muted)">
          <i class="bx bx-data text-4xl opacity-20 mb-3 block"></i>
          <p>Belum ada data PPh untuk periode ini.</p>
          <p class="text-sm mt-1">Klik <strong>Generate PPh</strong> untuk menghitung otomatis.</p>
        </div>
        <div v-else class="text-center py-12 text-(--text-muted)">
          <p>Pilih periode terlebih dahulu.</p>
        </div>
      </BaseCard>

      <!-- Edit PPh Modal -->
      <BaseModal :show="!!editPph" title="Edit Data PPh" @close="editPph = null" v-if="editPph">
        <div class="space-y-3 text-sm">
          <div class="bg-(--bg-elevated) p-3 rounded border border-(--border-soft)">
            <p class="font-medium">{{ editPph.employee_name }}</p>
            <p class="text-xs text-(--text-muted)">{{ editPph.employee_code }} · {{ editPph.ptkp_status }}</p>
          </div>
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs text-(--text-muted) mb-1">Gaji Pokok</label>
              <input v-model="editPphForm.gaji_pokok" type="number" class="w-full px-2 py-1.5 bg-(--bg-elevated) border border-(--border-soft) rounded text-sm" />
            </div>
            <div>
              <label class="block text-xs text-(--text-muted) mb-1">Tunjangan</label>
              <input v-model="editPphForm.tunjangan" type="number" class="w-full px-2 py-1.5 bg-(--bg-elevated) border border-(--border-soft) rounded text-sm" />
            </div>
            <div>
              <label class="block text-xs text-(--text-muted) mb-1">Premi</label>
              <input v-model="editPphForm.premi" type="number" class="w-full px-2 py-1.5 bg-(--bg-elevated) border border-(--border-soft) rounded text-sm" />
            </div>
            <div>
              <label class="block text-xs text-(--text-muted) mb-1">Lembur/Bonus/THR</label>
              <input v-model="editPphForm.lembur_bonus_thr" type="number" class="w-full px-2 py-1.5 bg-(--bg-elevated) border border-(--border-soft) rounded text-sm" />
            </div>
            <div>
              <label class="block text-xs text-(--text-muted) mb-1">Gross Income</label>
              <input v-model="editPphForm.gross_income" type="number" class="w-full px-2 py-1.5 bg-(--bg-elevated) border border-(--border-soft) rounded text-sm font-medium" />
            </div>
            <div>
              <label class="block text-xs text-(--text-muted) mb-1">Biaya Jabatan</label>
              <input v-model="editPphForm.biaya_jabatan" type="number" class="w-full px-2 py-1.5 bg-(--bg-elevated) border border-(--border-soft) rounded text-sm" />
            </div>
            <div>
              <label class="block text-xs text-(--text-muted) mb-1">JHT Karyawan</label>
              <input v-model="editPphForm.bpjs_jht_karyawan" type="number" class="w-full px-2 py-1.5 bg-(--bg-elevated) border border-(--border-soft) rounded text-sm" />
            </div>
            <div>
              <label class="block text-xs text-(--text-muted) mb-1">JP Karyawan</label>
              <input v-model="editPphForm.bpjs_jp_karyawan" type="number" class="w-full px-2 py-1.5 bg-(--bg-elevated) border border-(--border-soft) rounded text-sm" />
            </div>
            <div>
              <label class="block text-xs text-(--text-muted) mb-1">BPJS Kes Karyawan</label>
              <input v-model="editPphForm.bpjs_kes_karyawan" type="number" class="w-full px-2 py-1.5 bg-(--bg-elevated) border border-(--border-soft) rounded text-sm" />
            </div>
            <div>
              <label class="block text-xs text-(--text-muted) mb-1">Netto Income</label>
              <input v-model="editPphForm.netto_income" type="number" class="w-full px-2 py-1.5 bg-(--bg-elevated) border border-(--border-soft) rounded text-sm font-medium" />
            </div>
            <div>
              <label class="block text-xs text-(--text-muted) mb-1">Tarif PPh (%)</label>
              <input v-model="editPphForm.pph_rate" type="number" step="0.01" class="w-full px-2 py-1.5 bg-(--bg-elevated) border border-(--border-soft) rounded text-sm" />
            </div>
            <div>
              <label class="block text-xs text-(--text-muted) mb-1">PPh Amount</label>
              <input v-model="editPphForm.pph_amount" type="number" class="w-full px-2 py-1.5 bg-(--bg-elevated) border border-(--border-soft) rounded text-sm font-medium" />
            </div>
            <div>
              <label class="block text-xs text-(--text-muted) mb-1">PPh Dipotong</label>
              <input v-model="editPphForm.pph_deducted" type="number" class="w-full px-2 py-1.5 bg-(--bg-elevated) border border-(--border-soft) rounded text-sm font-medium" />
            </div>
          </div>
          <label class="flex items-center gap-2 pt-2 border-t border-(--border-soft)">
            <input type="checkbox" v-model="editPphForm.is_dtp" class="rounded" />
            <span class="font-medium text-sm">DTP (Ditanggung Pemerintah)</span>
          </label>
        </div>
        <template #footer>
          <BaseButton variant="ghost" @click="editPph = null">Batal</BaseButton>
          <BaseButton variant="danger" size="sm" @click="deletePph(editPph.id)">Hapus</BaseButton>
          <BaseButton variant="primary" @click="savePphEdit">Simpan</BaseButton>
        </template>
      </BaseModal>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import BaseCard from '../../../../Components/BaseCard.vue'
import BaseButton from '../../../../Components/BaseButton.vue'
import BaseModal from '../../../../Components/BaseModal.vue'
import SelectInput from '../../../../Components/SelectInput.vue'
import TextInput from '../../../../Components/TextInput.vue'
import { useApi } from '../../../../composables/useApi'
import { useNotification } from '../../../../composables/useNotification'

const api = useApi()
const notification = useNotification()

// Format helpers
function fmt(val) {
  if (!val && val !== 0) return '–'
  return 'Rp' + Number(val).toLocaleString('id-ID')
}
function fmtShort(val) {
  if (!val && val !== 0) return '–'
  const n = Number(val)
  if (n >= 1000000) return 'Rp' + (n / 1000000).toFixed(1) + 'jt'
  if (n >= 1000) return 'Rp' + (n / 1000).toFixed(0) + 'rb'
  return 'Rp' + n.toString()
}

const route = useRoute()

function getDefaultTab() {
  const map = {
    'pph.ter': 'ter',
    'pph.tahunan': 'progressive',
    'pph.bulanan': 'data',
  }
  return map[route.name] || 'general'
}

const activeTab = ref(getDefaultTab())
const tabs = [
  { key: 'general', label: 'Konfigurasi Umum' },
  { key: 'ptkp', label: 'Tarif PTKP' },
  { key: 'ter', label: 'TER Bulanan' },
  { key: 'progressive', label: 'Progresif Tahunan' },
]

const loading = reactive({
  general: true,
  ptkp: true,
  ter: true,
  progressive: true,
  data: false,
})
const saving = ref(false)

// Data
const pphConfig = reactive({
  calculation_method: 'ter',
  pph_method: 'gross',
  non_npwp_penalty: true,
  non_npwp_multiplier: 1.2,
  nik_as_npwp: true,
  is_dtp: false
})
const ptkpRates = ref([])
const terRates = ref([])
const progressiveRates = ref([])

onMounted(async () => {
  await Promise.all([
    fetchPphConfig(),
    fetchPtkp(),
    fetchTer(),
    fetchProgressive(),
    fetchPphPeriods(),
  ])
})

async function fetchPphConfig() {
  try {
    const res = await api.get('/api/v1/settings/payroll-configs/pph-config')
    if (res.data) {
      Object.assign(pphConfig, res.data)
      // MySQL boolean might come as 0/1
      pphConfig.non_npwp_penalty = Boolean(Number(pphConfig.non_npwp_penalty))
      pphConfig.nik_as_npwp = Boolean(Number(pphConfig.nik_as_npwp))
      pphConfig.is_dtp = Boolean(Number(pphConfig.is_dtp))
    }
  } catch (err) {
    console.error(err)
  } finally {
    loading.general = false
  }
}

async function fetchPtkp() {
  try {
    const res = await api.get('/api/v1/settings/payroll-configs/ptkp')
    ptkpRates.value = (res.data || []).map(r => ({...r, rate: r.value || r.rate}))
  } catch (err) {
    console.error(err)
  } finally {
    loading.ptkp = false
  }
}

async function fetchTer() {
  try {
    const res = await api.get('/api/v1/settings/payroll-configs/ter')
    terRates.value = res.data || []
  } catch (err) {
    console.error(err)
  } finally {
    loading.ter = false
  }
}

async function fetchProgressive() {
  try {
    const res = await api.get('/api/v1/settings/payroll-configs/progressive')
    progressiveRates.value = res.data || []
  } catch (err) {
    console.error(err)
  } finally {
    loading.progressive = false
  }
}

// Saves
async function savePphConfig() {
  saving.value = true
  try {
    await api.post('/api/v1/settings/payroll-configs/pph-config', pphConfig)
    notification.success('Pengaturan umum berhasil disimpan')
  } catch (err) {
    notification.error('Gagal menyimpan pengaturan umum')
  } finally {
    saving.value = false
  }
}

async function savePtkp() {
  saving.value = true
  try {
    const payload = ptkpRates.value.map(r => ({ id: r.id, rate: r.rate }))
    await api.post('/api/v1/settings/payroll-configs/ptkp', { rates: payload })
    notification.success('Tarif PTKP berhasil disimpan')
  } catch (err) {
    notification.error('Gagal menyimpan tarif PTKP')
  } finally {
    saving.value = false
  }
}

async function saveTer() {
  saving.value = true
  try {
    const payload = terRates.value.map(r => ({ id: r.id, min_income: r.min_income, max_income: r.max_income || null, rate: r.rate }))
    await api.post('/api/v1/settings/payroll-configs/ter', { rates: payload })
    notification.success('Tarif TER berhasil disimpan')
  } catch (err) {
    notification.error('Gagal menyimpan tarif TER')
  } finally {
    saving.value = false
  }
}

async function saveProgressive() {
  saving.value = true
  try {
    const payload = progressiveRates.value.map(r => ({ id: r.id, min_income: r.min_income, max_income: r.max_income || null, rate: r.rate }))
    await api.post('/api/v1/settings/payroll-configs/progressive', { rates: payload })
    notification.success('Tarif Progresif berhasil disimpan')
  } catch (err) {
    notification.error('Gagal menyimpan tarif progresif')
  } finally {
    saving.value = false
  }
}

// ─── Data PPh ────────────────────────────────────────────────

const pphPeriods = ref([])
const pphPeriodId = ref('')
const pphRecords = ref([])
const pphStats = ref({ total_karyawan: 0, total_pph: 0, total_pph_deducted: 0, total_dtp: 0, total_non_npwp: 0 })
const generating = ref(false)
const editPph = ref(null)
const editPphForm = reactive({
  gaji_pokok: 0, premi: 0, tunjangan: 0, lembur_bonus_thr: 0, gross_income: 0,
  biaya_jabatan: 0, bpjs_jht_karyawan: 0, bpjs_jp_karyawan: 0,
  bpjs_kes_karyawan: 0, netto_income: 0, pph_rate: 0,
  pph_amount: 0, pph_deducted: 0, is_dtp: false,
})

async function fetchPphPeriods() {
  try {
    const res = await api.get('/api/v1/payroll/periods')
    pphPeriods.value = res.data || []
  } catch (e) { console.error(e) }
}

async function fetchPphData() {
  if (!pphPeriodId.value) { pphRecords.value = []; return }
  loading.data = true
  try {
    const res = await api.get(`/api/v1/payroll/pph/data?period_id=${pphPeriodId.value}`)
    pphRecords.value = res.data || []
    pphStats.value = res.stats || { total_karyawan: 0, total_pph: 0, total_pph_deducted: 0, total_dtp: 0, total_non_npwp: 0 }
  } catch (e) {
    notification.error('Gagal memuat data PPh')
  } finally {
    loading.data = false
  }
}

async function generatePph(force = false) {
  if (!pphPeriodId.value) return
  generating.value = true
  try {
    const res = await api.post('/api/v1/payroll/pph/generate', { period_id: pphPeriodId.value, force })
    notification.success(res.message)
    await fetchPphData()
  } catch (e) {
    notification.error(e?.response?.data?.message || 'Gagal generate PPh')
  } finally {
    generating.value = false
  }
}

function regeneratePph() {
  if (confirm('Hapus semua data PPh periode ini dan generate ulang?')) {
    generatePph(true)
  }
}

function openPphEdit(r) {
  editPph.value = r
  Object.assign(editPphForm, {
    gaji_pokok: r.gaji_pokok, premi: r.premi, tunjangan: r.tunjangan, lembur_bonus_thr: r.lembur_bonus_thr,
    gross_income: r.gross_income, biaya_jabatan: r.biaya_jabatan,
    bpjs_jht_karyawan: r.bpjs_jht_karyawan, bpjs_jp_karyawan: r.bpjs_jp_karyawan,
    bpjs_kes_karyawan: r.bpjs_kes_karyawan, netto_income: r.netto_income,
    pph_rate: r.pph_rate, pph_amount: r.pph_amount, pph_deducted: r.pph_deducted,
    is_dtp: r.is_dtp,
  })
}

async function savePphEdit() {
  if (!editPph.value) return
  saving.value = true
  try {
    const res = await api.put(`/api/v1/payroll/pph/data/${editPph.value.id}`, editPphForm)
    notification.success(res.message)
    editPph.value = null
    await fetchPphData()
  } catch (e) {
    notification.error('Gagal menyimpan')
  } finally {
    saving.value = false
  }
}

async function deletePph(id) {
  if (!confirm('Hapus data PPh ini?')) return
  try {
    await api.delete(`/api/v1/payroll/pph/data/${id}`)
    notification.success('Data PPh dihapus')
    editPph.value = null
    await fetchPphData()
  } catch (e) {
    notification.error('Gagal menghapus')
  }
}

</script>
