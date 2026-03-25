<template>
  <AppLayout>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Packs de templates</h1>
        <p class="mt-1 text-sm text-gray-500">Importez des packs de templates d'email et de pages d'atterrissage</p>
      </div>
      <div class="flex items-center gap-3">
        <label
          class="inline-flex items-center gap-2 px-4 py-2.5 bg-white text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors font-medium text-sm cursor-pointer"
        >
          <Upload class="w-4 h-4" />
          Importer un fichier
          <input type="file" accept=".json" class="hidden" @change="handleFileUpload" />
        </label>
        <button
          @click="showExportDialog = true"
          class="inline-flex items-center gap-2 px-4 py-2.5 bg-white text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors font-medium text-sm"
        >
          <Download class="w-4 h-4" />
          Exporter
        </button>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
      <div v-for="i in 3" :key="i" class="bg-white rounded-xl border border-gray-200 p-6 animate-pulse">
        <div class="h-5 bg-gray-200 rounded w-3/4 mb-3" />
        <div class="h-4 bg-gray-200 rounded w-full mb-2" />
        <div class="h-4 bg-gray-200 rounded w-1/2 mb-4" />
        <div class="h-8 bg-gray-200 rounded w-1/3" />
      </div>
    </div>

    <!-- Empty state -->
    <div v-else-if="packs.length === 0" class="text-center py-16 bg-white rounded-xl border border-gray-200">
      <Package class="w-12 h-12 text-gray-300 mx-auto mb-3" />
      <h3 class="text-lg font-medium text-gray-900 mb-1">Aucun pack disponible</h3>
      <p class="text-sm text-gray-500 mb-4">
        Importez un fichier JSON contenant des templates et des pages.
      </p>
    </div>

    <!-- Packs grid -->
    <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
      <div
        v-for="pack in packs"
        :key="pack.filename"
        class="bg-white rounded-xl border border-gray-200 hover:shadow-md transition-shadow"
      >
        <div class="p-6">
          <div class="flex items-start justify-between mb-2">
            <h3 class="font-semibold text-gray-900 text-lg">{{ pack.name }}</h3>
            <span class="text-xs text-gray-400 font-mono">v{{ pack.version }}</span>
          </div>

          <p v-if="pack.author" class="text-xs text-gray-400 mb-2">
            Par {{ pack.author }}
          </p>

          <p v-if="pack.description" class="text-sm text-gray-500 mb-4 line-clamp-2">
            {{ pack.description }}
          </p>

          <div class="flex items-center gap-4 mb-4">
            <div class="flex items-center gap-1.5 text-sm text-gray-600">
              <Mail class="w-4 h-4 text-brand-500" />
              <span>{{ pack.email_templates_count }} template{{ pack.email_templates_count !== 1 ? 's' : '' }}</span>
            </div>
            <div class="flex items-center gap-1.5 text-sm text-gray-600">
              <Globe class="w-4 h-4 text-blue-500" />
              <span>{{ pack.landing_pages_count }} page{{ pack.landing_pages_count !== 1 ? 's' : '' }}</span>
            </div>
          </div>
        </div>

        <div class="flex items-center gap-2 px-6 py-3 border-t border-gray-100 bg-gray-50/50 rounded-b-xl">
          <button
            @click="previewPack(pack)"
            class="flex-1 inline-flex items-center justify-center gap-2 px-3 py-2 text-sm text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
          >
            <Eye class="w-4 h-4" />
            Apercu
          </button>
          <button
            @click="confirmImport(pack)"
            class="flex-1 inline-flex items-center justify-center gap-2 px-3 py-2 text-sm text-white bg-brand-500 rounded-lg hover:bg-brand-600 transition-colors font-medium"
            :disabled="importing"
          >
            <Download class="w-4 h-4" />
            Importer
          </button>
        </div>
      </div>
    </div>

    <!-- Preview modal -->
    <Teleport to="body">
      <div v-if="previewData" class="fixed inset-0 z-50 flex items-center justify-center p-4" @click.self="previewData = null">
        <div class="fixed inset-0 bg-black/50" @click="previewData = null" />
        <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-2xl max-h-[80vh] overflow-hidden flex flex-col z-10">
          <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">{{ previewData.name }}</h2>
            <button @click="previewData = null" class="p-1 text-gray-400 hover:text-gray-600 rounded">
              <X class="w-5 h-5" />
            </button>
          </div>
          <div class="overflow-y-auto p-6 space-y-6">
            <p v-if="previewData.description" class="text-sm text-gray-600">{{ previewData.description }}</p>

            <!-- Landing pages -->
            <div v-if="previewData.landing_pages?.length">
              <h3 class="font-medium text-gray-900 mb-3 flex items-center gap-2">
                <Globe class="w-4 h-4 text-blue-500" />
                Pages d'atterrissage ({{ previewData.landing_pages.length }})
              </h3>
              <div class="space-y-2">
                <div
                  v-for="lp in previewData.landing_pages"
                  :key="lp.ref"
                  class="p-3 bg-gray-50 rounded-lg border border-gray-200"
                >
                  <p class="font-medium text-sm text-gray-900">{{ lp.name }}</p>
                  <p v-if="lp.description" class="text-xs text-gray-500 mt-0.5">{{ lp.description }}</p>
                  <div v-if="lp.capture_fields" class="flex items-center gap-1 mt-1.5 text-xs text-gray-400">
                    <FormInput class="w-3 h-3" />
                    <span>{{ Array.isArray(lp.capture_fields) ? lp.capture_fields.length : 0 }} champ(s) de capture</span>
                  </div>
                </div>
              </div>
            </div>

            <!-- Email templates -->
            <div v-if="previewData.email_templates?.length">
              <h3 class="font-medium text-gray-900 mb-3 flex items-center gap-2">
                <Mail class="w-4 h-4 text-brand-500" />
                Templates d'email ({{ previewData.email_templates.length }})
              </h3>
              <div class="space-y-2">
                <div
                  v-for="(tpl, idx) in previewData.email_templates"
                  :key="idx"
                  class="p-3 bg-gray-50 rounded-lg border border-gray-200"
                >
                  <div class="flex items-start justify-between">
                    <p class="font-medium text-sm text-gray-900">{{ tpl.name }}</p>
                    <div class="flex items-center gap-0.5 ml-2 flex-shrink-0">
                      <Star
                        v-for="n in 5"
                        :key="n"
                        class="w-3 h-3"
                        :class="n <= tpl.difficulty_level ? 'text-yellow-400 fill-yellow-400' : 'text-gray-200'"
                      />
                    </div>
                  </div>
                  <p class="text-xs text-gray-500 mt-0.5">Objet : {{ tpl.subject }}</p>
                  <p class="text-xs text-gray-400">De : {{ tpl.sender_name }} &lt;{{ tpl.sender_email }}&gt;</p>
                  <div v-if="tpl.tags?.length" class="flex flex-wrap gap-1 mt-2">
                    <span
                      v-for="tag in tpl.tags"
                      :key="tag"
                      class="px-1.5 py-0.5 text-xs bg-brand-50 text-brand-700 rounded border border-brand-200"
                    >{{ tag }}</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-200 bg-gray-50">
            <button
              @click="previewData = null"
              class="px-4 py-2 text-sm text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50"
            >
              Fermer
            </button>
            <button
              @click="importPack(previewData); previewData = null"
              class="px-4 py-2 text-sm text-white bg-brand-500 rounded-lg hover:bg-brand-600 font-medium"
              :disabled="importing"
            >
              Importer ce pack
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Export dialog -->
    <Teleport to="body">
      <div v-if="showExportDialog" class="fixed inset-0 z-50 flex items-center justify-center p-4" @click.self="showExportDialog = false">
        <div class="fixed inset-0 bg-black/50" @click="showExportDialog = false" />
        <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-lg z-10">
          <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">Exporter un pack</h2>
            <button @click="showExportDialog = false" class="p-1 text-gray-400 hover:text-gray-600 rounded">
              <X class="w-5 h-5" />
            </button>
          </div>
          <div class="p-6 space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Nom du pack</label>
              <input
                v-model="exportForm.name"
                type="text"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-brand-400 focus:border-brand-400"
                placeholder="Mon pack de templates"
              />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
              <textarea
                v-model="exportForm.description"
                rows="2"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-brand-400 focus:border-brand-400"
                placeholder="Description du pack..."
              />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Templates d'email</label>
              <div class="max-h-40 overflow-y-auto border border-gray-200 rounded-lg divide-y divide-gray-100">
                <label
                  v-for="tpl in allTemplates"
                  :key="tpl.id"
                  class="flex items-center gap-3 px-3 py-2 hover:bg-gray-50 cursor-pointer"
                >
                  <input type="checkbox" v-model="exportForm.template_ids" :value="tpl.id" class="rounded text-brand-500 focus:ring-brand-400" />
                  <span class="text-sm text-gray-700">{{ tpl.name }}</span>
                </label>
                <p v-if="allTemplates.length === 0" class="px-3 py-2 text-sm text-gray-400">Aucun template</p>
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Pages d'atterrissage</label>
              <div class="max-h-40 overflow-y-auto border border-gray-200 rounded-lg divide-y divide-gray-100">
                <label
                  v-for="lp in allLandingPages"
                  :key="lp.id"
                  class="flex items-center gap-3 px-3 py-2 hover:bg-gray-50 cursor-pointer"
                >
                  <input type="checkbox" v-model="exportForm.landing_page_ids" :value="lp.id" class="rounded text-brand-500 focus:ring-brand-400" />
                  <span class="text-sm text-gray-700">{{ lp.name }}</span>
                </label>
                <p v-if="allLandingPages.length === 0" class="px-3 py-2 text-sm text-gray-400">Aucune page</p>
              </div>
            </div>
          </div>
          <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-200 bg-gray-50 rounded-b-xl">
            <button
              @click="showExportDialog = false"
              class="px-4 py-2 text-sm text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50"
            >
              Annuler
            </button>
            <button
              @click="doExport"
              class="px-4 py-2 text-sm text-white bg-brand-500 rounded-lg hover:bg-brand-600 font-medium"
              :disabled="exportForm.template_ids.length === 0 && exportForm.landing_page_ids.length === 0"
            >
              Exporter en JSON
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Import confirmation -->
    <ConfirmDialog
      :show="showImportDialog"
      title="Importer le pack"
      :message="importMessage"
      confirm-text="Importer"
      variant="primary"
      @confirm="doImport"
      @cancel="showImportDialog = false"
    />
  </div>
  </AppLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import { Upload, Download, Eye, Mail, Globe, Star, Package, X, FormInput } from 'lucide-vue-next'
