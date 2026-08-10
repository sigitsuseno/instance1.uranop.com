<script setup>
import { useCompanyStore } from '../../Stores/company'

const company = useCompanyStore()
</script>

<template>
  <footer class="bg-(--bg-sidebar) py-4 px-6 z-10">
    <div class="flex flex-col md:flex-row justify-between items-center text-xs text-(--text-soft)">
      <p>&copy; 2024 - {{ new Date().getFullYear() }} {{ company.name }}. All rights reserved.</p>
      <div class="flex space-x-4 mt-2 md:mt-0">
        <a href="#" class="hover:text-(--primary) transition-colors">{{ company.name }}</a>
        <a href="#" class="hover:text-(--primary) transition-colors">support@uranop.com</a>
      </div>
    </div>
  </footer>
</template>
