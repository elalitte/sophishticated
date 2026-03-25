<template>
  <AppLayout>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-3">
        <router-link
          to="/templates"
          class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg transition-colors"
        >
          <ArrowLeft class="w-5 h-5" />
        </router-link>
        <div>
          <h1 class="text-2xl font-bold text-gray-900">
            {{ isEditing ? 'Modifier le template' : 'Nouveau template' }}
          </h1>
          <p class="mt-0.5 text-sm text-gray-500">{{ isEditing ? form.name : 'Créez un modèle d\'email de phishing simulé' }}</p>
        </div>
      </div>
      <div class="flex items-center gap-3">
        <button
          @click="showPreview = true"
          class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
        >
          <Eye class="w-4 h-4" />
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

    <!-- Main content: two columns -->
    <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">
      <!-- Left column: form fields -->
      <div class="lg:col-span-2 space-y-5">
        <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-4">
          <h2 class="text-base font-semibold text-gray-900">Informations générales</h2>

          <!-- Name -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Nom du template *</label>
            <input
              v-model="form.name"
              type="text"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-brand-400 focus:border-brand-400"
              placeholder="Ex: Faux email RH - Congés"
            />
          </div>

          <!-- Description -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
            <textarea
              v-model="form.description"
              rows="2"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-brand-400 focus:border-brand-400 resize-none"
              placeholder="Description courte du template..."
            />
          </div>

          <!-- Subject -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Objet de l'email *</label>
            <input
              v-model="form.subject"
              type="text"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-brand-400 focus:border-brand-400"
              placeholder="Ex: Action requise : validation de vos congés"
            />
          </div>

          <!-- Sender name -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Nom de l'expéditeur *</label>
            <input
              v-model="form.sender_name"
              type="text"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-brand-400 focus:border-brand-400"
              placeholder="Ex: Service des Ressources Humaines"
            />
          </div>

          <!-- Sender email -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Email de l'expéditeur *</label>
            <input
              v-model="form.sender_email"
              type="email"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-brand-400 focus:border-brand-400"
              placeholder="Ex: rh@exemple-entreprise.fr"
            />
          </div>

          <!-- Difficulty -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Difficulté</label>
            <div class="flex items-center gap-1">
              <button
                v-for="n in 5"
                :key="n"
                @click="form.difficulty_level = n"
                class="p-0.5 focus:outline-none"
              >
                <Star
                  class="w-6 h-6 transition-colors"
                  :class="n <= form.difficulty_level ? 'text-yellow-400 fill-yellow-400' : 'text-gray-300 hover:text-yellow-200'"
                />
              </button>
              <span class="text-sm text-gray-500 ml-2">{{ form.difficulty_level }}/5</span>
            </div>
          </div>

          <!-- Tags -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Tags</label>
            <div class="flex flex-wrap gap-1.5 mb-2">
              <span
                v-for="(tag, idx) in form.tags"
                :key="idx"
                class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-brand-50 text-brand-700 border border-brand-200"
              >
                {{ tag }}
                <button @click="form.tags.splice(idx, 1)" class="hover:text-red-500">
                  <X class="w-3 h-3" />
                </button>
              </span>
            </div>
            <div class="flex gap-2">
              <input
                v-model="newTag"
                @keydown.enter.prevent="addTag"
                type="text"
                class="flex-1 px-3 py-1.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-brand-400 focus:border-brand-400"
                placeholder="Ajouter un tag..."
              />
              <button
                @click="addTag"
                class="px-3 py-1.5 text-sm font-medium text-brand-700 bg-brand-50 border border-brand-200 rounded-lg hover:bg-brand-100"
              >
                Ajouter
              </button>
            </div>
          </div>

          <!-- Landing page -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Page d'atterrissage</label>
            <select
              v-model="form.landing_page_id"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-brand-400 focus:border-brand-400"
            >
              <option :value="null">Aucune</option>
              <option v-for="page in landingPages" :key="page.id" :value="page.id">
                {{ page.name }}
              </option>
            </select>
          </div>
        </div>
      </div>

      <!-- Right column: HTML editor -->
      <div class="lg:col-span-3 space-y-0">
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
          <!-- Toolbar -->
          <div class="flex items-center justify-between px-4 py-2.5 border-b border-gray-200 bg-gray-50">
            <div class="flex items-center gap-2">
              <h2 class="text-sm font-semibold text-gray-700">Contenu HTML</h2>
            </div>
            <div class="flex items-center gap-2">
              <!-- Variable dropdown -->
              <div class="relative" ref="variableDropdownRef">
                <button
                  @click="showVariableDropdown = !showVariableDropdown"
                  class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50"
                >
                  <Code class="w-3.5 h-3.5" />
                  Insérer une variable
                  <ChevronDown class="w-3 h-3" />
                </button>
                <div
                  v-if="showVariableDropdown"
                  class="absolute right-0 mt-1 w-56 bg-white border border-gray-200 rounded-lg shadow-lg z-10"
                >
                  <div class="py-1">
                    <button
                      v-for="v in variables"
                      :key="v.value"
                      @click="insertVariable(v.value)"
                      class="w-full flex items-center justify-between px-3 py-2 text-sm text-gray-700 hover:bg-gray-50"
                    >
                      <span>{{ v.label }}</span>
                      <code class="text-xs text-gray-400 bg-gray-100 px-1.5 py-0.5 rounded">{{ v.value }}</code>
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Editor tabs -->
          <div class="flex border-b border-gray-200">
            <button
              @click="activeTab = 'visual'"
              :class="[
                'px-4 py-2.5 text-sm font-medium border-b-2 transition-colors',
                activeTab === 'visual'
                  ? 'text-brand-600 border-brand-500'
                  : 'text-gray-500 border-transparent hover:text-gray-700'
              ]"
            >
              <div class="flex items-center gap-1.5">
                <Eye class="w-4 h-4" />
                Éditeur visuel
              </div>
            </button>
            <button
              @click="activeTab = 'source'"
              :class="[
                'px-4 py-2.5 text-sm font-medium border-b-2 transition-colors',
                activeTab === 'source'
                  ? 'text-brand-600 border-brand-500'
                  : 'text-gray-500 border-transparent hover:text-gray-700'
              ]"
            >
              <div class="flex items-center gap-1.5">
                <Code class="w-4 h-4" />
                Code source HTML
              </div>
            </button>
          </div>

          <!-- Visual editor (iframe) -->
          <div v-show="activeTab === 'visual'" class="relative">
            <iframe
              ref="previewIframe"
              :srcdoc="form.html_body || '<p style=&quot;color:#999;padding:40px;font-family:sans-serif;&quot;>Saisissez le code HTML dans l\'onglet Code source...</p>'"
              class="w-full border-0"
              style="min-height: 500px;"
              sandbox="allow-same-origin"
            />
          </div>

          <!-- Source editor (textarea) -->
          <div v-show="activeTab === 'source'">
            <textarea
              ref="sourceTextarea"
              v-model="form.html_body"
              class="w-full px-4 py-3 font-mono text-sm text-gray-800 border-0 focus:ring-0 resize-none"
              style="min-height: 500px;"
              placeholder="Collez ou rédigez le code HTML de l'email ici..."
              spellcheck="false"
            />
          </div>
        </div>
      </div>
    </div>

    <!-- Preview modal -->
    <Modal :show="showPreview" title="Prévisualisation du template" max-width="4xl" @close="showPreview = false">
      <div class="space-y-3">
        <div class="grid grid-cols-2 gap-4 text-sm">
          <div>
            <span class="text-gray-500">De :</span>
            <span class="ml-1 font-medium">{{ form.sender_name }} &lt;{{ form.sender_email }}&gt;</span>
          </div>
          <div>
            <span class="text-gray-500">Objet :</span>
            <span class="ml-1 font-medium">{{ previewSubject }}</span>
          </div>
        </div>
        <div class="border-t border-gray-200 pt-3">
          <iframe
            :srcdoc="previewHtml"
            class="w-full border border-gray-200 rounded-lg"
            style="min-height: 500px;"
            sandbox="allow-same-origin"
          />
        </div>
      </div>
    </Modal>
  </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import { useRoute, useRouter } from 'vue-router'