import { useApi } from '../../composables/useApi'
import { useNotificationStore } from '../../stores/notifications'
import ConfirmDialog from '../../components/ui/ConfirmDialog.vue'

const { loading, get, post } = useApi()
const notifications = useNotificationStore()

const packs = ref([])
const previewData = ref(null)
const importing = ref(false)
const showImportDialog = ref(false)
const packToImport = ref(null)

// Export
const showExportDialog = ref(false)
const allTemplates = ref([])
const allLandingPages = ref([])
const exportForm = ref({
  name: '',
  description: '',
  template_ids: [],
  landing_page_ids: []
})

const importMessage = ref('')

async function fetchPacks() {
  try {
    const response = await get('/api/packs')
    packs.value = response.data || response || []
  } catch {
    // handled by useApi
  }
}

async function previewPack(pack) {
  try {
    const response = await get(`/api/packs/${encodeURIComponent(pack.filename)}`)
    previewData.value = response
  } catch {
    // handled by useApi
  }
}

function confirmImport(pack) {
  packToImport.value = pack
  importMessage.value = `Importer le pack "${pack.name}" ? Cela ajoutera ${pack.email_templates_count} template(s) et ${pack.landing_pages_count} page(s) d'atterrissage.`
  showImportDialog.value = true
}

async function doImport() {
  showImportDialog.value = false
  await importPack(packToImport.value)
}

