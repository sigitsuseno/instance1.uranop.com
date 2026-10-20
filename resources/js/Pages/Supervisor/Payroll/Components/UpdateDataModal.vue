<template>
  <BaseModal :show="show" title="Update Data dari Excel" size="lg" @close="close">
    <div class="space-y-5">

      <!-- ── Langkah 1: periode & segmen ── -->
      <div>
        <label class="block text-sm font-medium text-(--text-main) mb-1">Periode</label>
        <select
          v-model="periodId"
          class="w-full px-3 py-2 text-sm rounded-md border border-(--border-soft) bg-(--bg-elevated) text-(--text-main)"
          :disabled="isBusy"
          @change="resetPreview"
        >
          <option value="">— Pilih periode —</option>
          <option v-for="p in periods" :key="p.id" :value="p.id">{{ p.name }}</option>
        </select>

        <div v-if="selectedPeriod && selectedPeriod.is_split" class="mt-3">
          <label class="block text-sm font-medium text-(--text-main) mb-1">Segmen</label>
          <div class="flex gap-2">
            <button
              v-for="seg in ['A', 'B']"
              :key="seg"
              type="button"
              class="h-8 px-4 rounded-md text-xs font-semibold transition-all cursor-pointer"
              :class="segment === seg
                ? 'bg-(--primary) text-white shadow-sm'
                : 'bg-(--bg-elevated) text-(--text-muted) hover:text-(--text-main) border border-(--border-soft)'"
              :disabled="isBusy"
              @click="segment = seg; resetPreview()"
            >
              Seg-{{ seg === 'A' ? '1' : '2' }}
            </button>
          </div>
          <p class="text-xs text-(--text-muted) mt-1">
            Periode ini split — satu record per segmen, jadi segmen wajib dipilih.
          </p>
        </div>
      </div>

      <!-- ── Langkah 2: berkas ── -->
      <div>
        <label class="block text-sm font-medium text-(--text-main) mb-1">Berkas Excel</label>

        <div
          v-if="file"
          class="flex items-center gap-3 p-3 rounded-md bg-(--bg-elevated) border border-(--border-soft)"
        >
          <div class="flex-1 min-w-0">
            <p class="text-sm font-medium text-(--text-main) truncate">{{ file.name }}</p>
            <p class="text-xs text-(--text-muted) truncate">{{ file.size_human }} · {{ file.path }}</p>
          </div>
          <BaseButton variant="secondary" size="sm" :disabled="isBusy" @click="$emit('pick-file')">
            Ganti
          </BaseButton>
          <BaseButton variant="ghost" size="sm" :disabled="isBusy" @click="clearFile">
            <IconTrash class="w-4 h-4" />
          </BaseButton>
        </div>

        <div
          v-else
          class="border-2 border-dashed border-(--border-soft) rounded-md p-6 text-center"
        >
          <IconFileInvoice class="w-8 h-8 mx-auto mb-2 text-(--text-muted)" />
          <p class="text-sm text-(--text-muted) mb-3">
            Pilih berkas dari folder File Manager.
          </p>
          <BaseButton variant="secondary" size="sm" :disabled="isBusy" @click="$emit('pick-file')">
            <template #icon-left>
              <IconSearch class="w-4 h-4" />
            </template>
            Pilih dari File Manager
          </BaseButton>
        </div>

        <p class="text-xs text-(--text-muted) mt-2">
          Format <strong>LAPORAN SALARY BREAKDOWN</strong> — header di baris 4, data mulai baris 5.
          Kolom <strong>ID No</strong> dicocokkan ke NIP karyawan. Nilai diambil apa adanya
          (tanpa hitung ulang).
        </p>
      </div>

      <!-- ── Error ── -->
      <div v-if="errorMessage" class="p-3 rounded-md bg-red-50 border border-red-200">
        <p class="text-sm text-red-700 flex items-start gap-2">
          <IconAlertTriangle class="w-4 h-4 mt-0.5 shrink-0" />
          {{ errorMessage }}
        </p>
      </div>

      <!-- ── Langkah 3: ringkasan pratinjau ── -->
      <template v-if="summary">
        <div class="grid gap-3 sm:grid-cols-3">
          <div class="p-3 rounded-md bg-(--bg-elevated) border border-(--border-soft)">
            <p class="text-xs text-(--text-muted)">Baris dibaca</p>
            <p class="text-lg font-semibold text-(--text-main)">{{ summary.file.rows_read }}</p>
            <p class="text-xs text-(--text-muted)">{{ summary.file.skipped_non_data }} baris non-data dilewati</p>
          </div>
          <div class="p-3 rounded-md bg-(--bg-elevated) border border-(--border-soft)">
            <p class="text-xs text-(--text-muted)">Akan dibuat</p>
            <p class="text-lg font-semibold text-(--text-main)">{{ summary.will_create }}</p>
            <p class="text-xs text-(--text-muted)">record baru</p>
          </div>
          <div class="p-3 rounded-md bg-(--bg-elevated) border border-(--border-soft)">
            <p class="text-xs text-(--text-muted)">Akan diperbarui</p>
            <p class="text-lg font-semibold text-(--text-main)">{{ summary.will_update }}</p>
            <p class="text-xs text-(--text-muted)">record yang sudah ada</p>
          </div>
        </div>

        <div v-if="issues.length" class="p-3 rounded-md bg-amber-50 border border-amber-200">
          <p class="text-sm font-medium text-amber-800 flex items-center gap-2 mb-1">
            <IconAlertTriangle class="w-4 h-4" /> Catatan
          </p>
          <ul class="list-disc list-inside space-y-0.5">
            <li v-for="(msg, i) in issues" :key="i" class="text-xs text-amber-800">{{ msg }}</li>
          </ul>
        </div>

        <div v-if="summary.unmatched.length" class="rounded-md border border-(--border-soft)">
          <p class="px-3 py-2 text-xs font-medium text-(--text-main) border-b border-(--border-soft) bg-(--bg-elevated)">
            {{ summary.unmatched.length }} baris dilewati — NIP tidak ada di data karyawan
          </p>
          <div class="max-h-40 overflow-auto">
            <table class="w-full text-xs">
              <tbody>
                <tr v-for="u in summary.unmatched" :key="u.row" class="border-b border-(--border-soft) last:border-0">
                  <td class="px-3 py-1 text-(--text-muted) w-16">baris {{ u.row }}</td>
                  <td class="px-3 py-1 text-(--text-main)">{{ u.nip }}</td>
                  <td class="px-3 py-1 text-(--text-muted)">{{ u.nama }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <div class="rounded-md border border-(--border-soft)">
          <p class="px-3 py-2 text-xs font-medium text-(--text-main) border-b border-(--border-soft) bg-(--bg-elevated)">
            Pratinjau{{ summary.preview_truncated ? ' (sebagian)' : '' }} — {{ summary.preview.length }} baris
          </p>
          <div class="max-h-56 overflow-auto">
            <table class="w-full text-xs">
              <thead class="bg-(--bg-elevated) text-(--text-muted) sticky top-0">
                <tr>
                  <th class="px-3 py-1 text-left font-medium">Baris</th>
                  <th class="px-3 py-1 text-left font-medium">ID No</th>
                  <th class="px-3 py-1 text-left font-medium">Nama</th>
                  <th class="px-3 py-1 text-right font-medium">HK</th>
                  <th class="px-3 py-1 text-right font-medium">THP</th>
                  <th class="px-3 py-1 text-left font-medium">Aksi</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="r in summary.preview" :key="r.row" class="border-t border-(--border-soft)">
                  <td class="px-3 py-1 text-(--text-muted)">{{ r.row }}</td>
                  <td class="px-3 py-1 text-(--text-main)">{{ r.nip }}</td>
                  <td class="px-3 py-1 text-(--text-main) truncate max-w-48">{{ r.nama }}</td>
                  <td class="px-3 py-1 text-right text-(--text-main)">{{ r.hari_kerja }}</td>
                  <td class="px-3 py-1 text-right text-(--text-main)">{{ formatCurrency(r.gaji_bersih) }}</td>
                  <td class="px-3 py-1">
                    <span
                      class="px-1.5 py-0.5 rounded text-[11px] font-medium"
                      :class="r.action === 'create'
                        ? 'bg-green-100 text-green-700'
                        : 'bg-blue-100 text-blue-700'"
                    >
                      {{ r.action === 'create' ? 'baru' : 'update' }}
                    </span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </template>
    </div>

    <template #footer>
      <div class="flex items-center justify-between w-full gap-3">
        <p class="text-xs text-(--text-muted)">
          <template v-if="!summary">Susun pratinjau dulu sebelum import.</template>
          <template v-else-if="confirming">Import sekarang? Data periode ini akan ditulis ke database.</template>
          <template v-else>{{ summary.matched }} baris siap di-import.</template>
        </p>

        <div class="flex gap-2">
          <BaseButton variant="ghost" :disabled="isBusy" @click="close">Batal</BaseButton>

          <BaseButton
            v-if="!confirming"
            variant="secondary"
            :loading="previewing"
            :disabled="!canPreview"
            @click="doPreview"
          >
            Pratinjau
          </BaseButton>

          <BaseButton
            v-if="summary && !confirming"
            variant="primary"
            :disabled="isBusy"
            @click="confirming = true"
          >
            Import
          </BaseButton>

          <BaseButton
            v-if="confirming"
            variant="primary"
            :loading="committing"
            @click="doCommit"
          >
            Ya, import
          </BaseButton>
        </div>
      </div>
    </template>
  </BaseModal>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import BaseModal from '@/Components/BaseModal.vue'
import BaseButton from '@/Components/BaseButton.vue'
import {
  IconAlertTriangle,
  IconFileInvoice,
  IconSearch,
  IconTrash,
} from '@/Components/Icons/index.js'
import { useApi } from '@/composables/useApi'
import { useNotificationStore } from '@/Stores/notification'

const props = defineProps({
  show: { type: Boolean, default: false },
  periods: { type: Array, default: () => [] },
  defaultPeriodId: { type: [String, Number], default: '' },
  defaultSegment: { type: String, default: '' },
  file: { type: Object, default: null },
})

const emit = defineEmits(['close', 'pick-file', 'clear-file', 'imported'])

const { post } = useApi()
const notification = useNotificationStore()

const periodId = ref('')
const segment = ref('')
const previewing = ref(false)
const committing = ref(false)
const confirming = ref(false)
const errorMessage = ref('')
const summary = ref(null)
const issues = ref([])

const isBusy = computed(() => previewing.value || committing.value)

const selectedPeriod = computed(() => props.periods.find(p => String(p.id) === String(periodId.value)) || null)

const canPreview = computed(() =>
  !!props.file && !!periodId.value && (!selectedPeriod.value?.is_split || !!segment.value) && !isBusy.value
)

// Ikuti periode & segmen yang sedang dipilih di halaman tiap kali modal dibuka.
watch(() => props.show, (open) => {
  if (open) {
    periodId.value = props.defaultPeriodId || ''
    segment.value = props.defaultSegment || (selectedPeriod.value?.is_split ? 'A' : '')
    resetPreview()
  }
})

// Berkas berganti (dipilih / dihapus) → pratinjau lama tidak berlaku lagi.
watch(() => props.file, () => resetPreview())

function resetPreview() {
  summary.value = null
  issues.value = []
  errorMessage.value = ''
  confirming.value = false
}

function clearFile() {
  emit('clear-file')
  resetPreview()
}

function close() {
  if (isBusy.value) return
  resetPreview()
  emit('close')
}

function payload() {
  const body = { path: props.file.path, period_id: periodId.value }
  if (selectedPeriod.value?.is_split && segment.value) body.segment = segment.value
  return body
}

async function doPreview() {
  previewing.value = true
  resetPreview()

  try {
    const res = await post('/api/v1/supervisor/payroll/breakdown/import/preview', payload())
    summary.value = res.data.summary
    issues.value = res.data.issues || []
  } catch (e) {
    errorMessage.value = e.message || 'Gagal menyusun pratinjau.'
  } finally {
    previewing.value = false
  }
}

async function doCommit() {
  committing.value = true
  errorMessage.value = ''

  try {
    const res = await post('/api/v1/supervisor/payroll/breakdown/import/store', payload())
    notification.success(res.message || 'Import selesai.')
    confirming.value = false
    resetPreview()
    emit('imported')
    emit('close')
  } catch (e) {
    errorMessage.value = e.message || 'Gagal mengimport data.'
    notification.error(errorMessage.value)
  } finally {
    committing.value = false
  }
}

function formatCurrency(value) {
  return new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 }).format(value || 0)
}
</script>
