<template>
  <AppLayout>
    <!-- Alert banners -->
    <div v-if="user?.must_change_password" class="mb-4 p-3 bg-amber-50 border border-amber-200 rounded-lg flex items-center gap-3">
      <AlertTriangle class="w-5 h-5 text-amber-500 flex-shrink-0" />
      <p class="text-sm text-amber-700 flex-1">
        Vous devez changer votre mot de passe avant de continuer.
      </p>
      <router-link
        to="/change-password"
        class="text-sm font-medium text-amber-700 hover:text-amber-900 underline whitespace-nowrap"
      >
        Changer maintenant
      </router-link>
    </div>

    <div v-if="!syncData.lastSync" class="mb-4 p-3 bg-blue-50 border border-blue-200 rounded-lg flex items-center gap-3">
      <Info class="w-5 h-5 text-blue-500 flex-shrink-0" />
      <p class="text-sm text-blue-700 flex-1">
        Aucune synchronisation n'a encore ete effectuee. Synchronisez les destinataires depuis Office 365 pour commencer.
      </p>
      <button
        @click="handleSync"
        class="text-sm font-medium text-blue-700 hover:text-blue-900 underline whitespace-nowrap"
      >
        Synchroniser
      </button>
    </div>

    <!-- Summary cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
      <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
        <div class="flex items-center justify-between mb-3">
          <span class="text-sm font-medium text-gray-500">Campagnes en cours</span>
          <div class="p-2 bg-blue-50 rounded-lg">
            <Target class="w-5 h-5 text-blue-500" />
          </div>
        </div>
        <p v-if="!loading" class="text-3xl font-bold text-gray-800">{{ stats.runningCampaigns }}</p>
        <div v-else class="h-9 w-16 bg-gray-200 rounded animate-pulse" />
      </div>

      <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
        <div class="flex items-center justify-between mb-3">
          <span class="text-sm font-medium text-gray-500">Terminees ce mois</span>
          <div class="p-2 bg-green-50 rounded-lg">
            <CheckCircle2 class="w-5 h-5 text-green-500" />
          </div>
        </div>
        <p v-if="!loading" class="text-3xl font-bold text-gray-800">{{ stats.completedThisMonth }}</p>
        <div v-else class="h-9 w-16 bg-gray-200 rounded animate-pulse" />
      </div>

      <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
        <div class="flex items-center justify-between mb-3">
          <span class="text-sm font-medium text-gray-500">Taux de vigilance</span>
          <div class="p-2 bg-brand-50 rounded-lg">
            <ShieldCheck class="w-5 h-5 text-brand-500" />
          </div>
        </div>
        <p v-if="!loading" class="text-3xl font-bold text-gray-800">{{ stats.vigilanceRate }}<span class="text-lg text-gray-400">%</span></p>
        <div v-else class="h-9 w-16 bg-gray-200 rounded animate-pulse" />
      </div>

      <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
        <div class="flex items-center justify-between mb-3">
          <span class="text-sm font-medium text-gray-500">Destinataires actifs</span>
          <div class="p-2 bg-purple-50 rounded-lg">
            <Users class="w-5 h-5 text-purple-500" />
          </div>
        </div>
        <p v-if="!loading" class="text-3xl font-bold text-gray-800">{{ syncData.activeRecipients }}</p>
        <div v-else class="h-9 w-16 bg-gray-200 rounded animate-pulse" />
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
      <!-- Running campaigns -->
      <div class="lg:col-span-2">
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
          <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
            <h3 class="font-semibold text-gray-800">Campagnes en cours</h3>
            <router-link to="/campaigns" class="text-sm text-brand-500 hover:text-brand-600 font-medium">
              Voir tout
            </router-link>
          </div>
          <div class="p-5">
            <!-- Loading -->
            <div v-if="loading" class="space-y-4">
              <div v-for="i in 2" :key="i" class="p-4 border border-gray-100 rounded-lg">
                <div class="h-4 w-48 bg-gray-200 rounded animate-pulse mb-3" />
                <div class="h-2 w-full bg-gray-200 rounded animate-pulse mb-2" />
                <div class="h-3 w-24 bg-gray-200 rounded animate-pulse" />
              </div>
            </div>

            <!-- Empty state -->
            <div v-else-if="runningCampaigns.length === 0" class="text-center py-8">
              <Target class="w-10 h-10 text-gray-300 mx-auto mb-2" />
              <p class="text-sm text-gray-500">Aucune campagne en cours</p>
            </div>

            <!-- Campaign cards -->
            <div v-else class="space-y-3">
              <div
                v-for="campaign in runningCampaigns"
                :key="campaign.id"
                class="p-4 border border-gray-100 rounded-lg hover:border-gray-200 transition-colors"
              >
                <div class="flex items-center justify-between mb-2">
                  <h4 class="font-medium text-gray-800 text-sm">{{ campaign.name }}</h4>
                  <span class="text-xs text-gray-500">{{ campaign.sent }}/{{ campaign.total }} envoyes</span>
                </div>
                <ProgressBar
                  :value="campaign.total > 0 ? (campaign.sent / campaign.total) * 100 : 0"
                  color="green"
                  height="h-1.5"
                />
                <div class="flex items-center gap-4 mt-2 text-xs text-gray-500">
                  <span class="flex items-center gap-1">
                    <Eye class="w-3 h-3" />
                    {{ campaign.opened }} ouv.
                  </span>
                  <span class="flex items-center gap-1">
                    <MousePointer class="w-3 h-3" />
                    {{ campaign.clicked }} clics
                  </span>
                  <span class="flex items-center gap-1">
                    <AlertTriangle class="w-3 h-3" />
                    {{ campaign.submitted }} soumis
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Quick actions -->
      <div>
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
          <div class="px-5 py-4 border-b border-gray-100">
            <h3 class="font-semibold text-gray-800">Actions rapides</h3>
          </div>
          <div class="p-5 space-y-2">
            <router-link
              to="/campaigns/create"
              class="flex items-center gap-3 px-4 py-3 rounded-lg border border-gray-200 hover:border-brand-300 hover:bg-brand-50 transition-colors group"
            >
              <div class="p-2 bg-brand-50 rounded-lg group-hover:bg-brand-100">
                <Target class="w-4 h-4 text-brand-500" />
              </div>
              <span class="text-sm font-medium text-gray-700">Nouvelle campagne</span>
            </router-link>

            <router-link
              to="/templates/create"
              class="flex items-center gap-3 px-4 py-3 rounded-lg border border-gray-200 hover:border-brand-300 hover:bg-brand-50 transition-colors group"
            >
              <div class="p-2 bg-blue-50 rounded-lg group-hover:bg-blue-100">
                <Mail class="w-4 h-4 text-blue-500" />
              </div>
              <span class="text-sm font-medium text-gray-700">Nouveau template</span>
            </router-link>

            <button
              @click="handleSync"
              :disabled="syncing"
              class="flex items-center gap-3 w-full px-4 py-3 rounded-lg border border-gray-200 hover:border-brand-300 hover:bg-brand-50 transition-colors group disabled:opacity-50 disabled:cursor-not-allowed"
            >
              <div class="p-2 bg-purple-50 rounded-lg group-hover:bg-purple-100">
                <RefreshCw class="w-4 h-4 text-purple-500" :class="{ 'animate-spin': syncing }" />
              </div>
              <span class="text-sm font-medium text-gray-700">
                {{ syncing ? 'Synchronisation...' : 'Synchroniser' }}
              </span>
            </button>
          </div>

          <!-- Last sync info -->
          <div class="px-5 pb-4">
            <div class="p-3 bg-gray-50 rounded-lg text-xs text-gray-500 space-y-1">
              <p v-if="syncData.lastSync">
                <span class="font-medium">Derniere sync :</span> {{ formatDate(syncData.lastSync) }}
              </p>
              <p v-else class="italic">Jamais synchronise</p>
              <p>
                <span class="font-medium">Destinataires actifs :</span> {{ syncData.activeRecipients }}
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <!-- Recent completed campaigns -->
      <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
          <h3 class="font-semibold text-gray-800">Campagnes recentes</h3>
          <router-link to="/stats" class="text-sm text-brand-500 hover:text-brand-600 font-medium">
            Statistiques
          </router-link>
        </div>
        <div class="overflow-x-auto">
          <!-- Loading -->
          <div v-if="loading" class="p-5 space-y-3">
            <div v-for="i in 3" :key="i" class="h-8 bg-gray-100 rounded animate-pulse" />
          </div>

          <!-- Empty state -->
          <div v-else-if="recentCampaigns.length === 0" class="text-center py-8">
            <BarChart3 class="w-10 h-10 text-gray-300 mx-auto mb-2" />
            <p class="text-sm text-gray-500">Aucune campagne terminee</p>
          </div>

          <!-- Table -->
          <table v-else class="w-full text-sm">
            <thead>
              <tr class="border-b border-gray-100">
                <th class="text-left px-5 py-2.5 text-xs font-semibold text-gray-500 uppercase">Nom</th>
                <th class="text-left px-5 py-2.5 text-xs font-semibold text-gray-500 uppercase">Date</th>
                <th class="text-right px-5 py-2.5 text-xs font-semibold text-gray-500 uppercase">Vigilance</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="c in recentCampaigns"
                :key="c.id"
                class="border-b border-gray-50 hover:bg-gray-50 transition-colors"
              >
                <td class="px-5 py-2.5 font-medium text-gray-700">{{ c.name }}</td>
                <td class="px-5 py-2.5 text-gray-500">{{ formatDate(c.completed_at) }}</td>
                <td class="px-5 py-2.5 text-right">
                  <span
                    :class="[
                      'inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold',
                      c.vigilance_rate >= 70 ? 'bg-green-100 text-green-700' :
                      c.vigilance_rate >= 40 ? 'bg-amber-100 text-amber-700' :
                      'bg-red-100 text-red-700'
                    ]"
                  >
                    {{ c.vigilance_rate }}%
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Vigilance trend chart -->
      <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
        <div class="px-5 py-4 border-b border-gray-100">
          <h3 class="font-semibold text-gray-800">Tendance de vigilance (6 mois)</h3>
        </div>
        <div class="p-5">
          <div v-if="loading" class="h-64 bg-gray-100 rounded animate-pulse" />
          <div v-else-if="!chartData" class="h-64 flex items-center justify-center">
            <div class="text-center">
              <TrendingUp class="w-10 h-10 text-gray-300 mx-auto mb-2" />
              <p class="text-sm text-gray-500">Pas assez de donnees</p>
            </div>
          </div>
          <LineChart
            v-else
            :chart-data="chartData"
            :chart-options="chartOptions"
            class="h-64"
          />
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, reactive, onMounted, onUnmounted } from 'vue'
import { useAuth } from '@/composables/useAuth'
import { useApi } from '@/composables/useApi'
import { useWebSocket } from '@/composables/useWebSocket'
import { useNotificationStore } from '@/stores/notifications'
import AppLayout from '@/layouts/AppLayout.vue'
import ProgressBar from '@/components/ui/ProgressBar.vue'
import { Line as LineChart } from 'vue-chartjs'
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  Title,
  Tooltip,
  Filler
} from 'chart.js'
import {
  Target,
  CheckCircle2,
  ShieldCheck,
  Users,
  Mail,
  Eye,
  MousePointer,
  AlertTriangle,
  BarChart3,
  RefreshCw,
  TrendingUp,
  Info
} from 'lucide-vue-next'

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, Title, Tooltip, Filler)

