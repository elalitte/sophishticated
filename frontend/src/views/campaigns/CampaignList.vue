<template>
  <AppLayout>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Campagnes</h1>
        <p class="mt-1 text-sm text-gray-500">Gérez vos campagnes de phishing simulé</p>
      </div>
      <router-link
        to="/campaigns/create"
        class="inline-flex items-center gap-2 px-4 py-2.5 bg-brand-500 text-white rounded-lg hover:bg-brand-600 transition-colors font-medium text-sm"
      >
        <Plus class="w-4 h-4" />
        Nouvelle campagne
      </router-link>
    </div>

    <!-- Filters -->
    <div class="flex flex-wrap items-center gap-3 p-4 bg-white rounded-lg border border-gray-200">
      <div class="flex items-center gap-2">
        <Filter class="w-4 h-4 text-gray-400" />
        <span class="text-sm font-medium text-gray-600">Statut :</span>
      </div>
      <button
        v-for="s in statusOptions"
        :key="s.value"
        @click="statusFilter = statusFilter === s.value ? '' : s.value"
        :class="[
          'px-3 py-1.5 text-xs font-medium rounded-full border transition-colors',
          statusFilter === s.value
            ? statusColors[s.value].active
            : 'border-gray-200 text-gray-600 hover:bg-gray-50'
        ]"
      >
        {{ s.label }}
      </button>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
      <DataTable :columns="columns" :data="filteredCampaigns" :loading="loading">
        <template #cell-name="{ row }">
          <div class="font-medium text-gray-900">{{ row.name }}</div>
        </template>

        <template #cell-status="{ row }">
          <Badge :text="statusLabels[row.status] || row.status" :color="statusBadgeColor(row.status)" />
        </template>

        <template #cell-template_name="{ row }">
          <span class="text-sm text-gray-600">{{ row.template_name || '-' }}</span>
        </template>

        <template #cell-target_count="{ row }">
          <span class="text-sm font-medium">{{ row.target_count || 0 }}</span>
        </template>

        <template #cell-created_at="{ row }">
          <span class="text-sm text-gray-500">{{ formatDate(row.created_at) }}</span>
        </template>

        <template #cell-launched_at="{ row }">
          <span class="text-sm text-gray-500">{{ row.launched_at ? formatDate(row.launched_at) : '-' }}</span>
        </template>

        <template #cell-progress="{ row }">
          <div v-if="row.status === 'running' || row.status === 'completed' || row.status === 'paused'" class="space-y-1 min-w-[140px]">
            <div class="flex items-center gap-2">
              <span class="text-xs text-gray-500 w-8">Ouv.</span>
              <div class="flex-1 h-1.5 bg-gray-100 rounded-full overflow-hidden">
                <div class="h-full bg-blue-500 rounded-full" :style="{ width: (row.open_rate || 0) + '%' }" />
              </div>
              <span class="text-xs text-gray-500 w-8 text-right">{{ Math.round(row.open_rate || 0) }}%</span>
            </div>
            <div class="flex items-center gap-2">
              <span class="text-xs text-gray-500 w-8">Clic</span>
              <div class="flex-1 h-1.5 bg-gray-100 rounded-full overflow-hidden">
                <div class="h-full bg-orange-500 rounded-full" :style="{ width: (row.click_rate || 0) + '%' }" />
              </div>
              <span class="text-xs text-gray-500 w-8 text-right">{{ Math.round(row.click_rate || 0) }}%</span>
            </div>
            <div class="flex items-center gap-2">
              <span class="text-xs text-gray-500 w-8">Soum.</span>
              <div class="flex-1 h-1.5 bg-gray-100 rounded-full overflow-hidden">
                <div class="h-full bg-red-500 rounded-full" :style="{ width: (row.submit_rate || 0) + '%' }" />
              </div>
              <span class="text-xs text-gray-500 w-8 text-right">{{ Math.round(row.submit_rate || 0) }}%</span>
            </div>
          </div>
          <span v-else class="text-xs text-gray-400">-</span>
        </template>

        <template #cell-actions="{ row }">
          <div class="flex items-center gap-1">
            <!-- draft actions -->
            <template v-if="row.status === 'draft'">
              <router-link
                :to="`/campaigns/${row.id}/edit`"
                class="p-1.5 text-gray-400 hover:text-brand-600 hover:bg-brand-50 rounded-lg transition-colors"
                title="Modifier"
              >
                <Pencil class="w-4 h-4" />
              </router-link>
              <button
                @click="confirmLaunch(row)"
                class="p-1.5 text-gray-400 hover:text-green-600 hover:bg-green-50 rounded-lg transition-colors"
                title="Lancer"
              >
                <Play class="w-4 h-4" />
              </button>
              <button
                @click="confirmDelete(row)"
                class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors"
                title="Supprimer"
              >
                <Trash2 class="w-4 h-4" />
              </button>
            </template>

            <!-- scheduled -->
            <template v-else-if="row.status === 'scheduled'">
              <button
                @click="cancelCampaign(row)"
                class="p-1.5 text-gray-400 hover:text-orange-600 hover:bg-orange-50 rounded-lg transition-colors"
                title="Annuler"
              >
                <XCircle class="w-4 h-4" />
              </button>
            </template>

            <!-- running -->
            <template v-else-if="row.status === 'running'">
              <button
                @click="pauseCampaign(row)"
                class="p-1.5 text-gray-400 hover:text-yellow-600 hover:bg-yellow-50 rounded-lg transition-colors"
                title="Mettre en pause"
              >
                <Pause class="w-4 h-4" />
              </button>
              <router-link
                :to="`/campaigns/${row.id}/monitor`"
                class="p-1.5 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors"
                title="Surveiller"
              >
                <Activity class="w-4 h-4" />
              </router-link>
            </template>

            <!-- paused -->
            <template v-else-if="row.status === 'paused'">
              <button
                @click="resumeCampaign(row)"
                class="p-1.5 text-gray-400 hover:text-green-600 hover:bg-green-50 rounded-lg transition-colors"
                title="Reprendre"
              >
                <Play class="w-4 h-4" />
              </button>
              <button
                @click="cancelCampaign(row)"
                class="p-1.5 text-gray-400 hover:text-orange-600 hover:bg-orange-50 rounded-lg transition-colors"
                title="Annuler"
              >
                <XCircle class="w-4 h-4" />
              </button>
            </template>

            <!-- completed -->
            <template v-else-if="row.status === 'completed'">
              <router-link
                :to="`/stats/campaign/${row.id}`"
                class="p-1.5 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors"
                title="Statistiques"
              >
                <BarChart3 class="w-4 h-4" />
              </router-link>
              <button
                @click="duplicateCampaign(row)"
                class="p-1.5 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors"
                title="Dupliquer"
              >
                <Copy class="w-4 h-4" />
              </button>
            </template>

            <!-- cancelled -->
            <template v-else-if="row.status === 'cancelled'">
              <button
                @click="duplicateCampaign(row)"
                class="p-1.5 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors"
                title="Dupliquer"
              >
                <Copy class="w-4 h-4" />
              </button>
              <button
                @click="confirmDelete(row)"
                class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors"
                title="Supprimer"
              >
                <Trash2 class="w-4 h-4" />
              </button>
            </template>
          </div>
        </template>
      </DataTable>
    </div>

    <!-- Launch confirm -->
    <ConfirmDialog
      :show="showLaunchDialog"
      title="Lancer la campagne"
      :message="`Lancer la campagne « ${campaignToAction?.name} » ? Les emails seront envoyés aux destinataires sélectionnés.`"
      confirm-text="Lancer"
      variant="warning"
      @confirm="launchCampaign"
      @cancel="showLaunchDialog = false"
    />

    <!-- Delete confirm -->
    <ConfirmDialog
      :show="showDeleteDialog"
      title="Supprimer la campagne"
      :message="`Supprimer définitivement la campagne « ${campaignToAction?.name} » ?`"
      confirm-text="Supprimer"
      variant="danger"
      @confirm="deleteCampaign"
      @cancel="showDeleteDialog = false"
    />
  </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import {
  Plus, Filter, Pencil, Play, Pause, Trash2, XCircle,
  Activity, BarChart3, Copy
} from 'lucide-vue-next'
import { useApi } from '../../composables/useApi'
import { useWebSocket } from '../../composables/useWebSocket'
import { useNotificationStore } from '../../stores/notifications'
import DataTable from '../../components/ui/DataTable.vue'
import Badge from '../../components/ui/Badge.vue'
import ConfirmDialog from '../../components/ui/ConfirmDialog.vue'

