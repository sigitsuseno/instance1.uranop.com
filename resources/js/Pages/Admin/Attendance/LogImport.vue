<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-(--text-main)">Import Log Absensi</h1>
        <p class="text-sm text-(--text-muted) mt-1">Upload file log absensi dari mesin fingerprint</p>
      </div>
    </div>

    <div class="grid gap-6">
      <!-- Upload Card -->
      <BaseCard>
        <div class="space-y-4">
          <div>
            <h3 class="font-semibold text-(--text-main) mb-1">Panduan Import</h3>
            <p class="text-sm text-(--text-muted)">
              Unggah file log absensi dengan format <strong>.xlsx</strong> atau <strong>.csv</strong>.
              File harus memiliki kolom: <strong>NIP, Nama, Tanggal, Scan 1-4</strong>.
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

          <!-- Mode selector -->
          <div class="flex items-center gap-4">
            <label class="flex items-center gap-2 cursor-pointer">
              <input type="radio" v-model="importMode" value="create" class="text-(--primary)" />
              <span class="text-sm text-(--text-main)">Create (tambah data baru)</span>
            </label>
            <label class="flex items-center gap-2 cursor-pointer">
              <input type="radio" v-model="importMode" value="replace" class="text-(--primary)" />
              <span class="text-sm text-(--text-main)">Replace (hapus & ganti data dari file yang sama)</span>
            </label>
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
            <p class="text-xs text-(--text-muted) mt-1">Format: .xlsx, .csv (Maks. 20MB)</p>
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

          <!-- Progress / Result -->
          <div v-if="importResult" class="p-4 rounded-md" :class="importResult.success ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200'">
            <p class="font-medium" :class="importResult.success ? 'text-green-700' : 'text-red-700'">
              {{ importResult.message || (importResult.success ? 'Import berhasil!' : 'Import gagal!') }}
            </p>
            <div v-if="importResult.status === 'processing'" class="mt-2 flex items-center gap-2 text-sm text-(--text-muted)">
              <span class="animate-spin w-4 h-4 border-2 border-(--primary) border-t-transparent rounded-full"></span>
              {{ importResult.message }}
            </div>
            <div v-if="importResult.inserted !== undefined" class="mt-1 text-sm text-(--text-muted)">
              {{ importResult.inserted }} data berhasil diimport.
              <span v-if="importResult.total_errors > 0" class="text-red-600"> ({{ importResult.total_errors }} error)</span>
              <span v-if="importResult.total_warnings > 0" class="text-yellow-600"> ({{ importResult.total_warnings }} warning)</span>
            </div>
          </div>
        </div>
      </BaseCard>

      <!-- Import History -->
      <BaseCard>
        <template #title>Riwayat Import</template>

        <div v-if="importHistory.length === 0" class="text-sm text-(--text-muted) py-4 text-center">
          Belum ada riwayat import
        </div>

        <DataTable v-else :headers="historyHeaders" :items="importHistory">
          <template #item.date="{ value }">{{ value }}</template>
          <template #item.filename="{ value }">{{ value }}</template>
          <template #item.records="{ value }">{{ value }}</template>
          <template #item.status="{ value }">
            <Badge :variant="value === 'success' ? 'success' : value === 'processing' ? 'warning' : 'danger'">
              {{ value === 'success' ? 'Berhasil' : value === 'processing' ? 'Diproses' : 'Gagal' }}
            </Badge>
          </template>
        </DataTable>
      </BaseCard>
    </div>
  </div>
</template>

<script setup>
import { ref, onUnmounted } from 'vue'
import BaseCard from '../../../Components/BaseCard.vue'
import BaseButton from '../../../Components/BaseButton.vue'
import Badge from '../../../Components/Badge.vue'
import DataTable from '../../../Components/Table/DataTable.vue'
import { IconDownload, IconUpload, IconTrash } from '../../../Components/Icons/index.js'
import { useApi } from '../../../composables/useApi.js'

const { post, get } = useApi()

const fileInput = ref(null)
const selectedFile = ref(null)
const dragOver = ref(false)
const importing = ref(false)
const importMode = ref('create')
const importResult = ref(null)

let pollTimer = null

const historyHeaders = [
  { key: 'date', label: 'Tanggal' },
  { key: 'filename', label: 'Nama File' },
  { key: 'records', label: 'Jumlah Data' },
  { key: 'status', label: 'Status' },
]

const importHistory = ref([])

function triggerFileInput() {
  fileInput.value?.click()
}

function handleFileSelect(e) {
  const file = e.target.files[0]
  if (file) {
    selectedFile.value = file
    importResult.value = null
  }
}

function handleDrop(e) {
  dragOver.value = false
  const file = e.dataTransfer.files[0]
  if (file) {
    selectedFile.value = file
    importResult.value = null
  }
}

function clearFile() {
  selectedFile.value = null
  importResult.value = null
  if (fileInput.value) fileInput.value.value = ''
}

async function handleImport() {
  if (!selectedFile.value) return

  importing.value = true
  importResult.value = null

  try {
    const formData = new FormData()
    formData.append('file', selectedFile.value)
    formData.append('mode', importMode.value)

    const response = await post('/api/v1/attendance/logs/import', formData)

    if (response.batch) {
      // Start polling for result
      importResult.value = { status: 'processing', message: response.message }
      startPolling(response.batch)
    } else {
      importResult.value = { success: true, message: response.message }
      addHistory(selectedFile.value.name, 'success')
      clearFile()
    }
  } catch (error) {
    importResult.value = { success: false, message: error.message || 'Gagal mengimport file.' }
    addHistory(selectedFile.value?.name || 'unknown', 'failed')
  } finally {
    importing.value = false
  }
}

function startPolling(batch) {
  clearPolling()

  pollTimer = setInterval(async () => {
    try {
      const result = await get(`/api/v1/attendance/logs/import/status/${batch}`)

      if (result.status === 'completed') {
        importResult.value = result
        addHistory(result.file, 'success', result.inserted)
        clearFile()
        clearPolling()
      } else if (result.status === 'failed') {
        importResult.value = { success: false, message: result.message || 'Import gagal.' }
        addHistory(result.file, 'failed')
        clearPolling()
      }
      // else: still processing, keep polling
    } catch {
      // Silently retry
    }
  }, 2000)
}

function clearPolling() {
  if (pollTimer) {
    clearInterval(pollTimer)
    pollTimer = null
  }
}

function addHistory(filename, status, records = 0) {
  importHistory.value.unshift({
    id: Date.now(),
    date: new Date().toISOString().split('T')[0],
    filename,
    records,
    status,
  })
}

function downloadTemplate() {
  // Buka URL download template
  window.open('/api/v1/attendance/logs/import/template', '_blank')
}

function formatFileSize(bytes) {
  if (bytes < 1024) return bytes + ' B'
  if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB'
  return (bytes / 1048576).toFixed(1) + ' MB'
}

onUnmounted(() => {
  clearPolling()
})
</script>
