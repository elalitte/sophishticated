<template>
  <div class="fixed inset-0 bg-white z-50 flex flex-col">
    <!-- Toolbar -->
    <div class="flex items-center justify-between px-4 py-2 bg-gray-900 text-white">
      <div class="flex items-center gap-3">
        <button
          @click="goBack"
          class="p-1.5 hover:bg-gray-700 rounded-lg transition-colors"
        >
          <ArrowLeft class="w-5 h-5" />
        </button>
        <span class="text-sm font-medium">Prévisualisation</span>
        <Badge v-if="contentType" :text="contentType" color="blue" />
      </div>
      <div class="flex items-center gap-2">
        <!-- Device switcher -->
        <div class="flex items-center bg-gray-800 rounded-lg p-0.5">
          <button
            @click="device = 'desktop'"
            :class="[
              'p-1.5 rounded-md transition-colors',
              device === 'desktop' ? 'bg-gray-600 text-white' : 'text-gray-400 hover:text-white'
            ]"
          >
            <Monitor class="w-4 h-4" />
          </button>
          <button
            @click="device = 'tablet'"
            :class="[
              'p-1.5 rounded-md transition-colors',
              device === 'tablet' ? 'bg-gray-600 text-white' : 'text-gray-400 hover:text-white'
            ]"
          >
            <Tablet class="w-4 h-4" />
          </button>
          <button
            @click="device = 'mobile'"
            :class="[
              'p-1.5 rounded-md transition-colors',
              device === 'mobile' ? 'bg-gray-600 text-white' : 'text-gray-400 hover:text-white'
            ]"
          >
            <Smartphone class="w-4 h-4" />
          </button>
        </div>
        <button
          @click="goBack"
          class="px-3 py-1.5 text-sm font-medium bg-gray-700 hover:bg-gray-600 rounded-lg transition-colors"
        >
          Fermer
        </button>
      </div>
    </div>

    <!-- Preview area -->
    <div class="flex-1 bg-gray-100 flex items-start justify-center p-6 overflow-auto">
      <div
        :class="[
          'bg-white shadow-lg transition-all duration-300',
          deviceClasses
        ]"
      >
        <iframe
          v-if="content"
          :srcdoc="content"
          class="w-full h-full border-0"
          sandbox="allow-same-origin"
        />
        <div v-else class="flex items-center justify-center h-full">
          <div class="text-center">
            <FileX class="w-12 h-12 text-gray-300 mx-auto mb-3" />
            <p class="text-sm text-gray-500">Aucun contenu à afficher</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ArrowLeft, Monitor, Tablet, Smartphone, FileX } from 'lucide-vue-next'
import { useApi } from '../../composables/useApi'
import Badge from '../../components/ui/Badge.vue'

const route = useRoute()
const router = useRouter()
const { get } = useApi()

const content = ref('')
const contentType = ref('')
const device = ref('desktop')

const deviceClasses = computed(() => {
  switch (device.value) {
    case 'mobile':
      return 'w-[375px] h-[667px] rounded-2xl overflow-hidden'
    case 'tablet':
      return 'w-[768px] h-[1024px] rounded-xl overflow-hidden'
    default:
      return 'w-full max-w-5xl h-[80vh] rounded-lg overflow-hidden'
  }
})

function goBack() {
  if (window.history.length > 1) {
    router.back()
  } else {
    router.push('/templates')
  }
}

async function loadContent() {
  // Try route query first
  if (route.query.html) {
    content.value = route.query.html
    contentType.value = route.query.type || 'Template'
    return
  }

  // Try loading from session storage
  const stored = sessionStorage.getItem('preview_content')
  if (stored) {
    try {
      const data = JSON.parse(stored)
      content.value = data.html || ''
      contentType.value = data.type || 'Template'
    } catch {
      // Invalid stored data
    }
    sessionStorage.removeItem('preview_content')
    return
  }

  // Try loading from API if template/page ID is provided
  if (route.query.template_id) {
    try {
      const data = await get(`/api/templates/${route.query.template_id}`)
      content.value = data.html_content || ''
      contentType.value = 'Template'
    } catch {
      // Error handled by useApi
    }
  } else if (route.query.page_id) {
    try {
      const data = await get(`/api/landing-pages/${route.query.page_id}`)
      content.value = data.html_content || ''
      contentType.value = 'Page'
    } catch {
      // Error handled by useApi
    }
  }
}

onMounted(loadContent)
</script>
