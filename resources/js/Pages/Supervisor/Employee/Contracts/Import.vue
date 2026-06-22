<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useApi } from '../../../../composables/useApi'
import { useNotificationStore } from '../../../../Stores/notification'
import BaseCard from '../../../../Components/BaseCard.vue'
import BaseButton from '../../../../Components/BaseButton.vue'

const router = useRouter()
const notification = useNotificationStore()
const { post } = useApi()

const fileInput = ref(null)
const selectedFile = ref(null)
const loading = ref(false)
const dragActive = ref(false)
const uploadResult = ref(null)

const handleDragEnter = (e) => {
  e.preventDefault()
  dragActive.value = true
}

const handleDragLeave = (e) => {
  e.preventDefault()
  dragActive.value = false
}

const handleDrop = (e) => {
  e.preventDefault()
  dragActive.value = false
  if (e.dataTransfer.files && e.dataTransfer.files[0]) {
    handleFile(e.dataTransfer.files[0])
  }
}

const handleFileSelect = (e) => {
  if (e.target.files && e.target.files[0]) {
    handleFile(e.target.files[0])
  }
}

const handleFile = (file) => {
  const allowedTypes = [
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'application/vnd.ms-excel',
    'text/csv'
  ]
  
  if (!allowedTypes.includes(file.type) && !file.name.match(/\.(xlsx|xls|csv)$/)) {
    notification.addNotification('Format file tidak didukung. Harap unggah file Excel atau CSV.', 'error')
    return
  }
  
  selectedFile.value = file
  uploadResult.value = null
}

const removeFile = () => {
  selectedFile.value = null
  if (fileInput.value) fileInput.value.value = ''
}

const downloadTemplate = () => {
  window.open('/sample_kontrak.xlsx', '_blank')
}

