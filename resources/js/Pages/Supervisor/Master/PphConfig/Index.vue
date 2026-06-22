<template>
  <div>
    <div class="mb-6">
      <h1 class="text-2xl font-bold text-(--text-main)">Konfigurasi Pajak PPh 21 (Supervisor)</h1>
      <p class="text-sm text-(--text-muted) mt-1">
        Aturan perhitungan PPh21 (TER/Tahunan) bayangan untuk supervisor
      </p>
    </div>

    <BaseCard>
      <template #title>Pengaturan Umum PPh 21</template>
      <template #subtitle>Metode perhitungan pajak dan penalti Non-NPWP</template>
      
      <div v-if="loading" class="text-center py-8 text-(--text-muted)">Memuat data...</div>
      <div v-else class="space-y-4 max-w-2xl mt-4">
        <div>
          <label class="block text-xs mb-1">Metode Perhitungan</label>
          <select v-model="pphConfig.calculation_method" class="w-full bg-(--bg-input) border border-(--border-soft) rounded-lg px-3 py-2 text-sm">
            <option value="ter">Tarif Efektif Rata-rata (TER)</option>
            <option value="progressive">Tarif Progresif (Pasal 17)</option>
          </select>
        </div>

        <div>
          <label class="block text-xs mb-1">Metode PPh (Tunjangan/Potongan)</label>
          <select v-model="pphConfig.pph_method" class="w-full bg-(--bg-input) border border-(--border-soft) rounded-lg px-3 py-2 text-sm">
            <option value="gross">Gross (Pajak Ditanggung Karyawan)</option>
            <option value="gross_up">Gross Up (Pajak Ditanggung Perusahaan)</option>
            <option value="net">Net (Pajak Ditanggung Perusahaan tanpa Tunjangan)</option>
          </select>
        </div>
        
        <div class="pt-4 border-t border-(--border-soft)">
          <label class="flex items-center gap-2 text-sm text-(--text-main) font-medium mb-3">
            <input type="checkbox" v-model="pphConfig.non_npwp_penalty" class="rounded border-(--border-soft)" />
            Aktifkan Penalti Non-NPWP
          </label>
          <div v-if="pphConfig.non_npwp_penalty">
            <label class="block text-xs mb-1">Faktor Pengali Non-NPWP</label>
            <input v-model="pphConfig.non_npwp_multiplier" type="number" step="0.01" placeholder="Contoh: 1.2" class="w-full bg-(--bg-input) border border-(--border-soft) rounded-lg px-3 py-2 text-sm" />
          </div>
        </div>
        
        <div class="pt-6 flex justify-end">
          <BaseButton variant="primary" @click="savePphConfig" :loading="saving">
            {{ saving ? 'Menyimpan...' : 'Simpan Pengaturan' }}
          </BaseButton>
        </div>
      </div>
    </BaseCard>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import BaseCard from '@/Components/BaseCard.vue'
import BaseButton from '@/Components/BaseButton.vue'
import { useApi } from '@/composables/useApi'

const { get, post } = useApi()

const loading = ref(true)
const saving = ref(false)

const pphConfig = reactive({
  calculation_method: 'ter',
  pph_method: 'gross',
  non_npwp_penalty: true,
  non_npwp_multiplier: 1.2
})

onMounted(async () => {
  await fetchPphConfig()
})

async function fetchPphConfig() {
  try {
    const res = await get('/api/v1/supervisor/master/pph-configs')
    if (res.data) {
      Object.assign(pphConfig, res.data)
      pphConfig.non_npwp_penalty = Boolean(Number(pphConfig.non_npwp_penalty))
    }
  } catch (err) {
    console.error(err)
  } finally {
    loading.value = false
  }
}

async function savePphConfig() {
  saving.value = true
  try {
    await post('/api/v1/supervisor/master/pph-configs', pphConfig)
    alert('Pengaturan PPh berhasil disimpan')
  } catch (err) {
    alert('Gagal menyimpan pengaturan')
  } finally {
    saving.value = false
  }
}
</script>
