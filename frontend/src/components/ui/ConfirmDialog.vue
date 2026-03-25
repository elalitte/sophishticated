<template>
  <Modal :show="show" :title="title" max-width="md" @close="$emit('cancel')">
    <div class="flex flex-col items-center text-center gap-4 py-2">
      <div :class="['p-3 rounded-full', iconBgClass]">
        <AlertTriangle v-if="variant === 'danger' || variant === 'warning'" :class="['w-7 h-7', iconClass]" />
        <Info v-else :class="['w-7 h-7', iconClass]" />
      </div>
      <p class="text-gray-600 text-sm leading-relaxed">{{ message }}</p>
    </div>

    <template #footer>
      <div class="flex justify-end gap-3">
        <button
          @click="$emit('cancel')"
          class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
        >
          {{ cancelText }}
        </button>
        <button
          @click="$emit('confirm')"
          :class="['px-4 py-2 text-sm font-medium text-white rounded-lg transition-colors', confirmBtnClass]"
        >
          {{ confirmText }}
        </button>
      </div>
    </template>
  </Modal>
</template>

<script setup>
import { computed } from 'vue'
import { AlertTriangle, Info } from 'lucide-vue-next'
import Modal from './Modal.vue'

const props = defineProps({
  show: {
    type: Boolean,
    default: false
  },
  title: {
    type: String,
    default: 'Confirmation'
  },
  message: {
    type: String,
    default: 'Êtes-vous sûr ?'
  },
  confirmText: {
    type: String,
    default: 'Confirmer'
  },
  cancelText: {
    type: String,
    default: 'Annuler'
  },
  variant: {
    type: String,
    default: 'danger',
    validator: v => ['danger', 'warning', 'info'].includes(v)
  }
})

defineEmits(['confirm', 'cancel'])

const iconBgClass = computed(() => ({
  danger: 'bg-red-100',
  warning: 'bg-orange-100',
  info: 'bg-blue-100'
}[props.variant]))

const iconClass = computed(() => ({
  danger: 'text-red-600',
  warning: 'text-orange-600',
  info: 'text-blue-600'
}[props.variant]))

const confirmBtnClass = computed(() => ({
  danger: 'bg-red-600 hover:bg-red-700',
  warning: 'bg-orange-600 hover:bg-orange-700',
  info: 'bg-blue-600 hover:bg-blue-700'
}[props.variant]))
</script>
