<script setup>
import { ref, onMounted } from 'vue'
import BaseButton from '../../../../Components/BaseButton.vue'
import BaseCard from '../../../../Components/BaseCard.vue'
import TextInput from '../../../../Components/TextInput.vue'
import SelectInput from '../../../../Components/SelectInput.vue'
import { useApi } from '../../../../composables/useApi'

const { get, post } = useApi()

const form = ref({
  cut_off_date: '',
  working_day_type: 'fixed',
  fixed_working_day: 21,
})
const loading = ref(false)

async function fetchSettings() {
  try {
    const data = await get('/api/v1/settings/payroll')
    if (data.data) {
      form.value.cut_off_date = data.data.cut_off_date
      form.value.working_day_type = data.data.working_day_type || 'fixed'
      form.value.fixed_working_day = data.data.fixed_working_day
    }
  } catch (e) {
    console.error('Failed to load settings', e)
  }
}

async function saveSettings() {
  loading.value = true
  try {
    await post('/api/v1/settings/payroll', form.value)
    // You can add success notification here
  } catch (e) {
    console.error('Failed to save settings', e)
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  fetchSettings()
})

</script>

<template>
  <div class="space-y-6">
    <div>
      <h2 class="text-lg font-medium text-(--text-main)">Pengaturan Penggajian & Pajak</h2>
      <p class="text-sm text-(--text-muted)">Atur komponen dasar untuk perhitungan gaji, grade, dan pajak penghasilan.</p>
    </div>

    <!-- Form Konfigurasi Dasar -->
    <BaseCard>
      <template #title>Konfigurasi Dasar Penggajian</template>
      <div class="space-y-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
          <TextInput 
            v-model="form.cut_off_date" 
            label="Tanggal Cut Off (Misal: 25)" 
            type="number" 
            min="1" max="31" 
          />
          <SelectInput 
            v-model="form.working_day_type" 
            label="Tipe Hari Kerja"
            :options="[
              { value: 'fixed', label: 'Fixed (Tetap)' },
              { value: 'calendar', label: 'Kalender (Senin-Jumat)' },
              { value: 'flexible', label: 'Fleksibel' }
            ]"
          />
          <TextInput 
            v-if="form.working_day_type === 'fixed'"
            v-model="form.fixed_working_day" 
            label="Jumlah Hari Kerja Tetap" 
            type="number" 
          />
        </div>
        <div class="flex justify-end">
          <BaseButton variant="primary" @click="saveSettings" :disabled="loading">Simpan Pengaturan</BaseButton>
        </div>
      </div>
    </BaseCard>

    <!-- Master Data Enums / Tables -->
    <BaseCard>
      <template #title>Data Master Penggajian (Tabel Referensi)</template>
      <div class="space-y-4">
        <p class="text-sm text-(--text-muted)">Komponen penggajian bersifat sangat dinamis per instance/cabang. Oleh karena itu, pengaturan ini dipisahkan menjadi tabel data master (bukan hardcoded).</p>
        
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div class="p-4 border border-(--border-soft) rounded-md bg-(--bg-main) flex items-center justify-between">
            <div>
              <h4 class="text-sm font-medium text-(--text-main)">Grade Gaji (Salary Grades)</h4>
              <p class="text-xs text-(--text-muted)">Level gaji, batas bawah, dan batas atas.</p>
            </div>
            <BaseButton variant="ghost" size="sm" icon="bx bx-right-arrow-alt" @click="$router.push('/admin/organization/salary-grades')">Ke Menu</BaseButton>
          </div>
          
          <div class="p-4 border border-(--border-soft) rounded-md bg-(--bg-main) flex items-center justify-between">
            <div>
              <h4 class="text-sm font-medium text-(--text-main)">Komponen Gaji</h4>
              <p class="text-xs text-(--text-muted)">Tunjangan jabatan, makan, transport, dll.</p>
            </div>
            <BaseButton variant="ghost" size="sm" icon="bx bx-right-arrow-alt" @click="$router.push('/admin/payroll/configs')">Ke Menu</BaseButton>
          </div>

          <div class="p-4 border border-(--border-soft) rounded-md bg-(--bg-main) flex items-center justify-between">
            <div>
              <h4 class="text-sm font-medium text-(--text-main)">Konfigurasi PPh 21 & PTKP</h4>
              <p class="text-xs text-(--text-muted)">Tarif progresif, TER, dan batas PTKP.</p>
            </div>
            <BaseButton variant="ghost" size="sm" icon="bx bx-right-arrow-alt" @click="$router.push('/admin/payroll/configs')">Ke Menu</BaseButton>
          </div>

          <div class="p-4 border border-(--border-soft) rounded-md bg-(--bg-main) flex items-center justify-between">
            <div>
              <h4 class="text-sm font-medium text-(--text-main)">Konfigurasi BPJS</h4>
              <p class="text-xs text-(--text-muted)">Persentase potong perusahaan & karyawan.</p>
            </div>
            <BaseButton variant="ghost" size="sm" icon="bx bx-right-arrow-alt" @click="$router.push('/admin/payroll/configs')">Ke Menu</BaseButton>
          </div>
        </div>
      </div>
    </BaseCard>
  </div>
</template>
