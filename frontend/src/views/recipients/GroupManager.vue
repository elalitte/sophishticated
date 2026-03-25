<template>
  <AppLayout>
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-xl font-bold text-gray-800">Groupes de destinataires</h1>
      <button
        @click="openCreateModal"
        class="flex items-center gap-2 px-4 py-2 bg-brand-500 hover:bg-brand-600 text-white text-sm font-medium rounded-lg transition-colors"
      >
        <Plus class="w-4 h-4" />
        Nouveau groupe
      </button>
    </div>

    <!-- Loading state -->
    <div v-if="loading" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
      <div v-for="i in 6" :key="i" class="bg-white rounded-xl border border-gray-200 p-5">
        <div class="flex items-center gap-3 mb-3">
          <div class="w-4 h-4 rounded-full bg-gray-200 animate-pulse" />
          <div class="h-5 w-32 bg-gray-200 rounded animate-pulse" />
        </div>
        <div class="h-4 w-48 bg-gray-200 rounded animate-pulse mb-2" />
        <div class="h-3 w-20 bg-gray-200 rounded animate-pulse" />
      </div>
    </div>

    <!-- Empty state -->
    <div v-else-if="groups.length === 0" class="bg-white rounded-xl border border-gray-200 p-12 text-center">
      <UsersRound class="w-12 h-12 text-gray-300 mx-auto mb-3" />
      <h3 class="text-lg font-medium text-gray-700 mb-1">Aucun groupe</h3>
      <p class="text-sm text-gray-500 mb-4">Creez votre premier groupe de destinataires</p>
      <button
        @click="openCreateModal"
        class="inline-flex items-center gap-2 px-4 py-2 bg-brand-500 hover:bg-brand-600 text-white text-sm font-medium rounded-lg transition-colors"
      >
        <Plus class="w-4 h-4" />
        Creer un groupe
      </button>
    </div>

    <!-- Groups grid -->
    <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
      <div
        v-for="group in groups"
        :key="group.id"
        class="bg-white rounded-xl border border-gray-200 shadow-sm hover:shadow-md transition-shadow cursor-pointer"
        @click="openGroupDetail(group)"
      >
        <div class="p-5">
          <div class="flex items-center justify-between mb-2">
            <div class="flex items-center gap-2.5">
              <span
                class="w-4 h-4 rounded-full flex-shrink-0"
                :style="{ backgroundColor: group.color || '#6B7280' }"
              />
              <h3 class="font-semibold text-gray-800">{{ group.name }}</h3>
            </div>
            <span
              :class="[
                'inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold uppercase',
                typeBadgeClass(group.type)
              ]"
            >
              {{ typeLabel(group.type) }}
            </span>
          </div>
          <p v-if="group.description" class="text-sm text-gray-500 mb-3 line-clamp-2">
            {{ group.description }}
          </p>
          <div class="flex items-center justify-between">
            <span class="flex items-center gap-1.5 text-sm text-gray-500">
              <Users class="w-4 h-4" />
              {{ group.member_count ?? 0 }} membre{{ (group.member_count ?? 0) !== 1 ? 's' : '' }}
            </span>
            <div class="flex items-center gap-1">
              <button
                @click.stop="openEditModal(group)"
                class="p-1.5 rounded-lg text-gray-400 hover:text-brand-500 hover:bg-brand-50 transition-colors"
                title="Modifier"
              >
                <Pencil class="w-4 h-4" />
              </button>
              <button
                @click.stop="confirmDelete(group)"
                class="p-1.5 rounded-lg text-gray-400 hover:text-red-500 hover:bg-red-50 transition-colors"
                title="Supprimer"
              >
                <Trash2 class="w-4 h-4" />
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Create/Edit Group Modal -->
    <Modal
      :show="showFormModal"
      :title="editingGroup ? 'Modifier le groupe' : 'Nouveau groupe'"
      @close="closeFormModal"
    >
      <form @submit.prevent="handleSaveGroup" class="space-y-4">
        <!-- Name -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Nom du groupe</label>
          <input
            v-model="groupForm.name"
            type="text"
            required
            placeholder="Ex: Equipe IT"
            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-brand-500 focus:border-brand-500 outline-none"
          />
        </div>

        <!-- Type -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Type</label>
          <select
            v-model="groupForm.type"
            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-brand-500 focus:border-brand-500 outline-none"
          >
            <option value="team">Equipe</option>
            <option value="category">Categorie</option>
            <option value="custom">Personnalise</option>
          </select>
        </div>

        <!-- Color -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Couleur</label>
          <div class="flex items-center gap-3">
            <input
              v-model="groupForm.color"
              type="color"
              class="w-10 h-10 rounded-lg border border-gray-300 cursor-pointer"
            />
            <span class="text-sm text-gray-500">{{ groupForm.color }}</span>
            <!-- Preset colors -->
            <div class="flex gap-1.5 ml-2">
              <button
                v-for="preset in colorPresets"
                :key="preset"
                type="button"
                @click="groupForm.color = preset"
                class="w-6 h-6 rounded-full border-2 transition-transform hover:scale-110"
                :class="groupForm.color === preset ? 'border-gray-800 scale-110' : 'border-transparent'"
                :style="{ backgroundColor: preset }"
              />
            </div>
          </div>
        </div>

        <!-- Description -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
          <textarea
            v-model="groupForm.description"
            rows="3"
            placeholder="Description du groupe (optionnel)"
            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-brand-500 focus:border-brand-500 outline-none resize-none"
          />
        </div>
      </form>

      <template #footer>
        <div class="flex justify-end gap-3">
          <button
            @click="closeFormModal"
            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
          >
            Annuler
          </button>
          <button
            @click="handleSaveGroup"
            :disabled="saving || !groupForm.name.trim()"
            class="flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-brand-500 hover:bg-brand-600 rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
          >
            <Loader2 v-if="saving" class="w-4 h-4 animate-spin" />
            {{ editingGroup ? 'Enregistrer' : 'Creer' }}
          </button>
        </div>
      </template>
    </Modal>

    <!-- Group Detail / Members Panel Modal -->
    <Modal
      :show="showDetailModal"
      :title="selectedGroup?.name || 'Membres'"
      max-width="3xl"
      @close="closeDetailModal"
    >
      <div v-if="selectedGroup" class="space-y-4">
        <!-- Group info -->
        <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg">
          <span
            class="w-5 h-5 rounded-full flex-shrink-0"
            :style="{ backgroundColor: selectedGroup.color || '#6B7280' }"
          />
          <div class="flex-1">
            <p class="font-medium text-gray-800">{{ selectedGroup.name }}</p>
            <p v-if="selectedGroup.description" class="text-sm text-gray-500">{{ selectedGroup.description }}</p>
          </div>
          <span
            :class="[
              'inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold uppercase',
              typeBadgeClass(selectedGroup.type)
            ]"
          >
            {{ typeLabel(selectedGroup.type) }}
          </span>
        </div>

        <!-- Add members -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">Ajouter des membres</label>
          <div class="relative">
            <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
            <input
              v-model="memberSearch"
              type="text"
              placeholder="Rechercher par nom ou adresse mail..."
              class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-brand-500 focus:border-brand-500 outline-none"
              @input="debouncedSearchRecipients"
            />
          </div>
          <!-- Search results dropdown -->
          <div
            v-if="searchResults.length > 0"
            class="mt-1 border border-gray-200 rounded-lg max-h-40 overflow-y-auto bg-white shadow-sm"
          >
            <button
              v-for="r in searchResults"
              :key="r.id"
              type="button"
              @click="addMember(r)"
              class="w-full flex items-center gap-3 px-3 py-2 text-sm text-left hover:bg-gray-50 transition-colors"
            >
              <UserPlus class="w-4 h-4 text-brand-500 flex-shrink-0" />
              <div class="min-w-0 flex-1">
                <p class="font-medium text-gray-700 truncate">{{ r.email }}</p>
                <p class="text-xs text-gray-500 truncate">{{ r.full_name || r.display_name || `${r.first_name || ''} ${r.last_name || ''}`.trim() || '-' }}</p>
              </div>
            </button>
          </div>
        </div>

        <!-- Current members list -->
        <div>
          <h4 class="text-sm font-medium text-gray-700 mb-2">
            Membres ({{ groupMembers.length }})
          </h4>
          <div v-if="membersLoading" class="space-y-2">
            <div v-for="i in 3" :key="i" class="h-10 bg-gray-100 rounded animate-pulse" />
          </div>
          <div v-else-if="groupMembers.length === 0" class="text-center py-6">
            <Users class="w-8 h-8 text-gray-300 mx-auto mb-1" />
            <p class="text-sm text-gray-500">Aucun membre dans ce groupe</p>
          </div>
          <div v-else class="max-h-64 overflow-y-auto space-y-1">
            <div
              v-for="member in groupMembers"
              :key="member.id"
              class="flex items-center justify-between p-2.5 rounded-lg hover:bg-gray-50 group"
            >
              <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-full bg-brand-100 flex items-center justify-center text-xs font-bold text-brand-600">
                  {{ memberInitials(member) }}
                </div>
                <div class="min-w-0 flex-1">
                  <p class="text-sm font-medium text-gray-700 truncate">{{ member.email }}</p>
                  <p class="text-xs text-gray-500 truncate">{{ member.full_name || member.display_name || `${member.first_name || ''} ${member.last_name || ''}`.trim() || '-' }}</p>
                </div>
              </div>
              <button
                @click="removeMember(member)"
                class="p-1 rounded text-gray-400 hover:text-red-500 opacity-0 group-hover:opacity-100 transition-all"
                title="Retirer du groupe"
              >
                <X class="w-4 h-4" />
              </button>
            </div>
          </div>
        </div>
      </div>

      <template #footer>
        <div class="flex justify-end">
          <button
            @click="closeDetailModal"
            class="px-4 py-2 bg-brand-500 hover:bg-brand-600 text-white text-sm font-medium rounded-lg transition-colors"
          >
            Fermer
          </button>
        </div>
      </template>
    </Modal>

    <!-- Delete confirmation -->
    <ConfirmDialog
      :show="showDeleteConfirm"
      title="Supprimer le groupe"
      :message="`Etes-vous sur de vouloir supprimer le groupe &laquo; ${groupToDelete?.name} &raquo; ? Cette action est irreversible.`"
      confirm-text="Supprimer"
      variant="danger"
      @confirm="handleDelete"
      @cancel="showDeleteConfirm = false"
    />
  </AppLayout>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useApi } from '@/composables/useApi'
