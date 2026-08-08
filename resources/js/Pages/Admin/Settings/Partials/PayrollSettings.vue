<script setup>
import { ref, onMounted } from 'vue'
import BaseButton from '../../../../Components/BaseButton.vue'
import BaseCard from '../../../../Components/BaseCard.vue'
import TextInput from '../../../../Components/TextInput.vue'
import SelectInput from '../../../../Components/SelectInput.vue'
import { useApi } from '../../../../composables/useApi'
import { useAuth } from '../../../../composables/useAuth'

const { get, post, put } = useApi()
const { isSuperadmin } = useAuth()

const form = ref({
  cut_off_date: '',
  working_day_type: 'fixed',
  fixed_working_day: 21,
  split_days_a: '',
})
const loading = ref(false)

// Password unlock payroll (KEPUTUSAN #10 logic_payroll_baru.md — superadmin only)
const lockPassword = ref('')
const hasLockPassword = ref(false)
const savingLockPassword = ref(false)
const lockPasswordMsg = ref('')

async function fetchSettings() {
  try {
    const data = await get('/api/v1/settings/payroll')
    if (data.data) {
      form.value.cut_off_date = data.data.cut_off_date
      form.value.working_day_type = data.data.working_day_type || 'fixed'
      form.value.fixed_working_day = data.data.fixed_working_day
      form.value.split_days_a = data.data.split_days_a || ''
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

async function fetchLockPassword() {
  if (!isSuperadmin.value) return
  try {
    const res = await get('/api/v1/settings/payroll-lock-password')
    hasLockPassword.value = res.data?.has_password ?? false
  } catch (e) {
    console.error('Failed to load lock password status', e)
  }
}

async function saveLockPassword() {
  if (!lockPassword.value) return
  savingLockPassword.value = true
  lockPasswordMsg.value = ''
  try {
    await put('/api/v1/settings/payroll-lock-password', { password: lockPassword.value })
    hasLockPassword.value = true
    lockPassword.value = ''
    lockPasswordMsg.value = 'Password berhasil disimpan.'
  } catch (e) {
    console.error('Failed to save lock password', e)
    lockPasswordMsg.value = 'Gagal menyimpan password.'
  } finally {
    savingLockPassword.value = false
  }
}

onMounted(() => {
  fetchSettings()
  fetchLockPassword()
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
          <TextInput 
            v-if="form.working_day_type === 'fixed'"
            v-model="form.split_days_a" 
            label="Hari Kerja Segmen 1 (Split A)" 
            type="number" 
          />
          <TextInput 
            v-if="form.working_day_type === 'fixed' && form.split_days_a"
            :model-value="form.fixed_working_day - form.split_days_a" 
            label="Hari Kerja Segmen 2 (Split B)" 
            type="number" 
            disabled
          />
        </div>
        <div class="flex justify-end">
          <BaseButton variant="primary" @click="saveSettings" :disabled="loading">Simpan Pengaturan</BaseButton>
        </div>
      </div>
    </BaseCard>

    <!-- Password Unlock Payroll (superadmin only, KEPUTUSAN #10 logic_payroll_baru.md) -->
    <BaseCard v-if="isSuperadmin">
      <template #title>Password Unlock Payroll</template>
      <div class="space-y-3">
        <p class="text-sm text-(--text-muted)">
          Password untuk membuka kunci (<strong>Unlock</strong>) payroll yang sudah di-<em>lock</em> di menu Gaji Karyawan.
          Hanya superadmin yang bisa melihat &amp; mengubah.
        </p>
        <div class="flex flex-col sm:flex-row gap-3 items-start sm:items-end">
          <div class="w-full sm:max-w-xs">
            <TextInput
              v-model="lockPassword"
              label="Password Unlock Payroll"
              type="password"
              placeholder="Masukkan password baru"
            />
          </div>
          <BaseButton variant="primary" @click="saveLockPassword" :disabled="savingLockPassword || !lockPassword">
            {{ savingLockPassword ? 'Menyimpan...' : 'Simpan Password' }}
          </BaseButton>
        </div>
        <p class="text-xs" :class="hasLockPassword ? 'text-(--success)' : 'text-(--warning)'">
          {{ hasLockPassword ? '✓ Password sudah diatur.' : '⚠ Password belum diatur — tombol Unlock tidak bisa dipakai.' }}
        </p>
        <p v-if="lockPasswordMsg" class="text-xs text-(--success)">{{ lockPasswordMsg }}</p>
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
