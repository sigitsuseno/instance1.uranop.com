<script setup>
import { ref, reactive, onMounted } from 'vue'
import BaseButton from '../../../../Components/BaseButton.vue'
import BaseCard from '../../../../Components/BaseCard.vue'
import SelectInput from '../../../../Components/SelectInput.vue'
import { IconPencil } from '../../../../Components/Icons/index.js'
import { useNotification } from '../../../../composables/useNotification'
import { useApi } from '../../../../composables/useApi'

const notification = useNotification()
const api = useApi()
const isEditing = ref(false)

const generalSettings = reactive({
  timezone: 'Asia/Jakarta',
  date_format: 'DD/MM/YYYY',
  language: 'id',
  currency: 'IDR',
})

const form = reactive({
  timezone: '',
  date_format: '',
  language: '',
  currency: '',
})

async function fetchSettings() {
  try {
    const response = await api.get('/api/v1/settings/general')
    if (response.data) {
      Object.assign(generalSettings, response.data)
    }
  } catch (error) {
    notification.error('Gagal mengambil pengaturan umum')
  }
}

onMounted(() => {
  fetchSettings()
})

function toggleEdit() {
  if (isEditing.value) {
    isEditing.value = false
  } else {
    isEditing.value = true
    Object.assign(form, generalSettings)
  }
}

async function save() {
  try {
    await api.post('/api/v1/settings/general', form)
    Object.assign(generalSettings, form)
    isEditing.value = false
    notification.success('Pengaturan umum berhasil disimpan')
  } catch (error) {
    notification.error('Gagal menyimpan pengaturan umum')
  }
}
</script>

<template>
  <div class="space-y-6">
    <div>
      <h2 class="text-lg font-medium text-(--text-main)">Pengaturan Umum</h2>
      <p class="text-sm text-(--text-muted)">Atur zona waktu, format tanggal, bahasa, dan mata uang dasar sistem.</p>
    </div>

    <BaseCard>
      <template #actions>
        <BaseButton variant="secondary" size="sm" @click="toggleEdit">
          <template #icon-left>
            <IconPencil class="w-4 h-4" />
          </template>
          {{ isEditing ? 'Batal' : 'Edit' }}
        </BaseButton>
      </template>

      <div v-if="!isEditing" class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div>
          <span class="text-xs text-(--text-muted)">Zona Waktu</span>
          <p class="text-sm text-(--text-main) font-medium">{{ generalSettings.timezone }}</p>
        </div>
        <div>
          <span class="text-xs text-(--text-muted)">Format Tanggal</span>
          <p class="text-sm text-(--text-main) font-medium">{{ generalSettings.date_format }}</p>
        </div>
        <div>
          <span class="text-xs text-(--text-muted)">Bahasa</span>
          <p class="text-sm text-(--text-main) font-medium">{{ generalSettings.language === 'id' ? 'Bahasa Indonesia' : 'English' }}</p>
        </div>
        <div>
          <span class="text-xs text-(--text-muted)">Mata Uang Default</span>
          <p class="text-sm text-(--text-main) font-medium">{{ generalSettings.currency }}</p>
        </div>
      </div>

      <div v-else class="space-y-4">
        <div class="grid grid-cols-2 gap-4">
          <SelectInput
            v-model="form.timezone"
            label="Zona Waktu"
            :options="[
              { value: 'Asia/Jakarta', label: 'Asia/Jakarta (WIB)' },
              { value: 'Asia/Makassar', label: 'Asia/Makassar (WITA)' },
              { value: 'Asia/Jayapura', label: 'Asia/Jayapura (WIT)' },
            ]"
          />
          <SelectInput
            v-model="form.date_format"
            label="Format Tanggal"
            :options="[
              { value: 'DD/MM/YYYY', label: 'DD/MM/YYYY' },
              { value: 'MM/DD/YYYY', label: 'MM/DD/YYYY' },
              { value: 'YYYY-MM-DD', label: 'YYYY-MM-DD' },
            ]"
          />
        </div>
        <div class="grid grid-cols-2 gap-4">
          <SelectInput
            v-model="form.language"
            label="Bahasa"
            :options="[
              { value: 'id', label: 'Bahasa Indonesia' },
              { value: 'en', label: 'English' },
            ]"
          />
          <SelectInput
            v-model="form.currency"
            label="Mata Uang Default"
            :options="[
              { value: 'IDR', label: 'IDR (Rupiah)' },
              { value: 'USD', label: 'USD (Dollar)' },
            ]"
          />
        </div>
        <div class="pt-2">
          <BaseButton variant="primary" size="sm" @click="save" icon="bx bx-save">Simpan Perubahan</BaseButton>
        </div>
      </div>
    </BaseCard>
  </div>
</template>
