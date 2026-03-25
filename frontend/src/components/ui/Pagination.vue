<template>
  <div class="flex items-center justify-between px-2 py-3">
    <div class="text-sm text-gray-600">
      <span v-if="totalItems">
        {{ startItem }}–{{ endItem }} sur {{ totalItems }} résultats
      </span>
    </div>

    <nav class="flex items-center gap-1">
      <!-- Previous -->
      <button
        @click="changePage(currentPage - 1)"
        :disabled="currentPage <= 1"
        class="inline-flex items-center px-2 py-1.5 text-sm font-medium rounded-md transition-colors
               disabled:opacity-40 disabled:cursor-not-allowed
               text-gray-600 hover:bg-gray-100"
      >
        <ChevronLeft class="w-4 h-4" />
        <span class="hidden sm:inline ml-1">Précédent</span>
      </button>

      <!-- Page numbers -->
      <template v-for="page in visiblePages" :key="page">
        <span
          v-if="page === '...'"
          class="px-2 py-1.5 text-sm text-gray-400"
        >...</span>
        <button
          v-else
          @click="changePage(page)"
          :class="[
            'px-3 py-1.5 text-sm font-medium rounded-md transition-colors',
            page === currentPage
              ? 'bg-blue-600 text-white'
              : 'text-gray-600 hover:bg-gray-100'
          ]"
        >
          {{ page }}
        </button>
      </template>

      <!-- Next -->
      <button
        @click="changePage(currentPage + 1)"
        :disabled="currentPage >= totalPages"
        class="inline-flex items-center px-2 py-1.5 text-sm font-medium rounded-md transition-colors
               disabled:opacity-40 disabled:cursor-not-allowed
               text-gray-600 hover:bg-gray-100"
      >
        <span class="hidden sm:inline mr-1">Suivant</span>
        <ChevronRight class="w-4 h-4" />
      </button>
    </nav>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { ChevronLeft, ChevronRight } from 'lucide-vue-next'

const props = defineProps({
  currentPage: {
    type: Number,
    required: true
  },
  totalPages: {
    type: Number,
    required: true
  },
  totalItems: {
    type: Number,
    default: 0
  },
  perPage: {
    type: Number,
    default: 20
  }
})

const emit = defineEmits(['page-change'])

const startItem = computed(() => (props.currentPage - 1) * props.perPage + 1)
const endItem = computed(() => Math.min(props.currentPage * props.perPage, props.totalItems))

const visiblePages = computed(() => {
  const total = props.totalPages
  const current = props.currentPage
  if (total <= 7) {
    return Array.from({ length: total }, (_, i) => i + 1)
  }

  const pages = []
  pages.push(1)

  if (current > 3) {
    pages.push('...')
  }

  const start = Math.max(2, current - 1)
  const end = Math.min(total - 1, current + 1)

  for (let i = start; i <= end; i++) {
    pages.push(i)
  }

  if (current < total - 2) {
    pages.push('...')
  }

  pages.push(total)

  return pages
})

function changePage(page) {
  if (page >= 1 && page <= props.totalPages && page !== props.currentPage) {
    emit('page-change', page)
  }
}
</script>
