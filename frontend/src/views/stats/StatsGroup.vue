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
          <h1 class="text-2xl font-bold text-gray-900">{{ group.name || 'Groupe' }}</h1>
          <p class="mt-0.5 text-sm text-gray-500">
            {{ (group.member_count || members.length) }} membre{{ (group.member_count || members.length) !== 1 ? 's' : '' }}
          </p>
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
      <div class="bg-white rounded-xl border border-gray-200 p-5 animate-pulse" style="height: 350px;">
        <div class="h-4 bg-gray-200 rounded w-1/3 mb-4" />
        <div class="h-full bg-gray-100 rounded" />
      </div>
    </div>

    <template v-else>
      <!-- KPI summary -->
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl border border-gray-200 p-5">
          <span class="text-sm font-medium text-gray-500">Campagnes</span>
          <p class="text-3xl font-bold text-gray-900 mt-1">{{ stats.campaigns_count || 0 }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-5">
          <span class="text-sm font-medium text-gray-500">Taux d'ouverture moyen</span>
          <p class="text-3xl font-bold text-green-600 mt-1">{{ stats.avg_open_rate || 0 }}%</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-5">
          <span class="text-sm font-medium text-gray-500">Taux de clic moyen</span>
          <p class="text-3xl font-bold text-orange-600 mt-1">{{ stats.avg_click_rate || 0 }}%</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-5">
          <span class="text-sm font-medium text-gray-500">Taux de soumission moyen</span>
          <p class="text-3xl font-bold text-red-600 mt-1">{{ stats.avg_submit_rate || 0 }}%</p>
        </div>
      </div>

      <!-- Line chart: evolution over time -->
      <div class="bg-white rounded-xl border border-gray-200 p-5">
        <h3 class="text-sm font-semibold text-gray-700 mb-4">Évolution dans le temps</h3>
        <div class="relative" style="height: 320px;">
          <canvas ref="chartCanvas" />
          <div v-if="!stats.trends?.length" class="absolute inset-0 flex items-center justify-center">
            <p class="text-sm text-gray-400">Pas de données disponibles</p>
          </div>
        </div>
        <div class="flex items-center gap-6 mt-3 text-xs text-gray-500">
          <div class="flex items-center gap-1.5">
            <div class="w-4 h-0.5 bg-green-500" />
            <span>Ouverture (groupe)</span>
          </div>
          <div class="flex items-center gap-1.5">
            <div class="w-4 h-0.5 bg-orange-500" />
            <span>Clic (groupe)</span>
          </div>
          <div class="flex items-center gap-1.5">
            <div class="w-4 h-0.5 bg-red-500" />
            <span>Soumission (groupe)</span>
          </div>
          <div class="flex items-center gap-1.5">
            <div class="w-4 h-0.5 bg-gray-400 border-dashed border-t border-gray-400" style="border-style: dashed;" />
            <span>Moyenne entreprise (clic)</span>
          </div>
        </div>
      </div>

      <!-- Member list -->
      <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="px-5 py-3 border-b border-gray-200">
          <h3 class="text-sm font-semibold text-gray-700">Membres du groupe</h3>
        </div>
        <DataTable :columns="memberColumns" :data="members" :loading="loading">
          <template #cell-name="{ row }">
            <router-link
              :to="`/recipients/${row.id}`"
              class="group"
            >
              <p class="text-sm font-medium text-gray-900 group-hover:text-brand-600">{{ row.first_name }} {{ row.last_name }}</p>
              <p class="text-xs text-gray-500">{{ row.email }}</p>
            </router-link>
          </template>
          <template #cell-department="{ row }">
            <span class="text-sm text-gray-600">{{ row.department || '-' }}</span>
          </template>
          <template #cell-campaigns="{ row }">
            <span class="text-sm font-medium">{{ row.campaign_count || 0 }}</span>
          </template>
          <template #cell-open_rate="{ row }">
            <div class="flex items-center gap-2">
              <div class="w-16 h-1.5 bg-gray-100 rounded-full overflow-hidden">
                <div class="h-full bg-green-500 rounded-full" :style="{ width: (row.open_rate || 0) + '%' }" />
              </div>
              <span class="text-sm text-gray-600">{{ row.open_rate || 0 }}%</span>
            </div>
          </template>
          <template #cell-click_rate="{ row }">
            <div class="flex items-center gap-2">
              <div class="w-16 h-1.5 bg-gray-100 rounded-full overflow-hidden">
                <div class="h-full bg-orange-500 rounded-full" :style="{ width: (row.click_rate || 0) + '%' }" />
              </div>
              <span class="text-sm text-orange-600 font-medium">{{ row.click_rate || 0 }}%</span>
            </div>
          </template>
          <template #cell-submit_rate="{ row }">
            <div class="flex items-center gap-2">
              <div class="w-16 h-1.5 bg-gray-100 rounded-full overflow-hidden">
                <div class="h-full bg-red-500 rounded-full" :style="{ width: (row.submit_rate || 0) + '%' }" />
              </div>
              <span class="text-sm text-red-600 font-medium">{{ row.submit_rate || 0 }}%</span>
            </div>
          </template>
        </DataTable>
      </div>
    </template>
  </div>
  </AppLayout>
</template>

<script setup>
import { ref, onMounted, nextTick } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import { useRoute } from 'vue-router'
import { ArrowLeft, Download } from 'lucide-vue-next'
import { useApi } from '../../composables/useApi'
import DataTable from '../../components/ui/DataTable.vue'

const route = useRoute()
const { loading, get } = useApi()
const groupId = route.params.id

const group = ref({})
const members = ref([])
const stats = ref({
  campaigns_count: 0,
  avg_open_rate: 0,
  avg_click_rate: 0,
  avg_submit_rate: 0,
  trends: [],
  company_avg_trends: []
})

const chartCanvas = ref(null)

const memberColumns = [
  { key: 'name', label: 'Membre', sortable: true },
  { key: 'department', label: 'Département' },
  { key: 'campaigns', label: 'Campagnes', sortable: true },
  { key: 'open_rate', label: 'Ouverture', sortable: true },
  { key: 'click_rate', label: 'Clic', sortable: true },
  { key: 'submit_rate', label: 'Soumission', sortable: true }
]

function drawChart() {
  const canvas = chartCanvas.value
  if (!canvas) return

  const trends = stats.value.trends || []
  const companyAvg = stats.value.company_avg_trends || []
  if (!trends.length) return

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

  const maxVal = 100

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

  // Draw lines helper
  function drawLine(data, prop, color, dashed) {
    if (!data.length) return
    ctx.beginPath()
    ctx.strokeStyle = color
    ctx.lineWidth = 2
    if (dashed) ctx.setLineDash([6, 4])
    else ctx.setLineDash([])
    data.forEach((pt, i) => {
      const x = pad.left + (plotW * i / Math.max(1, data.length - 1))
      const y = pad.top + plotH - (plotH * (pt[prop] || 0) / maxVal)
      if (i === 0) ctx.moveTo(x, y)
      else ctx.lineTo(x, y)
    })
    ctx.stroke()
    ctx.setLineDash([])
  }

  // Group lines
  drawLine(trends, 'open_rate', '#22C55E', false)
  drawLine(trends, 'click_rate', '#F97316', false)
  drawLine(trends, 'submit_rate', '#EF4444', false)

  // Company average overlay (dashed)
  drawLine(companyAvg, 'click_rate', '#9CA3AF', true)

  // X labels
  ctx.fillStyle = '#9CA3AF'
  ctx.font = '10px sans-serif'
  ctx.textAlign = 'center'
  trends.forEach((t, i) => {
    if (trends.length <= 10 || i % Math.ceil(trends.length / 10) === 0) {
      const x = pad.left + (plotW * i / Math.max(1, trends.length - 1))
      ctx.fillText(t.label?.substring(0, 12) || `C${i + 1}`, x, h - pad.bottom + 16)
    }
  })
}

async function fetchData() {
  try {
    const [groupData, statsData] = await Promise.all([
      get(`/api/recipients/groups/${groupId}`),
      get(`/api/stats/group/${groupId}`)
    ])
    group.value = groupData
    members.value = statsData.members || []
    stats.value = {
      campaigns_count: statsData.campaigns_count || 0,
      avg_open_rate: statsData.avg_open_rate || 0,
      avg_click_rate: statsData.avg_click_rate || 0,
      avg_submit_rate: statsData.avg_submit_rate || 0,
      trends: statsData.trends || [],
      company_avg_trends: statsData.company_avg_trends || []
    }
    nextTick(drawChart)
  } catch {
    // Error handled by useApi
  }
}

function exportCsv() {
  const rows = [['Prénom', 'Nom', 'Email', 'Département', 'Campagnes', 'Ouverture %', 'Clic %', 'Soumission %']]
  members.value.forEach(m => {
    rows.push([
      m.first_name, m.last_name, m.email, m.department || '',
      m.campaign_count || 0, m.open_rate || 0, m.click_rate || 0, m.submit_rate || 0
    ])
  })
  const csv = rows.map(r => r.map(v => `"${v}"`).join(',')).join('\n')
  const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = `stats-groupe-${groupId}.csv`
  a.click()
  URL.revokeObjectURL(url)
}

onMounted(fetchData)
</script>
