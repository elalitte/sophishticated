<template>
  <div class="flex flex-col items-center">
    <svg :viewBox="'0 0 ' + size + ' ' + (size * 0.65)" class="w-full max-w-[200px]">
      <!-- Background arc -->
      <path
        :d="backgroundArc"
        fill="none"
        :stroke="'#E5E7EB'"
        :stroke-width="strokeWidth"
        stroke-linecap="round"
      />
      <!-- Value arc -->
      <path
        :d="valueArc"
        fill="none"
        :stroke="gaugeColor"
        :stroke-width="strokeWidth"
        stroke-linecap="round"
        class="gauge-arc"
        :style="{ '--dash': arcLength, '--offset': arcLength }"
      />
      <!-- Center text -->
      <text
        :x="size / 2"
        :y="size * 0.52"
        text-anchor="middle"
        class="fill-gray-900 font-bold"
        :font-size="size * 0.18"
      >
        {{ Math.round(clampedValue) }}%
      </text>
      <text
        v-if="label"
        :x="size / 2"
        :y="size * 0.62"
        text-anchor="middle"
        class="fill-gray-500"
        :font-size="size * 0.08"
      >
        {{ label }}
      </text>
    </svg>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  value: {
    type: Number,
    default: 0
  },
  label: {
    type: String,
    default: ''
  },
  color: {
    type: String,
    default: ''
  }
})

const size = 200
const strokeWidth = 16
const radius = (size - strokeWidth) / 2
const centerX = size / 2
const centerY = size * 0.55

const clampedValue = computed(() => Math.max(0, Math.min(100, props.value)))

const gaugeColor = computed(() => {
  if (props.color) return props.color
  const v = clampedValue.value
  if (v < 30) return '#EF4444'
  if (v < 60) return '#F97316'
  return '#22C55E'
})

function polarToCartesian(cx, cy, r, angleDeg) {
  const angleRad = (angleDeg * Math.PI) / 180
  return {
    x: cx + r * Math.cos(angleRad),
    y: cy + r * Math.sin(angleRad)
  }
}

function describeArc(cx, cy, r, startAngle, endAngle) {
  const start = polarToCartesian(cx, cy, r, endAngle)
  const end = polarToCartesian(cx, cy, r, startAngle)
  const largeArc = endAngle - startAngle > 180 ? 1 : 0
  return `M ${start.x} ${start.y} A ${r} ${r} 0 ${largeArc} 0 ${end.x} ${end.y}`
}

const startAngle = 180
const endAngle = 360

const backgroundArc = computed(() => {
  return describeArc(centerX, centerY, radius, startAngle, endAngle)
})

const valueArc = computed(() => {
  const angle = startAngle + (clampedValue.value / 100) * (endAngle - startAngle)
  if (clampedValue.value === 0) return ''
  return describeArc(centerX, centerY, radius, startAngle, Math.max(startAngle + 1, angle))
})

const arcLength = computed(() => {
  return (Math.PI * radius * (clampedValue.value / 100)).toFixed(2)
})
</script>

<style scoped>
.gauge-arc {
  stroke-dasharray: var(--dash) 999;
  animation: gauge-fill 1s ease-out forwards;
}

@keyframes gauge-fill {
  from {
    stroke-dashoffset: var(--offset);
  }
  to {
    stroke-dashoffset: 0;
  }
}
</style>
