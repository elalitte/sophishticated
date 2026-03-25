<template>
  <AppLayout>
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
      <div class="flex items-center gap-3">
        <h1 class="text-xl font-bold text-gray-800">Destinataires</h1>
        <span
          v-if="!loading"
          class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-brand-100 text-brand-700"
        >
          {{ pagination.total }} actifs
        </span>
      </div>
      <button
        @click="handleSync"
        :disabled="syncing"
        class="flex items-center gap-2 px-4 py-2 bg-brand-500 hover:bg-brand-600 text-white text-sm font-medium rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
      >
        <RefreshCw class="w-4 h-4" :class="{ 'animate-spin': syncing }" />
        {{ syncing ? 'Synchronisation...' : 'Synchroniser depuis Office 365' }}
      </button>
    </div>

    <!-- Filters bar -->
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 mb-4">
      <div class="flex flex-wrap items-center gap-3">
        <!-- Search -->
        <div class="relative flex-1 min-w-[240px]">
          <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
          <input
            v-model="filters.search"
            type="text"
            placeholder="Rechercher par nom, email..."
            class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-brand-500 focus:border-brand-500 outline-none"
            @input="debouncedFetch"
          />
        </div>

        <!-- Group filter (multi-select dropdown) -->
        <div class="relative" ref="groupDropdownRef">
          <button
            @click="showGroupDropdown = !showGroupDropdown"
            class="flex items-center gap-2 px-3 py-2 border border-gray-300 rounded-lg text-sm hover:bg-gray-50 transition-colors"
          >
            <Filter class="w-4 h-4 text-gray-400" />
            <span class="text-gray-700">
              {{ filters.groups.length > 0 ? `${filters.groups.length} groupe(s)` : 'Groupes' }}
            </span>
            <ChevronDown class="w-3 h-3 text-gray-400" />
          </button>
          <Transition name="fade">
            <div
              v-if="showGroupDropdown"
              class="absolute top-full left-0 mt-1 w-60 bg-white rounded-lg shadow-lg border border-gray-200 z-20 py-2 max-h-64 overflow-y-auto"
            >
              <label
                v-for="group in availableGroups"
                :key="group.id"
                class="flex items-center gap-2 px-3 py-1.5 hover:bg-gray-50 cursor-pointer"
              >
                <input
                  type="checkbox"
                  :value="group.id"
                  v-model="filters.groups"
                  class="rounded border-gray-300 text-brand-500 focus:ring-brand-500"
                  @change="fetchRecipients"
                />
                <span class="text-sm text-gray-700">{{ group.name }}</span>
              </label>
              <p v-if="availableGroups.length === 0" class="px-3 py-2 text-sm text-gray-500 italic">
                Aucun groupe
              </p>
            </div>
          </Transition>
        </div>

        <!-- Department filter -->
        <select
          v-model="filters.department"
          @change="fetchRecipients"
          class="px-3 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 focus:ring-2 focus:ring-brand-500 focus:border-brand-500 outline-none"
        >
          <option value="">Tous les departements</option>
          <option v-for="dept in departments" :key="dept" :value="dept">{{ dept }}</option>
        </select>

        <!-- Status toggle -->
        <div class="flex items-center gap-2 px-3 py-2 border border-gray-300 rounded-lg">
          <span class="text-sm text-gray-600">Actifs uniquement</span>
          <button
            @click="filters.activeOnly = !filters.activeOnly; fetchRecipients()"
            :class="[
              'relative inline-flex h-5 w-9 items-center rounded-full transition-colors',
              filters.activeOnly ? 'bg-brand-500' : 'bg-gray-300'
            ]"
          >
            <span
              :class="[
                'inline-block h-3.5 w-3.5 transform rounded-full bg-white transition-transform shadow-sm',
                filters.activeOnly ? 'translate-x-[18px]' : 'translate-x-1'
              ]"
            />
          </button>
        </div>

        <!-- Clear filters -->
        <button
          v-if="hasActiveFilters"
          @click="clearFilters"
          class="flex items-center gap-1 px-3 py-2 text-sm text-gray-500 hover:text-gray-700 transition-colors"
        >
          <X class="w-3.5 h-3.5" />
          Effacer
        </button>
      </div>
    </div>

    <!-- Data table -->
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
      <DataTable
        :columns="columns"
        :data="recipients"
        :loading="loading"
        @sort="handleSort"
      >
        <!-- Name cell -->
        <template #cell-name="{ row }">
          <router-link
            :to="`/recipients/${row.id}`"
            class="font-medium text-gray-800 hover:text-brand-500 transition-colors"
          >
            {{ row.full_name || row.name }}
          </router-link>
        </template>

        <!-- Email cell -->
        <template #cell-email="{ row }">
          <span class="text-gray-600">{{ row.email }}</span>
        </template>

        <!-- Department cell -->
        <template #cell-department="{ row }">
          <span class="text-gray-600">{{ row.department || '-' }}</span>
        </template>

        <!-- Job title cell -->
        <template #cell-job_title="{ row }">
          <span class="text-gray-600">{{ row.job_title || '-' }}</span>
        </template>

        <!-- Groups cell -->
        <template #cell-groups="{ row }">
          <div class="flex flex-wrap gap-1">
            <span
              v-for="group in (row.groups || [])"
              :key="group.id"
              class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium"
              :style="{
                backgroundColor: (group.color || '#6B7280') + '20',
                color: group.color || '#6B7280'
              }"
            >
              {{ group.name }}
            </span>
            <span v-if="!row.groups || row.groups.length === 0" class="text-gray-400 text-xs">-</span>
          </div>
        </template>

        <!-- Active status cell -->
        <template #cell-is_active="{ row }">
          <button
            @click="toggleActive(row)"
            :class="[
              'relative inline-flex h-5 w-9 items-center rounded-full transition-colors',
              row.is_active ? 'bg-green-500' : 'bg-gray-300'
            ]"
            :title="row.is_active ? 'Actif' : 'Inactif'"
          >
            <span
              :class="[
                'inline-block h-3.5 w-3.5 transform rounded-full bg-white transition-transform shadow-sm',
                row.is_active ? 'translate-x-[18px]' : 'translate-x-1'
              ]"
            />
          </button>
        </template>

        <!-- Last sync cell -->
        <template #cell-last_sync="{ row }">
          <span class="text-gray-500 text-xs">{{ formatDate(row.last_sync) }}</span>
        </template>
      </DataTable>

      <!-- Pagination -->
      <div
        v-if="pagination.totalPages > 1"
        class="flex items-center justify-between px-5 py-3 border-t border-gray-200 bg-gray-50"
      >
        <p class="text-sm text-gray-500">
          Affichage de {{ pagination.from }} a {{ pagination.to }} sur {{ pagination.total }}
        </p>
        <div class="flex items-center gap-1">
          <button
            @click="goToPage(pagination.page - 1)"
            :disabled="pagination.page <= 1"
            class="p-1.5 rounded-md hover:bg-gray-200 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
          >
            <ChevronLeft class="w-4 h-4" />
          </button>
          <template v-for="p in paginationPages" :key="p">
            <button
              v-if="p !== '...'"
              @click="goToPage(p)"
              :class="[
                'w-8 h-8 rounded-md text-sm font-medium transition-colors',
                p === pagination.page
                  ? 'bg-brand-500 text-white'
                  : 'hover:bg-gray-200 text-gray-600'
              ]"
            >
              {{ p }}
            </button>
            <span v-else class="px-1 text-gray-400">...</span>
          </template>
          <button
            @click="goToPage(pagination.page + 1)"
            :disabled="pagination.page >= pagination.totalPages"
            class="p-1.5 rounded-md hover:bg-gray-200 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
          >
            <ChevronRight class="w-4 h-4" />
          </button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useApi } from '@/composables/useApi'
