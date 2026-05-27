<script setup>
import { ref, onMounted } from 'vue'
import { useAuth } from '../composables/useAuth'
import LandingPage from '../Pages/Index.vue'

const auth = useAuth()
const loading = ref(true)

onMounted(async () => {
  if (auth.isAuthenticated && !auth.user) {
    await auth.fetchUser()
  }
  loading.value = false
})
</script>

<template>
  <div v-if="loading" class="min-h-screen bg-(--bg-main) flex items-center justify-center">
    <div class="text-(--text-muted)">Loading...</div>
  </div>
  <LandingPage v-else-if="!auth.isAuthenticated" />
  <router-view v-else />
</template>
