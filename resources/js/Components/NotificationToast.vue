<template>
  <div class="fixed top-4 right-4 z-50 space-y-2 max-w-sm w-full pointer-events-none">
    <TransitionGroup name="toast" tag="div" class="space-y-2">
      <div
        v-for="notification in notifications"
        :key="notification.id"
        :class="['pointer-events-auto bg-(--bg-card) shadow-lg rounded-md border border-(--border-soft) p-3 flex items-start gap-3', borderClass(notification.type)]"
      >
        <div :class="['flex-shrink-0 mt-0.5', iconColorClass(notification.type)]">
          <svg v-if="notification.type === 'success'" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10" />
            <polyline points="16 10 10.5 15 8 12" />
          </svg>
          <svg v-else-if="notification.type === 'error'" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10" />
            <line x1="15" y1="9" x2="9" y2="15" />
            <line x1="9" y1="9" x2="15" y2="15" />
          </svg>
          <svg v-else-if="notification.type === 'warning'" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" />
            <line x1="12" y1="9" x2="12" y2="13" />
            <line x1="12" y1="17" x2="12.01" y2="17" />
          </svg>
          <svg v-else-if="notification.type === 'info'" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10" />
            <line x1="12" y1="16" x2="12" y2="12" />
            <line x1="12" y1="8" x2="12.01" y2="8" />
          </svg>
        </div>
        <div class="flex-1 text-sm text-(--text-main)">
          {{ notification.message }}
        </div>
        <button
          class="flex-shrink-0 text-(--text-muted) hover:text-(--text-main) transition-colors"
          @click="remove(notification.id)"
        >
          <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <line x1="18" y1="6" x2="6" y2="18" />
            <line x1="6" y1="6" x2="18" y2="18" />
          </svg>
        </button>
      </div>
    </TransitionGroup>
  </div>
</template>

<script setup>
import { useNotificationStore } from '../Stores/notification'
import { storeToRefs } from 'pinia'

const store = useNotificationStore()
const { notifications } = storeToRefs(store)
const { remove } = store

function borderClass(type) {
  return {
    success: 'border-l-4 border-l-(--success)',
    error: 'border-l-4 border-l-(--danger)',
    warning: 'border-l-4 border-l-(--warning)',
    info: 'border-l-4 border-l-(--primary)',
  }[type] || ''
}

function iconColorClass(type) {
  return {
    success: 'text-(--success)',
    error: 'text-(--danger)',
    warning: 'text-(--warning)',
    info: 'text-(--primary)',
  }[type] || 'text-(--text-muted)'
}
</script>

<style scoped>
.toast-enter-active {
  transition: all 0.3s ease-out;
}
.toast-leave-active {
  transition: all 0.2s ease-in;
}
.toast-enter-from {
  opacity: 0;
  transform: translateX(100px);
}
.toast-leave-to {
  opacity: 0;
  transform: translateX(100px);
}
</style>
