<template>
  <AppLayout>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Tableau de bord</h1>
        <p class="mt-1 text-sm text-gray-500">Vue d'ensemble des campagnes de sensibilisation</p>
      </div>
      <div class="flex items-center gap-3">
        <button
          @click="exportCsv"
          class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50"
        >
          <Download class="w-4 h-4" />
          Exporter CSV
        </button>
      </div>
    </div>

    <!-- Period selector -->
    <div class="flex items-center gap-2 bg-white rounded-lg border border-gray-200 p-1 w-fit">
      <button
        v-for="p in periods"
        :key="p.value"
        @click="selectedPeriod = p.value"
        :class="[
          'px-3 py-1.5 text-sm font-medium rounded-md transition-colors',
          selectedPeriod === p.value
            ? 'bg-brand-500 text-white'
            : 'text-gray-600 hover:bg-gray-100'
        ]"
      >
        {{ p.label }}
      </button>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="space-y-6">
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <div v-for="i in 4" :key="i" class="bg-white rounded-xl border border-gray-200 p-5 animate-pulse">
          <div class="h-4 bg-gray-200 rounded w-1/2 mb-3" />
          <div class="h-8 bg-gray-200 rounded w-2/3" />
        </div>
      </div>
    </div>

    <template v-else>
      <!-- KPI cards -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
        <div class="bg-white rounded-xl border border-gray-200 p-5">
          <div class="flex items-center justify-between mb-2">
            <span class="text-sm font-medium text-gray-500">Campagnes</span>
            <Target class="w-5 h-5 text-brand-400" />
          </div>
          <p class="text-3xl font-bold text-gray-900">{{ stats.campaigns_count }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-5">
          <div class="flex items-center justify-between mb-2">
            <span class="text-sm font-medium text-gray-500">Collaborateurs ciblés</span>
            <Users class="w-5 h-5 text-blue-400" />
          </div>
          <p class="text-3xl font-bold text-gray-900">{{ stats.employees_targeted }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-5">
          <div class="flex items-center justify-between mb-2">
            <span class="text-sm font-medium text-gray-500">Taux d'ouverture</span>
            <MailOpen class="w-5 h-5 text-green-400" />
          </div>
          <p class="text-3xl font-bold text-gray-900">{{ stats.avg_open_rate }}%</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-5">
          <div class="flex items-center justify-between mb-2">
            <span class="text-sm font-medium text-gray-500">Taux de clic</span>
            <MousePointer class="w-5 h-5 text-orange-400" />
          </div>
          <p class="text-3xl font-bold text-gray-900">{{ stats.avg_click_rate }}%</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-5">
          <div class="flex items-center justify-between mb-2">
            <span class="text-sm font-medium text-gray-500">Taux de soumission</span>
            <FileCheck class="w-5 h-5 text-red-400" />
          </div>
          <p class="text-3xl font-bold text-gray-900">{{ stats.avg_submit_rate }}%</p>
        </div>
      </div>

      <!-- Charts row -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Line chart: rates per campaign -->
        <div class="bg-white rounded-xl border border-gray-200 p-5">
          <h3 class="text-sm font-semibold text-gray-700 mb-4">Évolution par campagne</h3>
          <div class="relative" style="height: 300px;">
            <canvas ref="lineChartCanvas" />
            <div v-if="!stats.campaign_trends?.length" class="absolute inset-0 flex items-center justify-center">
              <p class="text-sm text-gray-400">Pas de données disponibles</p>
            </div>
          </div>
        </div>

        <!-- Bar chart: comparison by group -->
        <div class="bg-white rounded-xl border border-gray-200 p-5">
          <h3 class="text-sm font-semibold text-gray-700 mb-4">Comparaison par groupe</h3>
          <div class="space-y-3">
            <div v-if="!stats.group_comparison?.length" class="py-12 text-center">
              <p class="text-sm text-gray-400">Pas de données disponibles</p>
            </div>
            <div
              v-for="group in stats.group_comparison"
              :key="group.name"
              class="space-y-1"
            >
              <div class="flex items-center justify-between text-sm">
                <span class="font-medium text-gray-700 truncate">{{ group.name }}</span>
                <span class="text-gray-500 text-xs">{{ group.click_rate }}% clic</span>
              </div>
              <div class="flex gap-1 h-4">
                <div
                  class="bg-green-400 rounded-l"
                  :style="{ width: group.open_rate + '%' }"
                  :title="`Ouverture: ${group.open_rate}%`"
                />
                <div
                  class="bg-orange-400"
                  :style="{ width: group.click_rate + '%' }"
                  :title="`Clic: ${group.click_rate}%`"
                />
                <div
                  class="bg-red-400 rounded-r"
                  :style="{ width: group.submit_rate + '%' }"
                  :title="`Soumission: ${group.submit_rate}%`"
                />
              </div>
            </div>
          </div>
          <!-- Legend -->
          <div v-if="stats.group_comparison?.length" class="flex items-center gap-4 mt-4 pt-3 border-t border-gray-100">
            <div class="flex items-center gap-1.5 text-xs text-gray-500">
              <div class="w-3 h-2 bg-green-400 rounded" />
              Ouverture
            </div>
            <div class="flex items-center gap-1.5 text-xs text-gray-500">
              <div class="w-3 h-2 bg-orange-400 rounded" />
              Clic
            </div>
            <div class="flex items-center gap-1.5 text-xs text-gray-500">
              <div class="w-3 h-2 bg-red-400 rounded" />
              Soumission
            </div>
          </div>
        </div>
      </div>

      <!-- Tables row -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Top templates -->
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
          <div class="px-5 py-3 border-b border-gray-200">
            <h3 class="text-sm font-semibold text-gray-700">Top 5 templates les plus efficaces</h3>
          </div>
          <table class="min-w-full divide-y divide-gray-100">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-600 uppercase">Template</th>
                <th class="px-4 py-2.5 text-right text-xs font-semibold text-gray-600 uppercase">Ouverture</th>
                <th class="px-4 py-2.5 text-right text-xs font-semibold text-gray-600 uppercase">Clic</th>
                <th class="px-4 py-2.5 text-right text-xs font-semibold text-gray-600 uppercase">Soumission</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
              <tr v-if="!stats.top_templates?.length">
                <td colspan="4" class="px-4 py-8 text-center text-sm text-gray-400">Aucune donnée</td>
              </tr>
              <tr v-for="tpl in stats.top_templates" :key="tpl.id" class="hover:bg-gray-50">
                <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ tpl.name }}</td>
                <td class="px-4 py-3 text-sm text-right text-gray-600">{{ tpl.open_rate }}%</td>
                <td class="px-4 py-3 text-sm text-right text-orange-600 font-medium">{{ tpl.click_rate }}%</td>
                <td class="px-4 py-3 text-sm text-right text-red-600 font-medium">{{ tpl.submit_rate }}%</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Top vulnerable employees (admin only) -->
        <div v-if="isAdmin" class="bg-white rounded-xl border border-gray-200 overflow-hidden">
          <div class="px-5 py-3 border-b border-gray-200">
            <h3 class="text-sm font-semibold text-gray-700">Top 10 collaborateurs les plus vulnérables</h3>
          </div>
          <table class="min-w-full divide-y divide-gray-100">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-600 uppercase">Collaborateur</th>
                <th class="px-4 py-2.5 text-right text-xs font-semibold text-gray-600 uppercase">Campagnes</th>
                <th class="px-4 py-2.5 text-right text-xs font-semibold text-gray-600 uppercase">Taux de clic</th>
                <th class="px-4 py-2.5 text-right text-xs font-semibold text-gray-600 uppercase">Soumissions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
              <tr v-if="!stats.vulnerable_employees?.length">
                <td colspan="4" class="px-4 py-8 text-center text-sm text-gray-400">Aucune donnée</td>
              </tr>
              <tr v-for="emp in stats.vulnerable_employees" :key="emp.id" class="hover:bg-gray-50">
                <td class="px-4 py-3">
                  <p class="text-sm font-medium text-gray-900">{{ emp.name }}</p>
                  <p class="text-xs text-gray-500">{{ emp.department }}</p>
                </td>
                <td class="px-4 py-3 text-sm text-right text-gray-600">{{ emp.campaign_count }}</td>
                <td class="px-4 py-3 text-sm text-right text-orange-600 font-medium">{{ emp.click_rate }}%</td>
                <td class="px-4 py-3 text-sm text-right text-red-600 font-medium">{{ emp.submit_count }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </template>
  </div>
  </AppLayout>
</template>

<script setup>
import { ref, watch, onMounted, nextTick } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import {
  Download, Target, Users, MailOpen, MousePointer, FileCheck
} from 'lucide-vue-next'
import { useApi } from '../../composables/useApi'
import { useAuth } from '../../composables/useAuth'
import { useWebSocket } from '../../composables/useWebSocket'

const { loading, get } = useApi()
const { isAdmin } = useAuth()
const { subscribe, onEvent } = useWebSocket()

const lineChartCanvas = ref(null)

const periods = [
  { value: '30d', label: '30j' },
  { value: '90d', label: '90j' },
  { value: '6m', label: '6 mois' },
  { value: '1y', label: '1 an' },
  { value: 'all', label: 'Tout' }
]

const selectedPeriod = ref('30d')

const stats = ref({
  campaigns_count: 0,
  employees_targeted: 0,
  avg_open_rate: 0,
  avg_click_rate: 0,
  avg_submit_rate: 0,
  campaign_trends: [],
  group_comparison: [],
  top_templates: [],
  vulnerable_employees: []
})

async function fetchStats() {
  try {
    const data = await get(`/api/stats/dashboard?period=${selectedPeriod.value}`)
    stats.value = {
      campaigns_count: data.campaigns_count || 0,
      employees_targeted: data.employees_targeted || 0,
      avg_open_rate: data.avg_open_rate || 0,
      avg_click_rate: data.avg_click_rate || 0,
      avg_submit_rate: data.avg_submit_rate || 0,
      campaign_trends: data.campaign_trends || [],
      group_comparison: data.group_comparison || [],
      top_templates: data.top_templates || [],
      vulnerable_employees: data.vulnerable_employees || []
    }
    nextTick(drawLineChart)
  } catch {
    // Error handled by useApi
  }
}

function drawLineChart() {
  const canvas = lineChartCanvas.value
  if (!canvas || !stats.value.campaign_trends?.length) return

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
  const pad = { top: 20, right: 20, bottom: 40, left: 45 }
  const plotW = w - pad.left - pad.right
  const plotH = h - pad.top - pad.bottom

  ctx.clearRect(0, 0, w, h)

  const trends = stats.value.campaign_trends
  const maxVal = Math.max(100, ...trends.flatMap(t => [t.open_rate, t.click_rate, t.submit_rate]))

  // Grid
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
    ctx.fillText(Math.round(maxVal * (4 - i) / 4) + '%', pad.left - 6, y + 4)
  }

  const series = [
    { values: trends.map(t => t.open_rate), color: '#22C55E' },
    { values: trends.map(t => t.click_rate), color: '#F97316' },
    { values: trends.map(t => t.submit_rate), color: '#EF4444' }
  ]

  series.forEach(s => {
    ctx.beginPath()
    ctx.strokeStyle = s.color
    ctx.lineWidth = 2
    s.values.forEach((val, i) => {
      const x = pad.left + (plotW * i / Math.max(1, trends.length - 1))
      const y = pad.top + plotH - (plotH * val / maxVal)
      if (i === 0) ctx.moveTo(x, y)
      else ctx.lineTo(x, y)
    })
    ctx.stroke()
  })

  // X labels
  ctx.fillStyle = '#9CA3AF'
  ctx.font = '10px sans-serif'
  ctx.textAlign = 'center'
  trends.forEach((t, i) => {
    if (trends.length <= 10 || i % Math.ceil(trends.length / 10) === 0) {
      const x = pad.left + (plotW * i / Math.max(1, trends.length - 1))
      ctx.fillText(t.name?.substring(0, 12) || `C${i + 1}`, x, h - pad.bottom + 16)
    }
  })

  // Legend
  const labels = [
    { text: 'Ouverture', color: '#22C55E' },
    { text: 'Clic', color: '#F97316' },
    { text: 'Soumission', color: '#EF4444' }
  ]
  let lx = pad.left
  labels.forEach(l => {
    ctx.fillStyle = l.color
    ctx.fillRect(lx, h - 12, 12, 3)
    ctx.fillStyle = '#6B7280'
    ctx.font = '11px sans-serif'
    ctx.textAlign = 'left'
    ctx.fillText(l.text, lx + 16, h - 7)
    lx += ctx.measureText(l.text).width + 32
  })
}

async function exportCsv() {
  try {
    const response = await fetch(`/api/stats/dashboard/export?period=${selectedPeriod.value}`, {
      headers: { 'Content-Type': 'application/json' }
    })
    const blob = await response.blob()
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = `stats-dashboard-${selectedPeriod.value}.csv`
    a.click()
    URL.revokeObjectURL(url)
  } catch {
    // Fallback: build CSV from current data
    const rows = [['Campagne', 'Ouverture %', 'Clic %', 'Soumission %']]
    ;(stats.value.campaign_trends || []).forEach(t => {
      rows.push([t.name, t.open_rate, t.click_rate, t.submit_rate])
    })
    const csv = rows.map(r => r.join(',')).join('\n')
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' })
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = `stats-dashboard-${selectedPeriod.value}.csv`
    a.click()
    URL.revokeObjectURL(url)
  }
}

watch(selectedPeriod, fetchStats)

onMounted(() => {
  fetchStats()
  subscribe('campaigns')
  onEvent('stats.updated', () => fetchStats())
})
</script>
