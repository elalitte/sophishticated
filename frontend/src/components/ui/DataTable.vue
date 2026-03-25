<template>
  <div class="overflow-x-auto rounded-lg border border-gray-200">
    <table class="min-w-full divide-y divide-gray-200">
      <thead class="bg-gray-50">
        <tr>
          <th
            v-for="col in columns"
            :key="col.key"
            class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider"
            :class="{ 'cursor-pointer select-none hover:bg-gray-100': col.sortable }"
            @click="col.sortable && toggleSort(col.key)"
          >
            <div class="flex items-center gap-1.5">
              <span>{{ col.label }}</span>
              <template v-if="col.sortable">
                <div class="flex flex-col">
                  <ChevronUp
                    class="w-3 h-3 -mb-0.5"
                    :class="sortKey === col.key && sortDir === 'asc' ? 'text-blue-600' : 'text-gray-300'"
                  />
                  <ChevronDown
                    class="w-3 h-3 -mt-0.5"
                    :class="sortKey === col.key && sortDir === 'desc' ? 'text-blue-600' : 'text-gray-300'"
                  />
                </div>
              </template>
            </div>
          </th>
        </tr>
      </thead>
      <tbody class="bg-white divide-y divide-gray-100">
        <!-- Loading skeleton -->
        <template v-if="loading">
          <tr v-for="i in 5" :key="'skeleton-' + i">
            <td v-for="col in columns" :key="col.key" class="px-4 py-3">
              <div class="h-4 bg-gray-200 rounded animate-pulse" :style="{ width: (50 + Math.random() * 40) + '%' }" />
            </td>
          </tr>
        </template>

        <!-- Empty state -->
        <tr v-else-if="!data || data.length === 0">
          <td :colspan="columns.length" class="px-4 py-12 text-center">
            <div class="flex flex-col items-center gap-2">
              <Inbox class="w-10 h-10 text-gray-300" />
              <p class="text-sm text-gray-500">Aucune donnée à afficher</p>
            </div>
          </td>
        </tr>

        <!-- Data rows -->
        <tr
          v-else
          v-for="(row, index) in data"
          :key="row.id || index"
          class="hover:bg-gray-50 transition-colors"
        >
          <td
            v-for="col in columns"
            :key="col.key"
            class="px-4 py-3 text-sm text-gray-700 whitespace-nowrap"
          >
            <slot :name="'cell-' + col.key" :row="row" :value="row[col.key]" :index="index">
              {{ row[col.key] }}
            </slot>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { ChevronUp, ChevronDown, Inbox } from 'lucide-vue-next'

defineProps({
  columns: {
    type: Array,
    required: true
  },
  data: {
    type: Array,
    default: () => []
  },
  loading: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['sort'])

const sortKey = ref(null)
const sortDir = ref('asc')

function toggleSort(key) {
  if (sortKey.value === key) {
    sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc'
  } else {
    sortKey.value = key
    sortDir.value = 'asc'
  }
  emit('sort', sortKey.value, sortDir.value)
}
</script>
