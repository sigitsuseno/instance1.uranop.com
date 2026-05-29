<template>
  <div class="space-y-6">
    <BaseCard>
      <template #title>Aturan Lembur</template>
      <template #subtitle>Pengali upah lembur berdasarkan jam kerja</template>
      
      <div class="space-y-4">
        <div class="overflow-x-auto">
          <table class="w-full border-collapse">
            <thead>
              <tr class="bg-(--bg-elevated)">
                <th class="px-4 py-3 text-left text-xs font-semibold text-(--text-muted) uppercase tracking-wider">Jam Ke</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-(--text-muted) uppercase tracking-wider">Pengali</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-(--text-muted) uppercase tracking-wider">Rumus</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-(--text-muted) uppercase tracking-wider">Keterangan</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="rule in rules" :key="rule.hour" class="border-b border-(--border-soft)">
                <td class="px-4 py-3 text-sm text-(--text-main)">{{ rule.hour }}</td>
                <td class="px-4 py-3 text-sm">
                  <div v-if="editing" class="flex items-center gap-2">
                    <input type="number" step="0.5" v-model="rule.multiplier" class="w-20 px-2 py-1 text-sm border border-(--border-soft) rounded focus:outline-none focus:border-(--primary)" />
                    <span class="text-(--text-muted)">x</span>
                  </div>
                  <Badge v-else variant="primary">{{ rule.multiplier }}x</Badge>
                </td>
                <td class="px-4 py-3 text-sm font-mono text-(--text-muted)">{{ rule.formula || `(1/173) * Gaji Pokok * ${rule.multiplier}` }}</td>
                <td class="px-4 py-3 text-sm text-(--text-muted)">{{ rule.description || 'Lembur reguler' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        
        <div class="bg-(--bg-elevated) rounded-md p-4">
          <h4 class="text-sm font-semibold text-(--text-main) mb-2">Ketentuan Umum</h4>
          <ul class="text-sm text-(--text-muted) space-y-1 list-disc list-inside">
            <li>Upah lembur dihitung berdasarkan 1/173 x Gaji Pokok per jam</li>
            <li>Lembur hari kerja: jam pertama 1.5x, jam berikutnya 2x</li>
            <li>Lembur hari libur: 2x untuk 8 jam pertama, 3x jam ke-9, 4x jam ke-10 dan ke-11</li>
            <li>Maksimal jam lembur: 4 jam per hari dan 18 jam per minggu</li>
          </ul>
        </div>
      </div>
      
      <template #footer>
        <BaseButton v-if="!editing" variant="primary" size="sm" @click="editing = true">
          <template #icon-left>
            <IconPencil class="w-4 h-4" />
          </template>
          Edit
        </BaseButton>
        <div v-else class="flex gap-2">
          <BaseButton variant="ghost" size="sm" @click="cancelEdit">Batal</BaseButton>
          <BaseButton variant="primary" size="sm" @click="save">Simpan</BaseButton>
        </div>
      </template>
    </BaseCard>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import BaseButton from '../../../../../Components/BaseButton.vue'
import BaseCard from '../../../../../Components/BaseCard.vue'
import Badge from '../../../../../Components/Badge.vue'
import { IconPencil } from '../../../../../Components/Icons/index.js'
import { useApi } from '../../../../../composables/useApi'
import { useNotification } from '../../../../../composables/useNotification'

const api = useApi()
const notification = useNotification()

const rules = ref([])
const originalRules = ref([])
const editing = ref(false)

async function fetchData() {
  try {
    const response = await api.get('/api/v1/settings/payroll-configs/overtime')
    rules.value = response.data || []
    originalRules.value = JSON.parse(JSON.stringify(rules.value))
  } catch (err) {
    notification.error('Gagal mengambil data lembur')
  }
}

onMounted(() => {
  fetchData()
})

function cancelEdit() {
  rules.value = JSON.parse(JSON.stringify(originalRules.value))
  editing.value = false
}

async function save() {
  try {
    const payload = {
      rules: rules.value.map(r => ({ id: r.id, multiplier: r.multiplier }))
    }
    await api.post('/api/v1/settings/payroll-configs/overtime', payload)
    notification.success('Aturan lembur berhasil diupdate')
    editing.value = false
    fetchData()
  } catch (err) {
    notification.error('Gagal mengupdate aturan lembur')
  }
}
</script>
