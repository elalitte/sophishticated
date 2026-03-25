<template>
  <AppLayout>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-3">
        <router-link
          to="/templates/pages"
          class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg transition-colors"
        >
          <ArrowLeft class="w-5 h-5" />
        </router-link>
        <div>
          <h1 class="text-2xl font-bold text-gray-900">
            {{ isEditing ? 'Modifier la page' : 'Nouvelle page' }}
          </h1>
          <p class="mt-0.5 text-sm text-gray-500">{{ isEditing ? form.name : 'Créez une page d\'atterrissage' }}</p>
        </div>
      </div>
      <div class="flex items-center gap-3">
        <button
          @click="openPreview"
          class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
        >
          <ExternalLink class="w-4 h-4" />
          Prévisualiser
        </button>
        <button
          @click="save(false)"
          :disabled="saving"
          class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-brand-700 bg-brand-50 border border-brand-200 rounded-lg hover:bg-brand-100 transition-colors disabled:opacity-50"
        >
          <Save class="w-4 h-4" />
          Enregistrer
        </button>
        <button
          @click="save(true)"
          :disabled="saving"
          class="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-white bg-brand-500 rounded-lg hover:bg-brand-600 transition-colors disabled:opacity-50"
        >
          <Check class="w-4 h-4" />
          Enregistrer et fermer
        </button>
      </div>
    </div>

    <!-- Validation errors -->
    <div v-if="Object.keys(errors).length" class="p-4 bg-red-50 border border-red-200 rounded-lg">
      <div class="flex items-center gap-2 mb-2">
        <AlertCircle class="w-5 h-5 text-red-500" />
        <h3 class="text-sm font-medium text-red-800">Veuillez corriger les erreurs suivantes :</h3>
      </div>
      <ul class="list-disc list-inside text-sm text-red-700 space-y-1">
        <li v-for="(msgs, field) in errors" :key="field">
          {{ Array.isArray(msgs) ? msgs.join(', ') : msgs }}
        </li>
      </ul>
    </div>

    <!-- General info -->
    <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-4">
      <h2 class="text-base font-semibold text-gray-900">Informations générales</h2>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Nom de la page *</label>
          <input
            v-model="form.name"
            type="text"
            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-brand-400 focus:border-brand-400"
            placeholder="Ex: Page de connexion Microsoft 365"
          />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">URL de redirection</label>
          <input
            v-model="form.redirect_url"
            type="url"
            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-brand-400 focus:border-brand-400"
            placeholder="https://exemple.fr/sensibilisation"
          />
        </div>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
        <textarea
          v-model="form.description"
          rows="2"
          class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-brand-400 focus:border-brand-400 resize-none"
          placeholder="Description de la page..."
        />
      </div>
    </div>

    <!-- Tabs -->
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
      <div class="flex border-b border-gray-200">
        <button
          v-for="tab in tabs"
          :key="tab.id"
          @click="activeTab = tab.id"
          :class="[
            'px-5 py-3 text-sm font-medium border-b-2 transition-colors',
            activeTab === tab.id
              ? 'text-brand-600 border-brand-500 bg-brand-50/50'
              : 'text-gray-500 border-transparent hover:text-gray-700 hover:bg-gray-50'
          ]"
        >
          <div class="flex items-center gap-1.5">
            <component :is="tab.icon" class="w-4 h-4" />
            {{ tab.label }}
          </div>
        </button>
      </div>

      <!-- Tab: Phishing page HTML -->
      <div v-show="activeTab === 'phishing'" class="p-0">
        <textarea
          v-model="form.html_content"
          class="w-full px-4 py-3 font-mono text-sm text-gray-800 border-0 focus:ring-0 resize-none"
          style="min-height: 450px;"
          placeholder="Code HTML de la page de phishing..."
          spellcheck="false"
        />
      </div>

      <!-- Tab: Capture fields -->
      <div v-show="activeTab === 'fields'" class="p-5 space-y-4">
        <div class="flex items-center justify-between">
          <p class="text-sm text-gray-500">Définissez les champs de formulaire à capturer.</p>
          <button
            @click="addCaptureField"
            class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-brand-700 bg-brand-50 border border-brand-200 rounded-lg hover:bg-brand-100"
          >
            <Plus class="w-4 h-4" />
            Ajouter un champ
          </button>
        </div>

        <!-- Empty state -->
        <div v-if="form.capture_fields.length === 0" class="text-center py-10">
          <FormInput class="w-10 h-10 text-gray-300 mx-auto mb-2" />
          <p class="text-sm text-gray-500">Aucun champ de capture défini</p>
        </div>

        <!-- Fields table -->
        <div v-else class="space-y-3">
          <div
            v-for="(field, idx) in form.capture_fields"
            :key="idx"
            class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg border border-gray-200"
          >
            <div class="flex-1">
              <label class="block text-xs font-medium text-gray-500 mb-1">Nom du champ</label>
              <input
                v-model="field.name"
                type="text"
                class="w-full px-2.5 py-1.5 border border-gray-300 rounded-md text-sm focus:ring-2 focus:ring-brand-400 focus:border-brand-400"
                placeholder="email"
              />
            </div>
            <div class="flex-1">
              <label class="block text-xs font-medium text-gray-500 mb-1">Label affiché</label>
              <input
                v-model="field.label"
                type="text"
                class="w-full px-2.5 py-1.5 border border-gray-300 rounded-md text-sm focus:ring-2 focus:ring-brand-400 focus:border-brand-400"
                placeholder="Adresse email"
              />
            </div>
            <div class="w-40">
              <label class="block text-xs font-medium text-gray-500 mb-1">Type</label>
              <select
                v-model="field.type"
                class="w-full px-2.5 py-1.5 border border-gray-300 rounded-md text-sm focus:ring-2 focus:ring-brand-400 focus:border-brand-400"
              >
                <option value="text">Texte</option>
                <option value="email">Email</option>
                <option value="password">Mot de passe</option>
                <option value="tel">Téléphone</option>
                <option value="number">Nombre</option>
                <option value="textarea">Zone de texte</option>
              </select>
            </div>
            <button
              @click="form.capture_fields.splice(idx, 1)"
              class="mt-5 p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors"
              title="Supprimer"
            >
              <Trash2 class="w-4 h-4" />
            </button>
          </div>
        </div>
      </div>

      <!-- Tab: Awareness page -->
      <div v-show="activeTab === 'awareness'" class="p-0">
        <div class="px-4 py-3 bg-blue-50 border-b border-blue-100">
          <p class="text-sm text-blue-700">
            Cette page sera affichée après la soumission du formulaire pour sensibiliser l'utilisateur.
          </p>
        </div>
        <textarea
          v-model="form.awareness_html"
          class="w-full px-4 py-3 font-mono text-sm text-gray-800 border-0 focus:ring-0 resize-none"
          style="min-height: 400px;"
          placeholder="Code HTML de la page de sensibilisation..."
          spellcheck="false"
        />
      </div>
    </div>
  </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted, markRaw } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import { useRoute, useRouter } from 'vue-router'
