<template>
  <DataTable
    :columns="columns"
    :data="recipients"
    :loading="loading"
    @sort="(key, dir) => $emit('sort', key, dir)"
  >
    <!-- Name cell -->
    <template #cell-name="{ row }">
      <button
        @click="$emit('view-detail', row)"
        class="text-sm font-medium text-blue-600 hover:text-blue-800 hover:underline"
      >
        {{ row.first_name }} {{ row.last_name }}
      </button>
    </template>

    <!-- Email cell -->
    <template #cell-email="{ row }">
      <span class="text-sm text-gray-600">{{ row.email }}</span>
    </template>

    <!-- Department cell -->
    <template #cell-department="{ row }">
      <span class="text-sm text-gray-700">{{ row.department || '—' }}</span>
    </template>

    <!-- Groups cell -->
    <template #cell-groups="{ row }">
      <div class="flex flex-wrap gap-1">
        <Badge
          v-for="group in (row.groups || [])"
          :key="group.id"
          :text="group.name"
          :color="group.color || 'blue'"
          variant="solid"
        />
        <span v-if="!row.groups || row.groups.length === 0" class="text-xs text-gray-400">
          Aucun groupe
        </span>
      </div>
    </template>

    <!-- Status cell -->
    <template #cell-status="{ row }">
      <button
        @click="$emit('toggle-active', row)"
        :class="[
          'inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium transition-colors cursor-pointer',
          row.is_active
            ? 'bg-green-100 text-green-700 hover:bg-green-200'
            : 'bg-gray-100 text-gray-500 hover:bg-gray-200'
        ]"
      >
        <span :class="['w-1.5 h-1.5 rounded-full', row.is_active ? 'bg-green-500' : 'bg-gray-400']" />
        {{ row.is_active ? 'Actif' : 'Inactif' }}
      </button>
    </template>

    <!-- Last sync cell -->
    <template #cell-last_sync="{ row }">
      <span class="text-xs text-gray-500">
        {{ row.last_sync ? formatDate(row.last_sync) : 'Jamais' }}
      </span>
    </template>
  </DataTable>
</template>

<script setup>
import DataTable from '../ui/DataTable.vue'
import Badge from '../ui/Badge.vue'

defineProps({
  recipients: {
    type: Array,
    default: () => []
  },
  loading: {
    type: Boolean,
    default: false
  }
})

defineEmits(['toggle-active', 'view-detail', 'sort'])

const columns = [
  { key: 'name', label: 'Nom', sortable: true },
  { key: 'email', label: 'Email', sortable: true },
  { key: 'department', label: 'Service', sortable: true },
  { key: 'groups', label: 'Groupes', sortable: false },
  { key: 'status', label: 'Statut', sortable: true },
  { key: 'last_sync', label: 'Dernière synchro', sortable: true }
]

function formatDate(dateStr) {
  const d = new Date(dateStr)
  return new Intl.DateTimeFormat('fr-FR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  }).format(d)
}
</script>
