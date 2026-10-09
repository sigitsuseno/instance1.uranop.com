<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useApi } from '../../../composables/useApi'
import { fetchFileBlob, saveBlob } from '../../../composables/useFileBlob'
import { useNotificationStore } from '../../../Stores/notification'
import BaseCard from '../../../Components/BaseCard.vue'
import BaseButton from '../../../Components/BaseButton.vue'
import BaseModal from '../../../Components/BaseModal.vue'
import TextInput from '../../../Components/TextInput.vue'
import ConfirmDialog from '../../../Components/ConfirmDialog.vue'
import Pagination from '../../../Components/Table/Pagination.vue'
import Breadcrumbs from './Components/Breadcrumbs.vue'
import FileGrid from './Components/FileGrid.vue'
import UploadModal from './Components/UploadModal.vue'
import PreviewModal from './Components/PreviewModal.vue'
import MoveModal from './Components/MoveModal.vue'

const { get, post } = useApi()
const notification = useNotificationStore()

const TYPE_OPTIONS = [
  { value: '', label: 'Semua jenis' },
  { value: 'image', label: 'Gambar' },
  { value: 'pdf', label: 'PDF' },
  { value: 'spreadsheet', label: 'Spreadsheet' },
  { value: 'document', label: 'Dokumen' },
  { value: 'text', label: 'Teks' },
  { value: 'archive', label: 'Arsip' },
  { value: 'other', label: 'Lainnya' },
]

const PER_PAGE_OPTIONS = [25, 50, 100]

const currentPath = ref('')
const search = ref('')
const typeFilter = ref('')
const page = ref(1)
const perPage = ref(25)

const folders = ref([])
const files = ref([])
const breadcrumbs = ref([])
const meta = ref({ current_page: 1, last_page: 1, per_page: 25, total: 0 })
const loading = ref(false)
const truncated = ref(false)

const showUpload = ref(false)
const showPreview = ref(false)
const showMove = ref(false)
const showNewFolder = ref(false)
const showRename = ref(false)
const showDelete = ref(false)

const submitting = ref(false)
const newFolderName = ref('')
const renameName = ref('')
const renameTarget = ref(null)
const deleteTarget = ref(null)
const previewItem = ref(null)
const movePaths = ref([])
const moveExclude = ref('')

async function fetchItems() {
  loading.value = true

  try {
    const params = new URLSearchParams()
    if (currentPath.value) params.set('path', currentPath.value)
    if (search.value) params.set('search', search.value)
    if (typeFilter.value) params.set('type', typeFilter.value)
    params.set('page', page.value)
    params.set('per_page', perPage.value)

    const res = await get(`/api/v1/file-manager/browse?${params.toString()}`)

    folders.value = res.folders || []
    files.value = res.data || []
    breadcrumbs.value = res.breadcrumbs || []
    meta.value = res.meta || {}
    truncated.value = res.truncated === true
  } catch (e) {
    notification.addNotification(e.message || 'Gagal memuat daftar berkas.', 'error')
    folders.value = []
    files.value = []
    breadcrumbs.value = []
  } finally {
    loading.value = false
  }
}

function openFolder(path) {
  currentPath.value = path
  search.value = ''
  typeFilter.value = ''
  page.value = 1
  fetchItems()
}

function navigateBreadcrumb(path) {
  if (path === currentPath.value) return
  openFolder(path)
}

function handlePageChange(nextPage) {
  page.value = nextPage
  fetchItems()
}

// --- Folder baru ---------------------------------------------------------

function openNewFolder() {
  newFolderName.value = ''
  showNewFolder.value = true
}

async function submitNewFolder() {
  if (submitting.value) return

  const name = newFolderName.value.trim()
  if (!name) {
    notification.addNotification('Nama folder tidak boleh kosong.', 'warning')
    return
  }

  submitting.value = true

  try {
    await post('/api/v1/file-manager/folders', { path: currentPath.value, name })
    notification.addNotification('Folder berhasil dibuat.', 'success')
    showNewFolder.value = false
    fetchItems()
  } catch (e) {
    notification.addNotification(e.message || 'Gagal membuat folder.', 'error')
  } finally {
    submitting.value = false
  }
}

// --- Ganti nama ----------------------------------------------------------

function openRename(item) {
  renameTarget.value = item
  renameName.value = item.name
  showRename.value = true
}

async function submitRename() {
  if (submitting.value || !renameTarget.value) return

  const name = renameName.value.trim()
  if (!name) {
    notification.addNotification('Nama tidak boleh kosong.', 'warning')
    return
  }

  if (name === renameTarget.value.name) {
    showRename.value = false
    return
  }

  submitting.value = true

  try {
    await post('/api/v1/file-manager/rename', { path: renameTarget.value.path, name })
    notification.addNotification('Nama berhasil diubah.', 'success')
    showRename.value = false
    fetchItems()
  } catch (e) {
    notification.addNotification(e.message || 'Gagal mengubah nama.', 'error')
  } finally {
    submitting.value = false
  }
}

