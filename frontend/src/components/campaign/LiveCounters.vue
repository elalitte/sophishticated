<template>
  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
    <div
      v-for="counter in counters"
      :key="counter.key"
      class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm"
    >
      <div class="flex items-center justify-between mb-3">
        <h4 class="text-sm font-medium text-gray-600">{{ counter.label }}</h4>
        <span :class="['text-xs font-semibold px-2 py-0.5 rounded-full', counter.badgeClass]">
          {{ counter.percentage }}%
        </span>
      </div>

      <GaugeChart
        :value="counter.percentage"
        :label="counter.label"
        :color="counter.color"
      />

      <div class="mt-3 text-center">
        <span class="text-2xl font-bold text-gray-900 tabular-nums counter-number">
          {{ animatedValues[counter.key] }}
        </span>
        <span class="text-sm text-gray-500 ml-1">/ {{ counter.total }}</span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, ref, watch, onMounted } from 'vue'
import GaugeChart from '../charts/GaugeChart.vue'

const props = defineProps({
  stats: {
    type: Object,
    required: true,
    default: () => ({
      delivered: 0, deliveredTotal: 0,
      opened: 0, openedTotal: 0,
      clicked: 0, clickedTotal: 0,
      submitted: 0, submittedTotal: 0
    })
  }
})

const animatedValues = ref({
  delivered: 0,
  opened: 0,
  clicked: 0,
  submitted: 0
})

const counters = computed(() => [
  {
    key: 'delivered',
    label: 'Délivrés',
    count: props.stats.delivered || 0,
    total: props.stats.deliveredTotal || 0,
    percentage: safePercent(props.stats.delivered, props.stats.deliveredTotal),
    color: '#22C55E',
    badgeClass: 'bg-green-100 text-green-700'
  },
  {
    key: 'opened',
    label: 'Ouverts',
    count: props.stats.opened || 0,
    total: props.stats.openedTotal || 0,
    percentage: safePercent(props.stats.opened, props.stats.openedTotal),
    color: '#F97316',
    badgeClass: 'bg-orange-100 text-orange-700'
  },
  {
    key: 'clicked',
    label: 'Cliqués',
    count: props.stats.clicked || 0,
    total: props.stats.clickedTotal || 0,
    percentage: safePercent(props.stats.clicked, props.stats.clickedTotal),
    color: '#EAB308',
    badgeClass: 'bg-yellow-100 text-yellow-700'
  },
  {
    key: 'submitted',
    label: 'Soumis',
    count: props.stats.submitted || 0,
    total: props.stats.submittedTotal || 0,
    percentage: safePercent(props.stats.submitted, props.stats.submittedTotal),
    color: '#EF4444',
    badgeClass: 'bg-red-100 text-red-700'
  }
])

function safePercent(value, total) {
  if (!total || total === 0) return 0
  return Math.round((value / total) * 100)
}

function animateValue(key, target) {
  const start = animatedValues.value[key]
  const diff = target - start
  if (diff === 0) return

  const duration = 800
  const startTime = performance.now()

  function step(currentTime) {
    const elapsed = currentTime - startTime
    const progress = Math.min(elapsed / duration, 1)
    const eased = 1 - Math.pow(1 - progress, 3)
    animatedValues.value[key] = Math.round(start + diff * eased)

    if (progress < 1) {
      requestAnimationFrame(step)
    }
  }

  requestAnimationFrame(step)
}

watch(
  () => props.stats,
  (newStats) => {
    animateValue('delivered', newStats.delivered || 0)
    animateValue('opened', newStats.opened || 0)
    animateValue('clicked', newStats.clicked || 0)
    animateValue('submitted', newStats.submitted || 0)
  },
  { deep: true }
)

onMounted(() => {
  animateValue('delivered', props.stats.delivered || 0)
  animateValue('opened', props.stats.opened || 0)
  animateValue('clicked', props.stats.clicked || 0)
  animateValue('submitted', props.stats.submitted || 0)
})
</script>

<style scoped>
.counter-number {
  font-variant-numeric: tabular-nums;
}
</style>
