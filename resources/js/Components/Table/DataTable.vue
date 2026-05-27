<template>
  <div>
    <div v-if="showSearch || hasHeaderSlot" class="flex items-center justify-between mb-4">
      <div v-if="showSearch" class="relative w-64">
        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-(--text-muted)">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="11" cy="11" r="8" />
            <line x1="21" y1="21" x2="16.65" y2="16.65" />
          </svg>
        </div>
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Cari..."
          class="w-full pl-10 pr-3 py-2 text-sm rounded-md border bg-(--bg-card) text-(--text-main) placeholder:text-(--text-soft) focus:outline-none focus:ring-2 focus:ring-(--primary)/25 focus:border-(--primary) transition-colors"
        />
      </div>
      <div class="text-sm text-(--text-muted)">
        {{ filteredItems.length }} data
      </div>
    </div>

    <div class="overflow-x-auto">
      <table class="w-full border-collapse">
        <thead>
          <tr class="bg-(--bg-elevated)">
            <th v-if="selectable" class="px-4 py-3 text-left text-xs font-semibold text-(--text-muted) uppercase tracking-wider w-10">
              <input
                type="checkbox"
                :checked="allSelected"
                @change="toggleAll"
                class="rounded border-(--border-soft) text-(--primary) focus:ring-(--primary)"
              />
            </th>
            <th
              v-for="header in headers"
              :key="header.key"
              :class="headerClasses(header)"
              :style="header.width ? { width: header.width } : {}"
              @click="header.sortable !== false && handleSort(header)"
            >
              <slot :name="`header.${header.key}`" :header="header">
                <span :class="header.sortable !== false ? 'cursor-pointer select-none inline-flex items-center gap-1' : ''">
                  {{ header.label }}
                  <span v-if="header.sortable !== false" class="inline-flex flex-col">
                    <svg
                      :class="sortKey === header.key && sortDirection === 'asc' ? 'text-(--primary)' : 'text-(--text-soft)'"
                      xmlns="http://www.w3.org/2000/svg"
                      class="h-2.5 w-2.5 -mb-0.5"
                      viewBox="0 0 24 24"
                      fill="currentColor"
                      stroke="none"
                    >
                      <path d="M12 8l-6 6h12z" />
                    </svg>
                    <svg
                      :class="sortKey === header.key && sortDirection === 'desc' ? 'text-(--primary)' : 'text-(--text-soft)'"
                      xmlns="http://www.w3.org/2000/svg"
                      class="h-2.5 w-2.5"
                      viewBox="0 0 24 24"
                      fill="currentColor"
                      stroke="none"
                    >
                      <path d="M12 16l6-6H6z" />
                    </svg>
                  </span>
                </span>
              </slot>
            </th>
          </tr>
        </thead>
        <tbody>
          <template v-if="loading">
            <tr v-for="i in 5" :key="'skeleton-' + i" class="border-b border-(--border-soft)">
              <td v-if="selectable" class="px-4 py-3">
                <div class="h-4 w-4 bg-(--bg-elevated) rounded animate-pulse" />
              </td>
              <td v-for="header in headers" :key="header.key" class="px-4 py-3">
                <div class="h-4 bg-(--bg-elevated) rounded animate-pulse w-3/4" />
              </td>
            </tr>
          </template>
          <template v-else-if="filteredItems.length === 0">
            <tr class="border-b border-(--border-soft)">
              <td :colspan="colspan" class="px-4 py-16 text-center">
                <div class="flex flex-col items-center justify-center text-(--text-muted)">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 mb-3 opacity-40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                    <polyline points="14 2 14 8 20 8" />
                  </svg>
                  <span class="text-sm">{{ emptyText }}</span>
                </div>
              </td>
            </tr>
          </template>
          <template v-else>
            <tr
              v-for="item in filteredItems"
              :key="item.id || item[headers[0]?.key]"
              :class="rowClasses"
              @click="handleRowClick(item)"
            >
              <td v-if="selectable" class="px-4 py-3" @click.stop>
                <input
                  type="checkbox"
                  :checked="isSelected(item)"
                  @change="toggleItem(item)"
                  class="rounded border-(--border-soft) text-(--primary) focus:ring-(--primary)"
                />
              </td>
              <td
                v-for="header in headers"
                :key="header.key"
                :class="cellClasses(header)"
              >
                <slot :name="`item.${header.key}`" :item="item" :value="item[header.key]">
                  {{ item[header.key] }}
                </slot>
              </td>
            </tr>
          </template>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'