const { user } = useAuth()
const api = useApi()
const notifications = useNotificationStore()

const loading = ref(true)
const syncing = ref(false)

const stats = reactive({
  runningCampaigns: 0,
  completedThisMonth: 0,
  vigilanceRate: 0
})

const syncData = reactive({
  lastSync: null,
  activeRecipients: 0
})

const runningCampaigns = ref([])
const recentCampaigns = ref([])
const chartData = ref(null)

const chartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    tooltip: {
      callbacks: {
        label: (ctx) => `Vigilance: ${ctx.parsed.y}%`
      }
    }
  },
  scales: {
    y: {
      min: 0,
      max: 100,
      ticks: {
        callback: (v) => v + '%'
      }
    }
  },
  elements: {
    line: {
      tension: 0.3
    }
  }
}

// WebSocket for real-time campaign updates
let ws = null
let cleanupWs = null

function setupWebSocket() {
  try {
    ws = useWebSocket()
    ws.subscribe('campaigns')

    cleanupWs = ws.onEvent('campaign_progress', (data) => {
      const campaign = runningCampaigns.value.find(c => c.id === data.campaign_id)
      if (campaign) {
        campaign.sent = data.sent ?? campaign.sent
        campaign.opened = data.opened ?? campaign.opened
        campaign.clicked = data.clicked ?? campaign.clicked
        campaign.submitted = data.submitted ?? campaign.submitted
      }
    })
  } catch {
    // WebSocket not available, graceful degradation
  }
}

