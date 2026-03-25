<template>
  <AppLayout>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Gestion des utilisateurs</h1>
        <p class="mt-1 text-sm text-gray-500">Administration des comptes et journal d'audit</p>
      </div>
      <button
        @click="openCreateModal"
        class="inline-flex items-center gap-2 px-4 py-2.5 bg-brand-500 text-white rounded-lg hover:bg-brand-600 transition-colors font-medium text-sm"
      >
        <UserPlus class="w-4 h-4" />
        Nouvel utilisateur
      </button>
    </div>

    <!-- Tabs -->
    <div class="flex border-b border-gray-200">
      <button
        @click="activeTab = 'users'"
        :class="[
          'px-5 py-3 text-sm font-medium border-b-2 transition-colors',
          activeTab === 'users'
            ? 'text-brand-600 border-brand-500'
            : 'text-gray-500 border-transparent hover:text-gray-700'
        ]"
      >
        <div class="flex items-center gap-1.5">
          <Users class="w-4 h-4" />
          Utilisateurs
        </div>
      </button>
      <button
        @click="activeTab = 'audit'; fetchAuditLogs()"
        :class="[
          'px-5 py-3 text-sm font-medium border-b-2 transition-colors',
          activeTab === 'audit'
            ? 'text-brand-600 border-brand-500'
            : 'text-gray-500 border-transparent hover:text-gray-700'
        ]"
      >
        <div class="flex items-center gap-1.5">
          <ScrollText class="w-4 h-4" />
          Journal d'audit
        </div>
      </button>
    </div>

    <!-- Users tab -->
    <div v-show="activeTab === 'users'">
      <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <DataTable :columns="userColumns" :data="users" :loading="loading">
          <template #cell-username="{ row }">
            <div class="flex items-center gap-3">
              <div class="flex items-center justify-center w-8 h-8 rounded-full bg-brand-100 text-brand-700 text-sm font-semibold">
                {{ (row.username || '?')[0].toUpperCase() }}
              </div>
              <span class="font-medium text-gray-900">{{ row.username }}</span>
            </div>
          </template>
          <template #cell-email="{ row }">
            <span class="text-sm text-gray-600">{{ row.email }}</span>
          </template>
          <template #cell-role="{ row }">
            <Badge
              :text="roleLabels[row.role] || row.role"
              :color="roleColors[row.role] || 'gray'"
            />
          </template>
          <template #cell-is_active="{ row }">
            <div class="flex items-center gap-1.5">
              <div :class="['w-2 h-2 rounded-full', row.is_active ? 'bg-green-500' : 'bg-gray-300']" />
              <span class="text-sm" :class="row.is_active ? 'text-green-700' : 'text-gray-500'">
                {{ row.is_active ? 'Actif' : 'Inactif' }}
              </span>
            </div>
          </template>
          <template #cell-last_login_at="{ row }">
            <span class="text-sm text-gray-500">{{ row.last_login_at ? formatDate(row.last_login_at) : 'Jamais' }}</span>
          </template>
          <template #cell-actions="{ row }">
            <div class="flex items-center gap-1">
              <button
                @click="openEditModal(row)"
                class="p-1.5 text-gray-400 hover:text-brand-600 hover:bg-brand-50 rounded-lg transition-colors"
                title="Modifier"
              >
                <Pencil class="w-4 h-4" />
              </button>
              <button
                @click="confirmDelete(row)"
                class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors"
                title="Supprimer"
                :disabled="row.id === currentUser?.id"
              >
                <Trash2 class="w-4 h-4" :class="row.id === currentUser?.id ? 'opacity-30' : ''" />
              </button>
            </div>
          </template>
        </DataTable>
      </div>
    </div>

    <!-- Audit log tab -->
    <div v-show="activeTab === 'audit'" class="space-y-4">
      <!-- Audit filters -->
      <div class="flex flex-wrap items-center gap-3 p-4 bg-white rounded-lg border border-gray-200">
        <div class="flex items-center gap-2">
          <Filter class="w-4 h-4 text-gray-400" />
        </div>
        <select
          v-model="auditFilters.action"
          @change="fetchAuditLogs"
          class="px-3 py-1.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-400 focus:border-brand-400"
        >
          <option value="">Toutes les actions</option>
          <option v-for="action in auditActions" :key="action" :value="action">{{ action }}</option>
        </select>
        <select
          v-model="auditFilters.user_id"
          @change="fetchAuditLogs"
          class="px-3 py-1.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-400 focus:border-brand-400"
        >
          <option value="">Tous les utilisateurs</option>
          <option v-for="u in users" :key="u.id" :value="u.id">{{ u.username }}</option>
        </select>
      </div>

      <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <DataTable :columns="auditColumns" :data="auditLogs" :loading="loadingAudit">
          <template #cell-created_at="{ row }">
            <span class="text-sm text-gray-500">{{ formatDate(row.created_at) }}</span>
          </template>
          <template #cell-username="{ row }">
            <span class="text-sm font-medium text-gray-900">{{ row.username }}</span>
          </template>
          <template #cell-action="{ row }">
            <Badge :text="row.action" color="blue" />
          </template>
          <template #cell-details="{ row }">
            <span class="text-sm text-gray-600 line-clamp-1">{{ row.details || '-' }}</span>
          </template>
          <template #cell-ip_address="{ row }">
            <code class="text-xs text-gray-500 bg-gray-100 px-1.5 py-0.5 rounded">{{ row.ip_address || '-' }}</code>
          </template>
        </DataTable>
      </div>
    </div>

    <!-- Create user modal -->
    <Modal :show="showCreateModal" title="Créer un utilisateur" max-width="lg" @close="showCreateModal = false">
      <div class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Nom d'utilisateur *</label>
          <input
            v-model="createForm.username"
            type="text"
            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-brand-400 focus:border-brand-400"
          />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Email *</label>
          <input
            v-model="createForm.email"
            type="email"
            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-brand-400 focus:border-brand-400"
          />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Mot de passe temporaire *</label>
          <input
            v-model="createForm.password"
            type="text"
            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-brand-400 focus:border-brand-400 font-mono"
          />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Rôle</label>
          <select
            v-model="createForm.role"
            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-brand-400 focus:border-brand-400"
          >
            <option value="user">Utilisateur</option>
            <option value="manager">Manager</option>
            <option value="admin">Administrateur</option>
          </select>
        </div>
        <label class="flex items-center gap-2 cursor-pointer">
          <input
            type="checkbox"
            v-model="createForm.must_change_password"
            class="h-4 w-4 text-brand-600 rounded border-gray-300 focus:ring-brand-500"
          />
          <span class="text-sm text-gray-700">Doit changer de mot de passe à la première connexion</span>
        </label>

        <!-- Validation errors -->
        <div v-if="Object.keys(formErrors).length" class="p-3 bg-red-50 border border-red-200 rounded-lg">
          <ul class="text-sm text-red-700 space-y-1">
            <li v-for="(msgs, field) in formErrors" :key="field">
              {{ Array.isArray(msgs) ? msgs.join(', ') : msgs }}
            </li>
          </ul>
        </div>
      </div>

      <template #footer>
        <div class="flex justify-end gap-3">
          <button
            @click="showCreateModal = false"
            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50"
          >
            Annuler
          </button>
          <button
            @click="createUser"
            :disabled="saving"
            class="px-4 py-2 text-sm font-medium text-white bg-brand-500 rounded-lg hover:bg-brand-600 disabled:opacity-50"
          >
            Créer
          </button>
        </div>
      </template>
    </Modal>

    <!-- Edit user modal -->
    <Modal :show="showEditModal" title="Modifier l'utilisateur" max-width="lg" @close="showEditModal = false">
      <div class="space-y-4">
        <div class="p-3 bg-gray-50 rounded-lg">
          <p class="text-sm font-medium text-gray-900">{{ editForm.username }}</p>
          <p class="text-xs text-gray-500">{{ editForm.email }}</p>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Rôle</label>
          <select
            v-model="editForm.role"
            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-brand-400 focus:border-brand-400"
          >
            <option value="user">Utilisateur</option>
            <option value="manager">Manager</option>
            <option value="admin">Administrateur</option>
          </select>
        </div>
        <label class="flex items-center gap-2 cursor-pointer">
          <input
            type="checkbox"
            v-model="editForm.is_active"
            class="h-4 w-4 text-brand-600 rounded border-gray-300 focus:ring-brand-500"
          />
          <span class="text-sm text-gray-700">Compte actif</span>
        </label>

        <div class="pt-2 border-t border-gray-200">
          <button
            @click="resetPassword"
            :disabled="saving"
            class="inline-flex items-center gap-2 px-3 py-2 text-sm font-medium text-orange-700 bg-orange-50 border border-orange-200 rounded-lg hover:bg-orange-100 disabled:opacity-50"
          >
            <KeyRound class="w-4 h-4" />
            Réinitialiser le mot de passe
          </button>
        </div>

        <div v-if="Object.keys(formErrors).length" class="p-3 bg-red-50 border border-red-200 rounded-lg">
          <ul class="text-sm text-red-700 space-y-1">
            <li v-for="(msgs, field) in formErrors" :key="field">
              {{ Array.isArray(msgs) ? msgs.join(', ') : msgs }}
            </li>
          </ul>
        </div>
      </div>

      <template #footer>
        <div class="flex justify-end gap-3">
          <button
            @click="showEditModal = false"
            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50"
          >
            Annuler
          </button>
          <button
            @click="updateUser"
            :disabled="saving"
            class="px-4 py-2 text-sm font-medium text-white bg-brand-500 rounded-lg hover:bg-brand-600 disabled:opacity-50"
          >
            Enregistrer
          </button>
        </div>
      </template>
    </Modal>

    <!-- Delete confirmation -->
    <ConfirmDialog
      :show="showDeleteDialog"
      title="Supprimer l'utilisateur"
      :message="`Supprimer définitivement l'utilisateur « ${userToDelete?.username} » ? Cette action est irréversible.`"
      confirm-text="Supprimer"
      variant="danger"
      @confirm="deleteUser"
      @cancel="showDeleteDialog = false"
    />

    <!-- Password reset result -->
    <Modal :show="showPasswordResult" title="Mot de passe réinitialisé" max-width="md" @close="showPasswordResult = false">
      <div class="space-y-3">
        <p class="text-sm text-gray-600">Le nouveau mot de passe temporaire est :</p>
        <div class="p-3 bg-gray-100 rounded-lg">
          <code class="text-lg font-mono font-bold text-gray-900">{{ newPassword }}</code>
        </div>
        <p class="text-xs text-gray-500">Communiquez ce mot de passe de manière sécurisée. L'utilisateur devra le changer à sa prochaine connexion.</p>
      </div>
      <template #footer>
        <div class="flex justify-end">
          <button
            @click="showPasswordResult = false"
            class="px-4 py-2 text-sm font-medium text-white bg-brand-500 rounded-lg hover:bg-brand-600"
          >
            Compris
          </button>
        </div>
      </template>
    </Modal>
  </div>
  </AppLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import {
  UserPlus, Users, ScrollText, Pencil, Trash2,
  Filter, KeyRound
} from 'lucide-vue-next'
import { useApi } from '../../composables/useApi'
import { useAuth } from '../../composables/useAuth'
import { useNotificationStore } from '../../stores/notifications'
import DataTable from '../../components/ui/DataTable.vue'
import Badge from '../../components/ui/Badge.vue'
import Modal from '../../components/ui/Modal.vue'
import ConfirmDialog from '../../components/ui/ConfirmDialog.vue'

