import { ref } from 'vue'
import { useAuthStore } from '../stores/auth'
import { useNotificationStore } from '../stores/notifications'
import { useRouter } from 'vue-router'

export function useApi() {
  const loading = ref(false)
  const router = useRouter()

  async function request(url, options = {}) {
    const authStore = useAuthStore()
    const notifications = useNotificationStore()

    loading.value = true

    const headers = {
      'Content-Type': 'application/json',
      ...options.headers
    }

    if (authStore.csrfToken) {
      headers['X-CSRF-TOKEN'] = authStore.csrfToken
    }

    try {
      const response = await fetch(url, {
        ...options,
        headers
      })

      if (response.status === 401) {
        authStore.user = null
        authStore.csrfToken = ''
        router.push({ name: 'Login' })
        throw new Error('Non authentifié')
      }

      if (response.status === 403) {
        notifications.error('Accès non autorisé')
        throw new Error('Accès non autorisé')
      }

      if (response.status === 422) {
        const data = await response.json()
        return { errors: data.errors || data }
      }

      if (response.status >= 500) {
        notifications.error('Erreur serveur')
        throw new Error('Erreur serveur')
      }

      if (!response.ok) {
        const data = await response.json().catch(() => ({}))
        throw new Error(data.error || data.message || `Erreur ${response.status}`)
      }

      if (response.status === 204) {
        return null
      }

      return await response.json()
    } catch (error) {
      if (error.message !== 'Non authentifié' &&
          error.message !== 'Accès non autorisé' &&
          error.message !== 'Erreur serveur') {
        notifications.error(error.message || 'Une erreur est survenue')
      }
      throw error
    } finally {
      loading.value = false
    }
  }

  function get(url) {
    return request(url, { method: 'GET' })
  }

  function post(url, data) {
    return request(url, {
      method: 'POST',
      body: data !== undefined ? JSON.stringify(data) : undefined
    })
  }

  function put(url, data) {
    return request(url, {
      method: 'PUT',
      body: JSON.stringify(data)
    })
  }

  function del(url) {
    return request(url, { method: 'DELETE' })
  }

  return {
    loading,
    get,
    post,
    put,
    del
  }
}
