import { defineStore } from 'pinia'
import { ref } from 'vue'

let nextId = 0

export const useNotificationStore = defineStore('notification', () => {
  const notifications = ref([])

  function add(type, message, duration = 4000) {
    const id = ++nextId
    notifications.value.push({ id, type, message, duration })
    if (duration > 0) {
      setTimeout(() => remove(id), duration)
    }
  }

  function remove(id) {
    notifications.value = notifications.value.filter((n) => n.id !== id)
  }

  function success(message) { add('success', message) }
  function error(message) { add('error', message) }
  function warning(message) { add('warning', message) }
  function info(message) { add('info', message) }

  function addNotification(message, type = 'info', duration = 4000) {
    add(type, message, duration)
  }

  return { notifications, add, remove, success, error, warning, info, addNotification }
})