const { loading, get, post, put, del } = useApi()
const { user: currentUser } = useAuth()
const notifications = useNotificationStore()

const activeTab = ref('users')
const users = ref([])
const auditLogs = ref([])
const loadingAudit = ref(false)
const saving = ref(false)
const formErrors = ref({})

const showCreateModal = ref(false)
const showEditModal = ref(false)
const showDeleteDialog = ref(false)
const showPasswordResult = ref(false)
const userToDelete = ref(null)
const newPassword = ref('')

const roleLabels = {
  admin: 'Administrateur',
  manager: 'Manager',
  user: 'Utilisateur'
}

const roleColors = {
  admin: 'purple',
  manager: 'blue',
  user: 'gray'
}

const auditActions = [
  'login', 'logout', 'create_user', 'update_user', 'delete_user',
  'create_campaign', 'launch_campaign', 'create_template', 'update_template',
  'sync_recipients', 'export_data'
]

const createForm = ref({
  username: '',
  email: '',
  password: '',
  role: 'user',
  must_change_password: true
})

const editForm = ref({
  id: null,
  username: '',
  email: '',
  role: 'user',
  is_active: true
})

const auditFilters = ref({
  action: '',
  user_id: ''
})

const userColumns = [
  { key: 'username', label: 'Utilisateur', sortable: true },
  { key: 'email', label: 'Email', sortable: true },
  { key: 'role', label: 'Rôle', sortable: true },
  { key: 'is_active', label: 'Statut' },
  { key: 'last_login_at', label: 'Dernière connexion', sortable: true },
  { key: 'actions', label: 'Actions' }
]

