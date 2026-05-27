<script setup>
import { ref, computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import BaseButton from '../../../Components/BaseButton.vue'
import BaseCard from '../../../Components/BaseCard.vue'
import Badge from '../../../Components/Badge.vue'
import { IconChevronLeft, IconPencil } from '../../../Components/Icons/index.js'

const router = useRouter()
const route = useRoute()

const employeeId = computed(() => Number(route.params.id))

const employee = computed(() => {
  return {
    id: employeeId.value,
    nip: `EMP${String(employeeId.value).padStart(3, '0')}`,
    name: 'Budi Santoso',
    email: 'budi@example.com',
    phone: '081234567890',
    birth_date: '1990-03-15',
    gender: 'Laki-laki',
    address: 'Jl. Merdeka No. 123, Jakarta Selatan, DKI Jakarta',
    department: 'IT',
    position: 'Senior Developer',
    employment_status: 'Tetap',
    join_date: '2023-01-15',
    contract_end: '-',
    is_active: true,
  }
})

const activeTab = ref('kontrak')

const tabs = [
  { key: 'kontrak', label: 'Kontrak' },
  { key: 'keluarga', label: 'Keluarga' },
  { key: 'dokumen', label: 'Dokumen' },
  { key: 'riwayat', label: 'Riwayat Jabatan' },
  { key: 'gaji', label: 'Gaji' },
]

const contracts = [
  { id: 1, type: 'Tetap', start_date: '2023-01-15', end_date: '-', status: 'active' },
  { id: 2, type: 'Kontrak 1', start_date: '2022-01-15', end_date: '2022-12-31', status: 'inactive' },
]

const familyMembers = [
  { id: 1, name: 'Siti Nurhaliza', relation: 'Istri', phone: '081298765432', birth_date: '1990-05-20' },
  { id: 2, name: 'Rizky Ramadhan', relation: 'Anak', phone: '-', birth_date: '2018-08-10' },
  { id: 3, name: 'Aisyah Putri', relation: 'Anak', phone: '-', birth_date: '2021-12-05' },
]

const documents = [
  { id: 1, name: 'KTP', type: 'Identitas', upload_date: '2023-01-10' },
  { id: 2, name: 'Ijazah S1', type: 'Pendidikan', upload_date: '2023-01-10' },
]

const positionHistory = [
  { id: 1, position: 'Junior Developer', department: 'IT', start_date: '2021-01', end_date: '2022-12' },
  { id: 2, position: 'Senior Developer', department: 'IT', start_date: '2023-01', end_date: '-' },
]

const salaryComponents = [
  { id: 1, component: 'Gaji Pokok', amount: 8000000, effective_date: '2023-01' },
  { id: 2, component: 'Tunjangan Jabatan', amount: 2000000, effective_date: '2023-01' },
  { id: 3, component: 'Tunjangan Transport', amount: 500000, effective_date: '2023-01' },
]

function formatRupiah(amount) {
  return 'Rp ' + amount.toLocaleString('id-ID')
}

function goBack() {
  if (window.history.length > 1) {
    router.back()
  } else {
    router.push('/employees')
  }
}
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center gap-3">
      <BaseButton variant="ghost" @click="goBack">
        <template #icon-left>
          <IconChevronLeft class="w-5 h-5" />
        </template>
        Kembali
      </BaseButton>
    </div>

    <div class="flex items-center justify-between">
      <div class="flex items-center gap-4">
        <div>
          <h1 class="text-2xl font-bold text-(--text-main)">{{ employee.name }}</h1>
          <p class="text-sm text-(--text-muted)">{{ employee.nip }}</p>
        </div>
        <div class="flex items-center gap-2">
          <Badge :variant="employee.is_active ? 'success' : 'danger'">
            {{ employee.is_active ? 'Aktif' : 'Nonaktif' }}
          </Badge>
          <Badge
            :variant="employee.employment_status === 'Tetap' ? 'primary' : employee.employment_status === 'Kontrak' ? 'warning' : 'info'"
          >
            {{ employee.employment_status }}
          </Badge>
        </div>
      </div>
      <BaseButton @click="router.push(`/employees/${employee.id}/edit`)">
        <template #icon-left>
          <IconPencil class="w-4 h-4" />
        </template>
        Edit Karyawan
      </BaseButton>
    </div>

    <div class="grid grid-cols-2 gap-6">
      <BaseCard>
        <template #title>Data Pribadi</template>
        <dl class="space-y-3">
          <div class="flex justify-between">
            <dt class="text-sm text-(--text-muted)">NIP</dt>
            <dd class="text-sm font-medium text-(--text-main)">{{ employee.nip }}</dd>
          </div>
          <div class="flex justify-between">
            <dt class="text-sm text-(--text-muted)">Nama Lengkap</dt>
            <dd class="text-sm font-medium text-(--text-main)">{{ employee.name }}</dd>
          </div>
          <div class="flex justify-between">
            <dt class="text-sm text-(--text-muted)">Email</dt>
            <dd class="text-sm font-medium text-(--text-main)">{{ employee.email }}</dd>
          </div>
          <div class="flex justify-between">
            <dt class="text-sm text-(--text-muted)">No. Telepon</dt>
            <dd class="text-sm font-medium text-(--text-main)">{{ employee.phone }}</dd>
          </div>
          <div class="flex justify-between">
            <dt class="text-sm text-(--text-muted)">Tanggal Lahir</dt>
            <dd class="text-sm font-medium text-(--text-main)">{{ employee.birth_date }}</dd>
          </div>
          <div class="flex justify-between">
            <dt class="text-sm text-(--text-muted)">Jenis Kelamin</dt>
            <dd class="text-sm font-medium text-(--text-main)">{{ employee.gender }}</dd>
          </div>
          <div class="flex justify-between">
            <dt class="text-sm text-(--text-muted)">Alamat</dt>
            <dd class="text-sm font-medium text-(--text-main) text-right max-w-[60%]">{{ employee.address }}</dd>
          </div>
        </dl>
      </BaseCard>

      <BaseCard>
        <template #title>Data Kepegawaian</template>
        <dl class="space-y-3">
          <div class="flex justify-between">
            <dt class="text-sm text-(--text-muted)">Departemen</dt>
            <dd class="text-sm font-medium text-(--text-main)">{{ employee.department }}</dd>
          </div>
          <div class="flex justify-between">
            <dt class="text-sm text-(--text-muted)">Jabatan</dt>
            <dd class="text-sm font-medium text-(--text-main)">{{ employee.position }}</dd>
          </div>
          <div class="flex justify-between">
            <dt class="text-sm text-(--text-muted)">Status Kepegawaian</dt>
            <dd class="text-sm font-medium text-(--text-main)">{{ employee.employment_status }}</dd>
          </div>
          <div class="flex justify-between">
            <dt class="text-sm text-(--text-muted)">Tanggal Masuk</dt>
            <dd class="text-sm font-medium text-(--text-main)">{{ employee.join_date }}</dd>
          </div>
          <div class="flex justify-between">
            <dt class="text-sm text-(--text-muted)">Akhir Kontrak</dt>
            <dd class="text-sm font-medium text-(--text-main)">{{ employee.contract_end }}</dd>
          </div>
        </dl>
      </BaseCard>
    </div>

    <BaseCard :padding="'p-0'">
      <div class="border-b border-(--border-soft)">
        <nav class="flex">
          <button
            v-for="tab in tabs"
            :key="tab.key"
            @click="activeTab = tab.key"
            :class="[
              'px-6 py-3 text-sm font-medium transition-colors border-b-2',
              activeTab === tab.key
                ? 'text-(--primary) border-(--primary)'
                : 'text-(--text-muted) border-transparent hover:text-(--text-main)',
            ]"
          >
            {{ tab.label }}
          </button>
        </nav>
      </div>

      <div class="p-6">
        <div v-if="activeTab === 'kontrak'">
          <table class="w-full border-collapse">
            <thead>
              <tr class="bg-(--bg-elevated)">
                <th class="px-4 py-3 text-left text-xs font-semibold text-(--text-muted) uppercase tracking-wider">Jenis Kontrak</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-(--text-muted) uppercase tracking-wider">Tanggal Mulai</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-(--text-muted) uppercase tracking-wider">Tanggal Berakhir</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-(--text-muted) uppercase tracking-wider">Status</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="c in contracts" :key="c.id" class="border-b border-(--border-soft)">
                <td class="px-4 py-3 text-sm text-(--text-main)">{{ c.type }}</td>
                <td class="px-4 py-3 text-sm text-(--text-main)">{{ c.start_date }}</td>
                <td class="px-4 py-3 text-sm text-(--text-main)">{{ c.end_date }}</td>
                <td class="px-4 py-3">
                  <Badge :variant="c.status === 'active' ? 'success' : 'neutral'">
                    {{ c.status === 'active' ? 'Aktif' : 'Tidak Aktif' }}
                  </Badge>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-if="activeTab === 'keluarga'">
          <table class="w-full border-collapse">
            <thead>
              <tr class="bg-(--bg-elevated)">
                <th class="px-4 py-3 text-left text-xs font-semibold text-(--text-muted) uppercase tracking-wider">Nama</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-(--text-muted) uppercase tracking-wider">Hubungan</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-(--text-muted) uppercase tracking-wider">No. Telepon</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-(--text-muted) uppercase tracking-wider">Tanggal Lahir</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="f in familyMembers" :key="f.id" class="border-b border-(--border-soft)">
                <td class="px-4 py-3 text-sm text-(--text-main)">{{ f.name }}</td>
                <td class="px-4 py-3 text-sm text-(--text-main)">{{ f.relation }}</td>
                <td class="px-4 py-3 text-sm text-(--text-main)">{{ f.phone }}</td>
                <td class="px-4 py-3 text-sm text-(--text-main)">{{ f.birth_date }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-if="activeTab === 'dokumen'">
          <table class="w-full border-collapse">
            <thead>
              <tr class="bg-(--bg-elevated)">
                <th class="px-4 py-3 text-left text-xs font-semibold text-(--text-muted) uppercase tracking-wider">Nama Dokumen</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-(--text-muted) uppercase tracking-wider">Tipe</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-(--text-muted) uppercase tracking-wider">Tanggal Upload</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="d in documents" :key="d.id" class="border-b border-(--border-soft)">
                <td class="px-4 py-3 text-sm text-(--text-main)">{{ d.name }}</td>
                <td class="px-4 py-3 text-sm text-(--text-main)">{{ d.type }}</td>
                <td class="px-4 py-3 text-sm text-(--text-main)">{{ d.upload_date }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-if="activeTab === 'riwayat'">
          <table class="w-full border-collapse">
            <thead>
              <tr class="bg-(--bg-elevated)">
                <th class="px-4 py-3 text-left text-xs font-semibold text-(--text-muted) uppercase tracking-wider">Jabatan</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-(--text-muted) uppercase tracking-wider">Departemen</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-(--text-muted) uppercase tracking-wider">Tanggal Mulai</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-(--text-muted) uppercase tracking-wider">Tanggal Berakhir</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="p in positionHistory" :key="p.id" class="border-b border-(--border-soft)">
                <td class="px-4 py-3 text-sm text-(--text-main)">{{ p.position }}</td>
                <td class="px-4 py-3 text-sm text-(--text-main)">{{ p.department }}</td>
                <td class="px-4 py-3 text-sm text-(--text-main)">{{ p.start_date }}</td>
                <td class="px-4 py-3 text-sm text-(--text-main)">{{ p.end_date }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-if="activeTab === 'gaji'">
          <table class="w-full border-collapse">
            <thead>
              <tr class="bg-(--bg-elevated)">
                <th class="px-4 py-3 text-left text-xs font-semibold text-(--text-muted) uppercase tracking-wider">Komponen</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-(--text-muted) uppercase tracking-wider">Jumlah</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-(--text-muted) uppercase tracking-wider">Tanggal Efektif</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="s in salaryComponents" :key="s.id" class="border-b border-(--border-soft)">
                <td class="px-4 py-3 text-sm text-(--text-main)">{{ s.component }}</td>
                <td class="px-4 py-3 text-sm text-(--text-main)">{{ formatRupiah(s.amount) }}</td>
                <td class="px-4 py-3 text-sm text-(--text-main)">{{ s.effective_date }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </BaseCard>
  </div>
</template>