import { useNotificationStore } from '@/stores/notifications'
import AppLayout from '@/layouts/AppLayout.vue'
import DataTable from '@/components/ui/DataTable.vue'
import {
  Search,
  Filter,
  RefreshCw,
  ChevronDown,
  ChevronLeft,
  ChevronRight,
  X
} from 'lucide-vue-next'

const api = useApi()
const notifications = useNotificationStore()

const loading = ref(true)
const syncing = ref(false)
const recipients = ref([])
const availableGroups = ref([])
const departments = ref([])
const showGroupDropdown = ref(false)
const groupDropdownRef = ref(null)

let searchTimeout = null

const filters = reactive({
  search: '',
  groups: [],
  department: '',
  activeOnly: true
})

const pagination = reactive({
  page: 1,
  perPage: 20,
  total: 0,
  totalPages: 0,
  from: 0,
  to: 0
})

const columns = [
  { key: 'name', label: 'Nom', sortable: true },
  { key: 'email', label: 'Email', sortable: true },
  { key: 'department', label: 'Departement', sortable: true },
  { key: 'job_title', label: 'Poste', sortable: false },
  { key: 'groups', label: 'Groupes', sortable: false },
  { key: 'is_active', label: 'Statut', sortable: false },
  { key: 'last_sync', label: 'Dern. sync', sortable: true }
]