import { useNotificationStore } from '@/stores/notifications'
import AppLayout from '@/layouts/AppLayout.vue'
import Modal from '@/components/ui/Modal.vue'
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue'
import {
  Plus,
  Users,
  UsersRound,
  Pencil,
  Trash2,
  Search,
  UserPlus,
  X,
  Loader2
} from 'lucide-vue-next'

const api = useApi()
const notifications = useNotificationStore()

const loading = ref(true)
const saving = ref(false)
const membersLoading = ref(false)
const groups = ref([])

// Form modal
const showFormModal = ref(false)
const editingGroup = ref(null)
const groupForm = reactive({
  name: '',
  type: 'custom',
  color: '#2D5016',
  description: ''
})

// Detail modal
const showDetailModal = ref(false)
const selectedGroup = ref(null)
const groupMembers = ref([])
const memberSearch = ref('')
const searchResults = ref([])

// Delete
const showDeleteConfirm = ref(false)
const groupToDelete = ref(null)

let searchTimeout = null

const colorPresets = [
  '#2D5016', '#1E40AF', '#7C3AED', '#DB2777',
  '#DC2626', '#EA580C', '#D97706', '#16A34A'
]

function typeBadgeClass(type) {
  const map = {
    team: 'bg-blue-100 text-blue-700',
    category: 'bg-purple-100 text-purple-700',
    custom: 'bg-gray-100 text-gray-700'
  }
  return map[type] || map.custom
}

