import { defineStore } from 'pinia'
import { ref } from 'vue'

export const useNotificationStore = defineStore('notifications', () => {
  const notifications = ref([])
  let nextId = 0

  function addNotification(type, message, duration = 5000) {
    const id = nextId++
    notifications.value.push({ id, type, message })

    if (duration > 0) {
      setTimeout(() => {
        removeNotification(id)
      }, duration)
    }

    return id
  }

  function removeNotification(id) {
    const index = notifications.value.findIndex(n => n.id === id)
    if (index !== -1) {
      notifications.value.splice(index, 1)
    }
  }

  function success(message, duration = 5000) {
    return addNotification('success', message, duration)
  }

  function error(message, duration = 5000) {
    return addNotification('error', message, duration)
  }

  function warning(message, duration = 5000) {
    return addNotification('warning', message, duration)
  }

  function info(message, duration = 5000) {
    return addNotification('info', message, duration)
  }

  return {
    notifications,
    addNotification,
    removeNotification,
    success,
    error,
    warning,
    info
  }
})
