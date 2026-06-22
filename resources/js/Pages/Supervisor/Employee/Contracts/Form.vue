<script setup>
import { ref, reactive, watch, onMounted } from 'vue'
import { useApi } from '../../../../composables/useApi'
import { useNotificationStore } from '../../../../Stores/notification'
import BaseButton from '../../../../Components/BaseButton.vue'
import TextInput from '../../../../Components/TextInput.vue'
import SelectInput from '../../../../Components/SelectInput.vue'

const props = defineProps({
  contract: {
    type: Object,
    default: null
  },
  employee: {
    type: Object,
    required: true
  }
})

const emit = defineEmits(['success', 'cancel'])

const { post, put } = useApi()
const notification = useNotificationStore()
const loading = ref(false)
const errors = ref({})

const form = reactive({
  contract_number: '',
  contract_type: 'pkwt',
  start_date: '',
  end_date: '',
  notes: '',
  status: 'active'
})

const contractTypes = [
  { value: 'pkwt', label: 'PKWT' },
  { value: 'pkwtt', label: 'PKWTT' },
  { value: 'outsourcing', label: 'Outsourcing' },
  { value: 'freelance', label: 'Freelance' }
]

const contractStatuses = [
  { value: 'active', label: 'Aktif' },
  { value: 'expired', label: 'Expired' },
  { value: 'terminated', label: 'Dihentikan' },
  { value: 'draft', label: 'Draft' }
]

function initForm() {
  errors.value = {}
  if (props.contract) {
    form.contract_number = props.contract.contract_number || ''
    form.contract_type = props.contract.contract_type || 'pkwt'
    form.start_date = props.contract.start_date ? props.contract.start_date.split('T')[0] : ''
    form.end_date = props.contract.end_date ? props.contract.end_date.split('T')[0] : ''
    form.notes = props.contract.notes || ''
    form.status = props.contract.status || 'active'
  } else {
    // Generate simple contract number placeholder
    const date = new Date()
    const prefix = `CTR-${date.getFullYear()}${String(date.getMonth() + 1).padStart(2, '0')}`
    form.contract_number = `${prefix}-XXXX`
    form.contract_type = 'pkwt'
    form.start_date = ''
    form.end_date = ''
    form.notes = ''
    form.status = 'active'
  }
}

watch(() => props.contract, () => {
  initForm()
}, { deep: true })

onMounted(() => {
  initForm()
})

async function submitForm() {
  loading.value = true
  errors.value = {}
  try {
    if (props.contract) {
      await put(`/api/v1/supervisor/employee-data/karyawan/${props.employee.id}/contracts/${props.contract.id}`, form)
      notification.addNotification('Kontrak berhasil diperbarui.', 'success')
    } else {
      await post(`/api/v1/supervisor/employee-data/karyawan/${props.employee.id}/contracts`, form)
      notification.addNotification('Kontrak berhasil ditambahkan.', 'success')
    }
    emit('success')
  } catch (e) {
    if (e.response?.data?.errors) {
      errors.value = e.response.data.errors
    } else {
      notification.addNotification(e.response?.data?.message || 'Gagal menyimpan kontrak.', 'error')
    }
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <form @submit.prevent="submitForm" class="space-y-4">
    <div class="bg-(--bg-elevated) border border-(--border-soft) rounded-md p-4 flex items-center gap-4 mb-4">
      <div class="w-12 h-12 rounded-full bg-(--primary)/10 flex items-center justify-center shrink-0">
        <img v-if="employee.photo_url" :src="employee.photo_url" class="w-full h-full rounded-full object-cover" />
        <i v-else class="bx bx-user text-xl text-(--primary)"></i>
      </div>
      <div>
        <p class="text-xs text-(--text-muted) uppercase font-bold tracking-wider">Karyawan</p>
        <p class="font-bold text-(--text-main)">{{ employee.name }}</p>
        <p class="text-xs text-(--text-soft)">{{ employee.employee_code }} • {{ employee.department?.name }}</p>
      </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <TextInput
        v-model="form.contract_number"
        label="Nomor Kontrak"
        placeholder="Cth: CTR-202605-0001"
        :error="errors.contract_number?.[0]"
        required
      />
      
      <SelectInput
        v-model="form.contract_type"
        label="Tipe Kontrak"
        :options="contractTypes"
        :error="errors.contract_type?.[0]"
        required
      />
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <TextInput
        type="date"
        v-model="form.start_date"
        label="Tanggal Mulai"
        :error="errors.start_date?.[0]"
        required
      />
      
      <TextInput
        type="date"
        v-model="form.end_date"
        label="Tanggal Berakhir"
        :error="errors.end_date?.[0]"
        :required="form.contract_type === 'pkwt'"
      />
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <SelectInput
        v-model="form.status"
        label="Status Kontrak"
        :options="contractStatuses"
        :error="errors.status?.[0]"
        required
      />
    </div>

    <div>
      <label class="block text-sm font-medium text-(--text-main) mb-1">Catatan</label>
      <textarea
        v-model="form.notes"
        rows="3"
        class="w-full rounded-md bg-(--bg-card) border border-(--border-soft) text-(--text-main) px-3 py-2 text-sm focus:ring-2 focus:ring-(--primary-glow) focus:border-(--primary) outline-none transition-all"
        placeholder="Catatan tambahan (opsional)"
      ></textarea>
      <p v-if="errors.notes" class="text-xs text-red-500 mt-1">{{ errors.notes[0] }}</p>
    </div>

    <div class="flex items-center justify-end gap-3 pt-4 border-t border-(--border-soft)">
      <BaseButton type="button" variant="secondary" @click="emit('cancel')">Batal</BaseButton>
      <BaseButton type="submit" variant="primary" :loading="loading">
        <template #icon-left>
          <i class="bx bx-save"></i>
        </template>
        Simpan Kontrak
      </BaseButton>
    </div>
  </form>
</template>
