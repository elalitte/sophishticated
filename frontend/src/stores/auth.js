import { defineStore } from 'pinia'
import { ref, computed } from 'vue'

export const useAuthStore = defineStore('auth', () => {
  const user = ref(null)
  const csrfToken = ref('')

  const isAuthenticated = computed(() => !!user.value)

  async function login(username, password) {
    const response = await fetch('/api/auth/login', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ username, password })
    })

    if (!response.ok) {
      const error = await response.json()
      throw new Error(error.message || 'Échec de la connexion')
    }

    const data = await response.json()
    user.value = data.user
    csrfToken.value = data.csrf_token || ''
    return data
  }

  async function logout() {
    try {
      await fetch('/api/auth/logout', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrfToken.value
        }
      })
    } finally {
      user.value = null
      csrfToken.value = ''
    }
  }

  async function fetchUser() {
    try {
      const response = await fetch('/api/auth/me', {
        headers: { 'Content-Type': 'application/json' }
      })

      if (!response.ok) {
        user.value = null
        csrfToken.value = ''
        return null
      }

      const data = await response.json()
      user.value = data.user
      csrfToken.value = data.csrf_token || ''
      return data.user
    } catch {
      user.value = null
      csrfToken.value = ''
      return null
    }
  }

  async function changePassword(oldPassword, newPassword) {
    const response = await fetch('/api/auth/change-password', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken.value
      },
      body: JSON.stringify({
        old_password: oldPassword,
        new_password: newPassword
      })
    })

    if (!response.ok) {
      const error = await response.json()
      throw new Error(error.message || 'Échec du changement de mot de passe')
    }

    return await response.json()
  }

  return {
    user,
    csrfToken,
    isAuthenticated,
    login,
    logout,
    fetchUser,
    changePassword
  }
})
