<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-bold text-(--text-main)">Pusat Notifikasi</h1>
      <BaseButton v-if="unreadCount > 0" variant="secondary" @click="markAllAsRead">
        Tandai Semua Sudah Dibaca
      </BaseButton>
    </div>

    <BaseCard class="p-6">
      <div v-if="loading" class="flex justify-center py-8">
        <span class="loading loading-spinner loading-md text-primary"></span>
      </div>

      <div v-else-if="notifications.length === 0" class="text-center py-8 text-(--text-muted)">
        <svg class="w-12 h-12 mx-auto mb-3 text-(--text-soft)" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
        </svg>
        <p>Tidak ada notifikasi saat ini.</p>
      </div>

      <div v-else class="space-y-4">
        <div 
          v-for="notification in notifications" 
          :key="notification.id"
          class="p-4 rounded-lg border transition-colors flex items-start gap-4"
          :class="notification.read_at ? 'bg-(--bg-card) border-(--border-soft)' : 'bg-(--primary)/5 border-(--primary)/20'"
        >
          <div class="flex-shrink-0 mt-1">
            <span class="w-2 h-2 rounded-full inline-block" :class="notification.read_at ? 'bg-(--border-strong)' : 'bg-(--primary)'"></span>
          </div>
          
          <div class="flex-grow">
            <h3 class="font-medium text-(--text-main)">
              {{ notification.data.title || 'Pemberitahuan Baru' }}
            </h3>
            <p class="text-sm text-(--text-muted) mt-1">
              {{ notification.data.message || 'Anda mendapatkan notifikasi baru.' }}
            </p>
            <div class="text-xs text-(--text-soft) mt-2 flex items-center gap-4">
              <span>{{ formatDate(notification.created_at) }}</span>
              <button 
                v-if="!notification.read_at"
                @click="markAsRead(notification.id)"
                class="text-(--primary) hover:underline"
              >
                Tandai dibaca
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Pagination -->
      <div v-if="totalPages > 1" class="mt-6">
        <Pagination 
          :current-page="currentPage" 
          :total-pages="totalPages" 
          @page-change="fetchNotifications" 
        />
      </div>
    </BaseCard>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useApi } from '../../../composables/useApi'
import { useDate } from '../../../composables/useDate'
import { useNotificationStore } from '../../../Stores/notification'

import BaseCard from '../../../Components/BaseCard.vue'
import BaseButton from '../../../Components/BaseButton.vue'
import Pagination from '../../../Components/Table/Pagination.vue'

const { get, post } = useApi()
const { formatDateTime: formatDate } = useDate()
const notificationStore = useNotificationStore()

const loading = ref(true)
const notifications = ref([])
const unreadCount = ref(0)
const currentPage = ref(1)
const totalPages = ref(1)

const fetchNotifications = async (page = 1) => {
  loading.value = true
  try {
    const { data } = await get(`/api/notifications?page=${page}`)
    notifications.value = data.notifications.data
    unreadCount.value = data.unread_count
    currentPage.value = data.notifications.current_page
    totalPages.value = data.notifications.last_page
    
    // Update store state as well
    notificationStore.unreadCount = data.unread_count
  } catch (error) {
    console.error('Failed to fetch notifications', error)
  } finally {
    loading.value = false
  }
}

const markAsRead = async (id) => {
  try {
    await post(`/api/notifications/${id}/mark-read`)
    // Update local state
    const item = notifications.value.find(n => n.id === id)
    if (item) item.read_at = new Date().toISOString()
    unreadCount.value--
    notificationStore.unreadCount = unreadCount.value
  } catch (error) {
    console.error('Failed to mark as read', error)
  }
}

const markAllAsRead = async () => {
  try {
    await post('/api/notifications/mark-all-read')
    notifications.value.forEach(n => {
      if (!n.read_at) n.read_at = new Date().toISOString()
    })
    unreadCount.value = 0
    notificationStore.unreadCount = 0
  } catch (error) {
    console.error('Failed to mark all as read', error)
  }
}

onMounted(() => {
  fetchNotifications()
})
</script>
