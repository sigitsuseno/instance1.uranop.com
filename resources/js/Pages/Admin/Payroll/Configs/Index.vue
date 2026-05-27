<template>
  <div>
    <div class="mb-6">
      <h1 class="text-xl font-semibold text-(--text-main)">Konfigurasi Penggajian</h1>
      <p class="text-sm text-(--text-muted) mt-1">Atur komponen gaji, BPJS, PPh21, dan aturan lembur</p>
    </div>

    <div class="border-b border-(--border-soft) mb-6">
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

    <div v-if="activeTab === 'components'" class="space-y-6">
      <BaseCard>
        <template #title>Daftar Komponen Gaji</template>
        <template #actions>
          <BaseButton variant="primary" size="sm" @click="openComponentForm(null)">
            <template #icon-left>
              <IconPlus class="w-4 h-4" />
            </template>
            Tambah Komponen
          </BaseButton>
        </template>

        <DataTable :headers="componentHeaders" :items="salaryComponents">
          <template #item.type="{ value }">
            <Badge :variant="value === 'Pendapatan' ? 'success' : 'danger'">{{ value }}</Badge>
          </template>
          <template #item.is_taxable="{ value }">
            <span :class="value ? 'text-(--success)' : 'text-(--text-muted)'">{{ value ? 'Ya' : 'Tidak' }}</span>
          </template>
          <template #item.is_active="{ value }">
            <Badge :variant="value ? 'success' : 'neutral'">{{ value ? 'Aktif' : 'Nonaktif' }}</Badge>
          </template>
          <template #item.actions="{ item }">
            <button
              class="p-1.5 rounded-md text-(--text-muted) hover:text-(--primary) hover:bg-(--primary)/10 transition-colors"
              @click="openComponentForm(item)"
            >
              <IconPencil class="w-4 h-4" />
            </button>
          </template>
        </DataTable>
      </BaseCard>
    </div>

    <div v-if="activeTab === 'bpjs'" class="space-y-6">
      <BaseCard>
        <template #title>Konfigurasi BPJS Kesehatan</template>
        <template #subtitle>Iuran yang ditanggung perusahaan dan karyawan</template>
        <div class="overflow-x-auto">
          <table class="w-full border-collapse">
            <thead>
              <tr class="bg-(--bg-elevated)">
                <th class="px-4 py-3 text-left text-xs font-semibold text-(--text-muted) uppercase tracking-wider">Komponen</th>
                <th class="px-4 py-3 text-right text-xs font-semibold text-(--text-muted) uppercase tracking-wider">Perusahaan</th>
                <th class="px-4 py-3 text-right text-xs font-semibold text-(--text-muted) uppercase tracking-wider">Karyawan</th>
                <th class="px-4 py-3 text-right text-xs font-semibold text-(--text-muted) uppercase tracking-wider">Total</th>
              </tr>
            </thead>
            <tbody>
              <tr class="border-b border-(--border-soft)">
                <td class="px-4 py-3 text-sm text-(--text-main)">Jaminan Kesehatan</td>
                <td class="px-4 py-3 text-sm text-(--text-main) text-right">{{ bpjsKesehatan.company }}%</td>
                <td class="px-4 py-3 text-sm text-(--text-main) text-right">{{ bpjsKesehatan.employee }}%</td>
                <td class="px-4 py-3 text-sm font-semibold text-(--text-main) text-right">{{ bpjsKesehatan.company + bpjsKesehatan.employee }}%</td>
              </tr>
            </tbody>
          </table>
        </div>
        <template #footer>
          <BaseButton variant="primary" size="sm" @click="editingBPJS = !editingBPJS">
            <template #icon-left>
              <IconPencil class="w-4 h-4" />
            </template>
            {{ editingBPJS ? 'Simpan' : 'Edit' }}
          </BaseButton>
        </template>
      </BaseCard>

      <BaseCard>
        <template #title>Konfigurasi BPJS Ketenagakerjaan</template>
        <template #subtitle>JKK, JKM, JHT, dan JP</template>
        <div class="overflow-x-auto">
          <table class="w-full border-collapse">
            <thead>
              <tr class="bg-(--bg-elevated)">
                <th class="px-4 py-3 text-left text-xs font-semibold text-(--text-muted) uppercase tracking-wider">Komponen</th>
                <th class="px-4 py-3 text-right text-xs font-semibold text-(--text-muted) uppercase tracking-wider">Perusahaan</th>
                <th class="px-4 py-3 text-right text-xs font-semibold text-(--text-muted) uppercase tracking-wider">Karyawan</th>
                <th class="px-4 py-3 text-right text-xs font-semibold text-(--text-muted) uppercase tracking-wider">Total</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in bpjsTK" :key="item.name" class="border-b border-(--border-soft)">
                <td class="px-4 py-3 text-sm text-(--text-main)">{{ item.name }}</td>
                <td class="px-4 py-3 text-sm text-(--text-main) text-right">{{ item.company }}%</td>
                <td class="px-4 py-3 text-sm text-(--text-main) text-right">{{ item.employee }}%</td>
                <td class="px-4 py-3 text-sm font-semibold text-(--text-main) text-right">{{ item.company + item.employee }}%</td>
              </tr>
            </tbody>
          </table>
        </div>
        <template #footer>
          <BaseButton variant="primary" size="sm" @click="editingBPJSTK = !editingBPJSTK">
            <template #icon-left>
              <IconPencil class="w-4 h-4" />
            </template>
            {{ editingBPJSTK ? 'Simpan' : 'Edit' }}
          </BaseButton>
        </template>
      </BaseCard>
    </div>

    <div v-if="activeTab === 'pph21'" class="space-y-6">
      <BaseCard>
        <template #title>Konfigurasi PPh 21</template>
        <div class="space-y-4">
          <div class="bg-(--bg-elevated) rounded-md p-4">
            <h4 class="text-sm font-semibold text-(--text-main) mb-3">Tarif PTKP (Penghasilan Tidak Kena Pajak)</h4>
            <div class="overflow-x-auto">
              <table class="w-full border-collapse text-sm">
                <thead>
                  <tr class="border-b border-(--border-soft)">
                    <th class="py-2 text-left text-(--text-muted) font-medium">Kategori</th>
                    <th class="py-2 text-right text-(--text-muted) font-medium">Tarif per Tahun</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="ptkp in ptkpRates" :key="ptkp.category" class="border-b border-(--border-soft)">
                    <td class="py-2 text-(--text-main)">{{ ptkp.category }}</td>
                    <td class="py-2 text-right font-medium text-(--text-main)">Rp {{ ptkp.rate.toLocaleString('id-ID') }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <div class="bg-(--bg-elevated) rounded-md p-4">
            <h4 class="text-sm font-semibold text-(--text-main) mb-3">Kategori TER</h4>
            <div class="grid grid-cols-3 gap-3">
              <div v-for="ter in terCategories" :key="ter.category" class="rounded-md border border-(--border-soft) p-3">
                <p class="text-sm font-semibold text-(--text-main)">{{ ter.category }}</p>
                <p class="text-xs text-(--text-muted) mt-1">{{ ter.description }}</p>
                <p class="text-xs text-(--primary) mt-1">Tarif: {{ ter.rate }}%</p>
              </div>
            </div>
          </div>

          <div class="bg-(--bg-elevated) rounded-md p-4">
            <h4 class="text-sm font-semibold text-(--text-main) mb-3">Tarif Progresif</h4>
            <div class="overflow-x-auto">
              <table class="w-full border-collapse text-sm">
                <thead>
                  <tr class="border-b border-(--border-soft)">
                    <th class="py-2 text-left text-(--text-muted) font-medium">Lapisan</th>
                    <th class="py-2 text-left text-(--text-muted) font-medium">Rentang Penghasilan</th>
                    <th class="py-2 text-right text-(--text-muted) font-medium">Tarif</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="rate in progressiveRates" :key="rate.layer" class="border-b border-(--border-soft)">
                    <td class="py-2 text-(--text-main)">{{ rate.layer }}</td>
                    <td class="py-2 text-(--text-main)">{{ rate.range }}</td>
                    <td class="py-2 text-right font-medium text-(--text-main)">{{ rate.rate }}%</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
        <template #footer>
          <BaseButton variant="primary" size="sm" @click="editingPPH = !editingPPH">
            <template #icon-left>
              <IconPencil class="w-4 h-4" />
            </template>
            {{ editingPPH ? 'Simpan' : 'Edit' }}
          </BaseButton>
        </template>
      </BaseCard>
    </div>

    <div v-if="activeTab === 'overtime'" class="space-y-6">
      <BaseCard>
        <template #title>Aturan Lembur</template>
        <template #subtitle>Pengali upah lembur berdasarkan jam kerja</template>
        <div class="space-y-4">
          <div class="overflow-x-auto">
            <table class="w-full border-collapse">
              <thead>
                <tr class="bg-(--bg-elevated)">
                  <th class="px-4 py-3 text-left text-xs font-semibold text-(--text-muted) uppercase tracking-wider">Jam Ke</th>
                  <th class="px-4 py-3 text-left text-xs font-semibold text-(--text-muted) uppercase tracking-wider">Pengali</th>
                  <th class="px-4 py-3 text-left text-xs font-semibold text-(--text-muted) uppercase tracking-wider">Rumus</th>
                  <th class="px-4 py-3 text-left text-xs font-semibold text-(--text-muted) uppercase tracking-wider">Keterangan</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="rule in overtimeRules" :key="rule.hour" class="border-b border-(--border-soft)">
                  <td class="px-4 py-3 text-sm text-(--text-main)">{{ rule.hour }}</td>
                  <td class="px-4 py-3 text-sm">
                    <Badge variant="primary">{{ rule.multiplier }}x</Badge>
                  </td>
                  <td class="px-4 py-3 text-sm font-mono text-(--text-muted)">{{ rule.formula }}</td>
                  <td class="px-4 py-3 text-sm text-(--text-muted)">{{ rule.description }}</td>
                </tr>
              </tbody>
            </table>
          </div>
          <div class="bg-(--bg-elevated) rounded-md p-4">
            <h4 class="text-sm font-semibold text-(--text-main) mb-2">Ketentuan Umum</h4>
            <ul class="text-sm text-(--text-muted) space-y-1 list-disc list-inside">
              <li>Upah lembur dihitung berdasarkan 1/173 x Gaji Pokok per jam</li>
              <li>Lembur hari kerja: jam pertama 1.5x, jam berikutnya 2x</li>
              <li>Lembur hari libur: 2x untuk 8 jam pertama, 3x jam ke-9, 4x jam ke-10 dan ke-11</li>
              <li>Maksimal jam lembur: 4 jam per hari dan 18 jam per minggu</li>
            </ul>
          </div>
        </div>
        <template #footer>
          <BaseButton variant="primary" size="sm" @click="editingOvertime = !editingOvertime">
            <template #icon-left>
              <IconPencil class="w-4 h-4" />
            </template>
            {{ editingOvertime ? 'Simpan' : 'Edit' }}
          </BaseButton>
        </template>
      </BaseCard>
    </div>

    <BaseModal :show="!!componentForm" :title="editingComponent?.id ? 'Edit Komponen Gaji' : 'Tambah Komponen Gaji'" @close="componentForm = null">
      <div class="space-y-4">
        <TextInput v-model="componentFormData.code" label="Kode" placeholder="Contoh: BASIC_SALARY" />
        <TextInput v-model="componentFormData.name" label="Nama" placeholder="Contoh: Gaji Pokok" />
        <SelectInput v-model="componentFormData.type" label="Tipe" :options="[{ value: 'Pendapatan', label: 'Pendapatan' }, { value: 'Potongan', label: 'Potongan' }]" />
        <label class="flex items-center gap-2 text-sm text-(--text-main)">
          <input type="checkbox" v-model="componentFormData.is_taxable" class="rounded border-(--border-soft)" />
          Komponen Kena Pajak
        </label>
        <label class="flex items-center gap-2 text-sm text-(--text-main)">
          <input type="checkbox" v-model="componentFormData.is_active" class="rounded border-(--border-soft)" />
          Aktif
        </label>
      </div>
      <template #footer>
        <BaseButton variant="ghost" @click="componentForm = null">Batal</BaseButton>
        <BaseButton variant="primary" @click="saveComponent">Simpan</BaseButton>
      </template>
    </BaseModal>
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue'
import DataTable from '../../../../Components/Table/DataTable.vue'
import BaseButton from '../../../../Components/BaseButton.vue'
import BaseCard from '../../../../Components/BaseCard.vue'
import BaseModal from '../../../../Components/BaseModal.vue'
import Badge from '../../../../Components/Badge.vue'
import TextInput from '../../../../Components/TextInput.vue'
import SelectInput from '../../../../Components/SelectInput.vue'
import { IconPlus, IconPencil } from '../../../../Components/Icons/index.js'

const activeTab = ref('components')

const tabs = [
  { key: 'components', label: 'Komponen Gaji' },
  { key: 'bpjs', label: 'BPJS' },
  { key: 'pph21', label: 'PPh 21' },
  { key: 'overtime', label: 'Lembur' },
]

const editingBPJS = ref(false)
const editingBPJSTK = ref(false)
const editingPPH = ref(false)
const editingOvertime = ref(false)

const componentHeaders = [
  { key: 'code', label: 'Kode' },
  { key: 'name', label: 'Nama' },
  { key: 'type', label: 'Tipe' },
  { key: 'is_taxable', label: 'Kena Pajak' },
  { key: 'is_active', label: 'Status' },
  { key: 'actions', label: 'Aksi', sortable: false, width: '80px' },
]

const salaryComponents = ref([
  { id: 1, code: 'BASIC_SALARY', name: 'Gaji Pokok', type: 'Pendapatan', is_taxable: true, is_active: true },
  { id: 2, code: 'FIXED_ALLOWANCE', name: 'Tunjangan Tetap', type: 'Pendapatan', is_taxable: true, is_active: true },
  { id: 3, code: 'MEAL_ALLOWANCE', name: 'Tunjangan Makan', type: 'Pendapatan', is_taxable: false, is_active: true },
  { id: 4, code: 'TRANSPORT_ALLOWANCE', name: 'Tunjangan Transport', type: 'Pendapatan', is_taxable: false, is_active: true },
  { id: 5, code: 'OVERTIME_PAY', name: 'Upah Lembur', type: 'Pendapatan', is_taxable: true, is_active: true },
  { id: 6, code: 'BPJS_HEALTH', name: 'BPJS Kesehatan', type: 'Potongan', is_taxable: false, is_active: true },
  { id: 7, code: 'BPJS_TK', name: 'BPJS Ketenagakerjaan', type: 'Potongan', is_taxable: false, is_active: true },
  { id: 8, code: 'PPH21', name: 'PPh 21', type: 'Potongan', is_taxable: false, is_active: true },
])

const bpjsKesehatan = reactive({ company: 4, employee: 1 })
const bpjsTK = ref([
  { name: 'JKK (Jaminan Kecelakaan Kerja)', company: 0.24, employee: 0 },
  { name: 'JKM (Jaminan Kematian)', company: 0.30, employee: 0 },
  { name: 'JHT (Jaminan Hari Tua)', company: 3.70, employee: 2 },
  { name: 'JP (Jaminan Pensiun)', company: 2, employee: 1 },
])

const ptkpRates = ref([
  { category: 'TK/0 (Tidak Kawin, Tanpa Tanggungan)', rate: 54000000 },
  { category: 'TK/1 (Tidak Kawin, 1 Tanggungan)', rate: 58500000 },
  { category: 'K/0 (Kawin, Tanpa Tanggungan)', rate: 58500000 },
  { category: 'K/1 (Kawin, 1 Tanggungan)', rate: 63000000 },
  { category: 'K/2 (Kawin, 2 Tanggungan)', rate: 67500000 },
  { category: 'K/3 (Kawin, 3 Tanggungan)', rate: 72000000 },
])

const terCategories = ref([
  { category: 'TER A', description: 'TK/0, TK/1, K/0', rate: '0 - 34' },
  { category: 'TER B', description: 'TK/2, TK/3, K/1, K/2', rate: '0 - 30' },
  { category: 'TER C', description: 'K/3', rate: '0 - 26' },
])

const progressiveRates = ref([
  { layer: 'Lapisan 1', range: '0 - Rp 60.000.000', rate: 5 },
  { layer: 'Lapisan 2', range: 'Rp 60.000.000 - Rp 250.000.000', rate: 15 },
  { layer: 'Lapisan 3', range: 'Rp 250.000.000 - Rp 500.000.000', rate: 25 },
  { layer: 'Lapisan 4', range: 'Rp 500.000.000 - Rp 5.000.000.000', rate: 30 },
  { layer: 'Lapisan 5', range: '> Rp 5.000.000.000', rate: 35 },
])

const overtimeRules = ref([
  { hour: 'Jam ke-1', multiplier: '1.5', formula: '1.5 x Upah per Jam', description: 'Jam pertama lembur di hari kerja' },
  { hour: 'Jam ke-2 dst', multiplier: '2', formula: '2 x Upah per Jam', description: 'Jam kedua dan selanjutnya di hari kerja' },
  { hour: 'Jam ke-1 s/d 8', multiplier: '2', formula: '2 x Upah per Jam', description: '8 jam pertama di hari libur' },
  { hour: 'Jam ke-9', multiplier: '3', formula: '3 x Upah per Jam', description: 'Jam ke-9 di hari libur' },
  { hour: 'Jam ke-10 s/d 11', multiplier: '4', formula: '4 x Upah per Jam', description: 'Jam ke-10 dan ke-11 di hari libur' },
])

const componentForm = ref(null)
const editingComponent = ref(null)
const componentFormData = reactive({ code: '', name: '', type: 'Pendapatan', is_taxable: false, is_active: true })

function openComponentForm(item) {
  editingComponent.value = item
  if (item) {
    componentFormData.code = item.code
    componentFormData.name = item.name
    componentFormData.type = item.type
    componentFormData.is_taxable = item.is_taxable
    componentFormData.is_active = item.is_active
  } else {
    componentFormData.code = ''
    componentFormData.name = ''
    componentFormData.type = 'Pendapatan'
    componentFormData.is_taxable = false
    componentFormData.is_active = true
  }
  componentForm.value = {}
}

function saveComponent() {
  const item = {
    id: editingComponent.value?.id || salaryComponents.value.length + 1,
    code: componentFormData.code,
    name: componentFormData.name,
    type: componentFormData.type,
    is_taxable: componentFormData.is_taxable,
    is_active: componentFormData.is_active,
  }
  if (editingComponent.value?.id) {
    const idx = salaryComponents.value.findIndex((c) => c.id === editingComponent.value.id)
    if (idx !== -1) salaryComponents.value[idx] = item
  } else {
    salaryComponents.value.push(item)
  }
  componentForm.value = null
  editingComponent.value = null
}
</script>
