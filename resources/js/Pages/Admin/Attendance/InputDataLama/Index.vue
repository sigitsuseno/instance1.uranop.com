<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-(--text-main)">Input Data Lama</h1>
        <p class="text-sm text-(--text-muted) mt-1">
          Isi kehadiran periode lama dari berkas Excel <code>absen_core</code> di File Manager
          ke att_prepare, leave request, dan consecutive day
        </p>
      </div>
    </div>

    <div class="grid gap-6">
      <!-- ── Langkah 1: periode + berkas ── -->
      <BaseCard>
        <template #title>1. Pilih Periode &amp; Berkas</template>

        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-(--text-main) mb-1">Periode</label>
            <select
              v-model="selectedPeriod"
              class="w-full md:w-96 px-3 py-2 text-sm rounded-md border border-(--border-soft) bg-(--bg-elevated) text-(--text-main)"
              :disabled="isBusy"
              @change="resetPreview"
            >
              <option value="">— Pilih periode —</option>
              <option v-for="p in payPeriods" :key="p.id" :value="p.id">{{ p.label }}</option>
            </select>
            <p class="text-xs text-(--text-muted) mt-1">
              Rentang tanggal di baris pertama berkas harus sama persis dengan periode ini.
            </p>
          </div>

          <div class="p-3 rounded-md bg-(--bg-elevated) border border-(--border-soft)">
            <p class="text-xs text-(--text-muted)">
              <strong>Format berkas:</strong> baris 1 = tanggal, baris 2 = nama hari, data mulai baris 3.
              Kolom 1 = <strong>NIP</strong>, kolom 2 = nama. Mulai kolom 3 berpasangan:
              kolom ganjil = kode status, kolom genap = jam lembur hari yang sama.
            </p>
          </div>

          <!-- Berkas terpilih -->
          <div v-if="selectedFile" class="flex items-center gap-3 p-3 rounded-md bg-(--bg-elevated) border border-(--border-soft)">
            <div class="flex-1 min-w-0">
              <p class="text-sm font-medium text-(--text-main) truncate">{{ selectedFile.name }}</p>
              <p class="text-xs text-(--text-muted) truncate">
                {{ selectedFile.size_human }} · {{ selectedFile.path }}
              </p>
            </div>
            <BaseButton variant="secondary" size="sm" :disabled="isBusy" @click="showPicker = true">
              Ganti
            </BaseButton>
            <BaseButton variant="ghost" size="sm" :disabled="isBusy" @click="clearFile">
              <IconTrash class="w-4 h-4" />
            </BaseButton>
          </div>

          <div
            v-else
            class="border-2 border-dashed border-(--border-soft) rounded-md p-8 text-center"
          >
            <IconFileInvoice class="w-10 h-10 mx-auto mb-3 text-(--text-muted)" />
            <p class="text-sm text-(--text-muted) mb-3">
              Pilih berkas absen dari folder File Manager.
            </p>
            <BaseButton variant="secondary" size="sm" :disabled="isBusy" @click="showPicker = true">
              <template #icon-left>
                <IconSearch class="w-4 h-4" />
              </template>
              Pilih dari File Manager
            </BaseButton>
          </div>

          <div class="flex justify-end">
            <BaseButton
              variant="primary"
              :loading="previewing"
              :disabled="!selectedFile || !selectedPeriod || isBusy"
              @click="doPreview"
            >
              <template #icon-left>
                <IconEye class="w-4 h-4" />
              </template>
              Pratinjau
            </BaseButton>
          </div>
        </div>
      </BaseCard>

      <!-- ── Error ── -->
      <div v-if="errorMessage" class="p-4 rounded-md bg-red-50 border border-red-200">
        <p class="font-medium text-red-700 flex items-center gap-2">
          <IconAlertTriangle class="w-4 h-4" />
          {{ errorMessage }}
        </p>
      </div>

      <!-- ── Langkah 2: hasil pratinjau ── -->
      <template v-if="summary">
        <BaseCard>
          <template #title>2. Ringkasan Pratinjau</template>

          <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
            <div class="p-3 rounded-md bg-(--bg-elevated) border border-(--border-soft)">
              <p class="text-xs text-(--text-muted)">Berkas</p>
              <p class="text-lg font-semibold text-(--text-main)">{{ summary.file.rows }} baris</p>
              <p class="text-xs text-(--text-muted)">
                {{ summary.file.unique_nip }} NIP unik · {{ summary.file.days }} hari
              </p>
              <p class="text-xs text-(--text-muted)">{{ summary.file.first_date }} s/d {{ summary.file.last_date }}</p>
            </div>

            <div class="p-3 rounded-md bg-(--bg-elevated) border border-(--border-soft)">
              <p class="text-xs text-(--text-muted)">Karyawan</p>
              <p class="text-lg font-semibold text-(--text-main)">{{ summary.employees.matched }} cocok</p>
              <p class="text-xs text-(--text-muted)">{{ summary.employees.grp_jkt_skeleton }} baris GRP-JKT</p>
              <p class="text-xs" :class="summary.employees.unmatched.length ? 'text-amber-600' : 'text-(--text-muted)'">
                {{ summary.employees.unmatched.length }} tidak ketemu
              </p>
            </div>

            <div class="p-3 rounded-md bg-(--bg-elevated) border border-(--border-soft)">
              <p class="text-xs text-(--text-muted)">Jadwal (roster)</p>
              <p class="text-lg font-semibold text-(--text-main)">{{ summary.roster.period_rows }} baris</p>
              <p class="text-xs" :class="summary.roster.missing_days ? 'text-amber-600' : 'text-(--text-muted)'">
                {{ summary.roster.missing_days }} hari tanpa jadwal
              </p>
            </div>

            <div class="p-3 rounded-md bg-(--bg-elevated) border border-(--border-soft)">
              <p class="text-xs text-(--text-muted)">Yang akan ditulis</p>
              <p class="text-lg font-semibold text-(--text-main)">{{ summary.att_prepare.total }} baris</p>
              <p class="text-xs text-(--text-muted)">
                {{ summary.leave_requests.total }} leave request · {{ summary.consecutive.total }} consecutive
              </p>
              <p v-if="summary.att_prepare.locked" class="text-xs text-amber-600">
                {{ summary.att_prepare.locked }} baris terkunci dilewati
              </p>
            </div>
          </div>

          <!-- rekap status -->
          <div class="mt-4">
            <p class="text-sm font-medium text-(--text-main) mb-2">Rekap status att_prepare</p>
            <div class="flex flex-wrap gap-2">
              <span
                v-for="(n, st) in summary.att_prepare.by_status"
                :key="st"
                class="inline-flex items-center gap-1 px-2 py-1 rounded text-xs font-medium bg-(--bg-elevated) border border-(--border-soft) text-(--text-main)"
              >
                {{ st }} <span class="text-(--text-muted)">× {{ n }}</span>
              </span>
            </div>
          </div>

          <!-- catatan -->
          <div v-if="issues.length" class="mt-4 p-3 rounded-md bg-amber-50 border border-amber-200">
            <p class="text-sm font-medium text-amber-800 flex items-center gap-2 mb-1">
              <IconAlertTriangle class="w-4 h-4" /> Catatan
            </p>
            <ul class="list-disc list-inside space-y-0.5">
              <li v-for="(msg, i) in issues" :key="i" class="text-xs text-amber-800">{{ msg }}</li>
            </ul>
          </div>

          <!-- karyawan tak ketemu -->
          <div v-if="summary.employees.unmatched.length" class="mt-4">
            <p class="text-sm font-medium text-(--text-main) mb-2">Baris yang dilewati</p>
            <div class="max-h-48 overflow-auto rounded-md border border-(--border-soft)">
              <table class="w-full text-xs">
                <thead class="bg-(--bg-elevated) text-(--text-muted)">
                  <tr>
                    <th class="px-2 py-1 text-left">Baris</th>
                    <th class="px-2 py-1 text-left">NIP</th>
                    <th class="px-2 py-1 text-left">Nama</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(u, i) in summary.employees.unmatched" :key="i" class="border-t border-(--border-soft)">
                    <td class="px-2 py-1">{{ u.row }}</td>
                    <td class="px-2 py-1">{{ u.nip }}</td>
                    <td class="px-2 py-1">{{ u.nama }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </BaseCard>

        <!-- leave request -->
        <BaseCard v-if="summary.leave_requests.total">
          <template #title>Leave Request yang akan dibuat ({{ summary.leave_requests.total }})</template>
          <div class="max-h-80 overflow-auto rounded-md border border-(--border-soft)">
            <table class="w-full text-xs">
              <thead class="bg-(--bg-elevated) text-(--text-muted) sticky top-0">
                <tr>
                  <th class="px-2 py-1 text-left">Karyawan</th>
                  <th class="px-2 py-1 text-left">Kode</th>
                  <th class="px-2 py-1 text-left">Mulai</th>
                  <th class="px-2 py-1 text-left">Selesai</th>
                  <th class="px-2 py-1 text-right">Hari</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(r, i) in summary.leave_requests.ranges" :key="i" class="border-t border-(--border-soft)">
                  <td class="px-2 py-1">{{ r.employee_nama || ('#' + r.employee_id) }}</td>
                  <td class="px-2 py-1">{{ r.leave_code }}</td>
                  <td class="px-2 py-1">{{ r.start_date }}</td>
                  <td class="px-2 py-1">{{ r.end_date }}</td>
                  <td class="px-2 py-1 text-right">{{ r.days_requested }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </BaseCard>

        <!-- ── Langkah 3: simpan ── -->
        <BaseCard>
          <template #title>3. Simpan</template>

          <div v-if="!confirming" class="flex items-center justify-between gap-4">
            <p class="text-sm text-(--text-muted)">
              Menulis {{ summary.att_prepare.total }} baris att_prepare, {{ summary.leave_requests.total }} leave request,
              dan {{ summary.consecutive.total }} consecutive day. Jalankan ulang aman (data yang sama tidak diduplikasi).
            </p>
            <BaseButton variant="primary" :disabled="isBusy" @click="confirming = true">
              Proses &amp; Simpan
            </BaseButton>
          </div>

          <div v-else class="p-3 rounded-md bg-amber-50 border border-amber-200 flex items-center justify-between gap-4">
            <p class="text-sm text-amber-800">
              Simpan sekarang? Data periode ini akan ditulis ke database.
            </p>
            <div class="flex gap-2">
              <BaseButton variant="ghost" size="sm" :disabled="committing" @click="confirming = false">Batal</BaseButton>
              <BaseButton variant="primary" size="sm" :loading="committing" @click="doCommit">Ya, simpan</BaseButton>
            </div>
          </div>
        </BaseCard>
      </template>

      <!-- ── Hasil simpan ── -->
      <div v-if="result" class="p-4 rounded-md bg-green-50 border border-green-200">
        <p class="font-medium text-green-700 flex items-center gap-2">
          <IconCheck class="w-4 h-4" /> Data lama berhasil disimpan.
        </p>
        <p class="text-sm text-green-700 mt-1">
          att_prepare: {{ result.att_prepare.inserted }} baru, {{ result.att_prepare.updated }} diperbarui,
          {{ result.att_prepare.skipped_locked }} dilewati (terkunci) ·
          leave request: {{ result.leave_requests }} ·
          consecutive: {{ result.consecutive }}
        </p>
      </div>
    </div>

    <FilePickerModal :show="showPicker" @close="showPicker = false" @select="onFileSelected" />
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import BaseCard from '@/Components/BaseCard.vue'
import BaseButton from '@/Components/BaseButton.vue'
import FilePickerModal from './Components/FilePickerModal.vue'
import {
  IconAlertTriangle,
  IconCheck,
  IconEye,
  IconFileInvoice,
  IconSearch,
  IconTrash,
} from '@/Components/Icons/index.js'
import { useApi } from '@/composables/useApi'
import { useNotification } from '@/composables/useNotification'

const { get, post } = useApi()
const notify = useNotification()

const showPicker = ref(false)
const selectedFile = ref(null)
const selectedPeriod = ref('')
const payPeriods = ref([])
const previewing = ref(false)
const committing = ref(false)
const confirming = ref(false)
const errorMessage = ref('')
const summary = ref(null)
const issues = ref([])
const result = ref(null)

const isBusy = computed(() => previewing.value || committing.value)

async function fetchPayPeriods() {
  try {
    const res = await get('/api/v1/payroll/periods')
    payPeriods.value = (res.data || []).map(p => ({
      id: p.id,
      label: `${p.name} (${p.start_date} - ${p.end_date})`,
    }))
  } catch (e) {
    notify.error('Gagal memuat daftar periode.')
  }
}

function resetPreview() {
  summary.value = null
  issues.value = []
  result.value = null
  errorMessage.value = ''
  confirming.value = false
}

function onFileSelected(file) {
  selectedFile.value = file
  resetPreview()
}

function clearFile() {
  selectedFile.value = null
  resetPreview()
}

async function doPreview() {
  previewing.value = true
  resetPreview()

  try {
    const res = await post('/api/v1/attendance/legacy-input/preview', {
      path: selectedFile.value.path,
      pay_period_id: selectedPeriod.value,
    })
    summary.value = res.data.summary
    issues.value = res.data.issues || []
    notify.success('Pratinjau berhasil disusun.')
  } catch (e) {
    errorMessage.value = e.message || 'Gagal menyusun pratinjau.'
    notify.error(errorMessage.value)
  } finally {
    previewing.value = false
  }
}

async function doCommit() {
  committing.value = true
  errorMessage.value = ''

  try {
    const res = await post('/api/v1/attendance/legacy-input/store', {
      path: selectedFile.value.path,
      pay_period_id: selectedPeriod.value,
    })
    result.value = res.data.result
    summary.value = res.data.summary
    issues.value = res.data.issues || []
    confirming.value = false
    notify.success(res.message || 'Data lama berhasil disimpan.')
  } catch (e) {
    errorMessage.value = e.message || 'Gagal menyimpan data.'
    notify.error(errorMessage.value)
  } finally {
    committing.value = false
  }
}

onMounted(fetchPayPeriods)
</script>
