<template>
  <div class="space-y-6">
    <!-- Page Header -->
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-3">
        <BaseButton variant="ghost" size="sm" @click="goBack" class="!h-10 !px-3">
          <template #icon-left>
            <IconArrowLeft class="w-4 h-4" />
          </template>
          Kembali
        </BaseButton>
        <div>
          <h1 class="text-xl font-semibold text-(--text-main)">Import Roster Jadwal Shift</h1>
          <p class="text-xs text-(--text-muted) mt-1">Upload jadwal shift karyawan secara massal menggunakan file Excel</p>
        </div>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
      <!-- Left Column: Upload Form (8 cols) -->
      <div class="lg:col-span-8 space-y-6">
        <BaseCard>
          <form @submit.prevent="submitImport" class="space-y-6">
            <!-- Step 1: Periode -->
            <div>
              <div class="flex items-center gap-2 mb-4">
                <span class="w-6 h-6 rounded-md bg-(--primary) text-white font-bold text-xs flex items-center justify-center">1</span>
                <span class="text-sm font-bold text-(--text-main)">Konfigurasi Periode Target</span>
              </div>

              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <select v-model="form.month" class="w-full h-10 px-3 text-xs rounded-md bg-(--bg-card) border border-(--border-strong) text-(--text-main) focus:ring-2 focus:ring-(--primary-glow)">
                    <option v-for="m in months" :key="m.value" :value="m.value">{{ m.label }}</option>
                  </select>
                </div>
                <div>
                  <select v-model="form.year" class="w-full h-10 px-3 text-xs rounded-md bg-(--bg-card) border border-(--border-strong) text-(--text-main) focus:ring-2 focus:ring-(--primary-glow)">
                    <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
                  </select>
                </div>
              </div>

              <!-- Period Target Summary Alert -->
              <div class="mt-4 p-3 bg-(--primary-glow)/5 rounded-md border border-(--primary-glow)/10 flex items-start gap-3">
                <span class="text-sm">ℹ️</span>
                <div class="text-xs">
                  <span class="font-bold text-(--text-main)">Target Periode Roster:</span>
                  <p class="text-(--text-muted) mt-0.5">
                    Mulai tanggal <span class="font-bold text-(--primary)">25 {{ monthLabels[prevMonthIndex] }} {{ prevMonthYear }}</span> sampai dengan <span class="font-bold text-(--primary)">24 {{ monthLabels[form.month - 1] }} {{ form.year }}</span>.
                  </p>
                </div>
              </div>
            </div>

            <!-- Step 2: Dropzone File -->
            <div>
              <div class="flex items-center gap-2 mb-4">
                <span class="w-6 h-6 rounded-md bg-(--primary) text-white font-bold text-xs flex items-center justify-center">2</span>
                <span class="text-sm font-bold text-(--text-main)">Upload File Roster</span>
              </div>

              <div
                @dragover.prevent="isDragging = true"
                @dragleave.prevent="isDragging = false"
                @drop.prevent="handleDrop"
                class="border-2 border-dashed rounded-md p-10 text-center transition-all cursor-pointer"
                :class="isDragging ? 'border-(--primary) bg-(--primary-glow)/5' : 'border-(--border-soft) hover:border-(--border-strong)'"
                @click="triggerFileSelect"
              >
                <input type="file" ref="fileInput" @change="handleFileSelect" class="hidden" accept=".xlsx,.xls" />
                
                <div v-if="!form.file" class="space-y-3">
                  <div class="w-12 h-12 bg-(--bg-elevated) rounded-md flex items-center justify-center mx-auto text-xl">
                    📤
                  </div>
                  <div>
                    <p class="text-sm font-bold text-(--text-main)">Klik atau seret file Excel ke sini</p>
                    <p class="text-xs text-(--text-muted) mt-0.5">Format file yang didukung: .xlsx atau .xls</p>
                  </div>
                </div>

                <!-- Selected File Display -->
                <div v-else class="flex items-center justify-center gap-4">
                  <div class="w-10 h-10 bg-emerald-100 dark:bg-emerald-950/20 text-emerald-600 rounded flex items-center justify-center text-xl font-bold">
                    📊
                  </div>
                  <div class="text-left">
                    <p class="text-xs font-bold text-(--text-main) max-w-[250px] truncate">{{ form.file.name }}</p>
                    <p class="text-[10px] text-emerald-600 font-bold uppercase">{{ formatSize(form.file.size) }}</p>
                  </div>
                  <button type="button" @click.stop="clearFile" class="text-rose-500 hover:text-rose-700 text-sm font-bold p-1 rounded hover:bg-rose-50 dark:hover:bg-rose-950/10">
                    Hapus
                  </button>
                </div>
              </div>
            </div>

            <!-- Submit Button -->
            <div class="flex justify-end pt-4 border-t border-(--border-soft)">
              <BaseButton variant="primary" type="submit" :disabled="!form.file || processing">
                <span v-if="processing">Memproses Import...</span>
                <span v-else>Mulai Import Roster</span>
              </BaseButton>
            </div>
          </form>
        </BaseCard>
      </div>

      <!-- Right Column: Sidebar Reference (4 cols) -->
      <div class="lg:col-span-4 space-y-6">
        <!-- Download Template -->
        <div class="bg-gradient-to-br from-blue-600 to-indigo-700 rounded-md p-5 text-white space-y-3 shadow-sm">
          <h3 class="font-bold text-sm flex items-center gap-2">
            <span>Template Excel Roster</span>
          </h3>
          <p class="text-xs text-blue-100 leading-relaxed">
            Download template excel kami untuk menyusun daftar roster shift karyawan secara rapi dan menghindari error.
          </p>
          <button @click="downloadTemplate" class="w-full py-2 bg-white text-blue-700 font-bold rounded text-xs hover:bg-blue-50 active:scale-[0.98] transition-all">
            Download Template Roster
          </button>
        </div>

        <!-- Shift Codes Info -->
        <BaseCard>
          <template #title>Kode Shift Referensi</template>
          <template #subtitle>Gunakan kode ini di dalam file Excel</template>

          <div class="mt-4 space-y-2">
            <div
              v-for="s in store.shifts"
              :key="s.id"
              class="p-2.5 bg-(--bg-elevated)/45 border border-(--border-soft) rounded-md flex items-center justify-between text-xs"
            >
              <span class="font-bold px-2 py-0.5 rounded border border-(--primary-glow)/30 bg-(--primary-glow)/10 text-(--primary)">
                {{ s.code }}
              </span>
              <span class="font-semibold text-(--text-main)">{{ s.name }}</span>
            </div>
            <div class="p-2.5 bg-rose-500/5 border border-rose-200/40 rounded-md flex items-center justify-between text-xs">
              <span class="font-bold px-2 py-0.5 rounded border border-rose-200 bg-rose-100 text-rose-700">
                L
              </span>
              <span class="font-semibold text-rose-700">Libur (Off Day)</span>
            </div>
          </div>
        </BaseCard>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useScheduleStore } from '../../../../Stores/schedule'
