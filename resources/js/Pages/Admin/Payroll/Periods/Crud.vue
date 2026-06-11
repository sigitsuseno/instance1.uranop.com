<template>
  <div>
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-xl font-semibold text-(--text-main)">Penggajian</h1>
        <p class="text-sm text-(--text-muted) mt-1">Kelola periode penggajian</p>
      </div>
      <BaseButton variant="primary" @click="openCreate">
        <template #icon-left>
          <IconPlus class="w-4 h-4" />
        </template>
        Tambah Periode
      </BaseButton>
    </div>

    <!-- Table -->
    <BaseCard class="overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-sm border-collapse">
          <thead>
            <tr class="bg-(--bg-elevated)">
              <th class="px-4 py-3 text-left text-xs font-semibold text-(--text-muted) uppercase">No</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-(--text-muted) uppercase">Nama</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-(--text-muted) uppercase">Kode</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-(--text-muted) uppercase">Tanggal</th>
              <th class="px-4 py-3 text-center text-xs font-semibold text-(--text-muted) uppercase">Split</th>
              <th class="px-4 py-3 text-center text-xs font-semibold text-(--text-muted) uppercase">Status</th>
              <th class="px-4 py-3 text-right text-xs font-semibold text-(--text-muted) uppercase">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="periods.length === 0">
              <td colspan="7" class="px-4 py-12 text-center text-(--text-muted)">
                Belum ada periode. Klik "Tambah Periode" untuk membuat.
              </td>
            </tr>
            <tr
              v-for="(p, idx) in periods"
              :key="p.id"
              class="border-b border-(--border-soft) hover:bg-(--bg-elevated)/50 transition-colors"
            >
              <td class="px-4 py-3 text-(--text-main)">{{ idx + 1 }}</td>
              <td class="px-4 py-3 font-medium text-(--text-main)">{{ p.name }}</td>
              <td class="px-4 py-3 text-(--text-muted) font-mono text-xs">{{ p.period_code }}</td>
              <td class="px-4 py-3 text-(--text-muted)">{{ p.date_range }}</td>
              <td class="px-4 py-3 text-center">
                <Badge :variant="p.is_split ? 'warning' : 'success'">
                  {{ p.is_split ? 'Split' : 'Normal' }}
                </Badge>
              </td>
              <td class="px-4 py-3 text-center">
                <Badge :variant="p.status === 'active' ? 'success' : 'neutral'">
                  {{ p.status === 'active' ? 'Aktif' : 'Nonaktif' }}
                </Badge>
              </td>
              <td class="px-4 py-3 text-right">
                <button @click="openEdit(p)" class="text-(--primary) hover:underline text-xs mr-3">Edit</button>
                <button @click="handleDelete(p)" class="text-(--danger) hover:underline text-xs">Hapus</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </BaseCard>

    <!-- Modal Create/Edit -->
    <BaseModal :show="showModal" :title="editing ? 'Edit Periode' : 'Tambah Periode'" @close="closeModal">
      <div class="space-y-4">
        <TextInput v-model="form.name" label="Nama Periode" placeholder="Contoh: Juni 2026" />
        <div class="grid grid-cols-2 gap-4">
          <TextInput v-model="form.start_date" label="Tanggal Mulai" type="date" />
          <TextInput v-model="form.end_date" label="Tanggal Selesai" type="date" />
        </div>
        <div class="flex items-center gap-3">
          <label class="flex items-center gap-2 cursor-pointer">
            <input type="checkbox" v-model="form.is_split" class="rounded border-(--border-soft) text-(--primary) focus:ring-(--primary)" />
            <span class="text-sm text-(--text-main)">Split Periode</span>
          </label>
          <label class="flex items-center gap-2 cursor-pointer">
            <input type="checkbox" v-model="form.status_active" class="rounded border-(--border-soft) text-(--primary) focus:ring-(--primary)" />
            <span class="text-sm text-(--text-main)">Aktif</span>
          </label>
        </div>
        <div v-if="error" class="px-3 py-2 bg-(--danger)/10 border border-(--danger)/30 rounded text-sm text-(--danger)">
          {{ error }}
        </div>
      </div>
      <template #footer>
        <BaseButton variant="ghost" @click="closeModal">Batal</BaseButton>
        <BaseButton variant="primary" :disabled="saving" @click="handleSave">
          {{ saving ? 'Menyimpan...' : 'Simpan' }}
        </BaseButton>
      </template>
    </BaseModal>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import BaseButton from '@/Components/BaseButton.vue'
import BaseCard from '@/Components/BaseCard.vue'
import BaseModal from '@/Components/BaseModal.vue'
import Badge from '@/Components/Badge.vue'
import TextInput from '@/Components/TextInput.vue'
import { IconPlus } from '@/Components/Icons/index.js'
import { useApi } from '@/composables/useApi'

const { get, post, put, destroy } = useApi()

const periods = ref([])
const showModal = ref(false)
const editing = ref(null)
const saving = ref(false)
const error = ref('')

const form = ref({
  name: '',
  start_date: '',
  end_date: '',
  is_split: false,
  status_active: true,
})

function openCreate() {
  editing.value = null
  form.value = { name: '', start_date: '', end_date: '', is_split: false, status_active: true }
  error.value = ''
  showModal.value = true
}

function openEdit(p) {
  editing.value = p
  form.value = {
    name: p.name,
    start_date: p.start_date,
    end_date: p.end_date,
    is_split: p.is_split,
    status_active: p.status === 'active',
  }
  error.value = ''
  showModal.value = true
}

function closeModal() {
  showModal.value = false
  editing.value = null
}

async function handleSave() {
  saving.value = true
  error.value = ''
  try {
    const payload = {
      name: form.value.name,
      start_date: form.value.start_date,
      end_date: form.value.end_date,
      is_split: form.value.is_split,
      status: form.value.status_active ? 'active' : 'inactive',
    }
    if (editing.value) {
      await put(`/api/v1/payroll/periods/${editing.value.id}`, payload)
    } else {
      await post('/api/v1/payroll/periods', payload)
    }
    closeModal()
    await fetchPeriods()
  } catch (e) {
    error.value = e?.message || 'Gagal menyimpan periode'
  } finally {
    saving.value = false
  }
}

async function handleDelete(p) {
  if (!confirm(`Hapus periode "${p.name}"?`)) return
  try {
    await destroy(`/api/v1/payroll/periods/${p.id}`)
    await fetchPeriods()
  } catch (e) {
    alert('Gagal menghapus periode')
  }
}

async function fetchPeriods() {
  try {
    const res = await get('/api/v1/payroll/periods')
    periods.value = res.data || []
  } catch (e) {
    console.error('Gagal fetch periods', e)
  }
}

onMounted(() => {
  fetchPeriods()
})
</script>
