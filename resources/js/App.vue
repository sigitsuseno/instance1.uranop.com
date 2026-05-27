<script setup>
import { useTheme } from './composables/useTheme'
const { init } = useTheme()
init()
</script>

<template>
  <router-view />
</template>
