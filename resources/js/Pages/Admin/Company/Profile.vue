<script setup>
import { ref, onMounted } from 'vue'
import BaseCard from '../../../Components/BaseCard.vue'
import BaseButton from '../../../Components/BaseButton.vue'
import TextInput from '../../../Components/TextInput.vue'
import { useApi } from '../../../composables/useApi'
import { useNotification } from '../../../composables/useNotification'
import { useCompanyStore } from '../../../Stores/company'

const { get, post, loading } = useApi()
const notification = useNotification()
const companyStore = useCompanyStore()

const form = ref({
  company_name: '',
  company_npwp: '',
  company_address: '',
  company_phone: '',
  company_email: '',
  company_website: '',
  
  branch_name: '',
  branch_code: '',
  branch_address: '',
  branch_phone: '',
  branch_email: '',
  branch_pic_name: '',
  branch_nama_pimpinan: '',

  logo: null,
  ttd_pimpinan: null
})

const currentLogoUrl = ref(null)
const currentTtdUrl = ref(null)
const fileInput = ref(null)
const ttdInput = ref(null)

onMounted(async () => {
  await fetchProfile()
})

async function fetchProfile() {
  try {
    const response = await get('/api/organization/company-profile')
    const company = response.data.company
    const branch = response.data.branch
    
    if (company) {
      form.value.company_name = company.name || ''
      form.value.company_npwp = company.npwp || ''
      form.value.company_address = company.address || ''
      form.value.company_phone = company.phone || ''
      form.value.company_email = company.email || ''
      form.value.company_website = company.website || ''
      currentLogoUrl.value = company.logo_path ? `/storage/${company.logo_path}` : null
    }

    if (branch) {
      form.value.branch_name = branch.name || ''
      form.value.branch_code = branch.code || ''
      form.value.branch_address = branch.address || ''
      form.value.branch_phone = branch.phone || ''
      form.value.branch_email = branch.email || ''
      form.value.branch_pic_name = branch.pic_name || ''
      form.value.branch_nama_pimpinan = branch.nama_pimpinan || ''
      currentTtdUrl.value = branch.ttd_pimpinan ? `/storage/${branch.ttd_pimpinan}` : null
    }
  } catch (error) {
    notification.error('Gagal memuat data profil perusahaan')
  }
}

function onFileChange(e) {
  const file = e.target.files[0]
  if (file) {
    form.value.logo = file
    currentLogoUrl.value = URL.createObjectURL(file)
  }
}

function onTtdChange(e) {
  const file = e.target.files[0]
  if (file) {
    form.value.ttd_pimpinan = file
    currentTtdUrl.value = URL.createObjectURL(file)
  }
}

