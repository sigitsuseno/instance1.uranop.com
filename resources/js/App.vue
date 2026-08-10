<script setup>
import { onMounted } from 'vue'
import { useTheme } from './composables/useTheme'
import { useCompanyStore } from './Stores/company'

const { init } = useTheme()
init()

const companyStore = useCompanyStore()

onMounted(() => {
  companyStore.fetchCompany()
})
</script>

<template>
  <router-view />
</template>
