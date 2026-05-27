<script setup>
import { ref } from 'vue'
import BaseCard from '../../Components/BaseCard.vue'
import BaseButton from '../../Components/BaseButton.vue'
import Badge from '../../Components/Badge.vue'
import {
  IconUsers,
  IconCalendarCheck,
  IconClock,
  IconFileInvoice,
  IconEye,
  IconChevronDown,
} from '../../Components/Icons/index.js'

const stats = ref([
  { label: 'Total Karyawan', value: 45, icon: IconUsers, color: 'blue', accent: 'bg-blue-500' },
  { label: 'Hadir Hari Ini', value: 42, icon: IconCalendarCheck, color: 'green', accent: 'bg-green-500' },
  { label: 'Terlambat', value: 3, icon: IconClock, color: 'yellow', accent: 'bg-amber-500' },
  { label: 'Izin', value: 2, icon: IconFileInvoice, color: 'orange', accent: 'bg-orange-500' },
])

const attendanceCompliance = ref(93)
const overtimeCompliance = ref(88)
const leaveBalance = ref(12)

const lateEmployees = ref([
  { id: 1, name: 'Andi Prasetyo', department: 'Produksi', checkIn: '08:15', lateMinutes: 45 },
  { id: 2, name: 'Budi Santoso', department: 'Produksi', checkIn: '08:10', lateMinutes: 40 },
  { id: 3, name: 'Citra Dewi', department: 'QC', checkIn: '07:50', lateMinutes: 20 },
  { id: 4, name: 'Dian Permata', department: 'Gudang', checkIn: '07:45', lateMinutes: 15 },
  { id: 5, name: 'Eko Wahyudi', department: 'Produksi', checkIn: '07:40', lateMinutes: 10 },
])

const leaveToday = ref([
  { id: 1, name: 'Fitriani', reason: 'Sakit', time: '07:00 - 17:00' },
  { id: 2, name: 'Gunawan', reason: 'Keperluan Keluarga', time: '07:00 - 17:00' },
  { id: 3, name: 'Hendra Gunawan', reason: 'Cuti Tahunan', time: '07:00 - 17:00' },
])

function circumference(r) {
  return 2 * Math.PI * r
}

function offset(r, pct) {
  return circumference(r) * (1 - pct / 100)
}
</script>

