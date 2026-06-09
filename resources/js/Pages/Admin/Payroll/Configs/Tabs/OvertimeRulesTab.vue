<template>
  <div class="space-y-6">
    <div class="flex justify-between items-center">
      <div>
        <h3 class="text-lg font-medium text-(--text-main)">Aturan Lembur</h3>
        <p class="text-sm text-(--text-muted)">Kelola pengali upah lembur berdasarkan tipe karyawan/work pattern</p>
      </div>
      <BaseButton v-if="!editing" variant="primary" size="sm" @click="createNewRule">
        Tambah Aturan
      </BaseButton>
    </div>

    <!-- List Mode -->
    <div v-if="!editing" class="grid gap-4">
      <BaseCard v-for="rule in rules" :key="rule.id">
        <div class="flex justify-between items-start mb-4">
          <div>
            <h4 class="font-semibold text-(--text-main)">{{ rule.name }} <span class="text-xs text-(--text-muted)">({{ rule.code }})</span></h4>
            <p class="text-sm text-(--text-muted)">Work Pattern: {{ rule.work_pattern ? rule.work_pattern.name : 'Semua' }} | Hari Libur: {{ rule.is_holiday ? 'Ya' : 'Tidak' }}</p>
          </div>
          <div class="flex gap-2">
            <BaseButton variant="ghost" size="sm" @click="editRule(rule)">Edit</BaseButton>
            <BaseButton variant="danger" size="sm" @click="deleteRule(rule.id)">Hapus</BaseButton>
          </div>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full border-collapse">
            <thead>
              <tr class="bg-(--bg-elevated)">
                <th class="px-4 py-2 text-left text-xs font-semibold text-(--text-muted) uppercase">Jam Ke</th>
                <th class="px-4 py-2 text-left text-xs font-semibold text-(--text-muted) uppercase">Pengali</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="detail in rule.details" :key="detail.id" class="border-b border-(--border-soft)">
                <td class="px-4 py-2 text-sm">{{ detail.hour }}</td>
                <td class="px-4 py-2 text-sm"><Badge variant="primary">{{ detail.multiplier }}x</Badge></td>
              </tr>
              <tr v-if="!rule.details || rule.details.length === 0">
                <td colspan="2" class="px-4 py-2 text-sm text-center text-(--text-muted)">Belum ada jam lembur disetel</td>
              </tr>
            </tbody>
          </table>
        </div>
      </BaseCard>

      <div v-if="rules.length === 0" class="text-center py-8 text-(--text-muted)">
        Belum ada aturan lembur. Silakan tambah aturan baru.
      </div>
    </div>

    <!-- Edit/Create Mode -->
    <BaseCard v-else>
      <template #title>{{ form.id ? 'Edit Aturan Lembur' : 'Tambah Aturan Lembur' }}</template>
      
      <div class="space-y-4">
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-(--text-main) mb-1">Kode Aturan</label>
            <input type="text" v-model="form.code" class="w-full px-3 py-2 text-sm border border-(--border-soft) rounded focus:outline-none focus:border-(--primary)" placeholder="Contoh: REG-WD" />
          </div>
          <div>
            <label class="block text-sm font-medium text-(--text-main) mb-1">Nama Aturan</label>
            <input type="text" v-model="form.name" class="w-full px-3 py-2 text-sm border border-(--border-soft) rounded focus:outline-none focus:border-(--primary)" placeholder="Contoh: Lembur Reguler" />
          </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-(--text-main) mb-1">Work Pattern</label>
            <select v-model="form.work_pattern_id" class="w-full px-3 py-2 text-sm border border-(--border-soft) rounded focus:outline-none focus:border-(--primary)">
              <option :value="null">Semua / Default</option>
              <option v-for="wp in workPatterns" :key="wp.id" :value="wp.id">{{ wp.name }}</option>
            </select>
          </div>
          <div class="flex items-center mt-6">
            <input type="checkbox" id="is_holiday" v-model="form.is_holiday" class="mr-2 rounded border-(--border-soft) text-(--primary) focus:ring-(--primary)" />
            <label for="is_holiday" class="text-sm text-(--text-main)">Berlaku di Hari Libur</label>
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium text-(--text-main) mb-1">Deskripsi</label>
          <input type="text" v-model="form.description" class="w-full px-3 py-2 text-sm border border-(--border-soft) rounded focus:outline-none focus:border-(--primary)" />
        </div>

        <div class="mt-6">
          <div class="flex justify-between items-center mb-2">
            <h4 class="text-sm font-semibold text-(--text-main)">Detail Jam Lembur</h4>
            <BaseButton variant="ghost" size="sm" @click="addHour">
              + Tambah Jam
            </BaseButton>
          </div>
          
          <table class="w-full border-collapse border border-(--border-soft)">
            <thead>
              <tr class="bg-(--bg-elevated)">
                <th class="px-4 py-2 text-left text-xs font-semibold text-(--text-muted) uppercase w-24">Jam Ke</th>
                <th class="px-4 py-2 text-left text-xs font-semibold text-(--text-muted) uppercase">Pengali</th>
                <th class="px-4 py-2 text-left text-xs font-semibold text-(--text-muted) uppercase w-20">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(detail, index) in form.details" :key="index" class="border-b border-(--border-soft)">
                <td class="px-4 py-2">
                  <input type="number" min="1" v-model="detail.hour" class="w-full px-2 py-1 text-sm border border-(--border-soft) rounded focus:outline-none focus:border-(--primary)" />
                </td>
                <td class="px-4 py-2">
                  <div class="flex items-center gap-2">
                    <input type="number" step="0.5" min="0" v-model="detail.multiplier" class="w-24 px-2 py-1 text-sm border border-(--border-soft) rounded focus:outline-none focus:border-(--primary)" />
                    <span class="text-(--text-muted)">x</span>
                  </div>
                </td>
                <td class="px-4 py-2">
                  <button type="button" @click="removeHour(index)" class="text-red-500 hover:text-red-700 text-sm">Hapus</button>
                </td>
              </tr>
              <tr v-if="form.details.length === 0">
                <td colspan="3" class="px-4 py-4 text-sm text-center text-(--text-muted)">Klik 'Tambah Jam' untuk mengatur pengali</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
      
      <template #footer>
        <div class="flex justify-end gap-2">
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
import { useApi } from '../../../../../composables/useApi'
import { useNotification } from '../../../../../composables/useNotification'