import {
  ArrowLeft, ExternalLink, Save, Check, Plus, Trash2,
  AlertCircle, Globe, FormInput, ShieldAlert
} from 'lucide-vue-next'
import { useApi } from '../../composables/useApi'
import { useNotificationStore } from '../../stores/notifications'

const route = useRoute()
const router = useRouter()
const { loading, get, post, put } = useApi()
const notifications = useNotificationStore()

const isEditing = computed(() => !!route.params.id)
const saving = ref(false)
const errors = ref({})
const activeTab = ref('phishing')

const tabs = [
  { id: 'phishing', label: 'Page de phishing', icon: markRaw(Globe) },
  { id: 'fields', label: 'Champs de capture', icon: markRaw(FormInput) },
  { id: 'awareness', label: 'Page de sensibilisation', icon: markRaw(ShieldAlert) }
]

const form = ref({
  name: '',
  description: '',
  redirect_url: '',
  html_content: '',
  awareness_html: '',
  capture_fields: []
})

function addCaptureField() {
  form.value.capture_fields.push({
    name: '',
    label: '',
    type: 'text'
  })
}

function openPreview() {
  const previewWindow = window.open('', '_blank')
  if (previewWindow) {
    previewWindow.document.write(form.value.html_content || '<p>Aucun contenu</p>')
    previewWindow.document.close()
  }
}

async function save(andClose) {
  saving.value = true
  errors.value = {}
  try {
    let result
    if (isEditing.value) {
      result = await put(`/api/landing-pages/${route.params.id}`, form.value)
    } else {
      result = await post('/api/landing-pages', form.value)
    }
    if (result?.errors) {
      errors.value = result.errors
      return
    }
    notifications.success(isEditing.value ? 'Page mise à jour' : 'Page créée')
    if (andClose) {
      router.push('/templates/pages')
    } else if (!isEditing.value && result?.id) {
      router.replace(`/templates/pages/${result.id}/edit`)
    }
  } catch {
    // Error handled by useApi
  } finally {
    saving.value = false
  }
}

async function fetchPage() {
  if (!route.params.id) return
  try {
    const data = await get(`/api/landing-pages/${route.params.id}`)
    form.value = {
      name: data.name || '',
      description: data.description || '',
      redirect_url: data.redirect_url || '',
      html_content: data.html_content || '',
      awareness_html: data.awareness_html || '',
      capture_fields: data.capture_fields || []
    }
  } catch {
    // Error handled by useApi
  }
}

onMounted(() => {
  if (isEditing.value) {
    fetchPage()
  }
})
</script>