import { ArrowLeft, Eye, Save, Check, Star, X, Code, ChevronDown, AlertCircle } from 'lucide-vue-next'
import { useApi } from '../../composables/useApi'
import { useNotificationStore } from '../../stores/notifications'
import Modal from '../../components/ui/Modal.vue'

const route = useRoute()
const router = useRouter()
const { loading, get, post, put } = useApi()
const notifications = useNotificationStore()

const isEditing = computed(() => !!route.params.id)
const saving = ref(false)
const errors = ref({})
const showPreview = ref(false)
const showVariableDropdown = ref(false)
const variableDropdownRef = ref(null)
const activeTab = ref('source')
const newTag = ref('')
const landingPages = ref([])
const sourceTextarea = ref(null)

const form = ref({
  name: '',
  description: '',
  subject: '',
  sender_name: '',
  sender_email: '',
  difficulty_level: 3,
  tags: [],
  landing_page_id: null,
  html_body: ''
})

const variables = [
  { label: 'Prénom', value: '{{prenom}}' },
  { label: 'Nom', value: '{{nom}}' },
  { label: 'Nom complet', value: '{{nom_complet}}' },
  { label: 'Email', value: '{{email}}' },
  { label: 'Entreprise', value: '{{entreprise}}' },
  { label: 'Département', value: '{{departement}}' },
  { label: 'Lien de phishing', value: '{{phishing_link}}' },
  { label: 'Pixel de suivi', value: '{{tracking_pixel}}' },
  { label: 'Date J-1 courte', value: '{{date_j-1_courte}}' },
  { label: 'Date J-1 (heure)', value: '{{date_j-1_heure}}' },
  { label: 'Date J-2 courte', value: '{{date_j-2_courte}}' },
  { label: 'Date J-3 courte', value: '{{date_j-3_courte}}' },
  { label: 'Date J-5 courte', value: '{{date_j-5_courte}}' },
  { label: 'Date J-5', value: '{{date_j-5}}' },
  { label: 'Date J-7', value: '{{date_j-7}}' },
  { label: 'Date J-15', value: '{{date_j-15}}' },
  { label: 'Date J+2', value: '{{date_j+2}}' },
  { label: 'Date J+2 courte', value: '{{date_j+2_courte}}' },
  { label: 'Date J+5', value: '{{date_j+5}}' },
]