function typeLabel(type) {
  const map = { team: 'Equipe', category: 'Categorie', custom: 'Personnalise' }
  return map[type] || type
}

function memberInitials(member) {
  const name = member.full_name || member.name || ''
  return name.split(' ').map(w => w[0]).join('').slice(0, 2).toUpperCase()
}

// Fetch groups
async function fetchGroups() {
  loading.value = true
  try {
    const data = await api.get('/api/recipients/groups')
    groups.value = data.data || data.groups || data || []
  } catch {
    // Error handled by useApi
  } finally {
    loading.value = false
  }
}

// Create/Edit modal
function openCreateModal() {
  editingGroup.value = null
  groupForm.name = ''
  groupForm.type = 'custom'
  groupForm.color = '#2D5016'
  groupForm.description = ''
  showFormModal.value = true
}

function openEditModal(group) {
  editingGroup.value = group
  groupForm.name = group.name
  groupForm.type = group.type || 'custom'
  groupForm.color = group.color || '#2D5016'
  groupForm.description = group.description || ''
  showFormModal.value = true
}

function closeFormModal() {
  showFormModal.value = false
  editingGroup.value = null
}

async function handleSaveGroup() {
  if (!groupForm.name.trim() || saving.value) return

  saving.value = true
  try {
    const payload = {
      name: groupForm.name.trim(),
      type: groupForm.type,
      color: groupForm.color,
      description: groupForm.description.trim()
    }

    if (editingGroup.value) {
      await api.put(`/api/recipients/groups/${editingGroup.value.id}`, payload)
      notifications.success('Groupe modifie')
    } else {
      await api.post('/api/recipients/groups', payload)
      notifications.success('Groupe cree')
    }

    closeFormModal()
    await fetchGroups()
  } catch {
    // Error handled by useApi
  } finally {
    saving.value = false
  }
}