let sortKey = ''
let sortDir = 'asc'

const hasActiveFilters = computed(() => {
  return filters.search || filters.groups.length > 0 || filters.department || !filters.activeOnly
})

const paginationPages = computed(() => {
  const total = pagination.totalPages
  const current = pagination.page
  const pages = []

  if (total <= 7) {
    for (let i = 1; i <= total; i++) pages.push(i)
    return pages
  }

  pages.push(1)
  if (current > 3) pages.push('...')

  const start = Math.max(2, current - 1)
  const end = Math.min(total - 1, current + 1)

  for (let i = start; i <= end; i++) pages.push(i)

  if (current < total - 2) pages.push('...')
  pages.push(total)

  return pages
})

function debouncedFetch() {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    pagination.page = 1
    fetchRecipients()
  }, 300)
}

async function fetchRecipients() {
  loading.value = true
  try {
    const params = new URLSearchParams()
    params.set('page', pagination.page)
    params.set('per_page', pagination.perPage)

    if (filters.search) params.set('search', filters.search)
    if (filters.department) params.set('department', filters.department)
    if (filters.activeOnly) params.set('active_only', '1')
    if (filters.groups.length > 0) params.set('groups', filters.groups.join(','))
    if (sortKey) {
      params.set('sort', sortKey)
      params.set('dir', sortDir)
    }

    const data = await api.get(`/api/recipients?${params}`)

    recipients.value = data.recipients || data.data || []
    pagination.total = data.total ?? 0
    pagination.totalPages = data.total_pages ?? Math.ceil(pagination.total / pagination.perPage)
    pagination.from = ((pagination.page - 1) * pagination.perPage) + 1
    pagination.to = Math.min(pagination.page * pagination.perPage, pagination.total)
  } catch {
    // Error handled by useApi
  } finally {
    loading.value = false
  }
}

async function fetchGroups() {
  try {
    const data = await api.get('/api/recipients/groups')
    availableGroups.value = data.data || data.groups || data || []
  } catch {
    // Graceful fallback
  }
}

async function fetchDepartments() {
  try {
    const data = await api.get('/api/recipients/departments')
    departments.value = data.departments || data || []
  } catch {
    // Graceful fallback
  }
}

function handleSort(key, dir) {
  sortKey = key
  sortDir = dir
  fetchRecipients()
}

function goToPage(page) {
  if (page < 1 || page > pagination.totalPages) return
  pagination.page = page
  fetchRecipients()
}

async function toggleActive(recipient) {
  try {
    await api.put(`/api/recipients/${recipient.id}`, {
      is_active: !recipient.is_active
    })
    recipient.is_active = !recipient.is_active
    notifications.success(
      recipient.is_active ? 'Destinataire active' : 'Destinataire desactive'
    )
  } catch {
    // Error handled by useApi
  }
}

async function handleSync() {
  syncing.value = true
  try {
    await api.post('/api/recipients/sync')
    notifications.success('Synchronisation terminee')
    await fetchRecipients()
    await fetchGroups()
    await fetchDepartments()
  } catch {
    // Error handled by useApi
  } finally {
    syncing.value = false
  }
}

function clearFilters() {
  filters.search = ''
  filters.groups = []
  filters.department = ''
  filters.activeOnly = true
  pagination.page = 1
  fetchRecipients()
}

function formatDate(dateStr) {
  if (!dateStr) return '-'
  return new Date(dateStr).toLocaleDateString('fr-FR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric'
  })
}

// Close group dropdown on outside click
function handleClickOutside(event) {
  if (groupDropdownRef.value && !groupDropdownRef.value.contains(event.target)) {
    showGroupDropdown.value = false
  }
}

onMounted(() => {
  fetchRecipients()
  fetchGroups()
  fetchDepartments()
  document.addEventListener('click', handleClickOutside)
})
</script>