const auditColumns = [
  { key: 'created_at', label: 'Date', sortable: true },
  { key: 'username', label: 'Utilisateur' },
  { key: 'action', label: 'Action' },
  { key: 'details', label: 'Détails' },
  { key: 'ip_address', label: 'IP' }
]

function formatDate(dateStr) {
  if (!dateStr) return '-'
  return new Date(dateStr).toLocaleDateString('fr-FR', {
    day: '2-digit', month: '2-digit', year: 'numeric',
    hour: '2-digit', minute: '2-digit'
  })
}

async function fetchUsers() {
  try {
    const response = await get('/api/admin/users')
    users.value = response.data || response || []
  } catch {
    // Error handled by useApi
  }
}

async function fetchAuditLogs() {
  loadingAudit.value = true
  try {
    const params = new URLSearchParams()
    if (auditFilters.value.action) params.append('action', auditFilters.value.action)
    if (auditFilters.value.user_id) params.append('user_id', auditFilters.value.user_id)
    const query = params.toString()
    const url = query ? `/api/admin/audit-logs?${query}` : '/api/admin/audit-logs'
    const data = await get(url)
    auditLogs.value = data.data || data || []
  } catch {
    // Error handled by useApi
  } finally {
    loadingAudit.value = false
  }
}

function openCreateModal() {
  createForm.value = {
    username: '',
    email: '',
    password: '',
    role: 'user',
    must_change_password: true
  }
  formErrors.value = {}
  showCreateModal.value = true
}

