<script setup>
import { computed, ref, watch } from 'vue'
import { useApi } from '../../../../composables/useApi'
import { useNotificationStore } from '../../../../Stores/notification'
import BaseModal from '../../../../Components/BaseModal.vue'
import BaseButton from '../../../../Components/BaseButton.vue'

const props = defineProps({
  show: { type: Boolean, default: false },
  path: { type: String, default: '' },
  folderName: { type: String, default: 'Beranda' },
})

const emit = defineEmits(['close', 'uploaded'])

const { post } = useApi()
const notification = useNotificationStore()

const selected = ref([])
const dragActive = ref(false)
const uploading = ref(false)
const failed = ref([])

const MAX_FILES = 50
const MAX_SIZE = 20 * 1024 * 1024

// Cermin dari daftar di server, hanya untuk umpan balik cepat.
// Server tetap menjadi penentu akhir.
const BLOCKED_EXT = ['php', 'phtml', 'phar', 'exe', 'bat', 'cmd', 'sh', 'py', 'pl', 'jsp', 'asp', 'dll', 'msi', 'htaccess']

const totalSize = computed(() => selected.value.reduce((sum, f) => sum + f.size, 0))

function formatSize(bytes) {
  const units = ['B', 'KB', 'MB', 'GB']
  let size = bytes
  let unit = 0
  while (size >= 1024 && unit < units.length - 1) {
    size /= 1024
    unit++
  }
  return `${unit === 0 ? size : size.toFixed(1)} ${units[unit]}`
}

function extensionOf(name) {
  return name.includes('.') ? name.split('.').pop().toLowerCase() : ''
}

function acceptFiles(fileList) {
  const incoming = Array.from(fileList || [])

  for (const file of incoming) {
    if (selected.value.length >= MAX_FILES) {
      notification.addNotification(`Maksimal ${MAX_FILES} berkas sekali unggah.`, 'warning')
      break
    }
    if (file.size > MAX_SIZE) {
      notification.addNotification(`"${file.name}" melebihi 20 MB.`, 'warning')
      continue
    }
    if (BLOCKED_EXT.includes(extensionOf(file.name))) {
      notification.addNotification(`"${file.name}" tidak diizinkan jenisnya.`, 'error')
      continue
    }
    if (selected.value.some((f) => f.name === file.name && f.size === file.size)) {
      continue
    }
    selected.value.push(file)
  }
}

function handleDrop(event) {
  dragActive.value = false
  acceptFiles(event.dataTransfer?.files)
}

function handleSelect(event) {
  acceptFiles(event.target.files)
  event.target.value = ''
}

function removeAt(index) {
  selected.value.splice(index, 1)
}

function reset() {
  selected.value = []
  failed.value = []
  dragActive.value = false
}

async function upload() {
  if (!selected.value.length || uploading.value) return

  uploading.value = true
  failed.value = []

  const formData = new FormData()
  if (props.path) formData.append('path', props.path)
  selected.value.forEach((file) => formData.append('files[]', file))

  try {
    const res = await post('/api/v1/file-manager/upload', formData)

    notification.addNotification(res.message || 'Berkas berhasil diunggah.', 'success')

    // Sebagian berkas bisa ditolak server walau permintaannya sukses.
    if (res.data?.errors?.length) {
      failed.value = res.data.errors
      selected.value = []
      emit('uploaded')
      return
    }

    reset()
    emit('uploaded')
    emit('close')
  } catch (e) {
    const errors = e.response?.data?.errors
    if (Array.isArray(errors) && errors.length) {
      failed.value = errors
      notification.addNotification('Sebagian berkas gagal diunggah.', 'warning')
    } else {
      notification.addNotification(e.response?.data?.message || e.message || 'Gagal mengunggah berkas.', 'error')
    }
  } finally {
    uploading.value = false
  }
}

watch(
  () => props.show,
  (visible) => {
    if (!visible) reset()
  }
)
</script>

