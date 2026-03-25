<template>
  <AppLayout>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Templates d'email</h1>
        <p class="mt-1 text-sm text-gray-500">Gérez vos modèles de phishing simulé</p>
      </div>
      <router-link
        to="/templates/create"
        class="inline-flex items-center gap-2 px-4 py-2.5 bg-brand-500 text-white rounded-lg hover:bg-brand-600 transition-colors font-medium text-sm"
      >
        <Plus class="w-4 h-4" />
        Nouveau template
      </router-link>
    </div>

    <!-- Filters -->
    <div class="flex flex-wrap items-center gap-3 p-4 bg-white rounded-lg border border-gray-200">
      <div class="flex items-center gap-2">
        <Filter class="w-4 h-4 text-gray-400" />
        <span class="text-sm font-medium text-gray-600">Filtres :</span>
      </div>

      <select
        v-model="filters.difficulty"
        class="px-3 py-1.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-400 focus:border-brand-400"
      >
        <option value="">Toutes les difficultés</option>
        <option v-for="n in 5" :key="n" :value="n">{{ n }} étoile{{ n > 1 ? 's' : '' }}</option>
      </select>

      <select
        v-model="filters.tag"
        class="px-3 py-1.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-400 focus:border-brand-400"
      >
        <option value="">Tous les tags</option>
        <option v-for="tag in availableTags" :key="tag" :value="tag">{{ tag }}</option>
      </select>

      <select
        v-model="filters.status"
        class="px-3 py-1.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-400 focus:border-brand-400"
      >
        <option value="">Tous les statuts</option>
        <option value="active">Actif</option>
        <option value="inactive">Inactif</option>
      </select>

      <button
        v-if="hasActiveFilters"
        @click="clearFilters"
        class="text-sm text-gray-500 hover:text-gray-700 underline"
      >
        Réinitialiser
      </button>
    </div>

    <!-- Loading skeleton -->
    <div v-if="loading" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
      <div v-for="i in 6" :key="i" class="bg-white rounded-xl border border-gray-200 p-5 animate-pulse">
        <div class="h-5 bg-gray-200 rounded w-3/4 mb-3" />
        <div class="h-4 bg-gray-200 rounded w-1/2 mb-4" />
        <div class="flex gap-2 mb-4">
          <div class="h-6 bg-gray-200 rounded-full w-16" />
          <div class="h-6 bg-gray-200 rounded-full w-20" />
        </div>
        <div class="h-4 bg-gray-200 rounded w-2/3" />
      </div>
    </div>

    <!-- Empty state -->
    <div v-else-if="filteredTemplates.length === 0" class="text-center py-16 bg-white rounded-xl border border-gray-200">
      <Mail class="w-12 h-12 text-gray-300 mx-auto mb-3" />
      <h3 class="text-lg font-medium text-gray-900 mb-1">Aucun template trouvé</h3>
      <p class="text-sm text-gray-500 mb-4">
        {{ hasActiveFilters ? 'Essayez de modifier vos filtres.' : 'Commencez par créer votre premier template.' }}
      </p>
      <router-link
        v-if="!hasActiveFilters"
        to="/templates/create"
        class="inline-flex items-center gap-2 px-4 py-2 bg-brand-500 text-white rounded-lg hover:bg-brand-600 text-sm font-medium"
      >
        <Plus class="w-4 h-4" />
        Créer un template
      </router-link>
    </div>

    <!-- Template grid -->
    <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
      <div
        v-for="template in filteredTemplates"
        :key="template.id"
        class="bg-white rounded-xl border border-gray-200 hover:shadow-md transition-shadow group"
      >
        <div class="p-5">
          <!-- Header -->
          <div class="flex items-start justify-between mb-3">
            <h3 class="font-semibold text-gray-900 text-base group-hover:text-brand-500 transition-colors line-clamp-1">
              {{ template.name }}
            </h3>
            <Badge
              :text="template.is_active ? 'Actif' : 'Inactif'"
              :color="template.is_active ? 'green' : 'gray'"
            />
          </div>

          <!-- Difficulty stars -->
          <div class="flex items-center gap-1 mb-3">
            <Star
              v-for="n in 5"
              :key="n"
              class="w-4 h-4"
              :class="n <= template.difficulty_level ? 'text-yellow-400 fill-yellow-400' : 'text-gray-200'"
            />
            <span class="text-xs text-gray-500 ml-1">Difficulté {{ template.difficulty_level }}/5</span>
          </div>

          <!-- Subject & Sender -->
          <p class="text-sm text-gray-600 mb-1 line-clamp-1" :title="template.subject">
            <strong>Objet :</strong> {{ template.subject }}
          </p>
          <p class="text-xs text-gray-400 mb-3 line-clamp-1">
            De : {{ template.sender_name }} &lt;{{ template.sender_email }}&gt;
          </p>

          <!-- Tags -->
          <div class="flex flex-wrap gap-1.5 mb-3 min-h-[28px]">
            <span
              v-for="tag in (template.tags || [])"
              :key="tag"
              class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium bg-brand-50 text-brand-700 border border-brand-200"
            >
              {{ tag }}
            </span>
          </div>

          <!-- Landing page -->
          <div v-if="template.landing_page_name" class="flex items-center gap-1.5 text-sm text-gray-500 mb-4">
            <Globe class="w-3.5 h-3.5" />
            <span class="truncate">{{ template.landing_page_name }}</span>
          </div>
        </div>

        <!-- Actions -->
        <div class="flex items-center justify-between px-5 py-3 border-t border-gray-100 bg-gray-50/50 rounded-b-xl">
          <div class="flex items-center gap-1">
            <router-link
              :to="`/templates/${template.id}/edit`"
              class="p-1.5 text-gray-400 hover:text-brand-600 hover:bg-brand-50 rounded-lg transition-colors"
              title="Modifier"
            >
              <Pencil class="w-4 h-4" />
            </router-link>
            <button
              @click="duplicateTemplate(template)"
              class="p-1.5 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors"
              title="Dupliquer"
            >
              <Copy class="w-4 h-4" />
            </button>
            <button
              @click="confirmDelete(template)"
              class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors"
              title="Supprimer"
            >
              <Trash2 class="w-4 h-4" />
            </button>
          </div>
          <button
            @click="toggleActive(template)"
            :class="[
              'relative inline-flex h-5 w-9 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out',
              template.is_active ? 'bg-brand-500' : 'bg-gray-200'
            ]"
            :title="template.is_active ? 'Désactiver' : 'Activer'"
          >
            <span
              :class="[
                'pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out',
                template.is_active ? 'translate-x-4' : 'translate-x-0'
              ]"
            />
          </button>
        </div>
      </div>
    </div>

    <!-- Delete confirmation -->
    <ConfirmDialog
      :show="showDeleteDialog"
      title="Supprimer le template"
      :message="`Êtes-vous sûr de vouloir supprimer le template « ${templateToDelete?.name} » ? Cette action est irréversible.`"
      confirm-text="Supprimer"
      variant="danger"
      @confirm="deleteTemplate"
      @cancel="showDeleteDialog = false"
    />
  </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import { Plus, Filter, Mail, Star, Globe, Pencil, Copy, Trash2 } from 'lucide-vue-next'