function openEditModal(user) {
  editForm.value = {
    id: user.id,
    username: user.username,
    email: user.email,
    role: user.role,
    is_active: user.is_active
  }
  formErrors.value = {}
  showEditModal.value = true
}

async function createUser() {
  saving.value = true
  formErrors.value = {}
  try {
    const result = await post('/api/admin/users', createForm.value)
    if (result?.errors) {
      formErrors.value = result.errors
      return
    }
    notifications.success('Utilisateur créé avec succès')
    showCreateModal.value = false
    await fetchUsers()
  } catch {
    // Error handled by useApi
  } finally {
    saving.value = false
  }
}

async function updateUser() {
  saving.value = true
  formErrors.value = {}
  try {
    const result = await put(`/api/admin/users/${editForm.value.id}`, {
      role: editForm.value.role,
      is_active: editForm.value.is_active
    })
    if (result?.errors) {
      formErrors.value = result.errors
      return
    }
    notifications.success('Utilisateur mis à jour')
    showEditModal.value = false
    await fetchUsers()
  } catch {
    // Error handled by useApi
  } finally {
    saving.value = false
  }
}

function confirmDelete(user) {
  if (user.id === currentUser.value?.id) {
    notifications.warning('Vous ne pouvez pas supprimer votre propre compte')
    return
  }
  userToDelete.value = user
  showDeleteDialog.value = true
}

async function deleteUser() {
  if (!userToDelete.value) return
  try {
    await del(`/api/admin/users/${userToDelete.value.id}`)
    notifications.success('Utilisateur supprimé')
    showDeleteDialog.value = false
    userToDelete.value = null
    await fetchUsers()
  } catch {
    // Error handled by useApi
  }
}

async function resetPassword() {
  saving.value = true
  try {
    const result = await post(`/api/admin/users/${editForm.value.id}/reset-password`)
    if (result?.password) {
      newPassword.value = result.password
      showEditModal.value = false
      showPasswordResult.value = true
    } else {
      notifications.success('Mot de passe réinitialisé')
      showEditModal.value = false
    }
  } catch {
    // Error handled by useApi
  } finally {
    saving.value = false
  }
}

onMounted(fetchUsers)
</script>