const { loading, get, post, del } = useApi()
const { subscribe, onEvent } = useWebSocket()
const notifications = useNotificationStore()

const campaigns = ref([])
const statusFilter = ref('')
const showLaunchDialog = ref(false)
const showDeleteDialog = ref(false)
const campaignToAction = ref(null)

const statusOptions = [
  { value: 'draft', label: 'Brouillon' },
  { value: 'scheduled', label: 'Planifiée' },
  { value: 'running', label: 'En cours' },
  { value: 'paused', label: 'En pause' },
  { value: 'completed', label: 'Terminée' },
  { value: 'cancelled', label: 'Annulée' }
]

const statusLabels = {
  draft: 'Brouillon',
  scheduled: 'Planifiée',
  running: 'En cours',
  paused: 'En pause',
  completed: 'Terminée',
  cancelled: 'Annulée'
}

const statusColors = {
  draft: { active: 'bg-gray-100 text-gray-700 border-gray-300' },
  scheduled: { active: 'bg-blue-100 text-blue-700 border-blue-300' },
  running: { active: 'bg-green-100 text-green-700 border-green-300' },
  paused: { active: 'bg-yellow-100 text-yellow-700 border-yellow-300' },
  completed: { active: 'bg-indigo-100 text-indigo-700 border-indigo-300' },
  cancelled: { active: 'bg-red-100 text-red-700 border-red-300' }
}

