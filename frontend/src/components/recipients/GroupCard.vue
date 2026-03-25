<template>
  <div class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm hover:shadow-md transition-shadow">
    <!-- Header -->
    <div class="flex items-start justify-between mb-3">
      <div class="flex items-center gap-2.5">
        <div
          :class="['w-3 h-3 rounded-full', colorDot]"
        />
        <h3 class="text-base font-semibold text-gray-900">{{ group.name }}</h3>
      </div>
      <Badge
        :text="typeLabel"
        :color="typeColor"
        variant="outline"
      />
    </div>

    <!-- Description -->
    <p v-if="group.description" class="text-sm text-gray-500 mb-4 line-clamp-2">
      {{ group.description }}
    </p>

    <!-- Member count -->
    <div class="flex items-center gap-1.5 mb-4">
      <Users class="w-4 h-4 text-gray-400" />
      <span class="text-sm text-gray-600">
        {{ group.member_count || 0 }} membre{{ (group.member_count || 0) !== 1 ? 's' : '' }}
      </span>
    </div>

    <!-- Actions -->
    <div class="flex items-center gap-2 pt-3 border-t border-gray-100">
      <button
        @click="$emit('view-members', group)"
        class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-1.5 text-xs font-medium
               text-blue-600 bg-blue-50 rounded-lg hover:bg-blue-100 transition-colors"
      >
        <Eye class="w-3.5 h-3.5" />
        Voir
      </button>
      <button
        @click="$emit('edit', group)"
        class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-1.5 text-xs font-medium
               text-gray-600 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors"
      >
        <Pencil class="w-3.5 h-3.5" />
        Modifier
      </button>
      <button
        @click="$emit('delete', group)"
        class="inline-flex items-center justify-center p-1.5 text-red-500 bg-red-50 rounded-lg
               hover:bg-red-100 transition-colors"
      >
        <Trash2 class="w-3.5 h-3.5" />
      </button>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { Users, Eye, Pencil, Trash2 } from 'lucide-vue-next'
import Badge from '../ui/Badge.vue'

const props = defineProps({
  group: {
    type: Object,
    required: true
  }
})

defineEmits(['edit', 'delete', 'view-members'])

const colorDot = computed(() => {
  const colors = {
    blue: 'bg-blue-500',
    green: 'bg-green-500',
    red: 'bg-red-500',
    orange: 'bg-orange-500',
    purple: 'bg-purple-500',
    yellow: 'bg-yellow-500',
    teal: 'bg-teal-500',
    pink: 'bg-pink-500',
    indigo: 'bg-indigo-500'
  }
  return colors[props.group.color] || 'bg-blue-500'
})

const typeLabel = computed(() => {
  const types = {
    department: 'Service',
    team: 'Équipe',
    custom: 'Personnalisé',
    category: 'Catégorie'
  }
  return types[props.group.type] || props.group.type || 'Groupe'
})

const typeColor = computed(() => {
  const colors = {
    department: 'blue',
    team: 'green',
    custom: 'purple',
    category: 'orange'
  }
  return colors[props.group.type] || 'gray'
})
</script>
