<script setup>
import BaseButton from '../../../../Components/BaseButton.vue'
import BaseCard from '../../../../Components/BaseCard.vue'
</script>

<template>
  <div class="space-y-6">
    <div>
      <h2 class="text-lg font-medium text-(--text-main)">Pengaturan Penggajian & Pajak</h2>
      <p class="text-sm text-(--text-muted)">Atur komponen dasar untuk perhitungan gaji, grade, dan pajak penghasilan.</p>
    </div>

    <!-- Master Data Enums / Tables -->
    <BaseCard>
      <template #title>Data Master Penggajian (Tabel Referensi)</template>
      <div class="space-y-4">
        <p class="text-sm text-(--text-muted)">Komponen penggajian bersifat sangat dinamis per instance/cabang. Oleh karena itu, pengaturan ini dipisahkan menjadi tabel data master (bukan hardcoded).</p>
        
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div class="p-4 border border-(--border-soft) rounded-md bg-(--bg-main) flex items-center justify-between">
            <div>
              <h4 class="text-sm font-medium text-(--text-main)">Grade Gaji (Salary Grades)</h4>
              <p class="text-xs text-(--text-muted)">Level gaji, batas bawah, dan batas atas.</p>
            </div>
            <BaseButton variant="ghost" size="sm" icon="bx bx-right-arrow-alt" @click="$router.push('/admin/organization/salary-grades')">Ke Menu</BaseButton>
          </div>
          
          <div class="p-4 border border-(--border-soft) rounded-md bg-(--bg-main) flex items-center justify-between">
            <div>
              <h4 class="text-sm font-medium text-(--text-main)">Komponen Gaji</h4>
              <p class="text-xs text-(--text-muted)">Tunjangan jabatan, makan, transport, dll.</p>
            </div>
            <BaseButton variant="ghost" size="sm" icon="bx bx-right-arrow-alt" @click="$router.push('/admin/payroll/configs')">Ke Menu</BaseButton>
          </div>

          <div class="p-4 border border-(--border-soft) rounded-md bg-(--bg-main) flex items-center justify-between">
            <div>
              <h4 class="text-sm font-medium text-(--text-main)">Konfigurasi PPh 21 & PTKP</h4>
              <p class="text-xs text-(--text-muted)">Tarif progresif, TER, dan batas PTKP.</p>
            </div>
            <BaseButton variant="ghost" size="sm" icon="bx bx-right-arrow-alt" @click="$router.push('/admin/payroll/configs')">Ke Menu</BaseButton>
          </div>

          <div class="p-4 border border-(--border-soft) rounded-md bg-(--bg-main) flex items-center justify-between">
            <div>
              <h4 class="text-sm font-medium text-(--text-main)">Konfigurasi BPJS</h4>
              <p class="text-xs text-(--text-muted)">Persentase potong perusahaan & karyawan.</p>
            </div>
            <BaseButton variant="ghost" size="sm" icon="bx bx-right-arrow-alt" @click="$router.push('/admin/payroll/configs')">Ke Menu</BaseButton>
          </div>
        </div>
      </div>
    </BaseCard>
  </div>
</template>
