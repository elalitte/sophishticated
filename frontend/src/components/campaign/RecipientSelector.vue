<template>
  <div class="space-y-4">
    <!-- Tabs -->
    <div class="flex border-b border-gray-200">
      <button
        v-for="tab in tabs"
        :key="tab.key"
        @click="activeTab = tab.key"
        :class="[
          'px-4 py-2.5 text-sm font-medium border-b-2 transition-colors -mb-px',
          activeTab === tab.key
            ? 'border-blue-600 text-blue-600'
            : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
        ]"
      >
        <component :is="tab.icon" class="w-4 h-4 inline-block mr-1.5 -mt-0.5" />
        {{ tab.label }}
      </button>
    </div>

    <!-- Tab: Individuel -->
    <div v-if="activeTab === 'individual'" class="space-y-3">
      <SearchInput v-model="individualSearch" placeholder="Rechercher par nom ou email..." />
      <div class="max-h-48 overflow-y-auto border border-gray-200 rounded-lg divide-y divide-gray-100">
        <label
          v-for="recipient in filteredIndividuals"
          :key="recipient.id"
          class="flex items-center gap-3 px-3 py-2 hover:bg-gray-50 cursor-pointer"
        >
          <input
            type="checkbox"
            :checked="isSelected(recipient.id)"
            @change="toggleRecipient(recipient.id)"
            class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
          />
          <div class="flex-1 min-w-0">
            <p class="text-sm font-medium text-gray-900 truncate">{{ recipient.name }}</p>
            <p class="text-xs text-gray-500 truncate">{{ recipient.email }}</p>
          </div>
        </label>
        <div v-if="filteredIndividuals.length === 0" class="px-3 py-6 text-center text-sm text-gray-500">
          Aucun résultat trouvé
        </div>
      </div>
    </div>

    <!-- Tab: Par équipe -->
    <div v-if="activeTab === 'team'" class="space-y-3">
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
        <label
          v-for="team in teams"
          :key="team.id"
          class="flex items-center gap-3 px-3 py-2.5 border border-gray-200 rounded-lg hover:bg-gray-50 cursor-pointer"
        >
          <input
            type="checkbox"
            :checked="isTeamSelected(team)"
            @change="toggleTeam(team)"
            class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
          />
          <div class="flex-1 min-w-0">
            <p class="text-sm font-medium text-gray-900">{{ team.name }}</p>
            <p class="text-xs text-gray-500">{{ team.members.length }} membres</p>
          </div>
          <Users class="w-4 h-4 text-gray-400" />
        </label>
      </div>
    </div>

    <!-- Tab: Par catégorie -->
    <div v-if="activeTab === 'category'" class="space-y-3">
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
        <label
          v-for="category in categories"
          :key="category.id"
          class="flex items-center gap-3 px-3 py-2.5 border border-gray-200 rounded-lg hover:bg-gray-50 cursor-pointer"
        >
          <input
            type="checkbox"
            :checked="isCategorySelected(category)"
            @change="toggleCategory(category)"
            class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
          />
          <div class="flex-1 min-w-0">
            <p class="text-sm font-medium text-gray-900">{{ category.name }}</p>
            <p class="text-xs text-gray-500">{{ category.members.length }} membres</p>
          </div>
          <Tag class="w-4 h-4 text-gray-400" />
        </label>
      </div>
    </div>

    <!-- Deduplicated count + preview -->
    <div class="bg-blue-50 border border-blue-200 rounded-lg p-3">
      <div class="flex items-center justify-between mb-2">
        <span class="text-sm font-medium text-blue-800">
          <UserCheck class="w-4 h-4 inline-block mr-1 -mt-0.5" />
          {{ selectedIds.length }} destinataire{{ selectedIds.length !== 1 ? 's' : '' }} sélectionné{{ selectedIds.length !== 1 ? 's' : '' }}
        </span>
        <button
          v-if="selectedIds.length > 0"
          @click="clearAll"
          class="text-xs text-blue-600 hover:text-blue-800 font-medium"
        >
          Tout effacer
        </button>
      </div>

      <!-- Preview list -->
      <div v-if="selectedIds.length > 0" class="flex flex-wrap gap-1.5 max-h-24 overflow-y-auto">
        <span
          v-for="id in selectedIds"
          :key="id"
          class="inline-flex items-center gap-1 px-2 py-0.5 bg-white border border-blue-200 rounded-full text-xs text-blue-800"
        >
          {{ getRecipientName(id) }}
          <button @click="removeRecipient(id)" class="hover:text-red-600">
            <X class="w-3 h-3" />
          </button>
        </span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, inject } from 'vue'
import { Users, Tag, UserCheck, X, User, FolderOpen } from 'lucide-vue-next'
import SearchInput from '../ui/SearchInput.vue'

const props = defineProps({
  modelValue: {
    type: Array,
    default: () => []
  }
})

const emit = defineEmits(['update:modelValue'])

const activeTab = ref('individual')
const individualSearch = ref('')

const tabs = [
  { key: 'individual', label: 'Individuel', icon: User },
  { key: 'team', label: 'Par équipe', icon: Users },
  { key: 'category', label: 'Par catégorie', icon: FolderOpen }
]

// These would normally come from a store or API
const allRecipients = inject('allRecipients', ref([]))
const teams = inject('teams', ref([]))
const categories = inject('categories', ref([]))

const selectedIds = computed(() => [...new Set(props.modelValue)])

const filteredIndividuals = computed(() => {
  if (!individualSearch.value) return allRecipients.value
  const query = individualSearch.value.toLowerCase()
  return allRecipients.value.filter(r =>
    r.name.toLowerCase().includes(query) || r.email.toLowerCase().includes(query)
  )
})

function isSelected(id) {
  return props.modelValue.includes(id)
}

function toggleRecipient(id) {
  const current = [...props.modelValue]
  const index = current.indexOf(id)
  if (index === -1) {
    current.push(id)
  } else {
    current.splice(index, 1)
  }
  emit('update:modelValue', [...new Set(current)])
}

function removeRecipient(id) {
  emit('update:modelValue', props.modelValue.filter(rid => rid !== id))
}

function isTeamSelected(team) {
  return team.members.every(id => props.modelValue.includes(id))
}

function toggleTeam(team) {
  const current = new Set(props.modelValue)
  if (isTeamSelected(team)) {
    team.members.forEach(id => current.delete(id))
  } else {
    team.members.forEach(id => current.add(id))
  }
  emit('update:modelValue', [...current])
}

function isCategorySelected(category) {
  return category.members.every(id => props.modelValue.includes(id))
}

function toggleCategory(category) {
  const current = new Set(props.modelValue)
  if (isCategorySelected(category)) {
    category.members.forEach(id => current.delete(id))
  } else {
    category.members.forEach(id => current.add(id))
  }
  emit('update:modelValue', [...current])
}

function getRecipientName(id) {
  const r = allRecipients.value.find(r => r.id === id)
  return r ? r.name : `#${id}`
}

function clearAll() {
  emit('update:modelValue', [])
}
</script>
