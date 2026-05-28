<script setup>
import { ref, computed } from 'vue'
import { useRoute } from 'vue-router'
import { useTheme } from '../../composables/useTheme'
import Sidebar from './Sidebar.vue'
import Topbar from './Topbar.vue'
import Footer from './Footer.vue'
import NotificationToast from '../../Components/NotificationToast.vue'

defineProps({
  title: { type: String, default: 'Dashboard' },
})

const route = useRoute()
const { isDark, toggle: toggleTheme } = useTheme()

const pageTitle = computed(() => route.meta?.title || 'Dashboard')
const sidebarCollapsed = ref(false)

function toggleSidebar() {
  sidebarCollapsed.value = !sidebarCollapsed.value
}
</script>

<template>
  <div class="min-h-screen bg-(--bg-main) text-(--text-main) transition-colors duration-300">
    <Sidebar :collapsed="sidebarCollapsed" @toggle="toggleSidebar" />

    <div :class="['transition-all duration-300', sidebarCollapsed ? 'ml-20' : 'ml-55']">
      <Topbar
        :title="pageTitle"
        :is-dark="isDark"
        :sidebar-collapsed="sidebarCollapsed"
        @toggle-sidebar="toggleSidebar"
        @toggle-theme="toggleTheme"
      />

      <main class="p-6 min-h-[calc(100vh-101px)]">
        <router-view />
      </main>

      <Footer />
    </div>

    <!-- Global Notification Toast -->
    <NotificationToast />
  </div>
</template>
