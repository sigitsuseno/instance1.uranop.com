<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-(--text-main)">Import Log Absensi</h1>
        <p class="text-sm text-(--text-muted) mt-1">Upload file log absensi dari mesin fingerprint</p>
      </div>
    </div>

    <div class="grid gap-6">
      <BaseCard>
        <div class="space-y-4">
          <div>
            <h3 class="font-semibold text-(--text-main) mb-1">Panduan Import</h3>
            <p class="text-sm text-(--text-muted)">
              Unggah file log absensi dengan format .xlsx atau .csv. File harus memiliki kolom berikut:
              <strong>NIP, Tanggal, Jam Masuk, Jam Pulang.</strong>
              Gunakan template yang disediakan untuk memastikan format yang benar.
            </p>
          </div>

          <div class="flex items-center gap-3">
            <BaseButton variant="secondary" size="sm" @click="downloadTemplate">
              <template #icon-left>
                <IconDownload class="w-4 h-4" />
              </template>
              Download Template
            </BaseButton>
          </div>

          <div
            class="border-2 border-dashed border-(--border-soft) rounded-md p-8 text-center cursor-pointer hover:border-(--primary)/50 hover:bg-(--primary)/5 transition-colors"
            :class="{ 'border-(--primary) bg-(--primary)/5': dragOver }"
            @click="triggerFileInput"
            @dragover.prevent="dragOver = true"
            @dragleave.prevent="dragOver = false"
            @drop.prevent="handleDrop"
          >
            <input
              ref="fileInput"
              type="file"
              accept=".xlsx,.csv"
              class="hidden"
              @change="handleFileSelect"
            />
            <IconUpload class="w-10 h-10 mx-auto mb-3 text-(--text-muted)" />
            <p class="text-sm text-(--text-main)">
              Seret & lepas file di sini, atau
              <span class="text-(--primary) font-medium">klik untuk memilih</span>
            </p>
            <p class="text-xs text-(--text-muted) mt-1">Format: .xlsx, .csv (Maks. 5MB)</p>
          </div>

          <div v-if="selectedFile" class="flex items-center gap-3 p-3 rounded-md bg-(--bg-elevated)">
            <div class="flex-1">
              <p class="text-sm font-medium text-(--text-main)">{{ selectedFile.name }}</p>
              <p class="text-xs text-(--text-muted)">{{ formatFileSize(selectedFile.size) }}</p>
            </div>
            <BaseButton variant="ghost" size="sm" @click="clearFile">
              <IconTrash class="w-4 h-4" />
            </BaseButton>
          </div>

          <div v-if="selectedFile" class="flex justify-end">
            <BaseButton variant="primary" :loading="importing" @click="handleImport">
              <template #icon-left>
                <IconUpload class="w-4 h-4" />
              </template>
              Import Data
            </BaseButton>
          </div>
        </div>
      </BaseCard>

      <BaseCard v-if="showPreview">
        <template #title>Pratinjau Data (5 data pertama)</template>

        <DataTable :headers="previewHeaders" :items="previewData" />

        <div class="mt-3 text-sm text-(--text-muted)">
          Total {{ previewTotal }} data akan diimport
        </div>
      </BaseCard>

      <BaseCard>
        <template #title>Riwayat Import</template>

        <DataTable :headers="historyHeaders" :items="importHistory">
          <template #item.date="{ value }">{{ value }}</template>
          <template #item.filename="{ value }">{{ value }}</template>
          <template #item.records="{ value }">{{ value }}</template>
          <template #item.status="{ value }">
            <Badge :variant="value === 'success' ? 'success' : 'danger'">
              {{ value === 'success' ? 'Berhasil' : 'Gagal' }}
            </Badge>
          </template>
        </DataTable>
      </BaseCard>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import BaseCard from '../../../Components/BaseCard.vue'
import BaseButton from '../../../Components/BaseButton.vue'
import Badge from '../../../Components/Badge.vue'
import DataTable from '../../../Components/Table/DataTable.vue'
import { IconDownload, IconUpload, IconTrash } from '../../../Components/Icons/index.js'

const fileInput = ref(null)
const selectedFile = ref(null)
const dragOver = ref(false)
const importing = ref(false)
const showPreview = ref(false)

const previewHeaders = [
  { key: 'nip', label: 'NIP' },
  { key: 'name', label: 'Nama' },
  { key: 'date', label: 'Tanggal' },
  { key: 'check_in', label: 'Jam Masuk' },
  { key: 'check_out', label: 'Jam Pulang' },
]

const previewData = ref([])
const previewTotal = ref(0)

const historyHeaders = [
  { key: 'date', label: 'Tanggal' },
  { key: 'filename', label: 'Nama File' },
  { key: 'records', label: 'Jumlah Data' },
  { key: 'status', label: 'Status' },
]

const importHistory = ref([
  { id: 1, date: '2026-05-27', filename: 'log_20260527.xlsx', records: 156, status: 'success' },
  { id: 2, date: '2026-05-26', filename: 'log_20260526.xlsx', records: 158, status: 'success' },
  { id: 3, date: '2026-05-25', filename: 'log_20260525.csv', records: 0, status: 'failed' },
])

function triggerFileInput() {
  fileInput.value?.click()
}

function handleFileSelect(e) {
  const file = e.target.files[0]
  if (file) processFile(file)
}

function handleDrop(e) {
  dragOver.value = false
  const file = e.dataTransfer.files[0]
  if (file) processFile(file)
}

function processFile(file) {
  selectedFile.value = file

  previewData.value = [
    { nip: 'EMP001', name: 'Budi Santoso', date: '2026-05-27', check_in: '07:55', check_out: '17:05' },
    { nip: 'EMP002', name: 'Siti Nurhaliza', date: '2026-05-27', check_in: '08:15', check_out: '17:00' },
    { nip: 'EMP003', name: 'Ahmad Fauzi', date: '2026-05-27', check_in: '07:50', check_out: '18:30' },
    { nip: 'EMP004', name: 'Dewi Lestari', date: '2026-05-27', check_in: '08:00', check_out: '17:00' },
    { nip: 'EMP005', name: 'Rudi Hartono', date: '2026-05-27', check_in: '--:--', check_out: '--:--' },
  ]
  previewTotal.value = 156
  showPreview.value = true
}

function clearFile() {
  selectedFile.value = null
  showPreview.value = false
  previewData.value = []
  if (fileInput.value) fileInput.value.value = ''
}

function handleImport() {
  importing.value = true
  setTimeout(() => {
    importing.value = false
    clearFile()
    importHistory.value.unshift({
      id: Date.now(),
      date: '2026-05-27',
      filename: selectedFile.value?.name || 'log.xlsx',
      records: 156,
      status: 'success',
    })
    alert('Import berhasil! 156 data absensi telah diimport.')
  }, 2000)
}

function downloadTemplate() {
  alert('Template akan diunduh.')
}

function formatFileSize(bytes) {
  if (bytes < 1024) return bytes + ' B'
  if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB'
  return (bytes / 1048576).toFixed(1) + ' MB'
}
</script>
