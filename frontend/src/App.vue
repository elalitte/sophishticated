<template>
  <Suspense>
    <template #default>
      <Transition name="fade" mode="out-in">
        <router-view />
      </Transition>
    </template>
    <template #fallback>
      <div class="flex items-center justify-center min-h-screen">
        <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
      </div>
    </template>
  </Suspense>
</template>

<script setup>
</script>