import BaseButton from '../../../../Components/BaseButton.vue'
import BaseCard from '../../../../Components/BaseCard.vue'
import { IconArrowLeft } from '../../../../Components/Icons/index.js'
import { useApi } from '../../../../composables/useApi'

const router = useRouter()
const store = useScheduleStore()
const { post } = useApi()

const fileInput = ref(null)
const isDragging = ref(false)
const processing = ref(false)

const months = [
  { value: 1, label: 'Januari' },
  { value: 2, label: 'Februari' },
  { value: 3, label: 'Maret' },
  { value: 4, label: 'April' },
  { value: 5, label: 'Mei' },
  { value: 6, label: 'Juni' },
  { value: 7, label: 'Juli' },
  { value: 8, label: 'Agustus' },
  { value: 9, label: 'September' },
  { value: 10, label: 'Oktober' },
  { value: 11, label: 'November' },
  { value: 12, label: 'Desember' }
]

const monthLabels = months.map(m => m.label)

const currentYear = new Date().getFullYear()
const years = Array.from({ length: 5 }, (_, i) => currentYear - 2 + i)

const form = reactive({
  month: new Date().getMonth() + 1,
  year: currentYear,
  file: null,
})

const prevMonthIndex = computed(() => {
  return form.month === 1 ? 11 : form.month - 2
})

const prevMonthYear = computed(() => {
  return form.month === 1 ? form.year - 1 : form.year
})

function goBack() {
  router.push({ name: 'schedule.roster' })
}

function triggerFileSelect() {
  fileInput.value.click()
}

function handleFileSelect(event) {
  const file = event.target.files[0]
  if (file) {
    form.file = file
  }
}

function handleDrop(event) {
  isDragging.value = false
  const file = event.dataTransfer.files[0]
  if (file && (file.name.endsWith('.xlsx') || file.name.endsWith('.xls'))) {
    form.file = file
  } else {
    alert('Hanya file Excel (.xlsx atau .xls) yang diperbolehkan!')
  }
}

function clearFile() {
  form.file = null
  if (fileInput.value) {
    fileInput.value.value = ''
  }
}

function formatSize(bytes) {
  if (bytes === 0) return '0 Bytes'
  const k = 1024
  const sizes = ['Bytes', 'KB', 'MB']
  const i = Math.floor(Math.log(bytes) / Math.log(k))
  return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i]
}

function downloadTemplate() {
  alert('Men-download Template excel roster... (Simulasi)')
}

async function submitImport() {
  if (!form.file) return
  
  processing.value = true
  
  try {
    const formData = new FormData()
    formData.append('file', form.file)
    formData.append('month', form.month)
    formData.append('year', form.year)
    
    const res = await post('/api/schedule/roster/import', formData)
    
    alert(res.message || `File "${form.file.name}" berhasil di-import.`)
    router.push({ name: 'schedule.roster' })
  } catch (error) {
    console.error('Failed to import', error)
    alert(error.message || 'Gagal melakukan import roster.')
  } finally {
    processing.value = false
  }
}
</script>
