<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-(--text-main)">Konfigurasi BPJS</h1>
        <p class="text-sm text-(--text-muted) mt-1">
          Atur persentase iuran BPJS — versi berlaku berdasarkan tanggal efektif
        </p>
      </div>
      <BaseButton variant="primary" @click="openAdd" :disabled="isManajemen">
        <template #icon-left>＋</template>
        Tambah Konfigurasi
      </BaseButton>
    </div>

    <!-- Legend -->
    <div class="bg-(--bg-card) border border-(--border-soft) rounded-xl p-3 mb-4 flex gap-6 text-xs font-medium">
      <span class="flex items-center gap-1.5">
        <span class="w-3 h-3 rounded-full bg-blue-500"></span> Porsi Perusahaan
      </span>
      <span class="flex items-center gap-1.5">
        <span class="w-3 h-3 rounded-full bg-orange-500"></span> Porsi Karyawan
      </span>
      <span class="flex items-center gap-1.5 text-green-600 ml-auto">
        🟢 = Aktif
      </span>
    </div>

    <!-- Table -->
    <BaseCard>
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="border-b border-(--border-soft)">
            <tr class="text-xs uppercase text-(--text-muted) tracking-wider">
              <th class="p-3 font-medium text-left">Tgl Efektif</th>
              <th class="p-3 font-medium text-center">JHT</th>
              <th class="p-3 font-medium text-center">JKK</th>
              <th class="p-3 font-medium text-center">JKM</th>
              <th class="p-3 font-medium text-center">JP</th>
              <th class="p-3 font-medium text-center">KES</th>
              <th class="p-3 font-medium text-right">Max Upah</th>
              <th class="p-3 font-medium text-center">Status</th>
              <th class="p-3 font-medium text-center w-[150px]">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-(--border-soft)">
            <tr v-if="loading"><td colspan="9" class="p-8 text-center text-(--text-muted)">Memuat...</td></tr>
            <tr v-else-if="configs.length === 0"><td colspan="9" class="p-8 text-center text-(--text-muted)">Belum ada konfigurasi.</td></tr>
            <tr v-for="c in configs" :key="c.id" class="hover:bg-(--bg-hover)"
              :class="c.is_active ? 'bg-green-50/20' : ''">
              <td class="p-3 font-medium">{{ formatDate(c.effective_date) }}</td>
              <td class="p-3 text-center">
                <span class="text-blue-600 text-xs">P: {{ c.jht_employer }}%</span>
                <span class="text-orange-600 text-xs ml-1">K: {{ c.jht_employee }}%</span>
              </td>
              <td class="p-3 text-center text-xs text-blue-600">P: {{ c.jkk }}%</td>
              <td class="p-3 text-center text-xs text-blue-600">P: {{ c.jkm }}%</td>
              <td class="p-3 text-center">
                <span class="text-blue-600 text-xs">P: {{ c.jp_employer }}%</span>
                <span class="text-orange-600 text-xs ml-1">K: {{ c.jp_employee }}%</span>
              </td>
              <td class="p-3 text-center">
                <span class="text-blue-600 text-xs">P: {{ c.kesehatan_employer }}%</span>
                <span class="text-orange-600 text-xs ml-1">K: {{ c.kesehatan_employee }}%</span>
              </td>
              <td class="p-3 text-right text-xs">{{ fmtMoney(c.max_wage_cap) }}</td>
              <td class="p-3 text-center">
                <span v-if="c.is_active" class="text-green-600 text-xs font-medium">🟢 Aktif</span>
                <span v-else class="text-(--text-muted) text-xs">⚪ Nonaktif</span>
              </td>
              <td class="p-3 text-center">
                <div class="flex justify-center gap-1">
                  <button @click="openEdit(c)" class="p-1.5 hover:text-(--primary) disabled:opacity-50 disabled:cursor-not-allowed" title="Edit" :disabled="isManajemen">✏️</button>
                  <button v-if="!c.is_active" @click="toggleActive(c, true)" class="p-1.5 hover:text-green-500 disabled:opacity-50 disabled:cursor-not-allowed" title="Aktifkan" :disabled="isManajemen">✅</button>
                  <button v-if="c.is_active" @click="toggleActive(c, false)" class="p-1.5 hover:text-yellow-500 disabled:opacity-50 disabled:cursor-not-allowed" title="Nonaktifkan" :disabled="isManajemen">⏸</button>
                  <button @click="confirmDelete(c)" class="p-1.5 hover:text-red-500 disabled:opacity-50 disabled:cursor-not-allowed" title="Hapus" :disabled="isManajemen">🗑</button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </BaseCard>

    <!-- Modal Form -->
    <BaseModal :show="showModal" @close="closeModal" :title="isEdit ? 'Edit Konfigurasi' : 'Tambah Konfigurasi'">
      <div class="space-y-4 p-2">
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-xs mb-1">Tgl Efektif <span class="text-red-500">*</span></label>
            <input v-model="form.effective_date" type="date"
              class="w-full bg-(--bg-input) border border-(--border-soft) rounded-lg px-3 py-2 text-sm" required />
          </div>
          <div>
            <label class="block text-xs mb-1">Batas Max Upah</label>
            <input v-model.number="form.max_wage_cap" type="number" min="0"
              class="w-full bg-(--bg-input) border border-(--border-soft) rounded-lg px-3 py-2 text-sm" />
          </div>
        </div>

        <!-- JHT -->
        <div class="border-t pt-4">
          <h4 class="text-sm font-medium mb-2">JHT — Jaminan Hari Tua</h4>
          <div class="grid grid-cols-2 gap-3">
            <div><label class="text-xs">Perusahaan (%)</label><input v-model="form.jht_employer" type="number" step="0.01" class="w-full bg-(--bg-input) border rounded-lg px-3 py-2 text-sm mt-1" /></div>
            <div><label class="text-xs">Karyawan (%)</label><input v-model="form.jht_employee" type="number" step="0.01" class="w-full bg-(--bg-input) border rounded-lg px-3 py-2 text-sm mt-1" /></div>
          </div>
        </div>

        <!-- JP -->
        <div class="border-t pt-4">
          <h4 class="text-sm font-medium mb-2">JP — Jaminan Pensiun</h4>
          <div class="grid grid-cols-2 gap-3">
            <div><label class="text-xs">Perusahaan (%)</label><input v-model="form.jp_employer" type="number" step="0.01" class="w-full bg-(--bg-input) border rounded-lg px-3 py-2 text-sm mt-1" /></div>
            <div><label class="text-xs">Karyawan (%)</label><input v-model="form.jp_employee" type="number" step="0.01" class="w-full bg-(--bg-input) border rounded-lg px-3 py-2 text-sm mt-1" /></div>
          </div>
        </div>

        <!-- Kesehatan -->
        <div class="border-t pt-4">
          <h4 class="text-sm font-medium mb-2">BPJS Kesehatan</h4>
          <div class="grid grid-cols-2 gap-3">
            <div><label class="text-xs">Perusahaan (%)</label><input v-model="form.kesehatan_employer" type="number" step="0.01" class="w-full bg-(--bg-input) border rounded-lg px-3 py-2 text-sm mt-1" /></div>
            <div><label class="text-xs">Karyawan (%)</label><input v-model="form.kesehatan_employee" type="number" step="0.01" class="w-full bg-(--bg-input) border rounded-lg px-3 py-2 text-sm mt-1" /></div>
          </div>
        </div>

        <!-- JKK + JKM -->
        <div class="border-t pt-4">
          <h4 class="text-sm font-medium mb-2">JKK & JKM (Perusahaan Only)</h4>
          <div class="grid grid-cols-2 gap-3">
            <div><label class="text-xs">JKK (%)</label><input v-model="form.jkk" type="number" step="0.01" class="w-full bg-(--bg-input) border rounded-lg px-3 py-2 text-sm mt-1" /></div>
            <div><label class="text-xs">JKM (%)</label><input v-model="form.jkm" type="number" step="0.01" class="w-full bg-(--bg-input) border rounded-lg px-3 py-2 text-sm mt-1" /></div>
          </div>
        </div>

        <div class="border-t pt-3">
          <label class="block text-xs mb-1">Keterangan</label>
          <input v-model="form.description" type="text" class="w-full bg-(--bg-input) border rounded-lg px-3 py-2 text-sm" placeholder="Opsional" />
        </div>
      </div>
      <template #footer>
        <div class="flex justify-end gap-2 p-4 border-t">
          <BaseButton variant="secondary" @click="closeModal">Batal</BaseButton>
          <BaseButton variant="primary" :loading="saving" @click="save">{{ saving ? 'Menyimpan...' : 'Simpan' }}</BaseButton>
        </div>
      </template>
    </BaseModal>

    <!-- Delete Confirmation -->
    <BaseModal :show="showDelete" @close="showDelete = false" title="Hapus Konfigurasi">
      <p class="p-4 text-sm">Hapus konfigurasi ini?</p>
      <template #footer>
        <div class="flex justify-end gap-2 p-4 border-t">
          <BaseButton variant="secondary" @click="showDelete = false">Batal</BaseButton>
          <BaseButton variant="danger" :loading="deleting" @click="doDelete">Hapus</BaseButton>
        </div>
      </template>
    </BaseModal>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useApi } from '@/composables/useApi'