async function fetchDashboardData() {
  loading.value = true
  try {
    const data = await api.get('/api/dashboard')

    stats.runningCampaigns = data.running_campaigns_count ?? 0
    stats.completedThisMonth = data.completed_this_month ?? 0
    stats.vigilanceRate = data.vigilance_rate ?? 0

    syncData.lastSync = data.last_sync ?? null
    syncData.activeRecipients = data.active_recipients ?? 0

    runningCampaigns.value = data.running_campaigns ?? []
    recentCampaigns.value = data.recent_campaigns ?? []

    // Build chart data
    if (data.vigilance_trend && data.vigilance_trend.length > 0) {
      chartData.value = {
        labels: data.vigilance_trend.map(d => d.month),
        datasets: [{
          label: 'Taux de vigilance',
          data: data.vigilance_trend.map(d => d.rate),
          borderColor: '#2D5016',
          backgroundColor: 'rgba(45, 80, 22, 0.1)',
          fill: true,
          pointBackgroundColor: '#2D5016',
          pointRadius: 4,
          pointHoverRadius: 6
        }]
      }
    }
  } catch {
    // Error handled by useApi
  } finally {
    loading.value = false
  }
}

async function handleSync() {
  syncing.value = true
  try {
    await api.post('/api/recipients/sync')
    notifications.success('Synchronisation terminee avec succes')
    await fetchDashboardData()
  } catch {
    // Error handled by useApi
  } finally {
    syncing.value = false
  }
}

function formatDate(dateStr) {
  if (!dateStr) return '-'
  const date = new Date(dateStr)
  return date.toLocaleDateString('fr-FR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}

onMounted(() => {
  fetchDashboardData()
  setupWebSocket()
})

onUnmounted(() => {
  if (cleanupWs) cleanupWs()
  if (ws) ws.disconnect()
})
</script>
