<script setup>
import { ref, reactive } from 'vue'
import BaseButton from '../../../../Components/BaseButton.vue'
import BaseCard from '../../../../Components/BaseCard.vue'
import TextInput from '../../../../Components/TextInput.vue'
import { IconPencil } from '../../../../Components/Icons/index.js'
import { useNotification } from '../../../../composables/useNotification'

const notification = useNotification()
const isEditing = ref(false)

// Mock Data
const attendanceSettings = reactive({
  work_start: '08:00',
  work_end: '17:00',
  tolerance: 30,
  max_overtime: 4,
})

const form = reactive({
  work_start: '08:00',
  work_end: '17:00',
  tolerance: 30,
  max_overtime: 4,
})

function toggleEdit() {
  if (isEditing.value) {
    isEditing.value = false
  } else {
    isEditing.value = true
    form.work_start = attendanceSettings.work_start
    form.work_end = attendanceSettings.work_end
    form.tolerance = attendanceSettings.tolerance
    form.max_overtime = attendanceSettings.max_overtime
  }
}

function save() {
  attendanceSettings.work_start = form.work_start
  attendanceSettings.work_end = form.work_end
  attendanceSettings.tolerance = form.tolerance
  attendanceSettings.max_overtime = form.max_overtime
  isEditing.value = false
  notification.success('Pengaturan absensi berhasil disimpan')
}
</script>

<template>
  <div class="space-y-6">
    <div>
      <h2 class="text-lg font-medium text-(--text-main)">Pengaturan Absensi & Lembur</h2>
      <p class="text-sm text-(--text-muted)">Atur jam operasional standar, toleransi, dan batasan lembur.</p>
    </div>

    <BaseCard>
      <template #title>Jam Kerja & Toleransi Default</template>
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
          <span class="text-xs text-(--text-muted)">Jam Masuk Default</span>
          <p class="text-sm text-(--text-main) font-medium">{{ attendanceSettings.work_start }}</p>
        </div>
        <div>
          <span class="text-xs text-(--text-muted)">Jam Pulang Default</span>
          <p class="text-sm text-(--text-main) font-medium">{{ attendanceSettings.work_end }}</p>
        </div>
        <div>
          <span class="text-xs text-(--text-muted)">Toleransi Keterlambatan</span>
          <p class="text-sm text-(--text-main) font-medium">{{ attendanceSettings.tolerance }} Menit</p>
        </div>
        <div>
          <span class="text-xs text-(--text-muted)">Maks. Jam Lembur</span>
          <p class="text-sm text-(--text-main) font-medium">{{ attendanceSettings.max_overtime }} Jam / Hari</p>
        </div>
      </div>

      <div v-else class="space-y-4">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
          <TextInput v-model="form.work_start" label="Jam Masuk" type="time" />
          <TextInput v-model="form.work_end" label="Jam Pulang" type="time" />
          <TextInput v-model="form.tolerance" label="Toleransi (Menit)" type="number" />
          <TextInput v-model="form.max_overtime" label="Maks. Lembur (Jam)" type="number" />
        </div>
        <div class="pt-2">
          <BaseButton variant="primary" size="sm" @click="save" icon="bx bx-save">Simpan Perubahan</BaseButton>
        </div>
      </div>
    </BaseCard>

    <BaseCard>
      <template #title>Data Master (Tabel Referensi)</template>
      <div class="space-y-4">
        <p class="text-sm text-(--text-muted)">Aturan lembur (Overtime Rules) dan konfigurasi shift/pola kerja dikelola sebagai data master di menu terpisah agar lebih fleksibel diterapkan per karyawan atau per hari.</p>
        
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div class="p-4 border border-(--border-soft) rounded-md bg-(--bg-main) flex items-center justify-between">
            <div>
              <h4 class="text-sm font-medium text-(--text-main)">Aturan Lembur (Overtime Rules)</h4>
              <p class="text-xs text-(--text-muted)">Perhitungan multiplier L1, L2, dst.</p>
            </div>
            <BaseButton variant="ghost" size="sm" icon="bx bx-right-arrow-alt" @click="$router.push('/admin/attendance/overtime')">Ke Menu</BaseButton>
          </div>
          
          <div class="p-4 border border-(--border-soft) rounded-md bg-(--bg-main) flex items-center justify-between">
            <div>
              <h4 class="text-sm font-medium text-(--text-main)">Pola Kerja & Shift</h4>
              <p class="text-xs text-(--text-muted)">Work patterns, tipe shift, dan rotasi.</p>
            </div>
            <BaseButton variant="ghost" size="sm" icon="bx bx-right-arrow-alt" @click="$router.push('/admin/schedule/work-patterns')">Ke Menu</BaseButton>
          </div>
        </div>
      </div>
    </BaseCard>
  </div>
</template>
