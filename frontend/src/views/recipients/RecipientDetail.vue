<template>
  <AppLayout>
    <!-- Loading state -->
    <div v-if="loading" class="space-y-6">
      <div class="bg-white rounded-xl border border-gray-200 p-6">
        <div class="flex items-center gap-4">
          <div class="w-16 h-16 bg-gray-200 rounded-full animate-pulse" />
          <div class="space-y-2 flex-1">
            <div class="h-5 w-48 bg-gray-200 rounded animate-pulse" />
            <div class="h-4 w-64 bg-gray-200 rounded animate-pulse" />
          </div>
        </div>
      </div>
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="h-64 bg-gray-200 rounded-xl animate-pulse" />
        <div class="h-64 bg-gray-200 rounded-xl animate-pulse" />
      </div>
    </div>

    <!-- Error state -->
    <div v-else-if="error" class="bg-white rounded-xl border border-gray-200 p-12 text-center">
      <AlertCircle class="w-12 h-12 text-red-400 mx-auto mb-3" />
      <p class="text-gray-600 mb-4">{{ error }}</p>
      <button
        @click="fetchRecipient"
        class="px-4 py-2 bg-brand-500 hover:bg-brand-600 text-white text-sm rounded-lg transition-colors"
      >
        Reessayer
      </button>
    </div>

    <!-- Content -->
    <template v-else-if="recipient">
      <!-- Back button -->
      <div class="mb-4">
        <router-link
          to="/recipients"
          class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-brand-500 transition-colors"
        >
          <ArrowLeft class="w-4 h-4" />
          Retour a la liste
        </router-link>
      </div>

      <!-- Personal info card -->
      <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 mb-6">
        <div class="flex items-start justify-between">
          <div class="flex items-center gap-4">
            <div class="w-16 h-16 rounded-full bg-brand-100 flex items-center justify-center text-xl font-bold text-brand-600">
              {{ initials }}
            </div>
            <div>
              <h2 class="text-xl font-bold text-gray-800">{{ recipient.full_name || recipient.name }}</h2>
              <p class="text-gray-500 flex items-center gap-1.5 mt-0.5">
                <MailIcon class="w-4 h-4" />
                {{ recipient.email }}
              </p>
              <div class="flex items-center gap-4 mt-1 text-sm text-gray-500">
                <span v-if="recipient.department" class="flex items-center gap-1">
                  <Building class="w-3.5 h-3.5" />
                  {{ recipient.department }}
                </span>
                <span v-if="recipient.job_title" class="flex items-center gap-1">
                  <Briefcase class="w-3.5 h-3.5" />
                  {{ recipient.job_title }}
                </span>
              </div>
            </div>
          </div>
          <span
            :class="[
              'inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold',
              recipient.is_active
                ? 'bg-green-100 text-green-700'
                : 'bg-gray-100 text-gray-600'
            ]"
          >
            {{ recipient.is_active ? 'Actif' : 'Inactif' }}
          </span>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
        <!-- Groups card -->
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
          <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
            <h3 class="font-semibold text-gray-800">Groupes</h3>
            <button
              @click="showGroupEditor = true"
              class="text-sm text-brand-500 hover:text-brand-600 font-medium"
            >
              Modifier
            </button>
          </div>
          <div class="p-5">
            <div v-if="recipientGroups.length === 0" class="text-center py-4">
              <UsersIcon class="w-8 h-8 text-gray-300 mx-auto mb-1" />
              <p class="text-sm text-gray-500">Aucun groupe</p>
            </div>
            <div v-else class="flex flex-wrap gap-2">
              <span
                v-for="group in recipientGroups"
                :key="group.id"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium"
                :style="{
                  backgroundColor: (group.color || '#6B7280') + '20',
                  color: group.color || '#6B7280'
                }"
              >
                <span
                  class="w-2 h-2 rounded-full"
                  :style="{ backgroundColor: group.color || '#6B7280' }"
                />
                {{ group.name }}
              </span>
            </div>
          </div>
        </div>

        <!-- Vigilance score -->
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
          <div class="px-5 py-4 border-b border-gray-100">
            <h3 class="font-semibold text-gray-800">Score de vigilance</h3>
          </div>
          <div class="p-5 flex items-center justify-center">
            <div class="relative w-36 h-36">
              <!-- Circular gauge -->
              <svg viewBox="0 0 120 120" class="w-full h-full -rotate-90">
                <circle
                  cx="60"
                  cy="60"
                  r="52"
                  fill="none"
                  stroke="#E5E7EB"
                  stroke-width="8"
                />
                <circle
                  cx="60"
                  cy="60"
                  r="52"
                  fill="none"
                  :stroke="gaugeColor"
                  stroke-width="8"
                  stroke-linecap="round"
                  :stroke-dasharray="`${gaugeOffset} ${gaugeCircumference}`"
                  class="transition-all duration-700 ease-out"
                />
              </svg>
              <div class="absolute inset-0 flex flex-col items-center justify-center">
                <span class="text-3xl font-bold" :style="{ color: gaugeColor }">
                  {{ recipient.vigilance_score ?? '-' }}
                </span>
                <span class="text-xs text-gray-500">/ 100</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Behavior evolution -->
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
          <div class="px-5 py-4 border-b border-gray-100">
            <h3 class="font-semibold text-gray-800">Evolution</h3>
          </div>
          <div class="p-5">
            <div v-if="!behaviorChartData" class="h-32 flex items-center justify-center">
              <p class="text-sm text-gray-400">Pas assez de donnees</p>
            </div>
            <LineChart
              v-else
              :chart-data="behaviorChartData"
              :chart-options="behaviorChartOptions"
              class="h-32"
            />
          </div>
        </div>
      </div>

      <!-- Campaign history -->
      <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
        <div class="px-5 py-4 border-b border-gray-100">
          <h3 class="font-semibold text-gray-800">Historique des campagnes</h3>
        </div>
        <div class="overflow-x-auto">
          <!-- Loading -->
          <div v-if="historyLoading" class="p-5 space-y-3">
            <div v-for="i in 3" :key="i" class="h-8 bg-gray-100 rounded animate-pulse" />
          </div>

          <!-- Empty state -->
          <div v-else-if="campaignHistory.length === 0" class="text-center py-12">
            <Target class="w-10 h-10 text-gray-300 mx-auto mb-2" />
            <p class="text-sm text-gray-500">Aucune campagne pour ce destinataire</p>
          </div>

          <!-- Table -->
          <table v-else class="w-full text-sm">
            <thead>
              <tr class="border-b border-gray-100">
                <th class="text-left px-5 py-2.5 text-xs font-semibold text-gray-500 uppercase">Campagne</th>
                <th class="text-left px-5 py-2.5 text-xs font-semibold text-gray-500 uppercase">Date</th>
                <th class="text-left px-5 py-2.5 text-xs font-semibold text-gray-500 uppercase">Template</th>
                <th class="text-center px-5 py-2.5 text-xs font-semibold text-gray-500 uppercase">Envoye</th>
                <th class="text-center px-5 py-2.5 text-xs font-semibold text-gray-500 uppercase">Ouvert</th>
                <th class="text-center px-5 py-2.5 text-xs font-semibold text-gray-500 uppercase">Clique</th>
                <th class="text-center px-5 py-2.5 text-xs font-semibold text-gray-500 uppercase">Soumis</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="entry in campaignHistory"
                :key="entry.id"
                class="border-b border-gray-50 hover:bg-gray-50 transition-colors"
              >
                <td class="px-5 py-3 font-medium text-gray-700">{{ entry.campaign_name }}</td>
                <td class="px-5 py-3 text-gray-500">{{ formatDate(entry.sent_at) }}</td>
                <td class="px-5 py-3 text-gray-500">{{ entry.template_name || '-' }}</td>
                <td class="px-5 py-3 text-center">
                  <StatusIcon :active="entry.sent" :icon="MailIcon" />
                </td>
                <td class="px-5 py-3 text-center">
                  <StatusIcon :active="entry.opened" :icon="Eye" />
                </td>
                <td class="px-5 py-3 text-center">
                  <StatusIcon :active="entry.clicked" :icon="MousePointer" />
                </td>
                <td class="px-5 py-3 text-center">
                  <StatusIcon :active="entry.submitted" :icon="AlertTriangle" variant="danger" />
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </template>

    <!-- Group editor modal -->
    <Modal
      :show="showGroupEditor"
      title="Modifier les groupes"
      @close="showGroupEditor = false"
    >
      <div class="space-y-3">
        <!-- Search groups -->
        <div class="relative">
          <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
          <input
            v-model="groupSearch"
            type="text"
            placeholder="Rechercher un groupe..."
            class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-brand-500 focus:border-brand-500 outline-none"
          />
        </div>

        <!-- Group list -->
        <div class="max-h-64 overflow-y-auto space-y-1">
          <label
            v-for="group in filteredAllGroups"
            :key="group.id"
            class="flex items-center gap-3 p-2 rounded-lg hover:bg-gray-50 cursor-pointer"
          >
            <input
              type="checkbox"
              :checked="isInGroup(group.id)"
              @change="toggleGroup(group)"
              class="rounded border-gray-300 text-brand-500 focus:ring-brand-500"
            />
            <span
              class="w-3 h-3 rounded-full flex-shrink-0"
              :style="{ backgroundColor: group.color || '#6B7280' }"
            />
            <span class="text-sm text-gray-700">{{ group.name }}</span>
          </label>
          <p v-if="filteredAllGroups.length === 0" class="text-center text-sm text-gray-500 py-4">
            Aucun groupe trouve
          </p>
        </div>
      </div>

      <template #footer>
        <div class="flex justify-end">
          <button
            @click="showGroupEditor = false"
            class="px-4 py-2 bg-brand-500 hover:bg-brand-600 text-white text-sm font-medium rounded-lg transition-colors"
          >
            Fermer
          </button>
        </div>
      </template>
    </Modal>
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted, h } from 'vue'
import { useRoute } from 'vue-router'
import { useApi } from '@/composables/useApi'
import { useNotificationStore } from '@/stores/notifications'
import AppLayout from '@/layouts/AppLayout.vue'
import Modal from '@/components/ui/Modal.vue'
import { Line as LineChart } from 'vue-chartjs'
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  Tooltip,
  Filler
} from 'chart.js'
import {
  ArrowLeft,
  Mail as MailIcon,
  Building,
  Briefcase,
  Users as UsersIcon,
  Target,
  Eye,
  MousePointer,
  AlertTriangle,
  AlertCircle,
  Search
} from 'lucide-vue-next'

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, Tooltip, Filler)

