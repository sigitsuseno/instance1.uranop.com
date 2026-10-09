<script setup>
import { useDate } from '../../../../composables/useDate'
import BaseButton from '../../../../Components/BaseButton.vue'

defineProps({
  folders: { type: Array, default: () => [] },
  files: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
})

const emit = defineEmits(['open-folder', 'preview', 'download', 'rename', 'move', 'delete', 'upload', 'new-folder'])

const { format } = useDate()

// Warna ikon per kategori. Ditulis lengkap sebagai literal agar terbaca
// pemindai kelas Tailwind.
const CATEGORY_COLOR = {
  image: 'text-(--info)',
  pdf: 'text-(--danger)',
  spreadsheet: 'text-(--success)',
  document: 'text-(--primary)',
  text: 'text-(--text-muted)',
  archive: 'text-(--warning)',
  other: 'text-(--text-soft)',
}

function fileColor(file) {
  return CATEGORY_COLOR[file.category] || CATEGORY_COLOR.other
}

const isPreviewable = (file) => Boolean(file.preview_kind)

// Tombol aksi kecil yang muncul saat kartu di-hover.
const ACTION_CLASS =
  'flex h-7 w-7 items-center justify-center rounded text-(--text-muted) transition-colors hover:bg-(--bg-elevated) hover:text-(--primary)'
</script>

<template>
  <div>
    <!-- Memuat -->
    <div v-if="loading" class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
      <div v-for="n in 10" :key="n" class="h-32 animate-pulse rounded-md bg-(--bg-elevated)"></div>
    </div>

    <!-- Kosong -->
    <div v-else-if="!folders.length && !files.length" class="py-16 text-center">
      <i class="bx bx-folder-open text-5xl text-(--text-soft)"></i>
      <p class="mt-3 text-sm text-(--text-muted)">Belum ada berkas di folder ini.</p>
      <div class="mt-5 flex justify-center gap-2">
        <BaseButton variant="secondary" size="sm" @click="emit('new-folder')">
          <template #icon-left><i class="bx bx-folder-plus text-base"></i></template>
          Folder Baru
        </BaseButton>
        <BaseButton variant="primary" size="sm" @click="emit('upload')">
          <template #icon-left><i class="bx bx-cloud-upload text-base"></i></template>
          Upload Berkas
        </BaseButton>
      </div>
    </div>

    <div v-else class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
      <!-- Folder -->
      <div
        v-for="folder in folders"
        :key="folder.path"
        class="group relative flex cursor-pointer flex-col items-center gap-2 rounded-md border border-(--border-soft) bg-(--bg-card) p-4 text-center transition-all hover:border-(--primary)/40 hover:shadow-sm"
        @click="emit('open-folder', folder.path)"
      >
        <i class="bx bxs-folder text-4xl text-(--warning)"></i>

        <p class="w-full truncate text-xs font-semibold text-(--text-main)" :title="folder.name">
          {{ folder.name }}
        </p>
        <p class="text-[11px] text-(--text-muted)">
          {{ folder.item_count }} item
        </p>

        <div
          class="absolute right-1.5 top-1.5 flex gap-0.5 rounded bg-(--bg-card)/90 opacity-0 transition-opacity focus-within:opacity-100 group-hover:opacity-100"
        >
          <button :class="ACTION_CLASS" title="Ganti nama" @click.stop="emit('rename', folder)">
            <i class="bx bx-pencil text-sm"></i>
          </button>
          <button :class="ACTION_CLASS" title="Pindahkan" @click.stop="emit('move', folder)">
            <i class="bx bx-move text-sm"></i>
          </button>
          <button
            :class="[ACTION_CLASS, 'hover:text-(--danger)']"
            title="Hapus"
            @click.stop="emit('delete', folder)"
          >
            <i class="bx bx-trash text-sm"></i>
          </button>
        </div>
      </div>

      <!-- Berkas -->
      <div
        v-for="file in files"
        :key="file.path"
        class="group relative flex cursor-pointer flex-col items-center gap-2 rounded-md border border-(--border-soft) bg-(--bg-card) p-4 text-center transition-all hover:border-(--primary)/40 hover:shadow-sm"
        @click="isPreviewable(file) ? emit('preview', file) : emit('download', file)"
      >
        <i :class="[file.icon, 'text-4xl', fileColor(file)]"></i>

        <p class="w-full truncate text-xs font-medium text-(--text-main)" :title="file.name">
          {{ file.name }}
        </p>
        <p class="text-[11px] text-(--text-muted)">
          {{ file.size_human }} · {{ format(file.modified_at, 'dd MMM yyyy') }}
        </p>

        <div
          class="absolute right-1.5 top-1.5 flex gap-0.5 rounded bg-(--bg-card)/90 opacity-0 transition-opacity focus-within:opacity-100 group-hover:opacity-100"
        >
          <button
            v-if="isPreviewable(file)"
            :class="ACTION_CLASS"
            title="Pratinjau"
            @click.stop="emit('preview', file)"
          >
            <i class="bx bx-show text-sm"></i>
          </button>
          <button :class="ACTION_CLASS" title="Unduh" @click.stop="emit('download', file)">
            <i class="bx bx-download text-sm"></i>
          </button>
          <button :class="ACTION_CLASS" title="Ganti nama" @click.stop="emit('rename', file)">
            <i class="bx bx-pencil text-sm"></i>
          </button>
          <button :class="ACTION_CLASS" title="Pindahkan" @click.stop="emit('move', file)">
            <i class="bx bx-move text-sm"></i>
          </button>
          <button
            :class="[ACTION_CLASS, 'hover:text-(--danger)']"
            title="Hapus"
            @click.stop="emit('delete', file)"
          >
            <i class="bx bx-trash text-sm"></i>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
