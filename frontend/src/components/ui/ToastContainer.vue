<template>
  <Teleport to="body">
    <div class="fixed top-4 right-4 z-[100] flex flex-col gap-2 pointer-events-none">
      <Toast
        v-for="notification in notifications"
        :key="notification.id"
        :notification="notification"
        @dismiss="dismiss"
      />
    </div>
  </Teleport>
</template>

<script setup>
import { storeToRefs } from 'pinia'
import { useNotificationStore } from '@/stores/notifications'
import Toast from './Toast.vue'

const store = useNotificationStore()
const { notifications } = storeToRefs(store)

function dismiss(id) {
  store.removeNotification(id)
}
</script>
