<template>
  <span :class="badgeClasses">
    {{ text }}
  </span>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  color: {
    type: String,
    default: 'blue'
  },
  text: {
    type: String,
    default: ''
  },
  variant: {
    type: String,
    default: 'solid',
    validator: v => ['solid', 'outline'].includes(v)
  }
})

const badgeClasses = computed(() => {
  const base = 'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium'

  const colorMap = {
    blue:    { solid: 'bg-blue-100 text-blue-800',     outline: 'border border-blue-300 text-blue-700 bg-transparent' },
    green:   { solid: 'bg-green-100 text-green-800',   outline: 'border border-green-300 text-green-700 bg-transparent' },
    red:     { solid: 'bg-red-100 text-red-800',       outline: 'border border-red-300 text-red-700 bg-transparent' },
    yellow:  { solid: 'bg-yellow-100 text-yellow-800', outline: 'border border-yellow-300 text-yellow-700 bg-transparent' },
    orange:  { solid: 'bg-orange-100 text-orange-800', outline: 'border border-orange-300 text-orange-700 bg-transparent' },
    purple:  { solid: 'bg-purple-100 text-purple-800', outline: 'border border-purple-300 text-purple-700 bg-transparent' },
    pink:    { solid: 'bg-pink-100 text-pink-800',     outline: 'border border-pink-300 text-pink-700 bg-transparent' },
    indigo:  { solid: 'bg-indigo-100 text-indigo-800', outline: 'border border-indigo-300 text-indigo-700 bg-transparent' },
    gray:    { solid: 'bg-gray-100 text-gray-800',     outline: 'border border-gray-300 text-gray-700 bg-transparent' },
    teal:    { solid: 'bg-teal-100 text-teal-800',     outline: 'border border-teal-300 text-teal-700 bg-transparent' },
    cyan:    { solid: 'bg-cyan-100 text-cyan-800',     outline: 'border border-cyan-300 text-cyan-700 bg-transparent' }
  }

  const colors = colorMap[props.color] || colorMap.blue
  return `${base} ${colors[props.variant]}`
})
</script>