async function saveProfile() {
  const formData = new FormData()
  for (const key in form.value) {
    if (form.value[key] !== null && form.value[key] !== undefined) {
      formData.append(key, form.value[key])
    }
  }

  try {
    const response = await post('/api/organization/company-profile', formData)
    notification.success(response.message)
    // Clear logo & ttd file input state but keep preview
    form.value.logo = null
    form.value.ttd_pimpinan = null
    if (fileInput.value) {
      fileInput.value.value = ''
    }
    if (ttdInput.value) {
      ttdInput.value.value = ''
    }
    // reload to get exact path from server
    await fetchProfile()
    // refresh store company agar logo & nama langsung ter-update di landing/login/sidebar
    companyStore.fetchCompany(true)
  } catch (error) {
    notification.error(error.response?.data?.message || 'Terjadi kesalahan')
  }
}
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-(--text-main)">Profil Perusahaan</h1>
        <p class="text-(--text-muted) text-sm mt-1">Kelola identitas perusahaan dan cabang untuk instance ini.</p>
      </div>
      <BaseButton @click="saveProfile" :loading="loading" icon="bx bx-save" variant="primary">
        Simpan Profil
      </BaseButton>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <!-- Company Profile -->
      <BaseCard title="Data Perusahaan">
        <div class="space-y-4">
          <div class="flex items-center gap-4 mb-6">
            <div class="w-24 h-24 rounded-lg bg-(--bg-elevated) border border-(--border-soft) flex items-center justify-center overflow-hidden shrink-0">
              <img v-if="currentLogoUrl" :src="currentLogoUrl" alt="Logo" class="w-full h-full object-contain" />
              <i v-else class="bx bx-buildings text-3xl text-(--text-muted)"></i>
            </div>
            <div>
              <label class="block text-sm font-medium text-(--text-main) mb-1">Logo Perusahaan</label>
              <input 
                type="file" 
                ref="fileInput"
                accept="image/*" 
                @change="onFileChange"
                class="block w-full text-sm text-(--text-muted) file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-(--primary-glow) file:text-(--primary) hover:file:bg-(--primary-glow)"
              />
              <p class="text-xs text-(--text-muted) mt-1">Format: JPG, PNG, GIF (Max 2MB)</p>
            </div>
          </div>

          <TextInput v-model="form.company_name" label="Nama Perusahaan" required />
          <TextInput v-model="form.company_npwp" label="NPWP" />
          
          <div>
            <label class="block text-sm font-medium text-(--text-main) mb-1">Alamat</label>
            <textarea 
              v-model="form.company_address" 
              class="w-full rounded-md border border-(--border-soft) bg-(--bg-main) px-3 py-2 text-sm text-(--text-main) focus:border-(--primary) focus:ring-1 focus:ring-(--primary) transition-colors"
              rows="3"
            ></textarea>
          </div>

          <div class="grid grid-cols-2 gap-4">
            <TextInput v-model="form.company_phone" label="No. Telepon" />
            <TextInput v-model="form.company_email" label="Email" type="email" />
          </div>
          
          <TextInput v-model="form.company_website" label="Website" />
        </div>
      </BaseCard>

      <!-- Branch Profile -->
      <BaseCard title="Data Cabang">
        <div class="space-y-4">
          <div class="grid grid-cols-2 gap-4">
            <TextInput v-model="form.branch_name" label="Nama Cabang" required />
            <TextInput v-model="form.branch_code" label="Kode Cabang" />
          </div>

          <div>
            <label class="block text-sm font-medium text-(--text-main) mb-1">Alamat Cabang</label>
            <textarea 
              v-model="form.branch_address" 
              class="w-full rounded-md border border-(--border-soft) bg-(--bg-main) px-3 py-2 text-sm text-(--text-main) focus:border-(--primary) focus:ring-1 focus:ring-(--primary) transition-colors"
              rows="3"
            ></textarea>
          </div>

          <div class="grid grid-cols-2 gap-4">
            <TextInput v-model="form.branch_phone" label="No. Telepon Cabang" />
            <TextInput v-model="form.branch_email" label="Email Cabang" type="email" />
          </div>

          <TextInput v-model="form.branch_nama_pimpinan" label="Nama Pimpinan (penandatangan kontrak)" />

          <div class="flex items-center gap-4">
            <div class="w-40 h-20 rounded-lg bg-(--bg-elevated) border border-(--border-soft) flex items-center justify-center overflow-hidden shrink-0">
              <img v-if="currentTtdUrl" :src="currentTtdUrl" alt="Tanda tangan" class="w-full h-full object-contain" />
              <i v-else class="bx bx-pen text-3xl text-(--text-muted)"></i>
            </div>
            <div>
              <label class="block text-sm font-medium text-(--text-main) mb-1">Tanda Tangan Pimpinan</label>
              <input
                type="file"
                ref="ttdInput"
                accept="image/*"
                @change="onTtdChange"
                class="block w-full text-sm text-(--text-muted) file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-(--primary-glow) file:text-(--primary) hover:file:bg-(--primary-glow)"
              />
              <p class="text-xs text-(--text-muted) mt-1">Format: PNG/JPG (Max 2MB). PNG latar transparan disarankan.</p>
            </div>
          </div>

          <TextInput v-model="form.branch_pic_name" label="Nama HR" />
        </div>
      </BaseCard>
    </div>
  </div>
</template>
