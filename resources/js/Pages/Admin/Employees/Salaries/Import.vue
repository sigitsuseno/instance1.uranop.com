<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useApi } from '../../../../composables/useApi'
import { useNotificationStore } from '../../../../Stores/notification'
import { usePermissionStore } from '../../../../Stores/permission'
import BaseCard from '../../../../Components/BaseCard.vue'
import BaseButton from '../../../../Components/BaseButton.vue'

const router = useRouter()
const notification = useNotificationStore()
const { post } = useApi()
const permission = usePermissionStore()

// Guard: redirect jika tidak punya permission import
if (!permission.can('import employees')) {
  router.replace('/admin/employees/salaries')
}

const fileInput = ref(null)
const selectedFile = ref(null)
const isDragging = ref(false)
const isUploading = ref(false)
const importResult = ref(null)

function handleDragOver(e) {
  e.preventDefault()
  isDragging.value = true
}

function handleDragLeave(e) {
  e.preventDefault()
  isDragging.value = false
}

function handleDrop(e) {
  e.preventDefault()
  isDragging.value = false
  const files = e.dataTransfer.files
  if (files.length > 0) {
    selectedFile.value = files[0]
  }
}

function handleFileSelect(e) {
  if (e.target.files.length > 0) {
    selectedFile.value = e.target.files[0]
  }
}

function browseFiles() {
  fileInput.value.click()
}

function removeFile() {
  selectedFile.value = null
  if (fileInput.value) fileInput.value.value = ''
  importResult.value = null
}

async function uploadFile() {
  if (!selectedFile.value) return

  isUploading.value = true
  importResult.value = null

  const formData = new FormData()
  formData.append('file', selectedFile.value)

  try {
    const res = await post('/api/v1/employees/salaries/import', formData, {
      headers: {
        'Content-Type': 'multipart/form-data'
      }
    })
    
    notification.addNotification('Import berhasil diproses', 'success')
    importResult.value = {
      success: true,
      stats: res.data?.stats || {},
      errors: []
    }
    
    setTimeout(() => {
      router.push('/admin/employees/salaries')
    }, 2000)
    
  } catch (err) {
    if (err.response?.status === 422 && err.response?.data?.errors) {
      // Partial success / validation errors
      notification.addNotification('Terdapat beberapa baris yang gagal diproses', 'warning')
      importResult.value = {
        success: false,
        stats: err.response.data.stats || {},
        errors: Array.isArray(err.response.data.errors) ? err.response.data.errors : Object.values(err.response.data.errors).flat()
      }
    } else {
      notification.addNotification(err.response?.data?.message || 'Gagal mengunggah file', 'error')
    }
  } finally {
    isUploading.value = false
  }
}
</script>

