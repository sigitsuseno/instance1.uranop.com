<template>
  <div>
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-xl font-semibold text-(--text-main)">Kalender Kerja</h1>
        <p class="text-sm text-(--text-muted) mt-1">Kelola kalender kerja dan hari libur perusahaan</p>
      </div>
      <BaseButton variant="primary" @click="openForm(null)">
        <template #icon-left>
          <IconPlus class="w-4 h-4" />
        </template>
        Tambah Kalender
      </BaseButton>
    </div>

    <!-- Search & Filter -->
    <div class="bg-(--bg-card) border border-(--border-soft) rounded-md p-4 mb-6">
      <div class="flex flex-col sm:flex-row gap-4">
        <div class="flex-1 relative">
          <input
            type="text"
            placeholder="Cari kalender..."
            v-model="searchValue"
            class="w-full h-10 pl-3 pr-4 rounded-md bg-(--bg-card) border border-(--border-strong) text-sm text-(--text-main) focus:ring-2 focus:ring-(--primary-glow)"
          />
        </div>
        <div>
          <select
            v-model="yearFilter"
            class="h-10 px-3 rounded-md bg-(--bg-card) border border-(--border-strong) text-sm text-(--text-main) focus:ring-2 focus:ring-(--primary-glow)"
          >
            <option value="">Semua Tahun</option>
            <option v-for="y in availableYears" :key="y" :value="y">{{ y }}</option>
          </select>
        </div>
      </div>
    </div>

    <!-- Calendars Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6" v-if="filteredCalendars.length > 0">
      <div
        v-for="cal in filteredCalendars"
        :key="cal.id"
        class="bg-(--bg-card) border border-(--border-soft) rounded-md p-5 flex flex-col justify-between hover:shadow-md transition-shadow group relative"
      >
        <!-- Card actions on hover -->
        <div class="absolute top-4 right-4 flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
          <button
            @click="openForm(cal)"
            class="p-1 text-(--text-muted) hover:text-(--primary) hover:bg-(--primary)/10 rounded transition-colors"
            title="Edit"
          >
            <IconPencil class="w-4 h-4" />
          </button>
          <button
            @click="confirmDelete = cal"
            class="p-1 text-(--text-muted) hover:text-red-500 hover:bg-red-50 rounded transition-colors"
            title="Hapus"
          >
            <IconTrash class="w-4 h-4" />
          </button>
        </div>

        <div>
          <h3 class="font-bold text-(--text-main) text-base pr-12">{{ cal.name }}</h3>
          <span class="inline-block mt-1 text-xs font-mono text-(--text-muted) bg-(--bg-elevated) px-2 py-0.5 rounded-md">
            Tahun {{ cal.year }}
          </span>

          <div class="mt-4 space-y-2 text-xs text-(--text-muted)">
            <div class="flex items-center gap-2">
              <span>🏢 Semua Cabang</span>
            </div>
            <div class="flex items-center gap-2">
              <span>📅 {{ cal.weekend_days?.length || 0 }} Hari Libur Mingguan</span>
            </div>
            <div class="flex items-center gap-2">
              <span>🎉 {{ cal.holidays?.length || 0 }} Hari Libur Terdaftar</span>
            </div>
          </div>
        </div>

        <div class="mt-6 pt-4 border-t border-(--border-soft) flex justify-between items-center">
          <router-link
            :to="{ name: 'schedule.calendars.show', params: { id: cal.id } }"
            class="text-sm font-semibold text-(--primary) hover:text-(--primary-hover) inline-flex items-center gap-1"
          >
            Lihat Detail &rarr;
          </router-link>
        </div>
      </div>
    </div>

    <!-- Empty state -->
    <div v-else class="text-center py-16 bg-(--bg-card) border border-(--border-soft) rounded-md">
      <p class="text-(--text-muted)">Belum ada kalender yang sesuai filter</p>
    </div>

    <!-- Calendar Form Modal -->
    <BaseModal :show="!!formVisible" :title="editingItem?.id ? 'Edit Kalender' : 'Tambah Kalender'" size="md" @close="formVisible = null">
      <div class="space-y-4">
        <TextInput v-model="form.name" label="Nama Kalender" placeholder="Contoh: Kalender Kerja Utama 2026" />
        <TextInput v-model.number="form.year" label="Tahun" type="number" placeholder="2026" />

        <div>
          <label class="block text-sm font-medium text-(--text-main) mb-2">Hari Libur Mingguan (Weekend)</label>
          <div class="grid grid-cols-2 gap-2">
            <label v-for="d in days" :key="d.value" class="flex items-center gap-2 p-2 rounded-md border border-(--border-soft) cursor-pointer hover:bg-(--bg-elevated)/45 transition-colors">
              <input
                type="checkbox"
                :value="d.value"
                v-model="form.weekend_days"
                class="rounded border-(--border-strong) text-(--primary) focus:ring-(--primary-glow)"
              />
              <span class="text-sm text-(--text-main)">{{ d.label }}</span>
            </label>
          </div>
        </div>
      </div>
      <template #footer>
        <BaseButton variant="ghost" @click="formVisible = null">Batal</BaseButton>
        <BaseButton variant="primary" @click="saveCalendar">Simpan</BaseButton>
      </template>
    </BaseModal>

    <!-- Confirm delete -->
    <ConfirmDialog
      :show="!!confirmDelete"
      title="Hapus Kalender"
      :message="`Apakah Anda yakin ingin menghapus kalender '${confirmDelete?.name}'?`"
      confirm-text="Hapus"
      variant="danger"
      @confirm="deleteCalendar"
      @cancel="confirmDelete = null"
    />
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useScheduleStore } from '../../../../Stores/schedule'
import BaseButton from '../../../../Components/BaseButton.vue'
import BaseCard from '../../../../Components/BaseCard.vue'
import BaseModal from '../../../../Components/BaseModal.vue'
import TextInput from '../../../../Components/TextInput.vue'
import ConfirmDialog from '../../../../Components/ConfirmDialog.vue'
import { IconPlus, IconPencil, IconTrash } from '../../../../Components/Icons/index.js'

