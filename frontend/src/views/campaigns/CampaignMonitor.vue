<template>
  <AppLayout>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-3">
        <router-link
          to="/campaigns"
          class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg transition-colors"
        >
          <ArrowLeft class="w-5 h-5" />
        </router-link>
        <div>
          <div class="flex items-center gap-3">
            <h1 class="text-2xl font-bold text-gray-900">{{ campaign.name || 'Campagne' }}</h1>
            <span
              :class="[
                'inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium',
                statusClasses
              ]"
            >
              <span
                v-if="campaign.status === 'running'"
                class="w-2 h-2 rounded-full bg-green-500 animate-pulse"
              />
              {{ statusLabel }}
            </span>
          </div>
          <div class="flex items-center gap-4 mt-1 text-sm text-gray-500">
            <span v-if="campaign.template_names?.length > 1">{{ campaign.template_names.length }} templates</span>
            <span v-else-if="campaign.template_name">Template : {{ campaign.template_name }}</span>
            <span v-if="campaign.launched_at">Lancée le {{ formatDate(campaign.launched_at) }}</span>
          </div>
        </div>
      </div>
      <router-link
        :to="`/stats/campaign/${campaignId}`"
        class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50"
      >
        <BarChart3 class="w-4 h-4" />
        Statistiques complètes
      </router-link>
    </div>

    <!-- Send progress -->
    <div class="bg-white rounded-xl border border-gray-200 p-5">
      <div class="flex items-center justify-between mb-2">
        <h3 class="text-sm font-semibold text-gray-700">Progression de l'envoi</h3>
        <span class="text-sm font-medium text-gray-900">
          {{ counters.delivered }} / {{ counters.total }}
        </span>
      </div>
      <div class="w-full h-3 bg-gray-100 rounded-full overflow-hidden">
        <div
          class="h-full bg-brand-500 rounded-full transition-all duration-500"
          :style="{ width: sendProgress + '%' }"
        />
      </div>
    </div>

    <!-- Live counters -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
      <div
        v-for="gauge in gauges"
        :key="gauge.key"
        class="bg-white rounded-xl border border-gray-200 p-5"
      >
        <div class="flex items-center justify-between mb-3">
          <span class="text-sm font-medium text-gray-500">{{ gauge.label }}</span>
          <component :is="gauge.icon" :class="['w-5 h-5', gauge.iconColor]" />
        </div>
        <p class="text-3xl font-bold text-gray-900">{{ counters[gauge.key] }}</p>
        <div class="mt-2 w-full h-1.5 bg-gray-100 rounded-full overflow-hidden">
          <div
            :class="['h-full rounded-full transition-all duration-500', gauge.barColor]"
            :style="{ width: gaugePercent(gauge.key) + '%' }"
          />
        </div>
        <p class="text-xs text-gray-400 mt-1">{{ gaugePercent(gauge.key) }}%</p>
      </div>
    </div>

    <!-- Charts and timeline -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Line chart -->
      <div class="lg:col-span-2 bg-white rounded-xl border border-gray-200 p-5">
        <h3 class="text-sm font-semibold text-gray-700 mb-4">Courbes d'accumulation en temps réel</h3>
        <div class="relative" style="height: 300px;">
          <canvas ref="chartCanvas" />
          <div v-if="chartData.labels.length === 0" class="absolute inset-0 flex items-center justify-center">
            <p class="text-sm text-gray-400">En attente d'événements...</p>
          </div>
        </div>
      </div>

      <!-- Live timeline -->
      <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="px-5 py-3 border-b border-gray-200">
          <h3 class="text-sm font-semibold text-gray-700">Événements en direct</h3>
        </div>
        <div class="max-h-[340px] overflow-y-auto" ref="timelineContainer">
          <div v-if="events.length === 0" class="p-8 text-center">
            <Clock class="w-8 h-8 text-gray-300 mx-auto mb-2" />
            <p class="text-sm text-gray-400">En attente...</p>
          </div>
          <div
            v-for="(event, idx) in events"
            :key="idx"
            :class="[
              'flex items-start gap-3 px-4 py-3 border-b border-gray-50 transition-colors',
              idx === 0 ? 'bg-yellow-50/50' : ''
            ]"
          >
            <div :class="['flex-shrink-0 w-2 h-2 mt-1.5 rounded-full', eventDotColor(event.type)]" />
            <div class="flex-1 min-w-0">
              <p class="text-sm text-gray-700">
                <span class="font-medium">{{ event.recipient_name || event.email }}</span>
                {{ eventVerb(event.type) }}
              </p>
              <p class="text-xs text-gray-400">{{ formatTime(event.timestamp) }}</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Recipients table -->
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
      <div class="px-5 py-3 border-b border-gray-200">
        <h3 class="text-sm font-semibold text-gray-700">Détail par destinataire</h3>
      </div>
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Destinataire</th>
              <th v-if="isMultiTemplate" class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Template</th>
              <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Envoyé</th>
              <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Ouvert</th>
              <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Cliqué</th>
              <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Soumis</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-if="loadingRecipients">
              <td colspan="5" class="px-4 py-8 text-center">
                <div class="inline-flex items-center gap-2 text-sm text-gray-500">
                  <Loader2 class="w-4 h-4 animate-spin" />
                  Chargement...
                </div>
              </td>
            </tr>
            <tr
              v-for="r in recipientStatuses"
              :key="r.id"
              :class="[
                'transition-colors duration-700',
                r.highlight ? 'bg-yellow-50' : 'hover:bg-gray-50'
              ]"
            >
              <td class="px-4 py-3">
                <div>
                  <p class="text-sm font-medium text-gray-900">{{ r.first_name }} {{ r.last_name }}</p>
                  <p class="text-xs text-gray-500">{{ r.email }}</p>
                </div>
              </td>
              <td v-if="isMultiTemplate" class="px-4 py-3">
                <span class="text-xs text-gray-600 line-clamp-1">{{ r.template_name || '-' }}</span>
              </td>
              <td class="px-4 py-3 text-center">
                <Send :class="['w-4 h-4 mx-auto', r.delivered ? 'text-blue-500' : 'text-gray-200']" />
              </td>
              <td class="px-4 py-3 text-center">
                <MailOpen :class="['w-4 h-4 mx-auto', r.opened ? 'text-green-500' : 'text-gray-200']" />
              </td>
              <td class="px-4 py-3 text-center">
                <MousePointer :class="['w-4 h-4 mx-auto', r.clicked ? 'text-orange-500' : 'text-gray-200']" />
              </td>
              <td class="px-4 py-3 text-center">
                <FileCheck :class="['w-4 h-4 mx-auto', r.submitted ? 'text-red-500' : 'text-gray-200']" />
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount, nextTick, markRaw } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import { useRoute } from 'vue-router'
import {
  ArrowLeft, BarChart3, Send, MailOpen, MousePointer,
  FileCheck, Clock, Loader2
} from 'lucide-vue-next'
import { useApi } from '../../composables/useApi'
import { useWebSocket } from '../../composables/useWebSocket'

