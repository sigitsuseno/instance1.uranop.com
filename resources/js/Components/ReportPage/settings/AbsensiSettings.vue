<!-- resources/js/Components/ReportPage/settings/AbsensiSettings.vue -->
<!-- Pengaturan spesifik Laporan Absensi — saat ini kosong (placeholder) -->

<template>
    <div>
        <p class="text-sm text-(--text-muted)">
            Saat ini belum ada pengaturan spesifik untuk laporan kehadiran.
        </p>
        <p class="text-xs text-(--text-soft) mt-1">
            Pengaturan group karyawan dapat diatur melalui checkbox di atas.
        </p>
    </div>
</template>

<script setup>
defineProps({
    config: { type: Object, default: () => ({}) },
})

const emit = defineEmits(['update:config'])

// No-op for now — config is empty
</script>
