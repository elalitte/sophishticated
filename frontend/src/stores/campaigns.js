import { defineStore } from 'pinia'
import { ref } from 'vue'
import { useApi } from '../composables/useApi'

export const useCampaignStore = defineStore('campaigns', () => {
  const campaigns = ref([])
  const currentCampaign = ref(null)

  const { get, post, put } = useApi()

  async function fetchCampaigns(filters = {}) {
    const params = new URLSearchParams()
    Object.entries(filters).forEach(([key, value]) => {
      if (value !== null && value !== undefined && value !== '') {
        params.append(key, value)
      }
    })
    const query = params.toString()
    const url = query ? `/api/campaigns?${query}` : '/api/campaigns'
    const data = await get(url)
    campaigns.value = data
    return data
  }

  async function fetchCampaign(id) {
    const data = await get(`/api/campaigns/${id}`)
    currentCampaign.value = data
    return data
  }

  async function createCampaign(data) {
    const result = await post('/api/campaigns', data)
    return result
  }

  async function updateCampaign(id, data) {
    const result = await put(`/api/campaigns/${id}`, data)
    return result
  }

  async function launchCampaign(id) {
    const result = await post(`/api/campaigns/${id}/launch`)
    return result
  }

  return {
    campaigns,
    currentCampaign,
    fetchCampaigns,
    fetchCampaign,
    createCampaign,
    updateCampaign,
    launchCampaign
  }
})