const route = useRoute()
const { get } = useApi()
const campaignId = route.params.id

const campaign = ref({})
const loadingRecipients = ref(true)
const recipientStatuses = ref([])
const events = ref([])
const chartCanvas = ref(null)
const timelineContainer = ref(null)
let chartInstance = null

const counters = ref({
  total: 0,
  delivered: 0,
  opened: 0,
  clicked: 0,
  submitted: 0
})

const gauges = [
  { key: 'delivered', label: 'Envoyés', icon: markRaw(Send), iconColor: 'text-blue-500', barColor: 'bg-blue-500' },
  { key: 'opened', label: 'Ouverts', icon: markRaw(MailOpen), iconColor: 'text-green-500', barColor: 'bg-green-500' },
  { key: 'clicked', label: 'Cliqués', icon: markRaw(MousePointer), iconColor: 'text-orange-500', barColor: 'bg-orange-500' },
  { key: 'submitted', label: 'Soumis', icon: markRaw(FileCheck), iconColor: 'text-red-500', barColor: 'bg-red-500' }
]

const chartData = ref({
  labels: [],
  delivered: [],
  opened: [],
  clicked: [],
  submitted: []
})

const statusLabels = {
  draft: 'Brouillon',
  scheduled: 'Planifiée',
  running: 'En cours',
  paused: 'En pause',
  completed: 'Terminée',
  cancelled: 'Annulée'
}

