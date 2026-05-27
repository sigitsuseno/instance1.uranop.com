<template>
  <div v-if="total > 0" class="flex items-center justify-between mt-4">
    <div class="text-sm text-(--text-muted)">
      Menampilkan {{ from }} - {{ to }} dari {{ total }} data
    </div>
    <div class="flex items-center gap-1">
      <button
        :disabled="currentPage <= 1"
        :class="pageButtonClasses(currentPage <= 1)"
        @click="goToPage(currentPage - 1)"
        class="p-2 rounded-md"
      >
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <polyline points="15 18 9 12 15 6" />
        </svg>
      </button>

      <button
        v-for="page in visiblePages"
        :key="page"
        :class="page === currentPage ? 'px-3 py-1 rounded-md text-sm font-medium bg-(--primary) text-white' : pageButtonClasses(false) + ' px-3 py-1 rounded-md text-sm font-medium'"
        @click="goToPage(page)"
      >
        {{ page }}
      </button>

      <button
        :disabled="currentPage >= totalPages"
        :class="pageButtonClasses(currentPage >= totalPages)"
        @click="goToPage(currentPage + 1)"
        class="p-2 rounded-md"
      >
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <polyline points="9 18 15 12 9 6" />
        </svg>
      </button>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  currentPage: { type: Number, required: true },
  totalPages: { type: Number, required: true },
  total: { type: Number, required: true },
  perPage: { type: Number, required: true },
})

const emit = defineEmits(['page-change'])

const from = computed(() => {
  return props.total === 0 ? 0 : (props.currentPage - 1) * props.perPage + 1
})

const to = computed(() => {
  return Math.min(props.currentPage * props.perPage, props.total)
})

const visiblePages = computed(() => {
  const pages = []
  const total = props.totalPages
  const current = props.currentPage

  if (total <= 7) {
    for (let i = 1; i <= total; i++) pages.push(i)
    return pages
  }

  pages.push(1)

  if (current > 3) pages.push('...')

  const start = Math.max(2, current - 1)
  const end = Math.min(total - 1, current + 1)

  for (let i = start; i <= end; i++) pages.push(i)

  if (current < total - 2) pages.push('...')

  pages.push(total)

  return pages
})

function pageButtonClasses(disabled) {
  if (disabled) {
    return 'text-(--text-soft) cursor-not-allowed hover:text-(--text-soft) hover:bg-transparent'
  }
  return 'text-(--text-muted) hover:text-(--text-main) hover:bg-(--bg-elevated) transition-colors'
}

function goToPage(page) {
  if (page === '...' || page < 1 || page > props.totalPages) return
  emit('page-change', page)
}
</script>
