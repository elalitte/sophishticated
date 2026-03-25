<template>
  <AppLayout>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Pages d'atterrissage</h1>
        <p class="mt-1 text-sm text-gray-500">Pages de phishing et de sensibilisation</p>
      </div>
      <router-link
        to="/templates/pages/create"
        class="inline-flex items-center gap-2 px-4 py-2.5 bg-brand-500 text-white rounded-lg hover:bg-brand-600 transition-colors font-medium text-sm"
      >
        <Plus class="w-4 h-4" />
        Nouvelle page
      </router-link>
    </div>

    <!-- Filters -->
    <div class="flex flex-wrap items-center gap-3 p-4 bg-white rounded-lg border border-gray-200">
      <div class="flex items-center gap-2">
        <Search class="w-4 h-4 text-gray-400" />
      </div>
      <input
        v-model="searchQuery"
        type="text"
        placeholder="Rechercher une page..."
        class="flex-1 min-w-[200px] px-3 py-1.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-400 focus:border-brand-400"
      />
      <select
        v-model="statusFilter"
        class="px-3 py-1.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-400 focus:border-brand-400"
      >
        <option value="">Tous les statuts</option>
        <option value="active">Actif</option>
        <option value="inactive">Inactif</option>
      </select>
    </div>

    <!-- Loading skeleton -->
    <div v-if="loading" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
      <div v-for="i in 6" :key="i" class="bg-white rounded-xl border border-gray-200 p-5 animate-pulse">
        <div class="h-5 bg-gray-200 rounded w-3/4 mb-3" />
        <div class="h-4 bg-gray-200 rounded w-full mb-2" />
        <div class="h-4 bg-gray-200 rounded w-1/2 mb-4" />
        <div class="h-4 bg-gray-200 rounded w-1/3" />
      </div>
    </div>

    <!-- Empty state -->
    <div v-else-if="filteredPages.length === 0" class="text-center py-16 bg-white rounded-xl border border-gray-200">
      <Globe class="w-12 h-12 text-gray-300 mx-auto mb-3" />
      <h3 class="text-lg font-medium text-gray-900 mb-1">Aucune page trouvée</h3>
      <p class="text-sm text-gray-500 mb-4">
        {{ searchQuery || statusFilter ? 'Modifiez vos critères de recherche.' : 'Créez votre première page d\'atterrissage.' }}
      </p>
      <router-link
        v-if="!searchQuery && !statusFilter"
        to="/templates/pages/create"
        class="inline-flex items-center gap-2 px-4 py-2 bg-brand-500 text-white rounded-lg hover:bg-brand-600 text-sm font-medium"
      >
        <Plus class="w-4 h-4" />
        Créer une page
      </router-link>
    </div>

    <!-- Pages grid -->
    <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
      <div
        v-for="page in filteredPages"
        :key="page.id"
        class="bg-white rounded-xl border border-gray-200 hover:shadow-md transition-shadow group"
      >
        <div class="p-5">
          <div class="flex items-start justify-between mb-2">
            <h3 class="font-semibold text-gray-900 group-hover:text-brand-500 transition-colors line-clamp-1">
              {{ page.name }}
            </h3>
            <Badge
              :text="page.is_active ? 'Actif' : 'Inactif'"
              :color="page.is_active ? 'green' : 'gray'"
            />
          </div>

          <p v-if="page.description" class="text-sm text-gray-500 mb-3 line-clamp-2">
            {{ page.description }}
          </p>

          <div class="flex items-center gap-4 text-sm text-gray-500">
            <div class="flex items-center gap-1">
              <FormInput class="w-3.5 h-3.5" />
              <span>{{ page.capture_fields_count ?? (page.capture_fields || []).length }} champ{{ (page.capture_fields_count ?? (page.capture_fields || []).length) !== 1 ? 's' : '' }}</span>
            </div>
          </div>
        </div>

        <!-- Actions -->
        <div class="flex items-center justify-between px-5 py-3 border-t border-gray-100 bg-gray-50/50 rounded-b-xl">
          <div class="flex items-center gap-1">
            <router-link
              :to="`/templates/pages/${page.id}/edit`"
              class="p-1.5 text-gray-400 hover:text-brand-600 hover:bg-brand-50 rounded-lg transition-colors"
              title="Modifier"
            >
              <Pencil class="w-4 h-4" />
            </router-link>
            <button
              @click="duplicatePage(page)"
              class="p-1.5 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors"
              title="Dupliquer"
            >
              <Copy class="w-4 h-4" />
            </button>
            <button
              @click="confirmDelete(page)"
              class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors"
              title="Supprimer"
            >
              <Trash2 class="w-4 h-4" />
            </button>
          </div>
          <button
            @click="toggleActive(page)"
            :class="[
              'relative inline-flex h-5 w-9 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200',
              page.is_active ? 'bg-brand-500' : 'bg-gray-200'
            ]"
          >
            <span
              :class="[
                'pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow ring-0 transition duration-200',
                page.is_active ? 'translate-x-4' : 'translate-x-0'
              ]"
            />
          </button>
        </div>
      </div>
    </div>

    <!-- Delete dialog -->
    <ConfirmDialog
      :show="showDeleteDialog"
      title="Supprimer la page"
      :message="`Êtes-vous sûr de vouloir supprimer la page « ${pageToDelete?.name} » ? Cette action est irréversible.`"
      confirm-text="Supprimer"
      variant="danger"
      @confirm="deletePage"
      @cancel="showDeleteDialog = false"
    />
  </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import { Plus, Search, Globe, Pencil, Copy, Trash2, FormInput } from 'lucide-vue-next'
import { useApi } from '../../composables/useApi'
import { useNotificationStore } from '../../stores/notifications'
import Badge from '../../components/ui/Badge.vue'
import ConfirmDialog from '../../components/ui/ConfirmDialog.vue'

const { loading, get, post, del } = useApi()
const notifications = useNotificationStore()

const pages = ref([])
const searchQuery = ref('')
const statusFilter = ref('')
const showDeleteDialog = ref(false)
const pageToDelete = ref(null)

const filteredPages = computed(() => {
  let result = pages.value
  if (searchQuery.value) {
    const q = searchQuery.value.toLowerCase()
    result = result.filter(p =>
      p.name.toLowerCase().includes(q) ||
      (p.description || '').toLowerCase().includes(q)
    )
  }
  if (statusFilter.value) {
    const isActive = statusFilter.value === 'active'
    result = result.filter(p => p.is_active === isActive)
  }
  return result
})

async function fetchPages() {
  try {
    const response = await get('/api/landing-pages')
    pages.value = response.data || response || []
  } catch {
    // Error handled by useApi
  }
}

async function duplicatePage(page) {
  try {
    await post(`/api/landing-pages/${page.id}/duplicate`)
    notifications.success('Page dupliquée avec succès')
    await fetchPages()
  } catch {
    // Error handled by useApi
  }
}

function confirmDelete(page) {
  pageToDelete.value = page
  showDeleteDialog.value = true
}

async function deletePage() {
  if (!pageToDelete.value) return
  try {
    await del(`/api/landing-pages/${pageToDelete.value.id}`)
    notifications.success('Page supprimée')
    showDeleteDialog.value = false
    pageToDelete.value = null
    await fetchPages()
  } catch {
    // Error handled by useApi
  }
}

async function toggleActive(page) {
  try {
    await post(`/api/landing-pages/${page.id}/toggle-active`)
    page.is_active = !page.is_active
    notifications.success(page.is_active ? 'Page activée' : 'Page désactivée')
  } catch {
    // Error handled by useApi
  }
}

onMounted(fetchPages)
</script>