import { useApi } from '../../composables/useApi'
import { useNotificationStore } from '../../stores/notifications'
import Badge from '../../components/ui/Badge.vue'
import ConfirmDialog from '../../components/ui/ConfirmDialog.vue'

const { loading, get, post, del } = useApi()
const notifications = useNotificationStore()

const templates = ref([])
const availableTags = ref([])
const showDeleteDialog = ref(false)
const templateToDelete = ref(null)

const filters = ref({
  difficulty: '',
  tag: '',
  status: ''
})

const hasActiveFilters = computed(() =>
  filters.value.difficulty || filters.value.tag || filters.value.status
)

const filteredTemplates = computed(() => {
  let result = templates.value
  if (filters.value.difficulty) {
    result = result.filter(t => t.difficulty_level === Number(filters.value.difficulty))
  }
  if (filters.value.tag) {
    result = result.filter(t => (t.tags || []).includes(filters.value.tag))
  }
  if (filters.value.status) {
    const isActive = filters.value.status === 'active'
    result = result.filter(t => t.is_active === isActive)
  }
  return result
})

function clearFilters() {
  filters.value = { difficulty: '', tag: '', status: '' }
}

async function fetchTemplates() {
  try {
    const response = await get('/api/templates')
    const raw = response.data || response || []
    // Normalize: parse tags JSON string, cast is_active to bool
    const list = raw.map(t => ({
      ...t,
      tags: typeof t.tags === 'string' ? JSON.parse(t.tags || '[]') : (t.tags || []),
      is_active: !!t.is_active,
    }))
    templates.value = list
    const tagSet = new Set()
    list.forEach(t => {
      const tags = typeof t.tags === 'string' ? JSON.parse(t.tags || '[]') : (t.tags || [])
      tags.forEach(tag => tagSet.add(tag))
    })
    availableTags.value = [...tagSet].sort()
  } catch {
    // Error handled by useApi
  }
}

async function duplicateTemplate(template) {
  try {
    await post(`/api/templates/${template.id}/duplicate`)
    notifications.success('Template dupliqué avec succès')
    await fetchTemplates()
  } catch {
    // Error handled by useApi
  }
}

function confirmDelete(template) {
  templateToDelete.value = template
  showDeleteDialog.value = true
}

async function deleteTemplate() {
  if (!templateToDelete.value) return
  try {
    await del(`/api/templates/${templateToDelete.value.id}`)
    notifications.success('Template supprimé')
    showDeleteDialog.value = false
    templateToDelete.value = null
    await fetchTemplates()
  } catch {
    // Error handled by useApi
  }
}

async function toggleActive(template) {
  try {
    await post(`/api/templates/${template.id}/toggle-active`)
    template.is_active = !template.is_active
    notifications.success(template.is_active ? 'Template activé' : 'Template désactivé')
  } catch {
    // Error handled by useApi
  }
}

onMounted(fetchTemplates)
</script>
