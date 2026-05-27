import { useNotificationStore } from '../Stores/notification'

export function useNotification() {
  const store = useNotificationStore()
  return {
    success: store.success,
    error: store.error,
    warning: store.warning,
    info: store.info,
  }
}
