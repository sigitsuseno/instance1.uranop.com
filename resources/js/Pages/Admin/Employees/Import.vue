<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useNotificationStore } from '../../../Stores/notification'
import BaseButton from '../../../Components/BaseButton.vue'
import BaseCard from '../../../Components/BaseCard.vue'
import { IconDownload, IconUpload, IconUsers } from '../../../Components/Icons/index.js'

const router = useRouter()
const notification = useNotificationStore()

const importFile = ref(null)
const importLoading = ref(false)

function handleFileChange(event) {
  importFile.value = event.target.files[0]
}

function downloadTemplate() {
  window.open('/templates/template_import_karyawan.xlsx', '_blank')
}

async function submitImport() {
  if (!importFile.value) {
    notification.error('Pilih file terlebih dahulu.')
    return
  }

  importLoading.value = true
  const formData = new FormData()
  formData.append('file', importFile.value)

  try {
    const res = await fetch('/api/v1/employees/import', {
      method: 'POST',
      headers: {
        'Authorization': 'Bearer ' + localStorage.getItem('token'),
        'Accept': 'application/json'
      },
      body: formData
    })
    const data = await res.json()
    
    if (res.ok) {
      notification.success(data.message || 'Import berhasil')
      router.push('/admin/employees')
    } else {
      notification.error(data.message || 'Gagal import file.')
      if (data.errors && data.errors.length) {
        data.errors.forEach(err => notification.error(err))
      }
    }
  } catch (e) {
    notification.error('Terjadi kesalahan koneksi.')
  } finally {
    importLoading.value = false
  }
}
</script>

<template>
  <div class="space-y-6 max-w-3xl mx-auto">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-bold text-(--text-main)">Import Data Karyawan</h1>
      <BaseButton variant="secondary" @click="router.push('/admin/employees')">
        <template #icon-left>
          <IconUsers class="w-4 h-4" />
        </template>
        Kembali
      </BaseButton>
    </div>

    <!-- Import Form -->
    <BaseCard>
      <div class="space-y-6">
        <div>
          <h3 class="text-lg font-semibold text-(--text-main) mb-2">1. Unduh Template</h3>
          <p class="text-sm text-(--text-muted) mb-4">
            Silakan unduh template excel standar, isi data karyawan sesuai dengan format kolom yang disediakan, dan jangan mengubah struktur header-nya.
          </p>
          <BaseButton variant="secondary" @click="downloadTemplate">
            <template #icon-left>
              <IconDownload class="w-4 h-4" />
            </template>
            Download Template Excel
          </BaseButton>
        </div>

        <div class="border-t border-(--border-soft) pt-6">
          <h3 class="text-lg font-semibold text-(--text-main) mb-2">2. Upload File</h3>
          <p class="text-sm text-(--text-muted) mb-4">
            Setelah template diisi, unggah kembali file tersebut di bawah ini. Pastikan format file adalah .xlsx atau .csv.
          </p>
          
          <div class="mt-4">
            <label class="block text-sm font-medium text-(--text-main) mb-2">Pilih File Excel</label>
            <input
              type="file"
              accept=".xlsx, .xls, .csv"
              @change="handleFileChange"
              class="block w-full text-sm text-(--text-main) file:mr-4 file:py-2.5 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-(--primary)/10 file:text-(--primary) hover:file:bg-(--primary)/20 cursor-pointer border border-(--border-soft) rounded-md p-1"
            />
          </div>
        </div>

        <div class="flex justify-end pt-4 border-t border-(--border-soft)">
          <BaseButton variant="primary" @click="submitImport" :disabled="importLoading" :loading="importLoading">
            <template #icon-left v-if="!importLoading">
              <IconUpload class="w-4 h-4" />
            </template>
            <template v-if="importLoading">Memproses...</template>
            <template v-else>Import Sekarang</template>
          </BaseButton>
        </div>
      </div>
    </BaseCard>
  </div>
</template>