async function importPack(pack) {
  importing.value = true
  try {
    const payload = pack.filename
      ? { filename: pack.filename }
      : { pack }

    const result = await post('/api/packs/import', payload)
    notifications.success(
      `Pack importé : ${result.imported_templates} template(s) et ${result.imported_landing_pages} page(s)`
    )
  } catch {
    // handled by useApi
  } finally {
    importing.value = false
  }
}

async function handleFileUpload(event) {
  const file = event.target.files[0]
  if (!file) return

  try {
    const text = await file.text()
    const pack = JSON.parse(text)

    if (!pack.name && !pack.email_templates && !pack.landing_pages) {
      notifications.error('Le fichier JSON ne semble pas être un pack valide')
      return
    }

    // Show preview first
    previewData.value = pack
  } catch {
    notifications.error('Impossible de lire le fichier JSON')
  }

  // Reset input
  event.target.value = ''
}

async function fetchExportData() {
  try {
    const [tplRes, lpRes] = await Promise.all([
      get('/api/templates'),
      get('/api/landing-pages')
    ])
    allTemplates.value = tplRes.data || tplRes || []
    allLandingPages.value = lpRes.data || lpRes || []
  } catch {
    // handled by useApi
  }
}

async function doExport() {
  try {
    const result = await post('/api/packs/export', {
      name: exportForm.value.name || 'Exported Pack',
      description: exportForm.value.description || '',
      template_ids: exportForm.value.template_ids,
      landing_page_ids: exportForm.value.landing_page_ids
    })

    // Download as JSON file
    const blob = new Blob([JSON.stringify(result, null, 2)], { type: 'application/json' })
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = (exportForm.value.name || 'pack').replace(/[^a-zA-Z0-9-_]/g, '-').toLowerCase() + '.json'
    a.click()
    URL.revokeObjectURL(url)

    showExportDialog.value = false
    notifications.success('Pack exporté avec succes')

    // Reset form
    exportForm.value = { name: '', description: '', template_ids: [], landing_page_ids: [] }
  } catch {
    // handled by useApi
  }
}

onMounted(() => {
  fetchPacks()
  fetchExportData()
})
</script>