const api = useApi()
const notification = useNotification()

const rules = ref([])
const workPatterns = ref([])
const editing = ref(false)

const form = ref({
  id: null,
  code: '',
  name: '',
  work_pattern_id: null,
  is_holiday: false,
  description: '',
  details: []
})

async function fetchData() {
  try {
    const [rulesRes, wpRes] = await Promise.all([
      api.get('/api/v1/settings/payroll-configs/overtime'),
      api.get('/api/v1/settings/payroll-configs/work-patterns')
    ])
    rules.value = rulesRes.data || []
    workPatterns.value = wpRes.data || []
  } catch (err) {
    notification.error('Gagal mengambil data lembur')
  }
}

onMounted(() => {
  fetchData()
})

function createNewRule() {
  form.value = {
    id: null,
    code: '',
    name: '',
    work_pattern_id: null,
    is_holiday: false,
    description: '',
    details: [
      { hour: 1, multiplier: 1.5 }
    ]
  }
  editing.value = true
}

function editRule(rule) {
  form.value = {
    id: rule.id,
    code: rule.code,
    name: rule.name,
    work_pattern_id: rule.work_pattern_id,
    is_holiday: rule.is_holiday,
    description: rule.description,
    details: rule.details.map(d => ({ hour: d.hour, multiplier: d.multiplier }))
  }
  editing.value = true
}

function cancelEdit() {
  editing.value = false
}

function addHour() {
  const nextHour = form.value.details.length > 0 
    ? Math.max(...form.value.details.map(d => d.hour)) + 1 
    : 1;
  form.value.details.push({ hour: nextHour, multiplier: 2.0 })
}

function removeHour(index) {
  form.value.details.splice(index, 1)
}

async function save() {
  try {
    if (form.value.id) {
      await api.put(`/api/v1/settings/payroll-configs/overtime/${form.value.id}`, form.value)
      notification.success('Aturan lembur berhasil diupdate')
    } else {
      await api.post('/api/v1/settings/payroll-configs/overtime', form.value)
      notification.success('Aturan lembur berhasil ditambahkan')
    }
    editing.value = false
    fetchData()
  } catch (err) {
    notification.error(err.response?.data?.message || 'Gagal menyimpan aturan lembur')
  }
}

async function deleteRule(id) {
  if (confirm('Yakin ingin menghapus aturan ini?')) {
    try {
      await api.destroy(`/api/v1/settings/payroll-configs/overtime/${id}`)
      notification.success('Aturan lembur dihapus')
      fetchData()
    } catch (err) {
      notification.error('Gagal menghapus aturan lembur')
    }
  }
}
</script>
