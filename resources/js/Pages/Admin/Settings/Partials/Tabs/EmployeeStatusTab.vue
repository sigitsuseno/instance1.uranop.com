<script setup>
import BaseCard from '../../../../../Components/BaseCard.vue'
</script>

<template>
  <div class="space-y-6">
    <BaseCard>
      <template #title>Tipe Kontrak & Status Karyawan</template>
      <div class="space-y-4">
        <p class="text-sm text-(--text-muted)">Pengaturan tipe kontrak (PKWT, PKWTT) dan status kepegawaian (Aktif, Resign, PHK) saat ini telah di-standarisasi oleh sistem dan tidak dapat diubah secara bebas untuk menjaga konsistensi laporan ketenagakerjaan.</p>
        
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-4">
          <div class="p-4 border border-(--border-soft) rounded-md bg-(--bg-main)">
            <h4 class="text-sm font-medium text-(--text-main)">Tipe Kontrak Standar</h4>
            <ul class="mt-2 text-sm text-(--text-muted) list-disc list-inside space-y-1">
              <li>PKWTT (Karyawan Tetap)</li>
              <li>PKWT (Karyawan Kontrak)</li>
              <li>Internship (Magang)</li>
              <li>Freelance (Lepas)</li>
            </ul>
          </div>
          
          <div class="p-4 border border-(--border-soft) rounded-md bg-(--bg-main)">
            <h4 class="text-sm font-medium text-(--text-main)">Status Karyawan Standar</h4>
            <ul class="mt-2 text-sm text-(--text-muted) list-disc list-inside space-y-1">
              <li>Aktif</li>
              <li>Resign (Mengundurkan Diri)</li>
              <li>PHK (Pemutusan Hubungan Kerja)</li>
              <li>Pensiun</li>
            </ul>
          </div>
        </div>
      </div>
    </BaseCard>
  </div>
</template>
