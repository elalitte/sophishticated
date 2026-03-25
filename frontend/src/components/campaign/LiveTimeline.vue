<template>
  <div class="space-y-3">
    <!-- Filters -->
    <div class="flex flex-wrap gap-3 items-center">
      <span class="text-sm font-medium text-gray-600">Filtrer :</span>
      <label
        v-for="type in eventTypes"
        :key="type.key"
        class="inline-flex items-center gap-1.5 text-xs cursor-pointer"
      >
        <input
          type="checkbox"
          :checked="activeFilters.has(type.key)"
          @change="toggleFilter(type.key)"
          class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 w-3.5 h-3.5"
        />
        <span :class="type.textColor">{{ type.label }}</span>
      </label>
    </div>

    <!-- Timeline -->
    <div class="max-h-[500px] overflow-y-auto space-y-1 pr-1" ref="timelineContainer">
      <TransitionGroup name="timeline-slide">
        <div
          v-for="event in filteredEvents"
          :key="event.id"
          class="flex items-start gap-3 px-3 py-2.5 rounded-lg hover:bg-gray-50 transition-colors"
        >
          <!-- Colored icon -->
          <div :class="['flex-shrink-0 mt-0.5 p-1.5 rounded-full', getEventStyle(event.type).bg]">
            <component
              :is="getEventStyle(event.type).icon"
              :class="['w-3.5 h-3.5', getEventStyle(event.type).text]"
            />
          </div>

          <!-- Content -->
          <div class="flex-1 min-w-0">
            <p class="text-sm text-gray-800">
              <span class="font-medium">{{ event.userName }}</span>
              {{ getEventLabel(event.type) }}
            </p>
            <p class="text-xs text-gray-400 mt-0.5">{{ formatTimeAgo(event.timestamp) }}</p>
          </div>
        </div>
      </TransitionGroup>

      <div v-if="filteredEvents.length === 0" class="py-8 text-center text-sm text-gray-500">
        Aucun événement à afficher
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, reactive } from 'vue'
import {
  Send,
  MailOpen,
  MousePointerClick,
  FileText,
  AlertCircle,
  Mail
} from 'lucide-vue-next'

const props = defineProps({
  events: {
    type: Array,
    default: () => []
  }
})

const MAX_EVENTS = 100

const eventTypes = [
  { key: 'sent',      label: 'Envoyé',    textColor: 'text-blue-600' },
  { key: 'delivered', label: 'Délivré',    textColor: 'text-gray-600' },
  { key: 'opened',    label: 'Ouvert',     textColor: 'text-green-600' },
  { key: 'clicked',   label: 'Cliqué',     textColor: 'text-orange-600' },
  { key: 'submitted', label: 'Soumis',     textColor: 'text-red-600' },
  { key: 'error',     label: 'Erreur',     textColor: 'text-red-600' }
]

const activeFilters = reactive(new Set(eventTypes.map(t => t.key)))

const filteredEvents = computed(() => {
  return props.events
    .filter(e => activeFilters.has(e.type))
    .sort((a, b) => new Date(b.timestamp) - new Date(a.timestamp))
    .slice(0, MAX_EVENTS)
})

function toggleFilter(key) {
  if (activeFilters.has(key)) {
    activeFilters.delete(key)
  } else {
    activeFilters.add(key)
  }
}

function getEventStyle(type) {
  const styles = {
    sent:      { icon: Send,              bg: 'bg-blue-100',   text: 'text-blue-600' },
    delivered: { icon: Mail,              bg: 'bg-gray-100',   text: 'text-gray-600' },
    opened:    { icon: MailOpen,          bg: 'bg-green-100',  text: 'text-green-600' },
    clicked:   { icon: MousePointerClick, bg: 'bg-orange-100', text: 'text-orange-600' },
    submitted: { icon: FileText,          bg: 'bg-red-100',    text: 'text-red-600' },
    error:     { icon: AlertCircle,       bg: 'bg-red-100',    text: 'text-red-600' }
  }
  return styles[type] || styles.sent
}

function getEventLabel(type) {
  const labels = {
    sent:      'a reçu le mail',
    delivered: 'a été délivré',
    opened:    'a ouvert le mail',
    clicked:   'a cliqué sur le lien',
    submitted: 'a soumis le formulaire',
    error:     'erreur de livraison'
  }
  return labels[type] || type
}

function formatTimeAgo(timestamp) {
  const now = Date.now()
  const diff = now - new Date(timestamp).getTime()
  const seconds = Math.floor(diff / 1000)

  if (seconds < 5) return "à l'instant"
  if (seconds < 60) return `il y a ${seconds} secondes`

  const minutes = Math.floor(seconds / 60)
  if (minutes < 60) return `il y a ${minutes} minute${minutes > 1 ? 's' : ''}`

  const hours = Math.floor(minutes / 60)
  if (hours < 24) return `il y a ${hours} heure${hours > 1 ? 's' : ''}`

  const days = Math.floor(hours / 24)
  return `il y a ${days} jour${days > 1 ? 's' : ''}`
}
</script>

<style scoped>
.timeline-slide-enter-active {
  transition: all 0.4s ease-out;
}
.timeline-slide-leave-active {
  transition: all 0.2s ease-in;
}
.timeline-slide-enter-from {
  opacity: 0;
  transform: translateX(-20px);
}
.timeline-slide-leave-to {
  opacity: 0;
  transform: translateX(20px);
}
.timeline-slide-move {
  transition: transform 0.3s ease;
}
</style>