// --- Pindah --------------------------------------------------------------

function openMove(item) {
  movePaths.value = [item.path]
  // Folder yang dipindah tidak boleh jadi tujuan bagi dirinya sendiri.
  moveExclude.value = item.type === 'folder' ? item.path : ''
  showMove.value = true
}

// --- Hapus ---------------------------------------------------------------

function openDelete(item) {
  deleteTarget.value = item
  showDelete.value = true
}

async function submitDelete() {
  if (submitting.value || !deleteTarget.value) return

  submitting.value = true

  try {
    await post('/api/v1/file-manager/delete', { paths: [deleteTarget.value.path] })
    notification.addNotification(
      `"${deleteTarget.value.name}" berhasil dihapus.`,
      'success'
    )
    showDelete.value = false
    fetchItems()
  } catch (e) {
    notification.addNotification(e.message || 'Gagal menghapus.', 'error')
  } finally {
    submitting.value = false
  }
}

// --- Pratinjau & unduh ---------------------------------------------------

function openPreview(item) {
  previewItem.value = item
  showPreview.value = true
}

async function downloadFile(item) {
  try {
    // Unduhan lewat fetch + Blob karena endpoint ini butuh header Bearer,
    // yang tidak dikirim oleh window.open maupun tautan biasa.
    const blob = await fetchFileBlob(
      `/api/v1/file-manager/download?path=${encodeURIComponent(item.path)}`
    )
    saveBlob(blob, item.name)
  } catch (e) {
    notification.addNotification(e.message || 'Gagal mengunduh berkas.', 'error')
  }
}

function handleUploaded() {
  fetchItems()
}

function currentFolderName() {
  return breadcrumbs.value.length
    ? breadcrumbs.value[breadcrumbs.value.length - 1].name
    : 'Beranda'
}

function handlePerPageChange() {
  page.value = 1
  fetchItems()
}

// Peringatan eksplisit bahwa penghapusan folder bersifat rekursif dan permanen —
// modul ini tidak punya tempat sampah.
const deleteMessage = computed(() => {
  if (!deleteTarget.value) return ''

  const name = deleteTarget.value.name

  return deleteTarget.value.type === 'folder'
    ? `Folder "${name}" beserta seluruh isinya akan dihapus permanen. Tindakan ini tidak bisa dibatalkan.`
    : `Berkas "${name}" akan dihapus permanen. Tindakan ini tidak bisa dibatalkan.`
})

let searchTimeout = null

watch(search, () => {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    page.value = 1
    fetchItems()
  }, 400)
})

watch(typeFilter, () => {
  page.value = 1
  fetchItems()
})

onMounted(fetchItems)
</script>

