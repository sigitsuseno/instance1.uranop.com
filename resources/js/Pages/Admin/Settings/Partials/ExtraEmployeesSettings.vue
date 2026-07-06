<script setup>
import { ref, reactive, onMounted } from 'vue'
import BaseButton from '../../../../Components/BaseButton.vue'
import BaseCard from '../../../../Components/BaseCard.vue'
import TextInput from '../../../../Components/TextInput.vue'
import DataTable from '../../../../Components/Table/DataTable.vue'
import { IconPencil, IconPlus } from '../../../../Components/Icons/index.js'
import { useNotification } from '../../../../composables/useNotification'
import { useApi } from '../../../../composables/useApi'
import Badge from '../../../../Components/Badge.vue'

const notification = useNotification()
const api = useApi()

const records = ref([])
const isEditing = ref(false)

const form = reactive({
  id: null,
  nama: '',
  kode: '',
  komponen_gaji: {
    gaji_pokok: 0,
    premi: 0,
    tj_mk: 0,
    tunjangan: 0,
    ttl_bpjs: 0,
    ttl_pph: 0,
    total_gaji: 0,
    cashbon: 0,
    total_terima: 0,
  },
})

const tableHeaders = [
  { key: 'nama', label: 'Nama' },
  { key: 'kode', label: 'Kode' },
  { key: 'total_gaji', label: 'Total Gaji' },
  { key: 'total_terima', label: 'Total Terima' },
  { key: 'actions', label: 'Aksi', sortable: false, width: '100px' },
]

function formatRupiah(val) {
  if (!val && val !== 0) return '-'
  return 'Rp ' + Number(val).toLocaleString('id-ID')
}

async function fetchRecords() {
  try {
    const response = await api.get('/api/v1/settings/extra-employees')
    records.value = response.data || []
  } catch (err) {
    notification.error('Gagal mengambil data karyawan titipan')
  }
}

onMounted(() => {
  fetchRecords()
})

function editRecord(item) {
  isEditing.value = true
  form.id = item.id
  form.nama = item.nama
  form.kode = item.kode || ''
  if (item.komponen_gaji) {
    form.komponen_gaji = { ...form.komponen_gaji, ...item.komponen_gaji }
  }
}

function resetForm() {
  isEditing.value = false
  form.id = null
  form.nama = ''
  form.kode = ''
  form.komponen_gaji = {
    gaji_pokok: 0,
    premi: 0,
    tj_mk: 0,
    tunjangan: 0,
    ttl_bpjs: 0,
    ttl_pph: 0,
    total_gaji: 0,
    cashbon: 0,
    total_terima: 0,
  }
}

async function saveRecord() {
  if (!form.nama) {
    notification.error('Nama wajib diisi!')
    return
  }

  const payload = {
    nama: form.nama,
    kode: form.kode || null,
    komponen_gaji: form.komponen_gaji,
  }

  try {
    if (form.id) {
      await api.put(`/api/v1/settings/extra-employees/${form.id}`, payload)
      notification.success('Karyawan titipan berhasil diupdate')
    } else {
      await api.post('/api/v1/settings/extra-employees', payload)
      notification.success('Karyawan titipan berhasil ditambahkan')
    }
    resetForm()
    fetchRecords()
  } catch (err) {
    const errorMsg = err.response?.data?.message || 'Gagal menyimpan data'
    notification.error(errorMsg)
  }
}

async function deleteRecord(id) {
  if (confirm('Yakin hapus karyawan titipan ini?')) {
    try {
      await api.destroy(`/api/v1/settings/extra-employees/${id}`)
      notification.success('Karyawan titipan berhasil dihapus')
      fetchRecords()
    } catch (err) {
      notification.error('Gagal menghapus data')
    }
  }
}
</script>

