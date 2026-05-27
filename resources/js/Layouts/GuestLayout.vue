<script setup>
import { ref, onMounted } from 'vue'

onMounted(() => {
  if (localStorage.getItem('theme') === 'dark' || (!localStorage.getItem('theme') && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
    document.documentElement.classList.add('dark')
  } else {
    document.documentElement.classList.remove('dark')
  }
})
</script>

<template>
  <div class="min-h-screen bg-(--bg-main) text-(--text-main)">
    <slot />
  </div>
</template>