const sampleData = {
  '{{prenom}}': 'Marie',
  '{{nom}}': 'Dupont',
  '{{nom_complet}}': 'Marie Dupont',
  '{{email}}': 'marie.dupont@entreprise.fr',
  '{{entreprise}}': 'Acme Corp',
  '{{departement}}': 'Direction Commerciale',
  '{{phishing_link}}': '#',
  '{{tracking_pixel}}': '<img src="#" width="1" height="1" />'
}

const previewHtml = computed(() => {
  let html = form.value.html_body || ''
  Object.entries(sampleData).forEach(([key, value]) => {
    html = html.replaceAll(key, value)
  })
  // Resolve date variables for preview
  html = html.replace(/\{\{date_j([+-]\d+)(?:_(courte|heure))?\}\}/g, (match, offset, fmt) => {
    const d = new Date()
    d.setDate(d.getDate() + parseInt(offset, 10))
    const day = d.getDate()
    const month = d.getMonth() + 1
    const year = d.getFullYear()
    const moisFr = ['', 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre']
    const moisShort = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']
    if (fmt === 'courte') return String(day).padStart(2, '0') + '/' + String(month).padStart(2, '0') + '/' + year
    if (fmt === 'heure') return `${day} ${moisShort[month]} ${year}, ${String(7 + Math.floor(Math.random() * 12)).padStart(2, '0')}:${String(10 + Math.floor(Math.random() * 50)).padStart(2, '0')} (UTC+1)`
    return `${day === 1 ? '1er' : day} ${moisFr[month]} ${year}`
  })
  return html
})

const previewSubject = computed(() => {
  let subj = form.value.subject || ''
  Object.entries(sampleData).forEach(([key, value]) => {
    subj = subj.replaceAll(key, value)
  })
  // Resolve date variables in subject preview
  subj = subj.replace(/\{\{date_j([+-]\d+)(?:_(courte|heure))?\}\}/g, (match, offset, fmt) => {
    const d = new Date()
    d.setDate(d.getDate() + parseInt(offset, 10))
    const day = d.getDate()
    const month = d.getMonth() + 1
    const year = d.getFullYear()
    const moisFr = ['', 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre']
    const moisShort = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']
    if (fmt === 'courte') return String(day).padStart(2, '0') + '/' + String(month).padStart(2, '0') + '/' + year
    if (fmt === 'heure') return `${day} ${moisShort[month]} ${year}, ${String(7 + Math.floor(Math.random() * 12)).padStart(2, '0')}:${String(10 + Math.floor(Math.random() * 50)).padStart(2, '0')} (UTC+1)`
    return `${day === 1 ? '1er' : day} ${moisFr[month]} ${year}`
  })
  return subj
})

function addTag() {
  const tag = newTag.value.trim()
  if (tag && !form.value.tags.includes(tag)) {
    form.value.tags.push(tag)
  }
  newTag.value = ''
}

function insertVariable(variable) {
  if (activeTab.value === 'source' && sourceTextarea.value) {
    const el = sourceTextarea.value
    const start = el.selectionStart
    const end = el.selectionEnd
    const text = form.value.html_body
    form.value.html_body = text.substring(0, start) + variable + text.substring(end)
    showVariableDropdown.value = false
    // Restore cursor position
    requestAnimationFrame(() => {
      el.focus()
      el.selectionStart = el.selectionEnd = start + variable.length
    })
  } else {
    form.value.html_body += variable
    showVariableDropdown.value = false
  }
}

function handleClickOutside(event) {
  if (variableDropdownRef.value && !variableDropdownRef.value.contains(event.target)) {
    showVariableDropdown.value = false
  }
}

async function save(andClose) {
  saving.value = true
  errors.value = {}
  try {
    let result
    if (isEditing.value) {
      result = await put(`/api/templates/${route.params.id}`, form.value)
    } else {
      result = await post('/api/templates', form.value)
    }
    if (result?.errors) {
      errors.value = result.errors
      return
    }
    notifications.success(isEditing.value ? 'Template mis à jour' : 'Template créé')
    if (andClose) {
      router.push('/templates')
    } else if (!isEditing.value && result?.id) {
      router.replace(`/templates/${result.id}/edit`)
    }
  } catch {
    // Error handled by useApi
  } finally {
    saving.value = false
  }
}

async function fetchTemplate() {
  if (!route.params.id) return
  try {
    const data = await get(`/api/templates/${route.params.id}`)
    form.value = {
      name: data.name || '',
      description: data.description || '',
      subject: data.subject || '',
      sender_name: data.sender_name || '',
      sender_email: data.sender_email || '',
      difficulty_level: data.difficulty_level || 3,
      tags: typeof data.tags === 'string' ? JSON.parse(data.tags || '[]') : (data.tags || []),
      landing_page_id: data.landing_page_id || null,
      html_body: data.html_body || ''
    }
  } catch {
    // Error handled by useApi
  }
}

async function fetchLandingPages() {
  try {
    const response = await get('/api/landing-pages')
    landingPages.value = response.data || response || []
  } catch {
    // Error handled by useApi
  }
}

onMounted(() => {
  document.addEventListener('click', handleClickOutside)
  fetchLandingPages()
  if (isEditing.value) {
    fetchTemplate()
  }
})

onBeforeUnmount(() => {
  document.removeEventListener('click', handleClickOutside)
})
</script>
