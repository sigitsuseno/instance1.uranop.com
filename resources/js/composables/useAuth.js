import { useAuthStore } from '../Stores/auth'

export function useAuth() {
  const store = useAuthStore()
  return store
}
