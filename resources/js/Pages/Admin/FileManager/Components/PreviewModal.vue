<script setup>
import { onBeforeUnmount, ref, watch } from 'vue'
import { fetchFileBlob, releaseObjectUrl, toObjectUrl } from '../../../../composables/useFileBlob'
import BaseModal from '../../../../Components/BaseModal.vue'
import BaseButton from '../../../../Components/BaseButton.vue'
import ExcelPreview from './ExcelPreview.vue'

const props = defineProps({
  show: { type: Boolean, default: false },
  item: { type: Object, default: null },
})

const emit = defineEmits(['close', 'download'])

const loading = ref(false)
const error = ref('')
const objectUrl = ref('')
const textContent = ref('')

// Batas tampilan teks, sekadar menjaga agar tab tidak berat.
const MAX_TEXT_CHARS = 200000

function cleanup() {
  releaseObjectUrl(objectUrl.value)
  objectUrl.value = ''
  textContent.value = ''
  error.value = ''
}

async function load() {
  cleanup()

  const item = props.item
  if (!props.show || !item || item.preview_kind === 'excel') return

  if (!item.preview_kind) {
    error.value = 'Jenis berkas ini tidak bisa dipratinjau. Silakan unduh berkasnya.'
    return
  }

  loading.value = true

  try {
    const blob = await fetchFileBlob(
      `/api/v1/file-manager/preview?path=${encodeURIComponent(item.path)}`
    )

    if (item.preview_kind === 'text') {
      const text = await blob.text()
      textContent.value =
        text.length > MAX_TEXT_CHARS
          ? `${text.slice(0, MAX_TEXT_CHARS)}\n\n… (tampilan dipotong)`
          : text
    } else {
      objectUrl.value = toObjectUrl(blob)
    }
  } catch (e) {
    error.value = e.message || 'Gagal memuat pratinjau.'
  } finally {
    loading.value = false
  }
}

watch(() => [props.show, props.item?.path], load)

onBeforeUnmount(cleanup)

function handleClose() {
  cleanup()
  emit('close')
}
</script>

<template>
  <BaseModal :show="show" :title="item?.name || 'Pratinjau'" size="xl" @close="handleClose">
    <div v-if="loading" class="flex flex-col items-center justify-center py-16">
      <i class="bx bx-loader-alt bx-spin text-3xl text-(--primary)"></i>
      <p class="mt-3 text-sm text-(--text-muted)">Memuat pratinjau…</p>
    </div>

    <div v-else-if="error" class="flex flex-col items-center justify-center py-16 text-center">
      <i class="bx bx-file-blank text-4xl text-(--text-soft)"></i>
      <p class="mt-3 max-w-md text-sm text-(--text-muted)">{{ error }}</p>
      <BaseButton v-if="item" variant="secondary" class="mt-4" @click="emit('download', item)">
        <template #icon-left><i class="bx bx-download text-lg"></i></template>
        Unduh Berkas
      </BaseButton>
    </div>

    <template v-else-if="item">
      <!-- Excel: dibaca dari endpoint /excel -->
      <ExcelPreview v-if="item.preview_kind === 'excel'" :path="item.path" />

      <!-- Gambar -->
      <div v-else-if="item.preview_kind === 'image'" class="flex justify-center">
        <img
          :src="objectUrl"
          :alt="item.name"
          class="max-h-[70vh] max-w-full rounded-md object-contain"
        />
      </div>

      <!-- PDF -->
      <iframe
        v-else-if="item.preview_kind === 'pdf'"
        :src="objectUrl"
        :title="item.name"
        class="h-[70vh] w-full rounded-md border border-(--border-soft)"
      ></iframe>

      <!-- Teks / CSV -->
      <pre
        v-else-if="item.preview_kind === 'text'"
        class="max-h-[70vh] overflow-auto rounded-md border border-(--border-soft) bg-(--bg-elevated) p-4 text-xs whitespace-pre-wrap text-(--text-main)"
      >{{ textContent }}</pre>
    </template>

    <template #footer>
      <BaseButton variant="ghost" @click="handleClose">Tutup</BaseButton>
      <BaseButton v-if="item" variant="secondary" @click="emit('download', item)">
        <template #icon-left><i class="bx bx-download text-lg"></i></template>
        Unduh
      </BaseButton>
    </template>
  </BaseModal>
</template>