<template>
  <div class="space-y-6">
    <!-- Kepala halaman -->
    <div class="flex flex-wrap items-center justify-between gap-4">
      <div class="flex items-center gap-3">
        <div class="flex h-12 w-12 items-center justify-center rounded-md bg-(--primary)/10 text-(--primary)">
          <i class="bx bxs-folder-open text-2xl"></i>
        </div>
        <div>
          <h1 class="text-2xl font-bold text-(--text-main)">File Manager</h1>
          <p class="text-sm text-(--text-muted)">
            Simpan dan kelola berkas aplikasi dalam folder privat.
          </p>
        </div>
      </div>

      <div class="flex gap-2">
        <BaseButton variant="secondary" @click="openNewFolder">
          <template #icon-left><i class="bx bx-folder-plus text-lg"></i></template>
          Folder Baru
        </BaseButton>
        <BaseButton variant="primary" @click="showUpload = true">
          <template #icon-left><i class="bx bx-cloud-upload text-lg"></i></template>
          Upload
        </BaseButton>
      </div>
    </div>

    <!-- Lokasi & penyaring -->
    <BaseCard padding="p-3">
      <div class="space-y-3">
        <Breadcrumbs :items="breadcrumbs" @navigate="navigateBreadcrumb" />

        <div class="flex flex-wrap items-center gap-2">
          <div class="relative min-w-56 flex-1">
            <i class="bx bx-search pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-lg text-(--text-soft)"></i>
            <input
              v-model="search"
              type="search"
              placeholder="Cari berkas di folder ini dan subfoldernya…"
              class="h-10 w-full rounded-md border border-(--border-soft) bg-(--bg-elevated) pl-10 pr-3 text-sm text-(--text-main) outline-none transition-all placeholder:text-(--text-soft) focus:border-(--primary) focus:ring-2 focus:ring-(--primary-glow)"
            />
          </div>

          <select
            v-model="typeFilter"
            class="h-10 rounded-md border border-(--border-soft) bg-(--bg-elevated) px-3 text-sm text-(--text-main) outline-none transition-all focus:border-(--primary) focus:ring-2 focus:ring-(--primary-glow)"
          >
            <option v-for="option in TYPE_OPTIONS" :key="option.value" :value="option.value">
              {{ option.label }}
            </option>
          </select>

          <select
            v-model.number="perPage"
            class="h-10 rounded-md border border-(--border-soft) bg-(--bg-elevated) px-3 text-sm text-(--text-main) outline-none transition-all focus:border-(--primary) focus:ring-2 focus:ring-(--primary-glow)"
            @change="handlePerPageChange"
          >
            <option v-for="option in PER_PAGE_OPTIONS" :key="option" :value="option">
              {{ option }} / halaman
            </option>
          </select>

          <button
            type="button"
            class="flex h-10 w-10 items-center justify-center rounded-md border border-(--border-soft) text-(--text-muted) transition-colors hover:bg-(--bg-elevated) hover:text-(--primary)"
            title="Muat ulang"
            @click="fetchItems"
          >
            <i class="bx bx-refresh text-lg" :class="{ 'bx-spin': loading }"></i>
          </button>
        </div>
      </div>
    </BaseCard>

    <!-- Pemberitahuan hasil pencarian yang dipotong -->
    <div
      v-if="truncated"
      class="flex items-center gap-2 rounded-md border border-(--warning)/30 bg-(--warning)/5 px-4 py-3 text-sm text-(--text-muted)"
    >
      <i class="bx bx-info-circle text-lg text-(--warning)"></i>
      Pencarian dihentikan setelah sejumlah besar berkas diperiksa. Persempit kata kunci
      atau masuk ke subfolder tertentu agar hasilnya lengkap.
    </div>

    <!-- Daftar isi -->
    <BaseCard padding="p-4">
      <FileGrid
        :folders="folders"
        :files="files"
        :loading="loading"
        @open-folder="openFolder"
        @preview="openPreview"
        @download="downloadFile"
        @rename="openRename"
        @move="openMove"
        @delete="openDelete"
        @upload="showUpload = true"
        @new-folder="openNewFolder"
      />

      <div v-if="!loading && meta.total > 0" class="mt-4 border-t border-(--border-soft) pt-4">
        <Pagination
          :current-page="meta.current_page ?? 1"
          :total-pages="meta.last_page ?? 1"
          :total="meta.total ?? 0"
          :per-page="meta.per_page ?? perPage"
          @page-change="handlePageChange"
        />
      </div>
    </BaseCard>

    <!-- Folder baru -->
    <BaseModal :show="showNewFolder" title="Folder Baru" @close="showNewFolder = false">
      <TextInput
        v-model="newFolderName"
        label="Nama Folder"
        placeholder="Contoh: Dokumen Kontrak"
        :disabled="submitting"
        @keyup.enter="submitNewFolder"
      />
      <p class="mt-2 text-xs text-(--text-muted)">
        Dibuat di dalam <span class="font-semibold text-(--text-main)">{{ currentFolderName() }}</span>.
      </p>

      <template #footer>
        <BaseButton variant="ghost" :disabled="submitting" @click="showNewFolder = false">Batal</BaseButton>
        <BaseButton variant="primary" :loading="submitting" @click="submitNewFolder">Simpan</BaseButton>
      </template>
    </BaseModal>

    <!-- Ganti nama -->
    <BaseModal :show="showRename" title="Ganti Nama" @close="showRename = false">
      <TextInput
        v-model="renameName"
        :label="renameTarget?.type === 'folder' ? 'Nama Folder' : 'Nama Berkas'"
        :disabled="submitting"
        @keyup.enter="submitRename"
      />
      <p v-if="renameTarget" class="mt-2 text-xs text-(--text-muted)">
        Sebelumnya: <span class="font-semibold text-(--text-main)">{{ renameTarget.name }}</span>
      </p>

      <template #footer>
        <BaseButton variant="ghost" :disabled="submitting" @click="showRename = false">Batal</BaseButton>
        <BaseButton variant="primary" :loading="submitting" @click="submitRename">Simpan</BaseButton>
      </template>
    </BaseModal>

    <!-- Modal lain -->
    <UploadModal
      :show="showUpload"
      :path="currentPath"
      :folder-name="currentFolderName()"
      @close="showUpload = false"
      @uploaded="handleUploaded"
    />

    <PreviewModal
      :show="showPreview"
      :item="previewItem"
      @close="showPreview = false"
      @download="downloadFile"
    />

    <MoveModal
      :show="showMove"
      :paths="movePaths"
      :exclude="moveExclude"
      @close="showMove = false"
      @moved="fetchItems"
    />

    <ConfirmDialog
      :show="showDelete"
      :title="deleteTarget?.type === 'folder' ? 'Hapus Folder' : 'Hapus Berkas'"
      :message="deleteMessage"
      confirm-text="Hapus"
      cancel-text="Batal"
      variant="danger"
      :loading="submitting"
      @confirm="submitDelete"
      @cancel="showDelete = false"
    />
  </div>
</template>