<template>
  <BaseModal :show="show" title="Upload Berkas" size="lg" @close="emit('close')">
    <div class="space-y-4">
      <p class="text-sm text-(--text-muted)">
        Tujuan: <span class="font-semibold text-(--text-main)">{{ folderName }}</span>
      </p>

      <!-- Area jatuhkan berkas -->
      <div
        :class="[
          'flex flex-col items-center justify-center rounded-md border-2 border-dashed px-6 py-10 text-center transition-colors',
          dragActive
            ? 'border-(--primary) bg-(--primary)/5'
            : 'border-(--border-soft) bg-(--bg-elevated)/40',
        ]"
        @dragenter.prevent="dragActive = true"
        @dragover.prevent="dragActive = true"
        @dragleave.prevent="dragActive = false"
        @drop.prevent="handleDrop"
      >
        <i class="bx bx-cloud-upload text-4xl text-(--primary)"></i>
        <p class="mt-3 text-sm font-medium text-(--text-main)">Tarik berkas ke sini</p>
        <p class="mt-1 text-xs text-(--text-muted)">atau pilih berkas dari komputer Anda</p>

        <label class="mt-4 cursor-pointer">
          <span
            class="inline-flex h-10 items-center rounded-md border border-(--border-soft) bg-(--bg-card) px-4 text-sm font-medium text-(--text-main) transition-colors hover:bg-(--bg-elevated)"
          >
            <i class="bx bx-folder-open mr-2 text-lg"></i>
            Pilih Berkas
          </span>
          <input type="file" multiple class="hidden" @change="handleSelect" />
        </label>

        <p class="mt-3 text-[11px] text-(--text-soft)">
          Maksimal {{ MAX_FILES }} berkas, 20 MB per berkas
        </p>
      </div>

      <!-- Berkas yang akan diunggah -->
      <div v-if="selected.length" class="space-y-2">
        <div class="flex items-center justify-between">
          <p class="text-xs font-semibold uppercase tracking-wider text-(--text-muted)">
            {{ selected.length }} berkas · {{ formatSize(totalSize) }}
          </p>
          <button
            type="button"
            class="text-xs text-(--text-muted) transition-colors hover:text-(--danger)"
            @click="reset"
          >
            Bersihkan
          </button>
        </div>

        <ul class="max-h-48 space-y-1 overflow-y-auto rounded-md border border-(--border-soft)">
          <li
            v-for="(file, index) in selected"
            :key="`${file.name}-${index}`"
            class="flex items-center gap-3 border-b border-(--border-soft) px-3 py-2 last:border-b-0"
          >
            <i class="bx bx-file text-lg text-(--text-muted)"></i>
            <span class="min-w-0 flex-1 truncate text-sm text-(--text-main)" :title="file.name">
              {{ file.name }}
            </span>
            <span class="text-xs text-(--text-muted)">{{ formatSize(file.size) }}</span>
            <button
              type="button"
              class="flex h-7 w-7 items-center justify-center rounded text-(--text-muted) transition-colors hover:text-(--danger)"
              title="Keluarkan dari daftar"
              @click="removeAt(index)"
            >
              <i class="bx bx-x text-base"></i>
            </button>
          </li>
        </ul>
      </div>

      <!-- Berkas yang ditolak server -->
      <div v-if="failed.length" class="rounded-md border border-(--danger)/30 bg-(--danger)/5 p-3">
        <p class="mb-2 flex items-center gap-2 text-xs font-semibold text-(--danger)">
          <i class="bx bx-error-circle text-base"></i>
          {{ failed.length }} berkas ditolak
        </p>
        <ul class="space-y-1 text-xs text-(--text-muted)">
          <li v-for="(item, index) in failed" :key="index">
            <span class="font-medium text-(--text-main)">{{ item.name }}</span> — {{ item.error }}
          </li>
        </ul>
      </div>
    </div>

    <template #footer>
      <BaseButton variant="ghost" :disabled="uploading" @click="emit('close')">Tutup</BaseButton>
      <BaseButton
        variant="primary"
        :loading="uploading"
        :disabled="!selected.length"
        @click="upload"
      >
        <template #icon-left><i class="bx bx-cloud-upload text-lg"></i></template>
        Upload{{ selected.length ? ` (${selected.length})` : '' }}
      </BaseButton>
    </template>
  </BaseModal>
</template>
