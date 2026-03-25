import { defineStore } from 'pinia'
import { ref } from 'vue'
import { useApi } from '../composables/useApi'

export const useRecipientStore = defineStore('recipients', () => {
  const recipients = ref([])
  const groups = ref([])

  const { get, post } = useApi()

  async function fetchRecipients(params = {}) {
    const query = new URLSearchParams()
    Object.entries(params).forEach(([key, value]) => {
      if (value !== null && value !== undefined && value !== '') {
        query.append(key, value)
      }
    })
    const queryStr = query.toString()
    const url = queryStr ? `/api/recipients?${queryStr}` : '/api/recipients'
    const data = await get(url)
    recipients.value = data
    return data
  }

  async function fetchGroups() {
    const data = await get('/api/recipients/groups')
    groups.value = data
    return data
  }

  async function syncFromGraph() {
    const data = await post('/api/recipients/sync')
    return data
  }

  return {
    recipients,
    groups,
    fetchRecipients,
    fetchGroups,
    syncFromGraph
  }
})