import { useAuth } from '@/composables/useAuth'
import BaseCard from '@/Components/BaseCard.vue'
import BaseButton from '@/Components/BaseButton.vue'
import BaseModal from '@/Components/BaseModal.vue'

const { get, post, put, destroy } = useApi()
const { isManajemen } = useAuth()

const configs = ref([])
const loading = ref(false)
const showModal = ref(false)
const isEdit = ref(false)
const editingId = ref(null)
const saving = ref(false)
const showDelete = ref(false)
const deleteTarget = ref(null)
const deleting = ref(false)

const defaultForm = () => ({
  effective_date: new Date().toISOString().split('T')[0],
  jht_employer: 3.70, jht_employee: 2.00,
  jkk: 0.24, jkm: 0.30,
  jp_employer: 2.00, jp_employee: 1.00,
  kesehatan_employer: 4.00, kesehatan_employee: 1.00,
  max_wage_cap: 12000000,
  description: '',
})

const form = ref(defaultForm())

function formatDate(d) {
  if (!d) return '-'
  return new Date(d).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' })
}

function fmtMoney(v) {
  if (!v) return '-'
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(v)
}

async function fetchConfigs() {
  loading.value = true
  try {
    const res = await get('/api/v1/settings/bpjs-configs')
    configs.value = res.data?.data || res.data || []
  } catch (e) { console.error(e) } finally { loading.value = false }
}

