<template>
  <div>
    <div class="mb-6">
      <h1 class="text-xl font-semibold text-(--text-main)">Pengelolaan PPh 21</h1>
      <p class="text-sm text-(--text-muted) mt-1">Konfigurasi Pengaturan Pajak, PTKP, TER, dan Tarif Progresif</p>
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
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import BaseCard from '../../../../Components/BaseCard.vue'
import BaseButton from '../../../../Components/BaseButton.vue'
import SelectInput from '../../../../Components/SelectInput.vue'
import TextInput from '../../../../Components/TextInput.vue'
import { useApi } from '../../../../composables/useApi'
import { useNotification } from '../../../../composables/useNotification'

const api = useApi()
const notification = useNotification()

const activeTab = ref('general')
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
})
const saving = ref(false)

// Data
const pphConfig = reactive({
  calculation_method: 'ter',
  pph_method: 'gross',
  non_npwp_penalty: true,
  non_npwp_multiplier: 1.2
})
const ptkpRates = ref([])
const terRates = ref([])
const progressiveRates = ref([])

onMounted(async () => {
  await Promise.all([
    fetchPphConfig(),
    fetchPtkp(),
    fetchTer(),
    fetchProgressive()
  ])
})

async function fetchPphConfig() {
  try {
    const res = await api.get('/api/v1/settings/payroll-configs/pph-config')
    if (res.data) {
      Object.assign(pphConfig, res.data)
      // MySQL boolean might come as 0/1
      pphConfig.non_npwp_penalty = Boolean(Number(pphConfig.non_npwp_penalty))
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
</script>