<template>
  <div class="space-y-6">
    <div>
      <h3 class="text-xl font-semibold text-(--text-main)">Selamat Datang, Supervisor!</h3>
      <p class="text-sm text-(--text-muted) mt-1">Ringkasan data tim Anda hari ini.</p>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      <div
        v-for="stat in stats"
        :key="stat.label"
        class="bg-(--bg-card) rounded-md border border-(--border-soft) overflow-hidden shadow-sm"
      >
        <div :class="`h-1 w-full ${stat.accent}`"></div>
        <div class="p-5">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-xs text-(--text-muted) uppercase tracking-wider">{{ stat.label }}</p>
              <p class="text-2xl font-bold text-(--text-main) mt-1">{{ stat.value }}</p>
            </div>
            <div :class="`p-2.5 rounded-md bg-${stat.color}-100/10`">
              <component :is="stat.icon" :class="`w-6 h-6 text-${stat.color}-500`" />
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <BaseCard>
        <template #title>Kepatuhan Absensi</template>
        <template #subtitle>{{ attendanceCompliance }}% bulan ini</template>
        <div class="flex justify-center py-4">
          <div class="relative w-36 h-36">
            <svg class="w-36 h-36 -rotate-90" viewBox="0 0 144 144">
              <circle
                cx="72" cy="72" r="60"
                fill="none"
                stroke="currentColor"
                stroke-width="10"
                class="text-(--bg-elevated)"
              />
              <circle
                cx="72" cy="72" r="60"
                fill="none"
                stroke="currentColor"
                stroke-width="10"
                stroke-linecap="round"
                class="text-(--success)"
                :stroke-dasharray="circumference(60)"
                :stroke-dashoffset="offset(60, attendanceCompliance)"
              />
            </svg>
            <div class="absolute inset-0 flex flex-col items-center justify-center">
              <span class="text-2xl font-bold text-(--text-main)">{{ attendanceCompliance }}%</span>
              <span class="text-xs text-(--text-muted)">Tercapai</span>
            </div>
          </div>
        </div>
      </BaseCard>

      <BaseCard>
        <template #title>Kepatuhan Lembur</template>
        <template #subtitle>{{ overtimeCompliance }}% bulan ini</template>
        <div class="flex justify-center py-4">
          <div class="relative w-36 h-36">
            <svg class="w-36 h-36 -rotate-90" viewBox="0 0 144 144">
              <circle
                cx="72" cy="72" r="60"
                fill="none"
                stroke="currentColor"
                stroke-width="10"
                class="text-(--bg-elevated)"
              />
              <circle
                cx="72" cy="72" r="60"
                fill="none"
                stroke="currentColor"
                stroke-width="10"
                stroke-linecap="round"
                class="text-(--primary)"
                :stroke-dasharray="circumference(60)"
                :stroke-dashoffset="offset(60, overtimeCompliance)"
              />
            </svg>
            <div class="absolute inset-0 flex flex-col items-center justify-center">
              <span class="text-2xl font-bold text-(--text-main)">{{ overtimeCompliance }}%</span>
              <span class="text-xs text-(--text-muted)">Tercapai</span>
            </div>
          </div>
        </div>
      </BaseCard>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
      <div class="lg:col-span-2">
        <BaseCard>
          <template #title>Karyawan Terlambat Hari Ini</template>
          <template #subtitle>{{ lateEmployees.length }} karyawan</template>
          <div class="overflow-x-auto">
            <table class="w-full border-collapse">
              <thead>
                <tr class="bg-(--bg-elevated)">
                  <th class="px-4 py-3 text-left text-xs font-semibold text-(--text-muted) uppercase tracking-wider">Nama</th>
                  <th class="px-4 py-3 text-left text-xs font-semibold text-(--text-muted) uppercase tracking-wider">Departemen</th>
                  <th class="px-4 py-3 text-left text-xs font-semibold text-(--text-muted) uppercase tracking-wider">Check In</th>
                  <th class="px-4 py-3 text-left text-xs font-semibold text-(--text-muted) uppercase tracking-wider">Terlambat</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="row in lateEmployees"
                  :key="row.id"
                  class="border-b border-(--border-soft) hover:bg-(--bg-elevated)/50 transition-colors"
                >
                  <td class="px-4 py-3 text-sm text-(--text-main)">{{ row.name }}</td>
                  <td class="px-4 py-3 text-sm text-(--text-muted)">{{ row.department }}</td>
                  <td class="px-4 py-3 text-sm text-(--text-main)">{{ row.checkIn }}</td>
                  <td class="px-4 py-3 text-sm">
                    <Badge variant="warning">{{ row.lateMinutes }} menit</Badge>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </BaseCard>
      </div>

      <div>
        <BaseCard>
          <template #title>Izin Hari Ini</template>
          <template #subtitle>{{ leaveToday.length }} karyawan</template>
          <div class="space-y-3">
            <div
              v-for="item in leaveToday"
              :key="item.id"
              class="flex items-start gap-3 p-3 rounded-md bg-(--bg-elevated)/50"
            >
              <div class="w-9 h-9 rounded-full bg-(--primary)/10 flex items-center justify-center shrink-0">
                <span class="text-xs font-semibold text-(--primary)">{{ item.name.charAt(0) }}</span>
              </div>
              <div class="min-w-0">
                <p class="text-sm font-medium text-(--text-main)">{{ item.name }}</p>
                <p class="text-xs text-(--text-muted)">{{ item.reason }}</p>
                <p class="text-xs text-(--text-soft) mt-0.5">{{ item.time }}</p>
              </div>
            </div>
          </div>
          <template #footer>
            <div class="flex items-center justify-between text-sm">
              <span class="text-(--text-muted)">Sisa kuota cuti: <strong class="text-(--text-main)">{{ leaveBalance }}</strong> hari</span>
              <BaseButton variant="ghost" size="sm">Lihat Semua</BaseButton>
            </div>
          </template>
        </BaseCard>
      </div>
    </div>
  </div>
</template>