const statusClasses = computed(() => {
  const map = {
    running: 'bg-green-100 text-green-700',
    paused: 'bg-yellow-100 text-yellow-700',
    completed: 'bg-indigo-100 text-indigo-700',
    cancelled: 'bg-red-100 text-red-700',
    draft: 'bg-gray-100 text-gray-700',
    scheduled: 'bg-blue-100 text-blue-700'
  }
  return map[campaign.value.status] || 'bg-gray-100 text-gray-700'
})

const statusLabel = computed(() => statusLabels[campaign.value.status] || campaign.value.status)

const isMultiTemplate = computed(() => (campaign.value.template_names?.length || 0) > 1)

const sendProgress = computed(() => {
  if (counters.value.total === 0) return 0
  return Math.round((counters.value.delivered / counters.value.total) * 100)
})

function gaugePercent(key) {
  if (counters.value.total === 0) return 0
  return Math.round((counters.value[key] / counters.value.total) * 100)
}

function eventDotColor(type) {
  const map = {
    delivered: 'bg-blue-500',
    opened: 'bg-green-500',
    clicked: 'bg-orange-500',
    submitted: 'bg-red-500'
  }
  return map[type] || 'bg-gray-400'
}

function eventVerb(type) {
  const map = {
    delivered: 'a reçu l\'email',
    opened: 'a ouvert l\'email',
    clicked: 'a cliqué sur le lien',
    submitted: 'a soumis le formulaire'
  }
  return map[type] || type
}

function formatDate(dateStr) {
  if (!dateStr) return '-'
  return new Date(dateStr).toLocaleDateString('fr-FR', {
    day: '2-digit', month: '2-digit', year: 'numeric',
    hour: '2-digit', minute: '2-digit'
  })
}

function formatTime(timestamp) {
  if (!timestamp) return ''
  return new Date(timestamp).toLocaleTimeString('fr-FR', {
    hour: '2-digit', minute: '2-digit', second: '2-digit'
  })
}

function updateChart() {
  if (!chartCanvas.value) return

  // Simple canvas-based chart rendering (no external dependency)
  const canvas = chartCanvas.value
  const ctx = canvas.getContext('2d')
  const dpr = window.devicePixelRatio || 1
  const rect = canvas.parentElement.getBoundingClientRect()

  canvas.width = rect.width * dpr
  canvas.height = rect.height * dpr
  canvas.style.width = rect.width + 'px'
  canvas.style.height = rect.height + 'px'
  ctx.scale(dpr, dpr)

  const w = rect.width
  const h = rect.height
  const pad = { top: 20, right: 20, bottom: 30, left: 40 }
  const plotW = w - pad.left - pad.right
  const plotH = h - pad.top - pad.bottom

  ctx.clearRect(0, 0, w, h)

  const series = [
    { data: chartData.value.delivered, color: '#3B82F6', label: 'Envoyés' },
    { data: chartData.value.opened, color: '#22C55E', label: 'Ouverts' },
    { data: chartData.value.clicked, color: '#F97316', label: 'Cliqués' },
    { data: chartData.value.submitted, color: '#EF4444', label: 'Soumis' }
  ]

  const maxVal = Math.max(
    1,
    ...series.flatMap(s => s.data)
  )

  const labels = chartData.value.labels
  if (labels.length < 2) return

  // Grid lines
  ctx.strokeStyle = '#E5E7EB'
  ctx.lineWidth = 1
  for (let i = 0; i <= 4; i++) {
    const y = pad.top + (plotH * i / 4)
    ctx.beginPath()
    ctx.moveTo(pad.left, y)
    ctx.lineTo(w - pad.right, y)
    ctx.stroke()

    ctx.fillStyle = '#9CA3AF'
    ctx.font = '11px sans-serif'
    ctx.textAlign = 'right'
    ctx.fillText(Math.round(maxVal * (4 - i) / 4), pad.left - 6, y + 4)
  }

  // Draw lines
  series.forEach(s => {
    if (s.data.length < 2) return
    ctx.beginPath()
    ctx.strokeStyle = s.color
    ctx.lineWidth = 2
    s.data.forEach((val, i) => {
      const x = pad.left + (plotW * i / (labels.length - 1))
      const y = pad.top + plotH - (plotH * val / maxVal)
      if (i === 0) ctx.moveTo(x, y)
      else ctx.lineTo(x, y)
    })
    ctx.stroke()
  })

  // Legend
  let legendX = pad.left
  series.forEach(s => {
    ctx.fillStyle = s.color
    ctx.fillRect(legendX, h - 12, 12, 3)
    ctx.fillStyle = '#6B7280'
    ctx.font = '11px sans-serif'
    ctx.textAlign = 'left'
    ctx.fillText(s.label, legendX + 16, h - 7)
    legendX += ctx.measureText(s.label).width + 32
  })
}

