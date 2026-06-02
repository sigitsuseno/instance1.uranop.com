<script setup>
import { ref, onMounted } from 'vue'
import { useApi } from '../../../../composables/useApi'
import { useNotificationStore } from '../../../../Stores/notification'

import BaseCard from '../../../../Components/BaseCard.vue'
import BaseButton from '../../../../Components/BaseButton.vue'
import BaseModal from '../../../../Components/BaseModal.vue'
import TextInput from '../../../../Components/TextInput.vue'
import DataTable from '../../../../Components/Table/DataTable.vue'

const { get, put } = useApi()
const notificationStore = useNotificationStore()

const loading = ref(true)
const saving = ref(false)
const types = ref([])

const modalOpen = ref(false)
const selectedItem = ref(null)

const headers = [
  { key: 'code', label: 'Kode', width: '150px' },
  { key: 'name', label: 'Nama (Sistem)', width: '200px' },
  { key: 'label', label: 'Label Tampilan' },
  { key: 'keterangan', label: 'Keterangan' },
  { key: 'aksi', label: 'Aksi', sortable: false, align: 'right', width: '100px' },
]

const form = ref({
  label: '',
  keterangan: '',
})

const fetchTypes = async () => {
  loading.value = true
  try {
    const response = await get('/api/v1/settings/work-pattern-types')
    types.value = response.data || []
  } catch (error) {
    notificationStore.addNotification('Gagal mengambil data tipe pola kerja', 'error')
  } finally {
    loading.value = false
  }
}

function openEditModal(item) {
  selectedItem.value = item
  form.value = {
    label: item.label || '',
    keterangan: item.keterangan || '',
  }
  modalOpen.value = true
}

function closeModal() {
  modalOpen.value = false
  setTimeout(() => {
    selectedItem.value = null
  }, 200)
}

async function handleSave() {
  saving.value = true
  try {
    await put(`/api/v1/settings/work-pattern-types/${selectedItem.value.id}`, form.value)
    notificationStore.addNotification('Tipe pola kerja berhasil diperbarui', 'success')
    closeModal()
    fetchTypes()
  } catch (error) {
    notificationStore.addNotification('Gagal menyimpan perubahan', 'error')
  } finally {
    saving.value = false
  }
}

onMounted(() => {
  fetchTypes()
})
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h2 class="text-lg font-semibold text-(--text-main)">Tipe Pola Kerja</h2>
        <p class="text-sm text-(--text-muted) mt-1">Kelola label dan keterangan untuk tipe pola kerja sistem</p>
      </div>
    </div>

    <BaseCard :padding="'p-0'" class="overflow-hidden border-(--border-soft) shadow-sm">
      <div v-if="loading" class="p-12 flex flex-col items-center justify-center">
        <div class="w-10 h-10 border-4 border-(--primary)/30 border-t-(--primary) rounded-full animate-spin mb-4"></div>
        <p class="text-(--text-muted)">Memuat data...</p>
      </div>
      
      <DataTable 
        v-else 
        :headers="headers" 
        :items="types" 
        empty-text="Belum ada data tipe pola kerja"
      >
        <template #item.code="{ value }">
          <span class="inline-flex items-center px-2 py-1 rounded-md bg-(--bg-elevated) text-(--text-main) font-mono text-xs border border-(--border-soft)">
            {{ value }}
          </span>
        </template>

        <template #item.aksi="{ item }">
          <div class="flex items-center justify-end gap-2">
            <button
              class="w-8 h-8 flex items-center justify-center rounded-md text-(--text-muted) hover:text-(--primary) hover:bg-(--primary)/10 transition-colors"
              title="Edit"
              @click="openEditModal(item)"
            >
              <i class="bx bx-edit-alt text-lg"></i>
            </button>
          </div>
        </template>
      </DataTable>
    </BaseCard>

    <!-- Edit Modal -->
    <BaseModal :show="modalOpen" title="Edit Tipe Pola Kerja" size="md" @close="closeModal">
      <form @submit.prevent="handleSave">
        <div class="space-y-5">
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium mb-1 text-(--text-muted)">Kode Sistem</label>
              <input type="text" :value="selectedItem?.code" disabled class="w-full px-3 py-2 rounded-md border border-(--border-soft) bg-(--bg-elevated) text-(--text-muted) cursor-not-allowed" />
            </div>
            <div>
              <label class="block text-sm font-medium mb-1 text-(--text-muted)">Nama Sistem</label>
              <input type="text" :value="selectedItem?.name" disabled class="w-full px-3 py-2 rounded-md border border-(--border-soft) bg-(--bg-elevated) text-(--text-muted) cursor-not-allowed" />
            </div>
          </div>
          
          <TextInput 
            v-model="form.label" 
            label="Label Tampilan" 
            placeholder="Contoh: Shift Tetap" 
            required 
          />
          
          <div>
            <label class="block text-sm font-medium mb-1 text-(--text-main)">Keterangan</label>
            <textarea 
              v-model="form.keterangan" 
              rows="3"
              placeholder="Jelaskan deskripsi tipe ini"
              class="w-full px-3 py-2 rounded-md border border-(--border-soft) bg-(--bg-card) text-(--text-main) placeholder:text-(--text-soft) hover:border-(--text-soft) focus:outline-none focus:ring-4 focus:ring-(--primary-glow) focus:border-(--primary) transition-all duration-300 resize-none"
            ></textarea>
          </div>
        </div>
      </form>
      <template #footer>
        <BaseButton variant="ghost" @click="closeModal">Batal</BaseButton>
        <BaseButton variant="primary" @click="handleSave" :disabled="saving" class="min-w-[120px]">
          <span v-if="saving" class="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin mr-2"></span>
          Simpan Perubahan
        </BaseButton>
      </template>
    </BaseModal>
  </div>
</template>