<template>
  <div class="space-y-6 max-w-4xl">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-(--text-main)">Import Data Gaji</h1>
        <p class="text-sm text-(--text-muted) mt-1">Unggah file Excel (xls, xlsx, csv) untuk menambah histori gaji massal</p>
      </div>
      <BaseButton variant="secondary" @click="$router.push('/admin/employees/salaries')">
        <template #icon-left><i class="bx bx-arrow-back text-lg"></i></template>
        Kembali
      </BaseButton>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <div class="lg:col-span-2 space-y-6">
        <BaseCard padding="p-6" class="border-(--border-soft) shadow-sm bg-(--bg-card)">
          <div 
            class="border-2 border-dashed rounded-lg p-10 text-center transition-all duration-200"
            :class="isDragging ? 'border-(--primary) bg-(--primary)/5' : 'border-(--border-strong) hover:border-(--primary)/50 bg-(--bg-elevated)'"
            @dragover="handleDragOver"
            @dragleave="handleDragLeave"
            @drop="handleDrop"
          >
            <div v-if="!selectedFile">
              <div class="w-16 h-16 bg-(--primary)/10 text-(--primary) rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="bx bx-upload text-3xl"></i>
              </div>
              <h3 class="text-lg font-semibold text-(--text-main) mb-2">Pilih atau Tarik File Excel ke sini</h3>
              <p class="text-sm text-(--text-muted) mb-6">Mendukung file .xlsx, .xls, .csv hingga maksimal 5MB</p>
              
              <input type="file" ref="fileInput" @change="handleFileSelect" accept=".xlsx, .xls, .csv" class="hidden">
              <BaseButton variant="secondary" @click="browseFiles">
                Browse Files
              </BaseButton>
            </div>
            
            <div v-else class="flex flex-col items-center">
              <div class="w-16 h-16 bg-emerald-500/10 text-emerald-500 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="bx bx-file text-3xl"></i>
              </div>
              <h3 class="text-lg font-semibold text-(--text-main) mb-1 truncate max-w-[300px]">{{ selectedFile.name }}</h3>
              <p class="text-sm text-(--text-muted) mb-6">{{ (selectedFile.size / 1024 / 1024).toFixed(2) }} MB</p>
              
              <div class="flex gap-3">
                <BaseButton variant="ghost" @click="removeFile" :disabled="isUploading">
                  Ganti File
                </BaseButton>
                <BaseButton variant="primary" @click="uploadFile" :loading="isUploading">
                  Mulai Import
                </BaseButton>
              </div>
            </div>
          </div>
        </BaseCard>

        <!-- Import Result Display -->
        <BaseCard v-if="importResult" padding="p-0" class="border-(--border-soft) shadow-sm overflow-hidden">
          <div :class="['p-4 font-semibold text-white flex items-center', importResult.success ? 'bg-emerald-600' : 'bg-amber-500']">
            <i :class="['bx text-xl mr-2', importResult.success ? 'bx-check-circle' : 'bx-error-circle']"></i>
            {{ importResult.success ? 'Import Berhasil' : 'Import Selesai dengan Peringatan' }}
          </div>
          <div class="p-6">
            <div class="flex gap-6 mb-6">
              <div>
                <p class="text-sm text-(--text-muted)">Total Baris</p>
                <p class="text-2xl font-bold text-(--text-main)">{{ importResult.stats.total || 0 }}</p>
              </div>
              <div>
                <p class="text-sm text-(--text-muted)">Berhasil</p>
                <p class="text-2xl font-bold text-emerald-600">{{ importResult.stats.success || 0 }}</p>
              </div>
              <div>
                <p class="text-sm text-(--text-muted)">Gagal</p>
                <p class="text-2xl font-bold text-red-600">{{ importResult.stats.failed || 0 }}</p>
              </div>
            </div>

            <div v-if="importResult.errors && importResult.errors.length > 0">
              <h4 class="font-semibold text-red-600 mb-2">Detail Error:</h4>
              <ul class="list-disc pl-5 text-sm text-(--text-muted) space-y-1 max-h-60 overflow-y-auto bg-red-50 dark:bg-red-900/10 p-4 rounded-md border border-red-200 dark:border-red-900/30">
                <li v-for="(err, index) in importResult.errors" :key="index">{{ err }}</li>
              </ul>
            </div>
          </div>
        </BaseCard>
      </div>

      <div class="lg:col-span-1">
        <BaseCard padding="p-6" class="border-(--border-soft) shadow-sm bg-(--bg-card)">
          <div class="flex items-center gap-3 mb-4">
            <div class="w-10 h-10 bg-blue-500/10 text-blue-500 rounded-lg flex items-center justify-center">
              <i class="bx bx-info-circle text-xl"></i>
            </div>
            <h2 class="font-semibold text-(--text-main)">Petunjuk Import</h2>
          </div>
          
          <ul class="text-sm text-(--text-muted) space-y-3 list-disc pl-4 mb-6">
            <li>Pastikan format kolom Excel Anda sama dengan format yang diizinkan (NIP, Gaji Pokok, Tunjangan, Tunjangan Makan, Premi, Tanggal Efektif, Tanggal Akhir, Nomer Surat, Alasan).</li>
            <li>Kolom <strong>NIP</strong> wajib diisi.</li>
            <li>Tanggal harus dalam format yang valid di Excel.</li>
            <li>Data lama untuk Karyawan yang sama akan otomatis diset sebagai Tidak Aktif, sehingga data baru ini menjadi gaji yang Aktif (Penyesuaian).</li>
          </ul>

          <p class="text-xs text-(--text-soft) mt-4 pt-4 border-t border-(--border-soft)">
            Contoh file yang bisa digunakan:<br>
            SE_Februari.xlsx, SE_Januari.xlsx, dll.
          </p>
        </BaseCard>
      </div>
    </div>
  </div>
</template>
