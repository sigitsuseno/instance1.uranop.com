<template>
  <div class="space-y-6 py-2">
    <!-- Header -->
    <div>
      <h1 class="text-2xl font-bold tracking-tight text-(--text-main)">Data Master</h1>
      <p class="text-sm text-(--text-muted) mt-1">
        Kelola konfigurasi dan referensi yang digunakan untuk penghitungan absensi dan payroll.
      </p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
      <!-- Card: Scan Detection Config -->
      <BaseCard class="hover:border-(--primary) transition-all duration-300 group cursor-pointer" @click="$router.push('/supervisor/master/scan-detection')">
        <div class="flex items-start gap-4">
          <div class="p-3 rounded-lg bg-(--primary)/10 text-(--primary) group-hover:scale-110 transition-transform">
            <IconClock class="w-6 h-6" />
          </div>
          <div>
            <h3 class="font-bold text-(--text-main) group-hover:text-(--primary) transition-colors">Aturan Jam Kerja & Denda</h3>
            <p class="text-xs text-(--text-muted) mt-1">
              Konfigurasi deteksi keterlambatan, pulang cepat, dan nominal denda.
            </p>
          </div>
        </div>
      </BaseCard>

      <!-- Card: BPJS Config -->
      <BaseCard class="hover:border-(--success) transition-all duration-300 group cursor-pointer" @click="$router.push('/supervisor/master/bpjs')">
        <div class="flex items-start gap-4">
          <div class="p-3 rounded-lg bg-(--success)/10 text-(--success) group-hover:scale-110 transition-transform">
            <IconFileInvoice class="w-6 h-6" />
          </div>
          <div>
            <h3 class="font-bold text-(--text-main) group-hover:text-(--success) transition-colors">Konfigurasi BPJS</h3>
            <p class="text-xs text-(--text-muted) mt-1">
              Aturan perhitungan BPJS (JHT, JP, Kesehatan) khusus kalkulasi payroll.
            </p>
          </div>
        </div>
      </BaseCard>

      <!-- Card: PPh Config -->
      <BaseCard class="hover:border-(--warning) transition-all duration-300 group cursor-pointer" @click="$router.push('/supervisor/master/pph')">
        <div class="flex items-start gap-4">
          <div class="p-3 rounded-lg bg-(--warning)/10 text-(--warning) group-hover:scale-110 transition-transform">
            <IconChartBar class="w-6 h-6" />
          </div>
          <div>
            <h3 class="font-bold text-(--text-main) group-hover:text-(--warning) transition-colors">Konfigurasi Pajak (PPh 21)</h3>
            <p class="text-xs text-(--text-muted) mt-1">
              Aturan perhitungan PPh21 (TER/Tahunan) untuk slip gaji.
            </p>
          </div>
        </div>
      </BaseCard>

      <!-- Card: THR Config -->
      <BaseCard class="hover:border-(--primary-hover) transition-all duration-300 group cursor-pointer" @click="$router.push('/supervisor/master/thr')">
        <div class="flex items-start gap-4">
          <div class="p-3 rounded-lg bg-(--primary-hover)/10 text-(--primary-hover) group-hover:scale-110 transition-transform">
            <IconGift class="w-6 h-6" />
          </div>
          <div>
            <h3 class="font-bold text-(--text-main) group-hover:text-(--primary-hover) transition-colors">Konfigurasi THR</h3>
            <p class="text-xs text-(--text-muted) mt-1">
              Parameter untuk menghitung nilai Tunjangan Hari Raya.
            </p>
          </div>
        </div>
      </BaseCard>
      <!-- Card: Departments -->
      <BaseCard class="hover:border-(--info) transition-all duration-300 group cursor-pointer" @click="$router.push('/supervisor/master/departments')">
        <div class="flex items-start gap-4">
          <div class="p-3 rounded-lg bg-(--info)/10 text-(--info) group-hover:scale-110 transition-transform">
            <IconBuilding class="w-6 h-6" />
          </div>
          <div>
            <h3 class="font-bold text-(--text-main) group-hover:text-(--info) transition-colors">Departemen</h3>
            <p class="text-xs text-(--text-muted) mt-1">
              Kelola struktur departemen perusahaan.
            </p>
          </div>
        </div>
      </BaseCard>

      <!-- Card: Positions -->
      <BaseCard class="hover:border-(--primary-hover) transition-all duration-300 group cursor-pointer" @click="$router.push('/supervisor/master/positions')">
        <div class="flex items-start gap-4">
          <div class="p-3 rounded-lg bg-(--primary-hover)/10 text-(--primary-hover) group-hover:scale-110 transition-transform">
            <IconUsers class="w-6 h-6" />
          </div>
          <div>
            <h3 class="font-bold text-(--text-main) group-hover:text-(--primary-hover) transition-colors">Jabatan</h3>
            <p class="text-xs text-(--text-muted) mt-1">
              Kelola data jabatan, tingkatan level, dan peran.
            </p>
          </div>
        </div>
      </BaseCard>

      <!-- Card: Leave Settings -->
      <BaseCard class="hover:border-(--success) transition-all duration-300 group cursor-pointer" @click="$router.push('/supervisor/master/leave-settings')">
        <div class="flex items-start gap-4">
          <div class="p-3 rounded-lg bg-(--success)/10 text-(--success) group-hover:scale-110 transition-transform">
            <IconCalendarCheck class="w-6 h-6" />
          </div>
          <div>
            <h3 class="font-bold text-(--text-main) group-hover:text-(--success) transition-colors">Pengaturan Cuti</h3>
            <p class="text-xs text-(--text-muted) mt-1">
              Konfigurasi tipe cuti, kebijakan, dan periode cuti karyawan.
            </p>
          </div>
        </div>
      </BaseCard>
    </div>
  </div>
</template>

<script setup>
import BaseCard from '../../../Components/BaseCard.vue'
import { IconClock, IconFileInvoice, IconChartBar, IconGift, IconBuilding, IconUsers, IconCalendarCheck } from '../../../Components/Icons/index.js'
</script>
