<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import GuestLayout from '../../Layouts/GuestLayout.vue'
import { useAuth } from '../../composables/useAuth'
import TextInput from '../../Components/TextInput.vue'
import BaseButton from '../../Components/BaseButton.vue'

const router = useRouter()
const auth = useAuth()

const email = ref('')
const password = ref('')
const error = ref('')
const loading = ref(false)

async function submit() {
  error.value = ''

  if (!email.value.trim() || !password.value.trim()) {
    error.value = 'Email dan password harus diisi.'
    return
  }

  loading.value = true
  try {
    await auth.login({ email: email.value.trim(), password: password.value })
    router.push('/')
  } catch (e) {
    const message = e?.response?.data?.message
      || e?.message
      || 'Login gagal. Periksa email dan password Anda.'
    error.value = message
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <GuestLayout>
    <div>
      <div class="text-center mb-6">
        <h1 class="text-3xl font-bold text-(--primary)">Uranop</h1>
        <p class="text-sm text-(--text-muted) mt-1">Enterprise HRIS</p>
      </div>

      <form @submit.prevent="submit">
        <div class="space-y-4">
          <TextInput
            v-model="email"
            label="Email"
            type="email"
            placeholder="Masukkan email"
            required
          />

          <TextInput
            v-model="password"
            label="Password"
            type="password"
            placeholder="Masukkan password"
            required
          />

          <div
            v-if="error"
            class="rounded-md bg-(--danger)/10 border border-(--danger)/25 px-4 py-3 text-sm text-(--danger)"
          >
            {{ error }}
          </div>

          <BaseButton
            type="submit"
            variant="primary"
            :loading="loading"
            class="w-full"
          >
            Masuk
          </BaseButton>
        </div>
      </form>

      <p class="mt-6 text-center text-xs text-(--text-muted)">
        Demo: superadmin@uranop.com / password
      </p>
    </div>
  </GuestLayout>
</template>
