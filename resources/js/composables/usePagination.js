import { ref, computed } from 'vue'

export function usePagination(itemsPerPage = 25) {
  const currentPage = ref(1)
  const perPage = ref(itemsPerPage)
  const total = ref(0)

  const totalPages = computed(() => Math.ceil(total.value / perPage.value) || 1)

  const hasNext = computed(() => currentPage.value < totalPages.value)
  const hasPrev = computed(() => currentPage.value > 1)

  function goTo(page) {
    if (page >= 1 && page <= totalPages.value) {
      currentPage.value = page
    }
  }

  function next() {
    if (hasNext.value) currentPage.value++
  }

  function prev() {
    if (hasPrev.value) currentPage.value--
  }

  function reset() {
    currentPage.value = 1
  }

  function getRange() {
    const start = Math.max(1, currentPage.value - 2)
    const end = Math.min(totalPages.value, currentPage.value + 2)
    const pages = []
    for (let i = start; i <= end; i++) {
      pages.push(i)
    }
    return pages
  }

  return {
    currentPage,
    perPage,
    total,
    totalPages,
    hasNext,
    hasPrev,
    goTo,
    next,
    prev,
    reset,
    getRange,
  }
}
