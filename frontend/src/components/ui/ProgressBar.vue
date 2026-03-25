<template>
  <div>
    <div v-if="showLabel" class="flex justify-between items-center mb-1">
      <slot name="label" />
      <span class="text-sm font-medium text-gray-700">{{ Math.round(value) }}%</span>
    </div>
    <div :class="['w-full bg-gray-200 rounded-full overflow-hidden', height]">
      <div
        :class="['rounded-full transition-all duration-500 ease-out', height, barColor]"
        :style="{ width: clampedValue + '%' }"
      />
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  value: {
    type: Number,
    default: 0
  },
  color: {
    type: String,
    default: 'blue'
  },
  height: {
    type: String,
    default: 'h-2'
  },
  showLabel: {
    type: Boolean,
    default: false
  }
})

const clampedValue = computed(() => Math.max(0, Math.min(100, props.value)))

const barColor = computed(() => {
  const colors = {
    blue: 'bg-blue-500',
    green: 'bg-green-500',
    red: 'bg-red-500',
    yellow: 'bg-yellow-500',
    orange: 'bg-orange-500',
    purple: 'bg-purple-500',
    indigo: 'bg-indigo-500',
    teal: 'bg-teal-500',
    gray: 'bg-gray-500'
  }
  return colors[props.color] || 'bg-blue-500'
})
</script>
