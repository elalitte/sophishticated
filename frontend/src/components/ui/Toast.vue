<template>
  <Transition name="toast-slide">
    <div
      v-if="visible"
      :class="[
        'flex items-start gap-3 px-4 py-3 rounded-lg shadow-lg border max-w-sm w-full pointer-events-auto',
        colorClasses.bg,
        colorClasses.border
      ]"
    >
      <div :class="['flex-shrink-0 mt-0.5', colorClasses.icon]">
        <CheckCircle2 v-if="notification.type === 'success'" class="w-5 h-5" />
        <XCircle v-else-if="notification.type === 'error'" class="w-5 h-5" />
        <AlertTriangle v-else-if="notification.type === 'warning'" class="w-5 h-5" />
        <Info v-else class="w-5 h-5" />
      </div>
      <p :class="['flex-1 text-sm font-medium', colorClasses.text]">
        {{ notification.message }}
      </p>
      <button
        @click="dismiss"
        :class="['flex-shrink-0 p-0.5 rounded hover:bg-black/10 transition-colors', colorClasses.text]"
      >
        <X class="w-4 h-4" />
      </button>
    </div>
  </Transition>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { X, CheckCircle2, XCircle, AlertTriangle, Info } from 'lucide-vue-next'

const props = defineProps({
  notification: {
    type: Object,
    required: true
  }
})

const emit = defineEmits(['dismiss'])

const visible = ref(false)
let timer = null

const colorClasses = computed(() => {
  const map = {
    success: {
      bg: 'bg-green-50',
      border: 'border-green-200',
      text: 'text-green-800',
      icon: 'text-green-500'
    },
    error: {
      bg: 'bg-red-50',
      border: 'border-red-200',
      text: 'text-red-800',
      icon: 'text-red-500'
    },
    warning: {
      bg: 'bg-orange-50',
      border: 'border-orange-200',
      text: 'text-orange-800',
      icon: 'text-orange-500'
    },
    info: {
      bg: 'bg-blue-50',
      border: 'border-blue-200',
      text: 'text-blue-800',
      icon: 'text-blue-500'
    }
  }
  return map[props.notification.type] || map.info
})

function dismiss() {
  visible.value = false
  setTimeout(() => emit('dismiss', props.notification.id), 300)
}

onMounted(() => {
  requestAnimationFrame(() => {
    visible.value = true
  })
  const duration = props.notification.duration || 5000
  if (duration > 0) {
    timer = setTimeout(dismiss, duration)
  }
})

onBeforeUnmount(() => {
  if (timer) clearTimeout(timer)
})
</script>

<style scoped>
.toast-slide-enter-active {
  transition: all 0.3s ease-out;
}
.toast-slide-leave-active {
  transition: all 0.2s ease-in;
}
.toast-slide-enter-from {
  opacity: 0;
  transform: translateX(100%);
}
.toast-slide-leave-to {
  opacity: 0;
  transform: translateX(100%);
}
</style>