const uploadFile = async () => {
  if (!selectedFile.value) return
  
  loading.value = true
  uploadResult.value = null
  
  const formData = new FormData()
  formData.append('file', selectedFile.value)
  
  try {
    const res = await post('/api/v1/supervisor/employee-data/karyawan/contracts/import', formData, {
      headers: {
        'Content-Type': 'multipart/form-data'
      }
    })
    
    uploadResult.value = {
      success: true,
      stats: res.stats,
      message: res.message
    }
    notification.addNotification('Import berhasil.', 'success')
  } catch (e) {
    if (e.response?.status === 422 && e.response?.data?.errors) {
      uploadResult.value = {
        success: false,
        stats: e.response.data.stats,
        errors: e.response.data.errors,
        message: e.response.data.message
      }
      notification.addNotification('Import selesai dengan error.', 'warning')
    } else {
      notification.addNotification(e.response?.data?.message || 'Gagal mengupload file.', 'error')
    }
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="max-w-4xl mx-auto space-y-6">
    <!-- Header -->
    <div class="flex items-center gap-4">
      <button @click="$router.push('/supervisor/employee-data/kontrak-kerja')" class="w-10 h-10 rounded-full bg-(--bg-card) border border-(--border-soft) flex items-center justify-center text-(--text-soft) hover:text-(--primary) hover:border-(--primary)/50 transition-colors shadow-sm">
        <i class="bx bx-arrow-back text-xl"></i>
      </button>
      <div>
        <h1 class="text-2xl font-bold text-(--text-main)">Import Data Kontrak</h1>
        <p class="text-sm text-(--text-muted) mt-1">Unggah file Excel untuk menambah atau memperbarui kontrak karyawan secara massal</p>
      </div>
    </div>

    <!-- Main Content -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
      
      <!-- Upload Section -->
      <div class="md:col-span-2 space-y-4">
        <BaseCard class="border-(--border-soft) shadow-sm">
          <div 
            class="border-2 border-dashed rounded-lg p-10 text-center transition-colors duration-200 ease-in-out relative"
            :class="[
              dragActive ? 'border-(--primary) bg-(--primary)/5' : 'border-(--border-soft) bg-(--bg-elevated)',
              selectedFile ? 'border-emerald-500/50 bg-emerald-500/5' : ''
            ]"
            @dragenter="handleDragEnter"
            @dragleave="handleDragLeave"
            @dragover.prevent
            @drop="handleDrop"
          >
            <input 
              type="file" 
              ref="fileInput" 
              class="hidden" 
              accept=".xlsx,.xls,.csv"
              @change="handleFileSelect"
            >
            
            <div v-if="!selectedFile">
              <div class="w-16 h-16 bg-(--primary)/10 text-(--primary) rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="bx bx-cloud-upload text-3xl"></i>
              </div>
              <h3 class="text-lg font-semibold text-(--text-main) mb-1">Upload File Excel</h3>
              <p class="text-sm text-(--text-muted) mb-6">Drag & drop file Anda ke sini, atau klik tombol di bawah</p>
              <BaseButton variant="primary" @click="$refs.fileInput.click()">Pilih File</BaseButton>
            </div>
            
            <div v-else class="py-4">
              <div class="w-16 h-16 bg-emerald-500/20 text-emerald-600 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="bx bx-file text-3xl"></i>
              </div>
              <p class="font-bold text-(--text-main) mb-1 truncate px-8">{{ selectedFile.name }}</p>
              <p class="text-xs text-(--text-muted) mb-6">{{ (selectedFile.size / 1024).toFixed(2) }} KB</p>
              
              <div class="flex items-center justify-center gap-3">
                <BaseButton variant="secondary" @click="removeFile" :disabled="loading">Batal</BaseButton>
                <BaseButton variant="primary" @click="uploadFile" :loading="loading">
                  <template #icon-left>
                    <i class="bx bx-upload"></i>
                  </template>
                  Mulai Import
                </BaseButton>
              </div>
            </div>
          </div>
        </BaseCard>

        <!-- Results Section -->
        <BaseCard v-if="uploadResult" class="border-(--border-soft) shadow-sm">
          <div class="flex items-center gap-3 mb-4">
            <div :class="['w-10 h-10 rounded-full flex items-center justify-center shrink-0', uploadResult.success ? 'bg-emerald-500/20 text-emerald-600' : 'bg-amber-500/20 text-amber-600']">
              <i :class="['bx text-2xl', uploadResult.success ? 'bx-check' : 'bx-info-circle']"></i>
            </div>
            <div>
              <h3 class="font-bold text-(--text-main)">Hasil Import</h3>
              <p class="text-sm text-(--text-muted)">{{ uploadResult.message }}</p>
            </div>
          </div>

          <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-(--bg-elevated) border border-(--border-soft) rounded-md p-3 text-center">
              <p class="text-xs text-(--text-muted) uppercase font-bold">Total Baris</p>
              <p class="text-xl font-black text-(--text-main) mt-1">{{ uploadResult.stats.total }}</p>
            </div>
            <div class="bg-(--bg-elevated) border border-(--border-soft) rounded-md p-3 text-center">
              <p class="text-xs text-(--text-muted) uppercase font-bold">Berhasil</p>
              <p class="text-xl font-black text-emerald-600 mt-1">{{ uploadResult.stats.success }}</p>
            </div>
            <div class="bg-(--bg-elevated) border border-(--border-soft) rounded-md p-3 text-center">
              <p class="text-xs text-(--text-muted) uppercase font-bold">Dilewati</p>
              <p class="text-xl font-black text-slate-500 mt-1">{{ uploadResult.stats.skipped }}</p>
            </div>
            <div class="bg-(--bg-elevated) border border-(--border-soft) rounded-md p-3 text-center">
              <p class="text-xs text-(--text-muted) uppercase font-bold">Gagal</p>
              <p class="text-xl font-black text-red-500 mt-1">{{ uploadResult.stats.failed }}</p>
            </div>
          </div>

          <div v-if="uploadResult.errors && uploadResult.errors.length > 0">
            <h4 class="font-bold text-red-500 mb-3 flex items-center gap-2">
              <i class="bx bx-error"></i> Detail Error ({{ uploadResult.errors.length }})
            </h4>
            <div class="bg-red-500/5 border border-red-500/20 rounded-md max-h-64 overflow-y-auto">
              <table class="w-full text-sm text-left">
                <thead class="bg-red-500/10 sticky top-0">
                  <tr>
                    <th class="px-4 py-2 text-red-700 font-semibold w-24">Baris Excel</th>
                    <th class="px-4 py-2 text-red-700 font-semibold w-48">Karyawan (NIK)</th>
                    <th class="px-4 py-2 text-red-700 font-semibold">Pesan Error</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-red-500/10">
                  <tr v-for="(err, idx) in uploadResult.errors" :key="idx" class="text-red-600/80">
                    <td class="px-4 py-2 font-mono text-xs">{{ err.row }}</td>
                    <td class="px-4 py-2">{{ err.nik || '-' }}</td>
                    <td class="px-4 py-2">{{ err.errors.join(', ') }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
          
          <div class="mt-6 flex justify-end">
            <BaseButton variant="primary" @click="$router.push('/supervisor/employee-data/kontrak-kerja')">
              Kembali ke Daftar Kontrak
            </BaseButton>
          </div>
        </BaseCard>
      </div>

      <!-- Instructions Sidebar -->
      <div class="space-y-4">
        <BaseCard padding="p-5" class="border-(--border-soft) shadow-sm bg-(--primary)/5 border-(--primary)/20">
          <div class="flex items-center gap-3 mb-4">
            <i class="bx bx-info-circle text-2xl text-(--primary)"></i>
            <h3 class="font-bold text-(--primary)">Petunjuk Import</h3>
          </div>
          
          <ul class="text-sm text-(--text-main) space-y-3 list-disc pl-5">
            <li>Gunakan format excel dengan baris pertama sebagai Header.</li>
            <li>Kolom wajib: <strong>Kolom 1 (NIP)</strong>. Jika kosong maka baris akan dilewati.</li>
            <li>Kolom 4 (AWAL) dan 5 (AKHIR) dst berisi pasangan tanggal mulai dan selesai kontrak.</li>
            <li>Sistem otomatis membaca kolom kontrak berikutnya hingga menemukan kata <code>HABIS</code> atau sel kosong.</li>
            <li>Tipe kontrak otomatis diatur sebagai <strong>PKWT</strong>. Semua riwayat lama akan ditimpa (Replace All).</li>
          </ul>
          
          <div class="mt-6">
            <BaseButton variant="outline" class="w-full text-(--primary) border-(--primary)/30 hover:bg-(--primary)/10" @click="downloadTemplate">
              <template #icon-left>
                <i class="bx bx-download"></i>
              </template>
              Download Template
            </BaseButton>
            <p class="text-xs text-center text-(--text-muted) mt-2">sample_kontrak.xlsx</p>
          </div>
        </BaseCard>
      </div>
    </div>
  </div>
</template>
