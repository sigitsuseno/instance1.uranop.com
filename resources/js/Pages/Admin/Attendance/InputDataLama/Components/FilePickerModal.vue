<template>
  <BaseModal :show="show" title="Pilih Berkas Absen" size="lg" @close="$emit('close')">
    <div class="space-y-4">
      <!-- Pencarian -->
      <div class="flex items-center gap-2">
        <input
          v-model="search"
          type="text"
          placeholder="Cari nama berkas…"
          class="flex-1 px-3 py-2 text-sm rounded-md border border-(--border-soft) bg-(--bg-elevated) text-(--text-main)"
          @keyup.enter="load(currentPath)"
        />
        <BaseButton variant="secondary" size="sm" :loading="loading" @click="load(currentPath)">Cari</BaseButton>
      </div>

      <!-- Breadcrumb -->
      <div class="flex flex-wrap items-center gap-1 text-xs text-(--text-muted)">
        <button class="hover:text-(--primary)" @click="load('')">Beranda</button>
        <template v-for="crumb in breadcrumbs" :key="crumb.path">
          <span>/</span>
          <button class="hover:text-(--primary)" @click="load(crumb.path)">{{ crumb.name }}</button>
        </template>
      </div>

      <!-- Isi -->
      <div class="border border-(--border-soft) rounded-md max-h-80 overflow-auto">
        <div v-if="loading" class="p-6 text-center text-sm text-(--text-muted)">Memuat…</div>

        <template v-else>
          <div v-if="!folders.length && !files.length" class="p-6 text-center text-sm text-(--text-muted)">
            Tidak ada berkas di folder ini.
          </div>

          <!-- Folder -->
          <button
            v-for="folder in folders"
            :key="folder.path"
            class="w-full flex items-center gap-3 px-3 py-2 text-left text-sm border-b border-(--border-soft) hover:bg-(--bg-elevated)"
            @click="load(folder.path)"
          >
            <span class="text-(--warning)">📁</span>
            <span class="flex-1 text-(--text-main)">{{ folder.name }}</span>
            <span class="text-xs text-(--text-muted)">folder</span>
          </button>

          <!-- Berkas -->
          <button
            v-for="file in files"
            :key="file.path"
            class="w-full flex items-center gap-3 px-3 py-2 text-left text-sm border-b border-(--border-soft)"
            :class="isSelectable(file)
              ? 'hover:bg-(--primary)/5 cursor-pointer'
              : 'opacity-50 cursor-not-allowed'"
            :disabled="!isSelectable(file)"
            @click="pick(file)"
          >
            <span :class="isSelectable(file) ? 'text-(--success)' : 'text-(--text-muted)'">📄</span>
            <span class="flex-1 truncate text-(--text-main)">{{ file.name }}</span>
            <span class="text-xs text-(--text-muted)">{{ file.size_human }}</span>
            <span v-if="isSelectable(file)" class="text-xs text-(--primary)">pilih</span>
            <span v-else class="text-xs text-(--text-muted)">bukan spreadsheet</span>
          </button>
        </template>
      </div>

      <p class="text-xs text-(--text-muted)">
        Hanya berkas spreadsheet (.xlsx, .xls, .xlsm, .xlsb, .ods) yang bisa dipilih.
        Unggah berkas baru lewat menu <strong>File Manager</strong>.
      </p>

      <p v-if="errorMessage" class="text-xs text-red-600">{{ errorMessage }}</p>
    </div>
  </BaseModal>
</template>

<script setup>
import { ref, watch, onMounted } from 'vue'
import BaseModal from '@/Components/BaseModal.vue'
import BaseButton from '@/Components/BaseButton.vue'
import { useApi } from '@/composables/useApi'

const props = defineProps({
  show: { type: Boolean, default: false },
})

const emit = defineEmits(['close', 'select'])

const { get } = useApi()

const SPREADSHEET_EXT = ['xlsx', 'xls', 'xlsm', 'xlsb', 'ods']

const loading = ref(false)
const errorMessage = ref('')
const search = ref('')
const currentPath = ref('')
const folders = ref([])
const files = ref([])
const breadcrumbs = ref([])

const isSelectable = (file) => SPREADSHEET_EXT.includes((file.ext || '').toLowerCase())

async function load(path = '') {
  loading.value = true
  errorMessage.value = ''

  try {
    const params = new URLSearchParams()
    params.set('path', path)
    params.set('per_page', '200')
    if (search.value.trim()) params.set('search', search.value.trim())

    const res = await get(`/api/v1/file-manager/browse?${params.toString()}`)

    currentPath.value = path
    folders.value = res.folders || []
    files.value = res.data || []
    breadcrumbs.value = res.breadcrumbs || []
  } catch (e) {
    errorMessage.value = e.message || 'Gagal memuat daftar berkas.'
    folders.value = []
    files.value = []
  } finally {
    loading.value = false
  }
}

function pick(file) {
  if (!isSelectable(file)) return
  emit('select', file)
  emit('close')
}

// Muat ulang tiap kali modal dibuka supaya isinya tidak basi.
watch(() => props.show, (open) => {
  if (open) {
    search.value = ''
    load('')
  }
})

onMounted(() => {
  if (props.show) load('')
})
</script>
