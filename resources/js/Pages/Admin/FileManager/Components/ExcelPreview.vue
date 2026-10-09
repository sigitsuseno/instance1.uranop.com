<script setup>
import { onMounted, ref, watch } from 'vue'
import { useApi } from '../../../../composables/useApi'

const props = defineProps({
  path: { type: String, required: true },
})

const { get } = useApi()

const loading = ref(true)
const error = ref('')
const sheets = ref([])
const activeIndex = ref(0)
const limits = ref({ rows: 0, columns: 0 })

async function load() {
  loading.value = true
  error.value = ''
  sheets.value = []
  activeIndex.value = 0

  try {
    const res = await get(`/api/v1/file-manager/excel?path=${encodeURIComponent(props.path)}`)
    const data = res.data || {}
    sheets.value = data.sheets || []
    limits.value = { rows: data.max_rows || 0, columns: data.max_columns || 0 }

    if (!sheets.value.length) {
      error.value = 'Berkas ini tidak memiliki sheet yang bisa dibaca.'
    }
  } catch (e) {
    error.value = e.response?.data?.message || e.message || 'Gagal membaca isi berkas.'
  } finally {
    loading.value = false
  }
}

onMounted(load)
watch(() => props.path, load)
</script>

<template>
  <div>
    <div v-if="loading" class="flex flex-col items-center justify-center py-16">
      <i class="bx bx-loader-alt bx-spin text-3xl text-(--primary)"></i>
      <p class="mt-3 text-sm text-(--text-muted)">Membaca isi berkas…</p>
    </div>

    <div v-else-if="error" class="flex flex-col items-center justify-center py-16 text-center">
      <i class="bx bx-error-circle text-4xl text-(--warning)"></i>
      <p class="mt-3 max-w-md text-sm text-(--text-muted)">{{ error }}</p>
    </div>

    <div v-else class="space-y-3">
      <!-- Tab sheet -->
      <div v-if="sheets.length > 1" class="flex flex-wrap gap-1.5">
        <button
          v-for="(sheet, index) in sheets"
          :key="sheet.name"
          type="button"
          :class="[
            'rounded-md px-3 py-1.5 text-xs font-medium transition-colors',
            index === activeIndex
              ? 'bg-(--primary)/10 text-(--primary)'
              : 'text-(--text-muted) hover:bg-(--bg-elevated) hover:text-(--text-main)',
          ]"
          @click="activeIndex = index"
        >
          {{ sheet.name }}
        </button>
      </div>

      <template v-if="sheets[activeIndex]">
        <p
          v-if="sheets[activeIndex].truncated"
          class="flex items-center gap-2 rounded-md border border-(--warning)/30 bg-(--warning)/5 px-3 py-2 text-xs text-(--text-muted)"
        >
          <i class="bx bx-info-circle text-base text-(--warning)"></i>
          Menampilkan {{ limits.rows }} baris pertama dari
          {{ sheets[activeIndex].total_rows }} baris pada sheet
          <span class="font-semibold text-(--text-main)">{{ sheets[activeIndex].name }}</span>.
          Unduh berkasnya untuk melihat seluruh isi.
        </p>

        <div class="max-h-[55vh] overflow-auto rounded-md border border-(--border-soft)">
          <table class="w-full border-collapse text-left text-xs">
            <tbody>
              <tr
                v-for="(row, rowIndex) in sheets[activeIndex].rows"
                :key="rowIndex"
                :class="rowIndex === 0 ? 'bg-(--bg-elevated) font-semibold' : ''"
                class="border-b border-(--border-soft) last:border-b-0"
              >
                <td
                  class="w-12 border-r border-(--border-soft) px-2 py-1.5 text-right text-(--text-soft)"
                >
                  {{ rowIndex + 1 }}
                </td>
                <td
                  v-for="(cell, cellIndex) in row"
                  :key="cellIndex"
                  class="whitespace-nowrap px-3 py-1.5 text-(--text-main)"
                >
                  {{ cell }}
                </td>
              </tr>
            </tbody>
          </table>

          <p
            v-if="!sheets[activeIndex].rows.length"
            class="px-4 py-10 text-center text-sm text-(--text-muted)"
          >
            Sheet ini kosong.
          </p>
        </div>
      </template>
    </div>
  </div>
</template>
