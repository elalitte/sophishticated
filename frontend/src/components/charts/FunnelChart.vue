<template>
  <div class="w-full space-y-1">
    <div
      v-for="(step, index) in data"
      :key="index"
      class="relative"
    >
      <!-- Bar -->
      <div class="flex items-center gap-3">
        <div class="flex-1">
          <div
            class="relative rounded-lg overflow-hidden transition-all duration-500 ease-out"
            :style="{
              width: barWidth(step.value) + '%',
              margin: '0 auto'
            }"
          >
            <div
              class="px-4 py-3 text-white text-sm font-medium flex items-center justify-between min-w-[200px]"
              :style="{ backgroundColor: step.color || defaultColors[index % defaultColors.length] }"
            >
              <span>{{ step.label }}</span>
              <span class="font-bold">{{ formatNumber(step.value) }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Percentage between steps -->
      <div
        v-if="index < data.length - 1"
        class="flex justify-center py-0.5"
      >
        <span class="text-xs font-medium px-2 py-0.5 rounded-full"
              :class="dropRate(index) > 50 ? 'text-red-600 bg-red-50' : 'text-gray-500 bg-gray-100'"
        >
          {{ conversionRate(index) }}% de conversion
        </span>
      </div>
    </div>
  </div>
</template>

<script setup>
const props = defineProps({
  data: {
    type: Array,
    required: true,
    default: () => []
  }
})

const defaultColors = [
  '#3B82F6', '#6366F1', '#8B5CF6', '#A855F7', '#D946EF', '#EC4899'
]

function barWidth(value) {
  if (!props.data.length) return 100
  const max = Math.max(...props.data.map(d => d.value))
  if (max === 0) return 100
  return Math.max(20, (value / max) * 100)
}

function conversionRate(index) {
  if (index >= props.data.length - 1) return 0
  const current = props.data[index].value
  const next = props.data[index + 1].value
  if (current === 0) return 0
  return Math.round((next / current) * 100)
}

function dropRate(index) {
  return 100 - conversionRate(index)
}

function formatNumber(n) {
  return new Intl.NumberFormat('fr-FR').format(n)
}
</script>