function openAdd() { isEdit.value = false; editingId.value = null; form.value = defaultForm(); showModal.value = true }
function openEdit(c) {
  isEdit.value = true; editingId.value = c.id
  form.value = { ...c }
  showModal.value = true
}
function closeModal() { showModal.value = false }

async function save() {
  saving.value = true
  try {
    if (isEdit.value) {
      await put(`/api/v1/settings/bpjs-configs/${editingId.value}`, form.value)
    } else {
      await post('/api/v1/settings/bpjs-configs', form.value)
    }
    closeModal()
    fetchConfigs()
  } catch (e) { alert(e.message || 'Gagal menyimpan') } finally { saving.value = false }
}

async function toggleActive(c, active) {
  try {
    const url = `/api/v1/settings/bpjs-configs/${c.id}/${active ? 'activate' : 'deactivate'}`
    await post(url)
    fetchConfigs()
  } catch (e) { alert(e.message || 'Gagal') }
}

function confirmDelete(c) { deleteTarget.value = c; showDelete.value = true }
async function doDelete() {
  if (!deleteTarget.value) return
  deleting.value = true
  try {
    await destroy(`/api/v1/settings/bpjs-configs/${deleteTarget.value.id}`)
    showDelete.value = false
    fetchConfigs()
  } catch (e) { alert(e.message || 'Gagal menghapus') } finally { deleting.value = false }
}

onMounted(fetchConfigs)
</script>
