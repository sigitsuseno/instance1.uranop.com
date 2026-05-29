import { ref } from 'vue'
import { useApi } from './useApi'

export function useWebPush() {
  const { post } = useApi()
  const isSubscribed = ref(false)
  const isSupported = ref('serviceWorker' in navigator && 'PushManager' in window)

  // Needs to be your public VAPID key
  const vapidPublicKey = import.meta.env.VITE_VAPID_PUBLIC_KEY

  function urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - base64String.length % 4) % 4);
    const base64 = (base64String + padding)
      .replace(/\-/g, '+')
      .replace(/_/g, '/');

    const rawData = window.atob(base64);
    const outputArray = new Uint8Array(rawData.length);

    for (let i = 0; i < rawData.length; ++i) {
      outputArray[i] = rawData.charCodeAt(i);
    }
    return outputArray;
  }

  async function registerServiceWorker() {
    if (!isSupported.value) return null
    try {
      const registration = await navigator.serviceWorker.register('/sw.js')
      return registration
    } catch (e) {
      console.error('Service Worker registration failed:', e)
      return null
    }
  }

  async function subscribe() {
    if (!isSupported.value) return false
    
    try {
      const permission = await Notification.requestPermission()
      if (permission !== 'granted') return false

      const registration = await registerServiceWorker()
      if (!registration) return false

      const subscription = await registration.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey: urlBase64ToUint8Array(vapidPublicKey)
      })

      // Send to server
      await post('/api/v1/push-subscribe', subscription)
      isSubscribed.value = true
      return true
    } catch (e) {
      console.error('Push subscription failed:', e)
      return false
    }
  }

  return {
    isSupported,
    isSubscribed,
    subscribe,
    registerServiceWorker
  }
}