// Detail modal
async function openGroupDetail(group) {
  selectedGroup.value = group
  showDetailModal.value = true
  memberSearch.value = ''
  searchResults.value = []
  await fetchGroupMembers(group.id)
}

function closeDetailModal() {
  showDetailModal.value = false
  selectedGroup.value = null
  groupMembers.value = []
}

async function fetchGroupMembers(groupId) {
  membersLoading.value = true
  try {
    const data = await api.get(`/api/recipients/groups/${groupId}/members`)
    groupMembers.value = data.data || data.members || data || []
  } catch {
    // Graceful fallback
  } finally {
    membersLoading.value = false
  }
}

function debouncedSearchRecipients() {
  clearTimeout(searchTimeout)
  if (!memberSearch.value.trim()) {
    searchResults.value = []
    return
  }
  searchTimeout = setTimeout(async () => {
    try {
      const data = await api.get(
        `/api/recipients?search=${encodeURIComponent(memberSearch.value)}&per_page=10&active_only=1`
      )
      const recipients = data.recipients || data.data || []
      // Filter out existing members
      const memberIds = new Set(groupMembers.value.map(m => m.id))
      searchResults.value = recipients.filter(r => !memberIds.has(r.id))
    } catch {
      searchResults.value = []
    }
  }, 300)
}

async function addMember(recipient) {
  if (!selectedGroup.value) return
  try {
    await api.post(`/api/recipients/groups/${selectedGroup.value.id}/members`, {
      recipient_id: recipient.id
    })
    groupMembers.value.push(recipient)
    searchResults.value = searchResults.value.filter(r => r.id !== recipient.id)
    memberSearch.value = ''

    // Update member count
    const group = groups.value.find(g => g.id === selectedGroup.value.id)
    if (group) group.member_count = (group.member_count ?? 0) + 1

    notifications.success(`${recipient.full_name || recipient.name} ajoute au groupe`)
  } catch {
    // Error handled by useApi
  }
}

async function removeMember(member) {
  if (!selectedGroup.value) return
  try {
    await api.del(`/api/recipients/groups/${selectedGroup.value.id}/members/${member.id}`)
    groupMembers.value = groupMembers.value.filter(m => m.id !== member.id)

    // Update member count
    const group = groups.value.find(g => g.id === selectedGroup.value.id)
    if (group) group.member_count = Math.max(0, (group.member_count ?? 1) - 1)

    notifications.success(`${member.full_name || member.name} retire du groupe`)
  } catch {
    // Error handled by useApi
  }
}

// Delete
function confirmDelete(group) {
  groupToDelete.value = group
  showDeleteConfirm.value = true
}

async function handleDelete() {
  if (!groupToDelete.value) return
  try {
    await api.del(`/api/recipients/groups/${groupToDelete.value.id}`)
    notifications.success(`Groupe "${groupToDelete.value.name}" supprime`)
    showDeleteConfirm.value = false
    groupToDelete.value = null

    // If detail modal is open for this group, close it
    if (selectedGroup.value?.id === groupToDelete.value?.id) {
      closeDetailModal()
    }

    await fetchGroups()
  } catch {
    // Error handled by useApi
  }
}

onMounted(() => {
  fetchGroups()
})
</script>
