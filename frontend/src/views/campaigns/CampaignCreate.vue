<template>
  <AppLayout>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-3">
        <router-link
          to="/campaigns"
          class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg transition-colors"
        >
          <ArrowLeft class="w-5 h-5" />
        </router-link>
        <div>
          <h1 class="text-2xl font-bold text-gray-900">{{ isEditMode ? 'Modifier la campagne' : 'Nouvelle campagne' }}</h1>
          <p class="mt-0.5 text-sm text-gray-500">Configurez et lancez une campagne de phishing simulé</p>
        </div>
      </div>
    </div>

    <!-- Stepper -->
    <div class="bg-white rounded-xl border border-gray-200 p-4">
      <nav class="flex items-center justify-between">
        <div
          v-for="(step, idx) in steps"
          :key="step.id"
          class="flex items-center"
          :class="idx < steps.length - 1 ? 'flex-1' : ''"
        >
          <div class="flex items-center gap-3">
            <div
              :class="[
                'flex items-center justify-center w-9 h-9 rounded-full text-sm font-semibold transition-colors',
                currentStep > idx
                  ? 'bg-brand-500 text-white'
                  : currentStep === idx
                    ? 'bg-brand-100 text-brand-700 ring-2 ring-brand-500'
                    : 'bg-gray-100 text-gray-400'
              ]"
            >
              <CheckIcon v-if="currentStep > idx" class="w-4 h-4" />
              <span v-else>{{ idx + 1 }}</span>
            </div>
            <span
              :class="[
                'text-sm font-medium hidden sm:block',
                currentStep >= idx ? 'text-gray-900' : 'text-gray-400'
              ]"
            >
              {{ step.label }}
            </span>
          </div>
          <div
            v-if="idx < steps.length - 1"
            :class="[
              'flex-1 h-0.5 mx-4',
              currentStep > idx ? 'bg-brand-500' : 'bg-gray-200'
            ]"
          />
        </div>
      </nav>
    </div>

    <!-- Step content -->
    <div class="bg-white rounded-xl border border-gray-200 p-6">
      <!-- Step 1: Info -->
      <div v-show="currentStep === 0" class="space-y-5">
        <h2 class="text-lg font-semibold text-gray-900">Informations de la campagne</h2>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Nom de la campagne *</label>
          <input
            v-model="form.name"
            type="text"
            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-brand-400 focus:border-brand-400"
            placeholder="Ex: Campagne Q1 2026 - Direction"
          />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
          <textarea
            v-model="form.description"
            rows="3"
            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-brand-400 focus:border-brand-400 resize-none"
            placeholder="Objectif et contexte de la campagne..."
          />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">
            Domaine de phishing
            <span class="text-gray-400 font-normal">(optionnel)</span>
          </label>
          <input
            v-model="form.phishing_domain"
            type="text"
            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-brand-400 focus:border-brand-400"
            placeholder="Ex: security-alert-microsoft.com"
          />
          <p class="mt-1 text-xs text-gray-500">
            Domaine personnalisé pour les liens de phishing. Le domaine doit pointer vers ce serveur (DNS + vhost Apache).
            Laissez vide pour utiliser le domaine par défaut.
          </p>
        </div>
      </div>

      <!-- Step 2: Templates (multi-select) -->
      <div v-show="currentStep === 1" class="space-y-5">
        <div class="flex items-center justify-between">
          <div>
            <h2 class="text-lg font-semibold text-gray-900">Choisir les templates</h2>
            <p class="text-sm text-gray-500 mt-0.5">
              Sélectionnez un ou plusieurs templates. Ils seront distribués aléatoirement aux destinataires
              en évitant les templates déjà reçus lors de campagnes précédentes.
            </p>
          </div>
          <span v-if="form.template_ids.length > 0" class="text-sm font-medium text-brand-600">
            {{ form.template_ids.length }} sélectionné{{ form.template_ids.length > 1 ? 's' : '' }}
          </span>
        </div>

        <div v-if="loadingTemplates" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          <div v-for="i in 6" :key="i" class="p-4 border border-gray-200 rounded-lg animate-pulse">
            <div class="h-5 bg-gray-200 rounded w-3/4 mb-2" />
            <div class="h-4 bg-gray-200 rounded w-1/2 mb-3" />
            <div class="h-3 bg-gray-200 rounded w-full" />
          </div>
        </div>

        <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          <button
            v-for="tpl in templates"
            :key="tpl.id"
            @click="toggleTemplate(tpl.id)"
            :class="[
              'text-left p-4 border-2 rounded-lg transition-all',
              form.template_ids.includes(tpl.id)
                ? 'border-brand-500 bg-brand-50 ring-1 ring-brand-500'
                : 'border-gray-200 hover:border-gray-300 hover:shadow-sm'
            ]"
          >
            <div class="flex items-start justify-between mb-1">
              <h3 class="font-medium text-gray-900 text-sm line-clamp-1">{{ tpl.name }}</h3>
              <div v-if="form.template_ids.includes(tpl.id)" class="flex-shrink-0 ml-2">
                <CheckCircle class="w-5 h-5 text-brand-500" />
              </div>
            </div>
            <div class="flex items-center gap-0.5 mb-2">
              <Star
                v-for="n in 5"
                :key="n"
                class="w-3 h-3"
                :class="n <= tpl.difficulty ? 'text-yellow-400 fill-yellow-400' : 'text-gray-200'"
              />
            </div>
            <p v-if="tpl.subject" class="text-xs text-gray-500 line-clamp-2">{{ tpl.subject }}</p>
            <div v-if="tpl.tags?.length" class="flex flex-wrap gap-1 mt-2">
              <span
                v-for="tag in tpl.tags.slice(0, 3)"
                :key="tag"
                class="px-1.5 py-0.5 text-xs bg-gray-100 text-gray-600 rounded"
              >
                {{ tag }}
              </span>
            </div>
          </button>
        </div>
      </div>

      <!-- Step 3: Recipients -->
      <div v-show="currentStep === 2" class="space-y-5">
        <h2 class="text-lg font-semibold text-gray-900">Sélectionner les destinataires</h2>

        <!-- Tabs for selection mode -->
        <div class="flex border-b border-gray-200">
          <button
            v-for="tab in recipientTabs"
            :key="tab.id"
            @click="recipientMode = tab.id"
            :class="[
              'px-4 py-2.5 text-sm font-medium border-b-2 transition-colors',
              recipientMode === tab.id
                ? 'text-brand-600 border-brand-500'
                : 'text-gray-500 border-transparent hover:text-gray-700'
            ]"
          >
            {{ tab.label }}
          </button>
        </div>

        <!-- Individual selection -->
        <div v-if="recipientMode === 'individual'" class="space-y-3">
          <input
            v-model="recipientSearch"
            type="text"
            placeholder="Rechercher un collaborateur (nom, email, département)..."
            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-brand-400 focus:border-brand-400"
          />
          <div class="max-h-64 overflow-y-auto border border-gray-200 rounded-lg divide-y divide-gray-100">
            <div v-if="loadingRecipients" class="px-4 py-8 text-center text-sm text-gray-500">
              Chargement...
            </div>
            <template v-else>
              <label
                v-for="r in filteredRecipients"
                :key="r.id"
                class="flex items-center gap-3 px-4 py-2.5 hover:bg-gray-50 cursor-pointer"
              >
                <input
                  type="checkbox"
                  :value="r.id"
                  v-model="form.recipient_ids"
                  class="h-4 w-4 text-brand-600 rounded border-gray-300 focus:ring-brand-500"
                />
                <div class="flex-1 min-w-0">
                  <p class="text-sm font-medium text-gray-900 truncate">{{ r.first_name }} {{ r.last_name }}</p>
                  <p class="text-xs text-gray-500 truncate">{{ r.email }}</p>
                </div>
                <span v-if="r.department" class="text-xs text-gray-400">{{ r.department }}</span>
              </label>
              <div v-if="filteredRecipients.length === 0" class="px-4 py-8 text-center text-sm text-gray-500">
                Aucun collaborateur trouvé
              </div>
            </template>
          </div>
          <!-- Pagination -->
          <div v-if="recipientPagination.totalPages > 1" class="flex items-center justify-between text-sm">
            <span class="text-gray-500">{{ recipientPagination.total }} collaborateur{{ recipientPagination.total !== 1 ? 's' : '' }}</span>
            <div class="flex items-center gap-1">
              <button
                @click="fetchRecipientPage(recipientPagination.page - 1)"
                :disabled="recipientPagination.page <= 1"
                class="px-2.5 py-1 border border-gray-300 rounded text-xs font-medium hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed"
              >
                ‹ Préc.
              </button>
              <span class="px-2 text-gray-600">{{ recipientPagination.page }} / {{ recipientPagination.totalPages }}</span>
              <button
                @click="fetchRecipientPage(recipientPagination.page + 1)"
                :disabled="recipientPagination.page >= recipientPagination.totalPages"
                class="px-2.5 py-1 border border-gray-300 rounded text-xs font-medium hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed"
              >
                Suiv. ›
              </button>
            </div>
          </div>
        </div>

        <!-- By team/group -->
        <div v-else-if="recipientMode === 'team'" class="space-y-3">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <label
              v-for="group in groups"
              :key="group.id"
              :class="[
                'flex items-center gap-3 p-4 border-2 rounded-lg cursor-pointer transition-all',
                form.group_ids.includes(group.id)
                  ? 'border-brand-500 bg-brand-50'
                  : 'border-gray-200 hover:border-gray-300'
              ]"
            >
              <input
                type="checkbox"
                :value="group.id"
                v-model="form.group_ids"
                class="h-4 w-4 text-brand-600 rounded border-gray-300 focus:ring-brand-500"
              />
              <div>
                <p class="text-sm font-medium text-gray-900">{{ group.name }}</p>
                <p class="text-xs text-gray-500">{{ group.member_count || 0 }} membre{{ (group.member_count || 0) !== 1 ? 's' : '' }}</p>
              </div>
            </label>
          </div>
        </div>

        <!-- By category/department -->
        <div v-else-if="recipientMode === 'category'" class="space-y-3">
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
            <label
              v-for="dept in departments"
              :key="dept"
              :class="[
                'flex items-center gap-3 p-3 border-2 rounded-lg cursor-pointer transition-all',
                form.departments.includes(dept)
                  ? 'border-brand-500 bg-brand-50'
                  : 'border-gray-200 hover:border-gray-300'
              ]"
            >
              <input
                type="checkbox"
                :value="dept"
                v-model="form.departments"
                class="h-4 w-4 text-brand-600 rounded border-gray-300 focus:ring-brand-500"
              />
              <span class="text-sm font-medium text-gray-900">{{ dept }}</span>
            </label>
          </div>
        </div>

        <p class="text-sm text-gray-500">
          {{ selectedRecipientCount }} destinataire{{ selectedRecipientCount !== 1 ? 's' : '' }} sélectionné{{ selectedRecipientCount !== 1 ? 's' : '' }}
        </p>
      </div>

      <!-- Step 4: Schedule -->
      <div v-show="currentStep === 3" class="space-y-5">
        <h2 class="text-lg font-semibold text-gray-900">Planification</h2>

        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Mode d'envoi</label>
            <div class="flex gap-4">
              <label
                :class="[
                  'flex-1 flex items-center gap-3 p-4 border-2 rounded-lg cursor-pointer transition-all',
                  form.send_mode === 'immediate' ? 'border-brand-500 bg-brand-50' : 'border-gray-200 hover:border-gray-300'
                ]"
              >
                <input
                  type="radio"
                  v-model="form.send_mode"
                  value="immediate"
                  class="h-4 w-4 text-brand-600 border-gray-300 focus:ring-brand-500"
                />
                <div>
                  <p class="text-sm font-medium text-gray-900">Envoi immédiat</p>
                  <p class="text-xs text-gray-500">Tous les emails sont envoyés en même temps</p>
                </div>
              </label>
              <label
                :class="[
                  'flex-1 flex items-center gap-3 p-4 border-2 rounded-lg cursor-pointer transition-all',
                  form.send_mode === 'staggered' ? 'border-brand-500 bg-brand-50' : 'border-gray-200 hover:border-gray-300'
                ]"
              >
                <input
                  type="radio"
                  v-model="form.send_mode"
                  value="staggered"
                  class="h-4 w-4 text-brand-600 border-gray-300 focus:ring-brand-500"
                />
                <div>
                  <p class="text-sm font-medium text-gray-900">Envoi échelonné</p>
                  <p class="text-xs text-gray-500">Les emails sont envoyés progressivement</p>
                </div>
              </label>
            </div>
          </div>

          <div v-if="form.send_mode === 'staggered'">
            <label class="block text-sm font-medium text-gray-700 mb-1">Intervalle entre les envois (secondes)</label>
            <input
              v-model.number="form.stagger_interval"
              type="number"
              min="10"
              max="3600"
              class="w-48 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-brand-400 focus:border-brand-400"
            />
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Date et heure de lancement (optionnel)</label>
            <input
              v-model="form.scheduled_at"
              type="datetime-local"
              class="w-72 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-brand-400 focus:border-brand-400"
            />
            <p class="mt-1 text-xs text-gray-500">Laissez vide pour un lancement manuel.</p>
          </div>
        </div>
      </div>

      <!-- Step 5: Summary -->
      <div v-show="currentStep === 4" class="space-y-5">
        <h2 class="text-lg font-semibold text-gray-900">Récapitulatif</h2>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div class="p-4 bg-gray-50 rounded-lg">
            <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Campagne</h3>
            <p class="text-sm font-medium text-gray-900">{{ form.name || '-' }}</p>
            <p v-if="form.description" class="text-sm text-gray-500 mt-1">{{ form.description }}</p>
          </div>

          <div class="p-4 bg-gray-50 rounded-lg">
            <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">
              Template{{ form.template_ids.length > 1 ? 's' : '' }}
            </h3>
            <ul v-if="selectedTemplateNames.length > 1" class="space-y-1">
              <li v-for="name in selectedTemplateNames" :key="name" class="text-sm text-gray-900 flex items-center gap-1.5">
                <span class="w-1.5 h-1.5 rounded-full bg-brand-400 flex-shrink-0" />
                {{ name }}
              </li>
            </ul>
            <p v-else class="text-sm font-medium text-gray-900">{{ selectedTemplateName }}</p>
            <p v-if="form.template_ids.length > 1" class="text-xs text-gray-500 mt-2">
              Distribution aléatoire avec exclusion des templates déjà reçus
            </p>
            <p v-if="form.phishing_domain" class="text-xs text-gray-500 mt-1">
              Domaine : <span class="font-mono text-gray-700">{{ form.phishing_domain }}</span>
            </p>
          </div>

          <div class="p-4 bg-gray-50 rounded-lg">
            <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Destinataires</h3>
            <p class="text-sm font-medium text-gray-900">{{ selectedRecipientCount }} destinataire{{ selectedRecipientCount !== 1 ? 's' : '' }}</p>
          </div>

          <div class="p-4 bg-gray-50 rounded-lg">
            <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Planification</h3>
            <p class="text-sm font-medium text-gray-900">
              {{ form.send_mode === 'staggered' ? 'Envoi échelonné' : 'Envoi immédiat' }}
              <template v-if="form.send_mode === 'staggered'">
                ({{ form.stagger_interval }}s d'intervalle)
              </template>
            </p>
            <p v-if="form.scheduled_at" class="text-sm text-gray-500 mt-1">
              Planifié le {{ formatDate(form.scheduled_at) }}
            </p>
            <p v-else class="text-sm text-gray-500 mt-1">Lancement manuel</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Navigation buttons -->
    <div class="flex items-center justify-between">
      <button
        v-if="currentStep > 0"
        @click="currentStep--"
        class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50"
      >
        <ArrowLeft class="w-4 h-4" />
        Précédent
      </button>
      <div v-else />

      <div class="flex items-center gap-3">
        <button
          @click="saveAsDraft"
          :disabled="saving"
          class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-50"
        >
          <Save class="w-4 h-4" />
          Enregistrer comme brouillon
        </button>

        <button
          v-if="currentStep < steps.length - 1"
          @click="nextStep"
          class="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-white bg-brand-500 rounded-lg hover:bg-brand-600"
        >
          Suivant
          <ArrowRight class="w-4 h-4" />
        </button>

        <button
          v-else
          @click="showLaunchConfirm = true"
          :disabled="saving"
          class="inline-flex items-center gap-2 px-5 py-2.5 text-sm font-medium text-white bg-green-600 rounded-lg hover:bg-green-700 disabled:opacity-50"
        >
          <Rocket class="w-4 h-4" />
          Lancer la campagne
        </button>
      </div>
    </div>

    <!-- Launch confirmation -->
    <ConfirmDialog
      :show="showLaunchConfirm"
      title="Lancer la campagne"
      :message="`Vous êtes sur le point de lancer la campagne « ${form.name} » vers ${selectedRecipientCount} destinataire(s). Confirmer le lancement ?`"
      confirm-text="Lancer maintenant"
      variant="warning"
      @confirm="launchCampaign"
      @cancel="showLaunchConfirm = false"
    />
  </div>
  </AppLayout>
</template>

<script setup>
import { ref, reactive, computed, watch, onMounted } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import { useRouter, useRoute } from 'vue-router'
import {
  ArrowLeft, ArrowRight, Save, Rocket, Star,
  Check as CheckIcon, CheckCircle
} from 'lucide-vue-next'
import { useApi } from '../../composables/useApi'
import { useNotificationStore } from '../../stores/notifications'
import ConfirmDialog from '../../components/ui/ConfirmDialog.vue'

const router = useRouter()
const route = useRoute()
const { get, post, put } = useApi()
const notifications = useNotificationStore()

const isEditMode = computed(() => !!route.params.id)
const campaignId = computed(() => route.params.id)

const currentStep = ref(0)
const saving = ref(false)
const showLaunchConfirm = ref(false)
const loadingTemplates = ref(false)
const recipientMode = ref('individual')
const recipientSearch = ref('')

const templates = ref([])
const recipients = ref([])
const groups = ref([])
const departments = ref([])

const steps = [
  { id: 'info', label: 'Informations' },
  { id: 'template', label: 'Template' },
  { id: 'recipients', label: 'Destinataires' },
  { id: 'schedule', label: 'Planification' },
  { id: 'summary', label: 'Récapitulatif' }
]

const recipientTabs = [
  { id: 'individual', label: 'Individuel' },
  { id: 'team', label: 'Par équipe' },
  { id: 'category', label: 'Par département' }
]

const form = ref({
  name: '',
  description: '',
  phishing_domain: '',
  template_ids: [],
  recipient_ids: [],
  group_ids: [],
  departments: [],
  send_mode: 'immediate',
  stagger_interval: 60,
  scheduled_at: ''
})

const recipientPagination = reactive({ page: 1, totalPages: 1, total: 0 })
const loadingRecipients = ref(false)
let searchDebounceTimer = null

async function fetchRecipientPage(page = 1) {
  loadingRecipients.value = true
  try {
    const params = new URLSearchParams()
    params.set('page', page)
    params.set('per_page', '50')
    if (recipientSearch.value) params.set('search', recipientSearch.value)
    const data = await get(`/api/recipients?${params}`)
    recipients.value = data.data || data || []
    recipientPagination.page = data.page || page
    recipientPagination.total = data.total || 0
    recipientPagination.totalPages = data.total_pages || Math.ceil((data.total || 0) / 50)
  } catch {
    // Error handled by useApi
  } finally {
    loadingRecipients.value = false
  }
}

watch(recipientSearch, () => {
  clearTimeout(searchDebounceTimer)
  searchDebounceTimer = setTimeout(() => {
    recipientPagination.page = 1
    fetchRecipientPage(1)
  }, 300)
})

const filteredRecipients = computed(() => recipients.value)

const selectedRecipientCount = computed(() => {
  if (recipientMode.value === 'individual') {
    return form.value.recipient_ids.length
  } else if (recipientMode.value === 'team') {
    return groups.value
      .filter(g => form.value.group_ids.includes(g.id))
      .reduce((sum, g) => sum + (g.member_count || 0), 0)
  } else {
    return form.value.departments.length > 0 ? form.value.departments.length + ' département(s)' : 0
  }
})

function toggleTemplate(id) {
  const idx = form.value.template_ids.indexOf(id)
  if (idx >= 0) {
    form.value.template_ids.splice(idx, 1)
  } else {
    form.value.template_ids.push(id)
  }
}

const selectedTemplateNames = computed(() => {
  return form.value.template_ids
    .map(id => templates.value.find(t => t.id === id)?.name)
    .filter(Boolean)
})

const selectedTemplateName = computed(() => {
  if (selectedTemplateNames.value.length === 0) return '-'
  if (selectedTemplateNames.value.length === 1) return selectedTemplateNames.value[0]
  return selectedTemplateNames.value.length + ' templates'
})

function formatDate(dateStr) {
  if (!dateStr) return '-'
  const d = new Date(dateStr)
  return d.toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

function nextStep() {
  if (currentStep.value === 0 && !form.value.name) {
    notifications.warning('Veuillez saisir un nom pour la campagne')
    return
  }
  if (currentStep.value === 1 && form.value.template_ids.length === 0) {
    notifications.warning('Veuillez sélectionner au moins un template')
    return
  }
  if (currentStep.value === 2 && selectedRecipientCount.value === 0) {
    notifications.warning('Veuillez sélectionner au moins un destinataire')
    return
  }
  currentStep.value++
}

async function saveAsDraft() {
  saving.value = true
  try {
    const payload = buildPayload()
    payload.status = 'draft'
    if (isEditMode.value) {
      await put(`/api/campaigns/${campaignId.value}`, payload)
      notifications.success('Campagne mise à jour')
    } else {
      await post('/api/campaigns', payload)
      notifications.success('Campagne enregistrée comme brouillon')
    }
    router.push('/campaigns')
  } catch {
    // Error handled by useApi
  } finally {
    saving.value = false
  }
}

async function launchCampaign() {
  saving.value = true
  showLaunchConfirm.value = false
  try {
    const payload = buildPayload()
    payload.launch = true
    await post('/api/campaigns', payload)
    notifications.success('Campagne lancée avec succès')
    router.push('/campaigns')
  } catch {
    // Error handled by useApi
  } finally {
    saving.value = false
  }
}

function buildPayload() {
  // Collect all selected recipient_ids and group_ids regardless of mode
  const recipientIds = form.value.recipient_ids || []
  const groupIds = form.value.group_ids || []

  return {
    name: form.value.name,
    description: form.value.description,
    phishing_domain: form.value.phishing_domain || undefined,
    email_template_ids: form.value.template_ids,
    recipient_ids: recipientIds.length > 0 ? recipientIds : undefined,
    group_ids: groupIds.length > 0 ? groupIds : undefined,
    send_mode: form.value.send_mode || 'immediate',
    stagger_minutes: form.value.send_mode === 'staggered' ? (form.value.stagger_interval || 1) : undefined,
    scheduled_at: form.value.scheduled_at || undefined
  }
}

async function fetchData() {
  loadingTemplates.value = true
  try {
    const fetches = [
      get('/api/templates?active=true'),
      get('/api/recipients/groups'),
      get('/api/recipients/departments')
    ]
    // If editing, also fetch the campaign
    if (isEditMode.value) {
      fetches.push(get(`/api/campaigns/${campaignId.value}`))
    }

    const results = await Promise.all(fetches)
    const [tplData, grpData, deptData] = results
    templates.value = tplData?.data || tplData || []
    groups.value = grpData?.data || grpData || []
    departments.value = Array.isArray(deptData) ? deptData : (deptData?.data || [])

    // Load first page of recipients
    await fetchRecipientPage(1)

    // Pre-fill form if editing
    if (isEditMode.value && results[3]) {
      const c = results[3]
      form.value.name = c.name || ''
      form.value.description = c.description || ''
      form.value.phishing_domain = c.phishing_domain || ''
      form.value.template_ids = c.template_ids || (c.email_template_id ? [c.email_template_id] : [])
      form.value.send_mode = c.send_mode || 'immediate'
      form.value.stagger_interval = c.stagger_minutes || 60
      form.value.scheduled_at = c.scheduled_at || ''

      // Load campaign recipients
      try {
        const crData = await get(`/api/campaigns/${campaignId.value}/recipients`)
        const crList = crData?.data || crData || []
        form.value.recipient_ids = crList.map(cr => cr.recipient_id || cr.id)
        recipientMode.value = 'individual'
      } catch {
        // Graceful fallback
      }
    }
  } catch {
    // Error handled by useApi
  } finally {
    loadingTemplates.value = false
  }
}

onMounted(fetchData)
</script>
