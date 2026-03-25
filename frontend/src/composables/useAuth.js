import { computed } from 'vue'
import { useAuthStore } from '../stores/auth'

export function useAuth() {
  const authStore = useAuthStore()

  const isAuthenticated = computed(() => authStore.isAuthenticated)
  const user = computed(() => authStore.user)

  const isAdmin = computed(() => {
    return authStore.user?.role === 'admin'
  })

  const isManager = computed(() => {
    return authStore.user?.role === 'admin' || authStore.user?.role === 'manager'
  })

  function hasRole(role) {
    if (!authStore.user) return false

    const roleHierarchy = {
      admin: 3,
      manager: 2,
      user: 1
    }

    const userLevel = roleHierarchy[authStore.user.role] || 0
    const requiredLevel = roleHierarchy[role] || 0

    return userLevel >= requiredLevel
  }

  return {
    isAuthenticated,
    user,
    isAdmin,
    isManager,
    hasRole
  }
}
