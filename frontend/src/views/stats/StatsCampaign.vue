<template>
  <AppLayout>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-3">
        <router-link
          to="/stats"
          class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg transition-colors"
        >
          <ArrowLeft class="w-5 h-5" />
        </router-link>
        <div>
          <h1 class="text-2xl font-bold text-gray-900">{{ campaign.name || 'Statistiques de campagne' }}</h1>
          <div class="flex items-center gap-3 mt-1 text-sm text-gray-500">
            <span v-if="campaign.template_name">{{ campaign.template_name }}</span>
            <Badge v-if="campaign.status" :text="statusLabel" :color="statusColor" />
          </div>
        </div>
      </div>
      <button
        @click="exportCsv"
        class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50"
      >
        <Download class="w-4 h-4" />
        Exporter CSV
      </button>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="space-y-6">
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div v-for="i in 4" :key="i" class="bg-white rounded-xl border border-gray-200 p-5 animate-pulse">
          <div class="h-4 bg-gray-200 rounded w-1/2 mb-3" />
          <div class="h-8 bg-gray-200 rounded w-2/3" />
        </div>
      </div>
    </div>

    <template v-else>
      <!-- Funnel chart -->
      <div class="bg-white rounded-xl border border-gray-200 p-6">
        <h3 class="text-sm font-semibold text-gray-700 mb-6">Entonnoir de conversion</h3>
        <div class="flex items-end justify-center gap-4">
          <div
            v-for="step in funnelSteps"
            :key="step.key"
            class="flex flex-col items-center"
            style="width: 22%;"
          >
            <div class="w-full flex items-end" style="height: 120px;">
              <div
                :class="['w-full rounded-t-lg transition-all', step.bgColor]"
                :style="{ height: funnelHeight(step.key) + 'px' }"
              />
            </div>
            <div class="mt-2 text-center">
              <p class="text-2xl font-bold text-gray-900">{{ stats[step.key] || 0 }}</p>
              <p class="text-xs text-gray-500">{{ step.label }}</p>
              <p class="text-xs font-medium" :class="step.textColor">
                {{ funnelPercent(step.key) }}%
              </p>
            </div>
          </div>
        </div>
        <!-- Arrows between steps -->
        <div class="flex justify-center mt-2">
          <div class="flex items-center gap-2 text-xs text-gray-400">
            <span>{{ stats.delivered || 0 }} envoyés</span>
            <ChevronRight class="w-3 h-3" />
            <span>{{ stats.opened || 0 }} ouverts</span>
            <ChevronRight class="w-3 h-3" />
            <span>{{ stats.clicked || 0 }} cliqués</span>
            <ChevronRight class="w-3 h-3" />
            <span>{{ stats.submitted || 0 }} soumis</span>
          </div>
        </div>
      </div>

      <!-- Bar chart by group + Average times -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Bar chart by group -->
        <div class="lg:col-span-2 bg-white rounded-xl border border-gray-200 p-5">
          <h3 class="text-sm font-semibold text-gray-700 mb-4">Résultats par groupe</h3>
          <div class="space-y-3">
            <div v-if="!stats.group_breakdown?.length" class="py-8 text-center text-sm text-gray-400">
              Pas de données par groupe
            </div>
            <div v-for="group in stats.group_breakdown" :key="group.name" class="space-y-1">
              <div class="flex items-center justify-between text-sm">
                <router-link
                  :to="`/stats/group/${group.id}`"
                  class="font-medium text-gray-700 hover:text-brand-600 truncate"
                >
                  {{ group.name }}
                </router-link>
                <span class="text-xs text-gray-500">{{ group.recipients || group.count || 0 }} destinataires</span>
              </div>
              <div class="flex gap-0.5 h-5 rounded overflow-hidden bg-gray-100">
                <div class="bg-green-400" :style="{ width: group.open_rate + '%' }" :title="`Ouverture: ${group.open_rate}%`" />
                <div class="bg-orange-400" :style="{ width: group.click_rate + '%' }" :title="`Clic: ${group.click_rate}%`" />
                <div class="bg-red-400" :style="{ width: group.submit_rate + '%' }" :title="`Soumission: ${group.submit_rate}%`" />
              </div>
            </div>
          </div>
        </div>

        <!-- Average times -->
        <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-5">
          <h3 class="text-sm font-semibold text-gray-700">Temps de réaction moyens</h3>

          <div class="space-y-4">
            <div class="p-4 bg-green-50 rounded-lg">
              <div class="flex items-center gap-2 mb-1">
                <Clock class="w-4 h-4 text-green-600" />
                <span class="text-xs font-medium text-green-700 uppercase">Réception → Ouverture</span>
              </div>
              <p class="text-2xl font-bold text-green-900">{{ formatDuration(stats.avg_time_to_open) }}</p>
            </div>

            <div class="p-4 bg-orange-50 rounded-lg">
              <div class="flex items-center gap-2 mb-1">
                <Clock class="w-4 h-4 text-orange-600" />
                <span class="text-xs font-medium text-orange-700 uppercase">Ouverture → Clic</span>
              </div>
              <p class="text-2xl font-bold text-orange-900">{{ formatDuration(stats.avg_time_to_click) }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Heatmap: hours x days -->
      <div class="bg-white rounded-xl border border-gray-200 p-5">
        <h3 class="text-sm font-semibold text-gray-700 mb-4">Carte de chaleur des interactions</h3>
        <div class="overflow-x-auto">
          <table class="w-full">
            <thead>
              <tr>
                <th class="px-2 py-1 text-xs text-gray-500 text-left w-16" />
                <th
                  v-for="hour in hours"
                  :key="hour"
                  class="px-0.5 py-1 text-xs text-gray-400 text-center"
                  style="min-width: 28px;"
                >
                  {{ hour }}h
                </th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(day, dayIdx) in days" :key="day">
                <td class="px-2 py-0.5 text-xs font-medium text-gray-600">{{ day }}</td>
                <td
                  v-for="hour in hours"
                  :key="hour"
                  class="px-0.5 py-0.5"
                >
                  <div
                    :class="['w-full h-6 rounded-sm', heatmapColor(dayIdx, hour)]"
                    :title="`${day} ${hour}h: ${getHeatmapValue(dayIdx, hour)} interactions`"
                  />
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <!-- Heatmap legend -->
        <div class="flex items-center gap-2 mt-3 text-xs text-gray-500">
          <span>Moins</span>
          <div class="w-5 h-3 bg-green-50 rounded-sm border border-green-100" />
          <div class="w-5 h-3 bg-green-200 rounded-sm" />
          <div class="w-5 h-3 bg-green-400 rounded-sm" />
          <div class="w-5 h-3 bg-green-600 rounded-sm" />
          <div class="w-5 h-3 bg-green-800 rounded-sm" />
          <span>Plus</span>
        </div>
      </div>

      <!-- Detailed recipient list -->
      <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="px-5 py-3 border-b border-gray-200 flex items-center justify-between">
          <h3 class="text-sm font-semibold text-gray-700">Liste détaillée des destinataires</h3>
          <span class="text-xs text-gray-400">{{ (stats.recipients || []).length }} destinataires</span>
        </div>
        <DataTable :columns="recipientColumns" :data="stats.recipients || []" :loading="loading">
          <template #cell-name="{ row }">
            <div>
              <p class="text-sm font-medium text-gray-900">{{ row.first_name }} {{ row.last_name }}</p>
              <p class="text-xs text-gray-500">{{ row.email }}</p>
            </div>
          </template>
          <template #cell-group="{ row }">
            <span class="text-sm text-gray-600">{{ row.group || '-' }}</span>
          </template>
          <template #cell-delivered="{ row }">
            <Badge :text="row.mail_status === 'delivered' ? 'Oui' : 'Non'" :color="row.mail_status === 'delivered' ? 'green' : 'gray'" />
          </template>
          <template #cell-opened="{ row }">
            <Badge :text="row.opened == 1 ? 'Oui' : 'Non'" :color="row.opened == 1 ? 'green' : 'gray'" />
          </template>
          <template #cell-clicked="{ row }">
            <Badge :text="row.clicked == 1 ? 'Oui' : 'Non'" :color="row.clicked == 1 ? 'orange' : 'gray'" />
          </template>
          <template #cell-submitted="{ row }">
            <Badge :text="row.submitted_credentials == 1 ? 'Oui' : 'Non'" :color="row.submitted_credentials == 1 ? 'red' : 'gray'" />
          </template>
        </DataTable>
      </div>
    </template>
  </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import { useRoute } from 'vue-router'
import { ArrowLeft, Download, Clock, ChevronRight } from 'lucide-vue-next'
import { useApi } from '../../composables/useApi'
import DataTable from '../../components/ui/DataTable.vue'
import Badge from '../../components/ui/Badge.vue'

const route = useRoute()
const { loading, get } = useApi()
const campaignId = route.params.id

const campaign = ref({})
const stats = ref({
  delivered: 0,
  opened: 0,
  clicked: 0,
  submitted: 0,
  group_breakdown: [],
  avg_time_to_open: null,
  avg_time_to_click: null,
  heatmap: {},
  recipients: []
})

const funnelSteps = [
  { key: 'delivered', label: 'Envoyés', bgColor: 'bg-blue-400', textColor: 'text-blue-600' },
  { key: 'opened', label: 'Ouverts', bgColor: 'bg-green-400', textColor: 'text-green-600' },
  { key: 'clicked', label: 'Cliqués', bgColor: 'bg-orange-400', textColor: 'text-orange-600' },
  { key: 'submitted', label: 'Soumis', bgColor: 'bg-red-400', textColor: 'text-red-600' }
]

const hours = Array.from({ length: 24 }, (_, i) => i)
const days = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim']

const recipientColumns = [
  { key: 'name', label: 'Destinataire', sortable: true },
  { key: 'group', label: 'Groupe' },
  { key: 'delivered', label: 'Envoyé' },
  { key: 'opened', label: 'Ouvert' },
  { key: 'clicked', label: 'Cliqué' },
  { key: 'submitted', label: 'Soumis' }
]

const statusLabels = {
  draft: 'Brouillon', scheduled: 'Planifiée', running: 'En cours',
  paused: 'En pause', completed: 'Terminée', cancelled: 'Annulée'
}
const statusColors = {
  draft: 'gray', scheduled: 'blue', running: 'green',
  paused: 'yellow', completed: 'indigo', cancelled: 'red'
}

const statusLabel = computed(() => statusLabels[campaign.value.status] || campaign.value.status)
const statusColor = computed(() => statusColors[campaign.value.status] || 'gray')

function funnelHeight(key) {
  const total = stats.value.delivered || 1
  const val = stats.value[key] || 0
  return Math.max(16, (val / total) * 110)
}

function funnelPercent(key) {
  const total = stats.value.delivered || 1
  return Math.round(((stats.value[key] || 0) / total) * 100)
}

function formatDuration(seconds) {
  if (!seconds && seconds !== 0) return '-'
  if (seconds < 60) return `${Math.round(seconds)}s`
  if (seconds < 3600) return `${Math.floor(seconds / 60)}min ${Math.round(seconds % 60)}s`
  const h = Math.floor(seconds / 3600)
  const m = Math.floor((seconds % 3600) / 60)
  return `${h}h ${m}min`
}

function getHeatmapValue(dayIdx, hour) {
  if (!stats.value.heatmap) return 0
  return stats.value.heatmap[`${dayIdx}_${hour}`] || 0
}

function heatmapColor(dayIdx, hour) {
  const val = getHeatmapValue(dayIdx, hour)
  const maxVal = Math.max(
    1,
    ...Object.values(stats.value.heatmap || {}).map(Number)
  )
  const intensity = val / maxVal

  if (intensity === 0) return 'bg-gray-50'
  if (intensity < 0.2) return 'bg-green-50'
  if (intensity < 0.4) return 'bg-green-200'
  if (intensity < 0.6) return 'bg-green-400'
  if (intensity < 0.8) return 'bg-green-600'
  return 'bg-green-800'
}

async function fetchData() {
  try {
    const [campaignData, statsData] = await Promise.all([
      get(`/api/campaigns/${campaignId}`),
      get(`/api/stats/campaign/${campaignId}`)
    ])
    campaign.value = campaignData
    const funnel = statsData.funnel || statsData || {}
    stats.value = {
      delivered: funnel.delivered || 0,
      opened: funnel.opened || 0,
      clicked: funnel.clicked || 0,
      submitted: funnel.submitted || 0,
      open_rate: funnel.open_rate || 0,
      click_rate: funnel.click_rate || 0,
      submit_rate: funnel.submit_rate || 0,
      group_breakdown: statsData.by_group || statsData.group_breakdown || [],
      avg_time_to_open: statsData.avg_times?.deliver_to_open_seconds,
      avg_time_to_click: statsData.avg_times?.open_to_click_seconds,
      heatmap: statsData.heatmap || {},
      recipients: statsData.recipients || []
    }
  } catch {
    // Error handled by useApi
  }
}

function exportCsv() {
  const rows = [['Prénom', 'Nom', 'Email', 'Groupe', 'Envoyé', 'Ouvert', 'Cliqué', 'Soumis']]
  ;(stats.value.recipients || []).forEach(r => {
    rows.push([
      r.first_name, r.last_name, r.email, r.group || '',
      r.mail_status === 'delivered' ? 'Oui' : 'Non', r.opened == 1 ? 'Oui' : 'Non',
      r.clicked == 1 ? 'Oui' : 'Non', r.submitted_credentials == 1 ? 'Oui' : 'Non'
    ])
  })
  const csv = rows.map(r => r.map(v => `"${v}"`).join(',')).join('\n')
  const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = `stats-campagne-${campaignId}.csv`
  a.click()
  URL.revokeObjectURL(url)
}

let pollInterval = null

onMounted(() => {
  fetchData()
  // Auto-refresh every 10 seconds if campaign is still running
  pollInterval = setInterval(() => {
    if (campaign.value?.status === 'running') fetchData()
  }, 10000)
})

onUnmounted(() => {
  if (pollInterval) clearInterval(pollInterval)
})
</script>