// StatusIcon inline component
const StatusIcon = (props) => {
  const iconClass = props.active
    ? (props.variant === 'danger' ? 'text-red-500' : 'text-green-500')
    : 'text-gray-300'
  return h(props.icon, { class: `w-4 h-4 inline-block ${iconClass}` })
}
StatusIcon.props = {
  active: Boolean,
  icon: Object,
  variant: { type: String, default: 'success' }
}

const route = useRoute()
const api = useApi()
const notifications = useNotificationStore()

const loading = ref(true)
const historyLoading = ref(true)
const error = ref('')
const recipient = ref(null)
const recipientGroups = ref([])
const campaignHistory = ref([])
const allGroups = ref([])

const showGroupEditor = ref(false)
const groupSearch = ref('')

const recipientId = computed(() => route.params.id)

const initials = computed(() => {
  const name = recipient.value?.full_name || recipient.value?.name || ''
  return name.split(' ').map(w => w[0]).join('').slice(0, 2).toUpperCase()
})

// Gauge chart
const gaugeCircumference = 2 * Math.PI * 52 // ~326.7
const gaugeOffset = computed(() => {
  const score = recipient.value?.vigilance_score ?? 0
  return (score / 100) * gaugeCircumference
})
const gaugeColor = computed(() => {
  const score = recipient.value?.vigilance_score ?? 0
  if (score >= 70) return '#16A34A'
  if (score >= 40) return '#D97706'
  return '#DC2626'
})