const props = defineProps({
  headers: { type: Array, required: true },
  items: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  emptyText: { type: String, default: 'Tidak ada data' },
  selectable: { type: Boolean, default: false },
  selected: { type: Array, default: () => [] },
  showSearch: { type: Boolean, default: false },
})

const emit = defineEmits(['update:selected', 'sort', 'rowClick'])

const searchQuery = ref('')
const sortKey = ref('')
const sortDirection = ref('asc')

const colspan = computed(() => {
  return props.selectable ? props.headers.length + 1 : props.headers.length
})

const hasHeaderSlot = computed(() => {
  return false
})

const filteredItems = computed(() => {
  let items = [...props.items]

  if (searchQuery.value) {
    const q = searchQuery.value.toLowerCase()
    items = items.filter((item) => {
      return props.headers.some((header) => {
        const val = item[header.key]
        return val != null && String(val).toLowerCase().includes(q)
      })
    })
  }

  if (sortKey.value) {
    items.sort((a, b) => {
      const aVal = a[sortKey.value]
      const bVal = b[sortKey.value]
      if (aVal == null) return 1
      if (bVal == null) return -1
      const compare = aVal < bVal ? -1 : aVal > bVal ? 1 : 0
      return sortDirection.value === 'desc' ? -compare : compare
    })
  }

  return items
})

const allSelected = computed(() => {
  if (filteredItems.value.length === 0) return false
  return filteredItems.value.every((item) => isSelected(item))
})

const rowClasses = computed(() => {
  return 'border-b border-(--border-soft) hover:bg-(--bg-elevated)/50 transition-colors'
})

function isSelected(item) {
  return props.selected.some((s) => {
    if (s.id !== undefined && item.id !== undefined) return s.id === item.id
    return JSON.stringify(s) === JSON.stringify(item)
  })
}

function toggleAll() {
  if (allSelected.value) {
    emit('update:selected', [])
  } else {
    emit('update:selected', [...filteredItems.value.map((item) => ({ ...item }))])
  }
}

function toggleItem(item) {
  const current = [...props.selected]
  if (isSelected(item)) {
    emit('update:selected', current.filter((s) => {
      if (s.id !== undefined && item.id !== undefined) return s.id !== item.id
      return JSON.stringify(s) !== JSON.stringify(item)
    }))
  } else {
    current.push({ ...item })
    emit('update:selected', current)
  }
}

function handleSort(header) {
  if (header.sortable === false) return
  if (sortKey.value === header.key) {
    sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc'
  } else {
    sortKey.value = header.key
    sortDirection.value = 'asc'
  }
  emit('sort', { key: sortKey.value, direction: sortDirection.value })
}

function handleRowClick(item) {
  emit('rowClick', item)
}

function headerClasses(header) {
  const base = 'px-4 py-3 text-left text-xs font-semibold text-(--text-muted) uppercase tracking-wider'
  const align = header.align ? `text-${header.align}` : 'text-left'
  const sortable = header.sortable !== false ? 'cursor-pointer select-none' : ''
  return `${base} ${align} ${sortable}`
}

function cellClasses(header) {
  const base = 'px-4 py-3 text-sm text-(--text-main)'
  const align = header.align ? `text-${header.align}` : 'text-left'
  return `${base} ${align}`
}
</script>
