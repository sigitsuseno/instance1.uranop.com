<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuth } from '../../composables/useAuth'

const router = useRouter()
const auth = useAuth()

const form = ref({
  email: '',
  password: '',
  remember: false
})

const showPassword = ref(false)
const processing = ref(false)
const errors = ref({})

async function submit() {
  processing.value = true
  errors.value = {}

  try {
    await auth.login({
      email: form.value.email,
      password: form.value.password,
    })

    if (auth.canAccessAdmin) {
      router.push('/')
    } else {
      router.push('/supervisor')
    }
  } catch (e) {
    errors.value = { error: e.message || 'Login gagal. Periksa email dan password Anda.' }
  } finally {
    processing.value = false
  }
}
</script>

<template>
  <div class="min-h-screen bg-(--bg-main) flex items-center justify-center p-4">
    <div class="absolute inset-0 overflow-hidden pointer-events-none">
      <div class="absolute -top-40 -right-40 w-80 h-80 bg-(--primary) opacity-5 rounded-full blur-3xl"></div>
      <div class="absolute -bottom-40 -left-40 w-80 h-80 bg-(--primary) opacity-5 rounded-full blur-3xl"></div>
    </div>

    <div class="max-w-sm w-full relative z-10">
      <div class="text-center mb-8">
        <div class="inline-flex items-center justify-center space-x-3 mb-4">
          <div class="w-14 h-14 bg-(--primary) rounded-xl flex items-center justify-center font-black text-white text-3xl shadow-lg shadow-(--primary-glow)">
            U
          </div>
          <span class="text-2xl font-bold tracking-tight text-(--text-main)">Uranop</span>
        </div>
        <h2 class="text-lg font-medium text-(--text-muted)">Enterprise HRIS System</h2>
      </div>

      <div class="bg-(--bg-card) rounded-md border border-(--border-soft) shadow-xl p-8">
        <form @submit.prevent="submit" class="space-y-6">
          <div>
            <label class="block text-sm font-medium text-(--text-main) mb-2">Email</label>
            <div class="relative">
              <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-(--text-soft)">
                <i class="bx bx-envelope text-lg"></i>
              </span>
              <input
                v-model="form.email"
                type="email"
                required
                :class="[
                  'w-full pl-10 pr-3 py-2 rounded-md border bg-(--bg-elevated) text-(--text-main) placeholder-(--text-soft) focus:ring-2 focus:ring-(--primary-glow) outline-none transition-all',
                  errors.email ? 'border-red-500' : 'border-(--border-soft)'
                ]"
                placeholder="nama@perusahaan.com"
              />
            </div>
            <p v-if="errors.email" class="mt-1 text-sm text-red-500">{{ errors.email }}</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-(--text-main) mb-2">Password</label>
            <div class="relative">
              <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-(--text-soft)">
                <i class="bx bx-lock-alt text-lg"></i>
              </span>
              <input
                v-model="form.password"
                :type="showPassword ? 'text' : 'password'"
                required
                :class="[
                  'w-full pl-10 pr-10 py-2 rounded-md border bg-(--bg-elevated) text-(--text-main) placeholder-(--text-soft) focus:ring-2 focus:ring-(--primary-glow) outline-none transition-all',
                  errors.password ? 'border-red-500' : 'border-(--border-soft)'
                ]"
                placeholder="&#8226;&#8226;&#8226;&#8226;&#8226;&#8226;&#8226;&#8226;"
              />
              <button
                type="button"
                @click="showPassword = !showPassword"
                class="absolute inset-y-0 right-0 pr-3 flex items-center text-(--text-soft) hover:text-(--primary) transition-colors"
              >
                <i :class="showPassword ? 'bx bx-hide' : 'bx bx-show'"></i>
              </button>
            </div>
            <p v-if="errors.password" class="mt-1 text-sm text-red-500">{{ errors.password }}</p>
          </div>

          <div class="flex items-center justify-between">
            <label class="flex items-center space-x-2 cursor-pointer">
              <input
                v-model="form.remember"
                type="checkbox"
                class="w-4 h-4 rounded border-(--border-strong) bg-(--bg-elevated) text-(--primary) focus:ring-(--primary-glow) focus:ring-2"
              />
              <span class="text-sm text-(--text-muted) select-none">Remember me</span>
            </label>
          </div>

          <div v-if="errors.error" class="p-3 bg-red-500/10 border border-red-500/20 rounded-md">
            <p class="text-sm text-red-500 flex items-center">
              <i class="bx bx-error-circle text-lg mr-2"></i>
              {{ errors.error }}
            </p>
          </div>

          <button
            type="submit"
            :disabled="processing"
            class="w-full py-2 px-4 bg-(--primary) hover:bg-(--primary-hover) text-white font-medium rounded-md transition-all duration-200 flex items-center justify-center space-x-2 disabled:opacity-50 disabled:cursor-not-allowed shadow-lg shadow-(--primary-glow)"
          >
            <i v-if="processing" class="bx bx-loader-alt bx-spin text-xl"></i>
            <span>{{ processing ? 'Authenticating...' : 'Sign In' }}</span>
            <i v-if="!processing" class="bx bx-log-in text-xl"></i>
          </button>

          <div class="text-center">
            <p class="text-xs text-(--text-soft)">
              <i class="bx bx-shield-quarter text-sm mr-1"></i>
              Secured by Uranop Enterprise
            </p>
          </div>
        </form>
      </div>

      <p class="text-center text-xs text-(--text-soft) mt-6">
        &copy; {{ new Date().getFullYear() }} Uranop Enterprise. All rights reserved.
      </p>
    </div>
  </div>
</template>
