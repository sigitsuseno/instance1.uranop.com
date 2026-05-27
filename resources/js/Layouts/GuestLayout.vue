<script setup>
import NotificationToast from '../Components/NotificationToast.vue'
</script>

<template>
  <div class="min-h-screen flex items-center justify-center bg-(--bg-main) p-4">
    <div class="w-full max-w-md bg-(--bg-card) rounded-md shadow-lg border border-(--border-soft) p-8">
      <slot />
    </div>
    <NotificationToast />
  </div>
</template>
