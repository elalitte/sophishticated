<template>
  <div class="relative">
    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
      <Search class="w-4 h-4 text-gray-400" />
    </div>
    <input
      type="text"
      :value="modelValue"
      @input="onInput"
      :placeholder="placeholder"
      class="block w-full pl-9 pr-9 py-2 text-sm border border-gray-300 rounded-lg
             bg-white placeholder-gray-400
             focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500
             transition-colors"
    />
    <button
      v-if="modelValue"
      @click="clear"
      class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600"
    >
      <X class="w-4 h-4" />
    </button>
  </div>
</template>

<script setup>
import { ref, onBeforeUnmount } from 'vue'
import { Search, X } from 'lucide-vue-next'

defineProps({
  modelValue: {
    type: String,
    default: ''
  },
  placeholder: {
    type: String,
    default: 'Rechercher...'
  }
})

const emit = defineEmits(['update:modelValue'])

let debounceTimer = null

function onInput(event) {
  const value = event.target.value
  if (debounceTimer) clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => {
    emit('update:modelValue', value)
  }, 300)
}

function clear() {
  if (debounceTimer) clearTimeout(debounceTimer)
  emit('update:modelValue', '')
}

onBeforeUnmount(() => {
  if (debounceTimer) clearTimeout(debounceTimer)
})
</script>