<template>
  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Form Area -->
    <div class="lg:col-span-1">
      <BaseCard>
        <template #title>{{ isEditing ? 'Edit Karyawan Titipan' : 'Tambah Karyawan Titipan' }}</template>

        <div class="space-y-4 mt-2">
          <TextInput
            v-model="form.nama"
            label="Nama"
            placeholder="Nama karyawan"
            required
          />

          <TextInput
            v-model="form.kode"
            label="Kode (Opsional)"
            placeholder="Kode unik (boleh kosong)"
          />

          <!-- Komponen Gaji -->
          <div class="border-t border-(--border-soft) pt-3">
            <h4 class="text-sm font-semibold text-(--text-main) mb-3">Komponen Gaji</h4>
            <div class="grid grid-cols-2 gap-3">
              <TextInput
                v-model.number="form.komponen_gaji.gaji_pokok"
                label="Gaji Pokok"
                type="number"
                placeholder="0"
              />
              <TextInput
                v-model.number="form.komponen_gaji.premi"
                label="Premi"
                type="number"
                placeholder="0"
              />
              <TextInput
                v-model.number="form.komponen_gaji.tj_mk"
                label="Tj. MK"
                type="number"
                placeholder="0"
              />
              <TextInput
                v-model.number="form.komponen_gaji.tunjangan"
                label="Tunjangan"
                type="number"
                placeholder="0"
              />
              <TextInput
                v-model.number="form.komponen_gaji.ttl_bpjs"
                label="Total BPJS"
                type="number"
                placeholder="0"
              />
              <TextInput
                v-model.number="form.komponen_gaji.ttl_pph"
                label="Total PPh"
                type="number"
                placeholder="0"
              />
              <TextInput
                v-model.number="form.komponen_gaji.total_gaji"
                label="Total Gaji"
                type="number"
                placeholder="0"
              />
              <TextInput
                v-model.number="form.komponen_gaji.cashbon"
                label="Cashbon"
                type="number"
                placeholder="0"
              />
            </div>
            <div class="mt-3">
              <TextInput
                v-model.number="form.komponen_gaji.total_terima"
                label="Total Terima"
                type="number"
                placeholder="0"
              />
            </div>
          </div>

          <div class="flex gap-2 pt-3 border-t border-(--border-soft)">
            <BaseButton variant="primary" size="md" class="flex-1" @click="saveRecord">
              {{ isEditing ? 'Simpan Perubahan' : 'Tambah' }}
            </BaseButton>
            <BaseButton v-if="isEditing" variant="secondary" size="md" @click="resetForm">
              Batal
            </BaseButton>
          </div>
        </div>
      </BaseCard>
    </div>

    <!-- Table Area -->
    <div class="lg:col-span-2">
      <BaseCard>
        <template #title>Daftar Karyawan Titipan (EXTRA EMP)</template>
        <template #actions>
          <BaseButton v-if="isEditing" variant="secondary" size="sm" @click="resetForm">
            <template #icon-left>
              <IconPlus class="w-4 h-4" />
            </template>
            Tambah Baru
          </BaseButton>
        </template>

        <DataTable :headers="tableHeaders" :items="records" class="mt-4">
          <template #item.total_gaji="{ item }">
            <span class="text-sm font-medium text-(--text-main)">
              {{ formatRupiah(item.komponen_gaji?.total_gaji) }}
            </span>
          </template>

          <template #item.total_terima="{ item }">
            <span class="text-sm font-medium text-(--text-main)">
              {{ formatRupiah(item.komponen_gaji?.total_terima) }}
            </span>
          </template>

          <template #item.actions="{ item }">
            <div class="flex items-center gap-2">
              <button
                @click="editRecord(item)"
                class="p-1 text-(--text-muted) hover:text-(--color-primary) transition-colors"
                title="Edit"
              >
                <IconPencil class="w-4 h-4" />
              </button>
              <button
                @click="deleteRecord(item.id)"
                class="p-1 text-(--text-muted) hover:text-red-500 transition-colors"
                title="Hapus"
              >
                <i class="bx bx-trash text-lg"></i>
              </button>
            </div>
          </template>
        </DataTable>

        <div v-if="records.length === 0" class="text-center py-8 text-sm text-(--text-muted)">
          Belum ada data karyawan titipan. Klik "Tambah Baru" untuk menambahkan.
        </div>
      </BaseCard>
    </div>
  </div>
</template>