// WebSocket
const { subscribe, onEvent, disconnect } = useWebSocket()

function handleWsEvent(data) {
  const { event_type, recipient_id, recipient_name, email, timestamp } = data

  // Update counter
  if (counters.value[event_type] !== undefined) {
    counters.value[event_type]++
  }

  // Update recipient status
  const recipient = recipientStatuses.value.find(r => r.id === recipient_id)
  if (recipient) {
    if (event_type === 'delivered') recipient.delivered = true
    if (event_type === 'opened') recipient.opened = true
    if (event_type === 'clicked') recipient.clicked = true
    if (event_type === 'submitted') recipient.submitted = true
    recipient.highlight = true
    setTimeout(() => { recipient.highlight = false }, 2000)
  }

  // Add to events feed (newest first)
  events.value.unshift({
    type: event_type,
    recipient_name,
    email,
    timestamp: timestamp || new Date().toISOString()
  })
  if (events.value.length > 100) events.value.pop()

  // Update chart data
  const now = new Date().toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })
  const lastLabel = chartData.value.labels[chartData.value.labels.length - 1]
  if (lastLabel !== now) {
    chartData.value.labels.push(now)
    chartData.value.delivered.push(counters.value.delivered)
    chartData.value.opened.push(counters.value.opened)
    chartData.value.clicked.push(counters.value.clicked)
    chartData.value.submitted.push(counters.value.submitted)
  } else {
    const len = chartData.value.delivered.length - 1
    chartData.value.delivered[len] = counters.value.delivered
    chartData.value.opened[len] = counters.value.opened
    chartData.value.clicked[len] = counters.value.clicked
    chartData.value.submitted[len] = counters.value.submitted
  }

  nextTick(updateChart)
}

async function fetchCampaign() {
  try {
    const data = await get(`/api/campaigns/${campaignId}`)
    campaign.value = data
    counters.value.total = data.target_count || 0
    counters.value.delivered = data.delivered_count || 0
    counters.value.opened = data.opened_count || 0
    counters.value.clicked = data.clicked_count || 0
    counters.value.submitted = data.submitted_count || 0

    // Initial chart point
    const now = new Date().toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })
    chartData.value.labels.push(now)
    chartData.value.delivered.push(counters.value.delivered)
    chartData.value.opened.push(counters.value.opened)
    chartData.value.clicked.push(counters.value.clicked)
    chartData.value.submitted.push(counters.value.submitted)
  } catch {
    // Error handled by useApi
  }
}

async function fetchRecipientStatuses() {
  loadingRecipients.value = true
  try {
    const data = await get(`/api/campaigns/${campaignId}/recipients`)
    const rows = data?.data || data || []
    recipientStatuses.value = (Array.isArray(rows) ? rows : []).map(r => ({
      ...r,
      delivered: r.mail_status === 'delivered',
      opened: !!r.opened,
      clicked: !!r.clicked,
      submitted: !!r.submitted_credentials,
      highlight: false
    }))
  } catch {
    // Error handled by useApi
  } finally {
    loadingRecipients.value = false
  }
}

let pollInterval = null

onMounted(async () => {
  await Promise.all([fetchCampaign(), fetchRecipientStatuses()])
  nextTick(updateChart)

  // Subscribe to WebSocket channel (may not work over HTTPS without proxy)
  try {
    subscribe(`campaign.${campaignId}`)
    onEvent('campaign_event', handleWsEvent)
  } catch {
    // WebSocket not available
  }

  // Polling fallback: refresh every 5 seconds while campaign is running
  pollInterval = setInterval(async () => {
    if (campaign.value?.status === 'running' || campaign.value?.status === 'paused') {
      await Promise.all([fetchCampaign(), fetchRecipientStatuses()])
      nextTick(updateChart)
    }
  }, 5000)
})

onBeforeUnmount(() => {
  try { disconnect() } catch {}
  if (pollInterval) clearInterval(pollInterval)
})
</script>