// Behavior chart
const behaviorChartData = ref(null)
const behaviorChartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: { tooltip: { enabled: true } },
  scales: {
    y: { min: 0, max: 100, display: false },
    x: { ticks: { font: { size: 10 } } }
  },
  elements: { line: { tension: 0.3 }, point: { radius: 3 } }
}

// Group management
const filteredAllGroups = computed(() => {
  if (!groupSearch.value) return allGroups.value
  const q = groupSearch.value.toLowerCase()
  return allGroups.value.filter(g => g.name.toLowerCase().includes(q))
})

function isInGroup(groupId) {
  return recipientGroups.value.some(g => g.id === groupId)
}

async function toggleGroup(group) {
  const inGroup = isInGroup(group.id)
  try {
    if (inGroup) {
      await api.del(`/api/recipients/${recipientId.value}/groups/${group.id}`)
      recipientGroups.value = recipientGroups.value.filter(g => g.id !== group.id)
      notifications.success(`Retire du groupe "${group.name}"`)
    } else {
      await api.post(`/api/recipients/${recipientId.value}/groups/${group.id}`)
      recipientGroups.value.push(group)
      notifications.success(`Ajoute au groupe "${group.name}"`)
    }
  } catch {
    // Error handled by useApi
  }
}

async function fetchRecipient() {
  loading.value = true
  error.value = ''
  try {
    const data = await api.get(`/api/recipients/${recipientId.value}`)
    recipient.value = data.recipient || data
    recipientGroups.value = data.groups || recipient.value?.groups || []

    // Build behavior chart
    const history = data.behavior_history || data.vigilance_history
    if (history && history.length > 0) {
      behaviorChartData.value = {
        labels: history.map(h => h.month || h.label),
        datasets: [{
          label: 'Score',
          data: history.map(h => h.score || h.value),
          borderColor: '#2D5016',
          backgroundColor: 'rgba(45, 80, 22, 0.1)',
          fill: true,
          pointBackgroundColor: '#2D5016'
        }]
      }
    }
  } catch (err) {
    error.value = err.message || 'Impossible de charger le destinataire'
  } finally {
    loading.value = false
  }
}

async function fetchHistory() {
  historyLoading.value = true
  try {
    const data = await api.get(`/api/recipients/${recipientId.value}/history`)
    campaignHistory.value = data.history || data || []
  } catch {
    // Graceful fallback
  } finally {
    historyLoading.value = false
  }
}

async function fetchAllGroups() {
  try {
    const data = await api.get('/api/recipients/groups')
    allGroups.value = data.data || data.groups || data || []
  } catch {
    // Graceful fallback
  }
}

function formatDate(dateStr) {
  if (!dateStr) return '-'
  return new Date(dateStr).toLocaleDateString('fr-FR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric'
  })
}

onMounted(() => {
  fetchRecipient()
  fetchHistory()
  fetchAllGroups()
})
</script>