function statusBadgeColor(status) {
  const map = {
    draft: 'gray',
    scheduled: 'blue',
    running: 'green',
    paused: 'yellow',
    completed: 'indigo',
    cancelled: 'red'
  }
  return map[status] || 'gray'
}

const columns = [
  { key: 'name', label: 'Nom', sortable: true },
  { key: 'status', label: 'Statut', sortable: true },
  { key: 'template_name', label: 'Template' },
  { key: 'target_count', label: 'Cibles', sortable: true },
  { key: 'created_at', label: 'Créée le', sortable: true },
  { key: 'launched_at', label: 'Lancée le', sortable: true },
  { key: 'progress', label: 'Progression' },
  { key: 'actions', label: 'Actions' }
]

const filteredCampaigns = computed(() => {
  if (!statusFilter.value) return campaigns.value
  return campaigns.value.filter(c => c.status === statusFilter.value)
})

function formatDate(dateStr) {
  if (!dateStr) return '-'
  const d = new Date(dateStr)
  return d.toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

async function fetchCampaigns() {
  try {
    const response = await get('/api/campaigns')
    campaigns.value = response.data || response || []
  } catch {
    // Error handled by useApi
  }
}

function confirmLaunch(campaign) {
  campaignToAction.value = campaign
  showLaunchDialog.value = true
}

function confirmDelete(campaign) {
  campaignToAction.value = campaign
  showDeleteDialog.value = true
}

async function launchCampaign() {
  try {
    await post(`/api/campaigns/${campaignToAction.value.id}/launch`)
    notifications.success('Campagne lancée')
    showLaunchDialog.value = false
    await fetchCampaigns()
  } catch {
    // Error handled by useApi
  }
}

async function deleteCampaign() {
  try {
    await del(`/api/campaigns/${campaignToAction.value.id}`)
    notifications.success('Campagne supprimée')
    showDeleteDialog.value = false
    await fetchCampaigns()
  } catch {
    // Error handled by useApi
  }
}

async function pauseCampaign(campaign) {
  try {
    await post(`/api/campaigns/${campaign.id}/pause`)
    notifications.success('Campagne mise en pause')
    await fetchCampaigns()
  } catch {
    // Error handled by useApi
  }
}

async function resumeCampaign(campaign) {
  try {
    await post(`/api/campaigns/${campaign.id}/resume`)
    notifications.success('Campagne reprise')
    await fetchCampaigns()
  } catch {
    // Error handled by useApi
  }
}

async function cancelCampaign(campaign) {
  try {
    await post(`/api/campaigns/${campaign.id}/cancel`)
    notifications.success('Campagne annulée')
    await fetchCampaigns()
  } catch {
    // Error handled by useApi
  }
}

async function duplicateCampaign(campaign) {
  try {
    await post(`/api/campaigns/${campaign.id}/duplicate`)
    notifications.success('Campagne dupliquée')
    await fetchCampaigns()
  } catch {
    // Error handled by useApi
  }
}

onMounted(() => {
  fetchCampaigns()
  subscribe('campaigns')
  onEvent('stats.updated', () => fetchCampaigns())
})
</script>
