<template>
  <div class="report-page p-4">
    <!-- Header Bar -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 mb-6">
      <div>
        <h1 class="text-xl font-bold text-(--text-main)">{{ title }}</h1>
        <p v-if="description" class="text-sm text-(--text-muted) mt-1">{{ description }}</p>
      </div>

      <div class="print-hide flex items-center gap-2 shrink-0">
        <!-- Slot: extra actions (export, print, dll) -->
        <slot name="actions" />

        <!-- Tombol Setting -->
        <button
          @click="$emit('openSettings')"
          class="px-3 py-2 text-sm rounded-lg border border-(--border-soft) hover:bg-(--bg-hover) flex items-center gap-1.5 transition-colors"
          title="Pengaturan Laporan"
        >
          <i class="bx bx-cog text-lg"></i>
          <span class="hidden sm:inline">Setting</span>
        </button>
      </div>
    </div>

    <!-- Filter Area (opsional) -->
    <div v-if="$slots.filter" class="mb-4">
      <slot name="filter" />
    </div>

    <!-- Main Content -->
    <slot />
  </div>
</template>

<script setup>
defineProps({
  title: { type: String, required: true },
  description: { type: String, default: '' },
});

defineEmits(['openSettings']);
</script>