const store = useScheduleStore()
const router = useRouter()

onMounted(() => {
  store.fetchCalendars()
})

const searchValue = ref('')
const yearFilter = ref('')

const days = [
  { value: 0, label: 'Minggu' },
  { value: 1, label: 'Senin' },
  { value: 2, label: 'Selasa' },
  { value: 3, label: 'Rabu' },
  { value: 4, label: 'Kamis' },
  { value: 5, label: 'Jumat' },
  { value: 6, label: 'Sabtu' },
]

const availableYears = computed(() => {
  const years = new Set()
  store.calendars.forEach(c => years.add(c.year))
  return Array.from(years).sort((a, b) => b - a)
})

const filteredCalendars = computed(() => {
  return store.calendars.filter(cal => {
    const matchesSearch = cal.name.toLowerCase().includes(searchValue.value.toLowerCase())
    const matchesYear = !yearFilter.value || cal.year === parseInt(yearFilter.value)
    return matchesSearch && matchesYear
  })
})

const formVisible = ref(null)
const editingItem = ref(null)
const confirmDelete = ref(null)

const form = reactive({
  name: '',
  year: 2026,
  weekend_days: [0, 6],
})

function openForm(item) {
  editingItem.value = item
  if (item) {
    form.name = item.name
    form.year = item.year
    form.weekend_days = [...(item.weekend_days || [])]
  } else {
    form.name = ''
    form.year = 2026
    form.weekend_days = [0, 6]
  }
  formVisible.value = {}
}

function saveCalendar() {
  const item = {
    id: editingItem.value?.id || store.calendars.length + 1,
    name: form.name,
    year: form.year,
    weekend_days: [...form.weekend_days],
    holidays: editingItem.value?.holidays || [],
  }
  
  if (editingItem.value?.id) {
    const idx = store.calendars.findIndex(c => c.id === editingItem.value.id)
    if (idx !== -1) store.calendars[idx] = item
  } else {
    store.calendars.push(item)
  }
  
  formVisible.value = null
  editingItem.value = null
}

function deleteCalendar() {
  if (confirmDelete.value) {
    store.calendars = store.calendars.filter(c => c.id !== confirmDelete.value.id)
    confirmDelete.value = null
  }
}
</script>
