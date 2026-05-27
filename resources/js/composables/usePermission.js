import { usePermissionStore } from '../Stores/permission'

export function usePermission() {
  const store = usePermissionStore()
  return store
}
