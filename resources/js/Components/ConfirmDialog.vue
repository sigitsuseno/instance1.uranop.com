<template>
  <BaseModal :show="show" :title="title" :closeable="!loading" @close="$emit('cancel')">
    <p class="text-(--text-main)">{{ message }}</p>

    <template #footer>
      <BaseButton variant="ghost" :disabled="loading" @click="$emit('cancel')">
        {{ cancelText }}
      </BaseButton>
      <BaseButton
        :variant="variant"
        :loading="loading"
        @click="$emit('confirm')"
      >
        {{ confirmText }}
      </BaseButton>
    </template>
  </BaseModal>
</template>

<script setup>
import BaseModal from './BaseModal.vue'
import BaseButton from './BaseButton.vue'

defineProps({
  show: { type: Boolean, default: false },
  title: { type: String, default: 'Konfirmasi' },
  message: { type: String, default: '' },
  confirmText: { type: String, default: 'Ya, Lanjutkan' },
  cancelText: { type: String, default: 'Batal' },
  variant: { type: String, default: 'primary' },
  loading: { type: Boolean, default: false },
})

defineEmits(['confirm', 'cancel'])
</script>
