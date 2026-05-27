<script setup>
import { ref, computed } from 'vue'
import BaseCard from '../../../../Components/BaseCard.vue'
import BaseButton from '../../../../Components/BaseButton.vue'
import Badge from '../../../../Components/Badge.vue'
import SelectInput from '../../../../Components/SelectInput.vue'
import {
  IconChevronDown,
  IconDownload,
} from '../../../../Components/Icons/index.js'

const viewMode = ref('table')
const selectedMonth = ref('2026-05')

const rosterData = ref([
  { id: 1, name: 'Andi Prasetyo', nip: '2024001', department: 'Produksi', shifts: { 1: 'P', 2: 'P', 3: 'P', 4: 'P', 5: 'P', 6: 'L', 7: 'L', 8: 'P', 9: 'P', 10: 'P', 11: 'P', 12: 'P', 13: 'L', 14: 'L', 15: 'P', 16: 'P', 17: 'P', 18: 'P', 19: 'P', 20: 'L', 21: 'L', 22: 'P', 23: 'P', 24: 'P', 25: 'P', 26: 'P', 27: 'L', 28: 'L', 29: 'P', 30: 'P', 31: 'P' } },
  { id: 2, name: 'Budi Santoso', nip: '2024002', department: 'Produksi', shifts: { 1: 'M', 2: 'M', 3: 'M', 4: 'M', 5: 'M', 6: 'L', 7: 'L', 8: 'M', 9: 'M', 10: 'M', 11: 'M', 12: 'M', 13: 'L', 14: 'L', 15: 'M', 16: 'M', 17: 'M', 18: 'M', 19: 'M', 20: 'L', 21: 'L', 22: 'M', 23: 'M', 24: 'M', 25: 'M', 26: 'M', 27: 'L', 28: 'L', 29: 'M', 30: 'M', 31: 'M' } },
  { id: 3, name: 'Citra Dewi', nip: '2024003', department: 'QC', shifts: { 1: 'P', 2: 'P', 3: 'P', 4: 'P', 5: 'P', 6: 'L', 7: 'L', 8: 'P', 9: 'P', 10: 'P', 11: 'P', 12: 'P', 13: 'L', 14: 'L', 15: 'P', 16: 'P', 17: 'P', 18: 'P', 19: 'P', 20: 'L', 21: 'L', 22: 'P', 23: 'P', 24: 'P', 25: 'P', 26: 'P', 27: 'L', 28: 'L', 29: 'P', 30: 'P', 31: '' } },
  { id: 4, name: 'Dian Permata', nip: '2024004', department: 'Gudang', shifts: { 1: 'P', 2: 'P', 3: 'P', 4: 'P', 5: 'P', 6: 'L', 7: 'L', 8: 'P', 9: 'P', 10: 'P', 11: 'P', 12: 'P', 13: 'L', 14: 'L', 15: 'P', 16: 'P', 17: 'P', 18: 'P', 19: 'P', 20: 'L', 21: 'L', 22: 'P', 23: 'P', 24: 'P', 25: 'P', 26: 'P', 27: 'L', 28: 'L', 29: 'P', 30: 'P', 31: 'P' } },
  { id: 5, name: 'Eko Wahyudi', nip: '2024005', department: 'Produksi', shifts: { 1: 'M', 2: 'M', 3: 'M', 4: 'M', 5: 'M', 6: 'L', 7: 'L', 8: 'M', 9: 'M', 10: 'M', 11: 'M', 12: 'M', 13: 'L', 14: 'L', 15: 'M', 16: 'M', 17: 'M', 18: 'M', 19: 'M', 20: 'L', 21: 'L', 22: 'M', 23: 'M', 24: 'M', 25: 'M', 26: 'M', 27: 'L', 28: 'L', 29: 'M', 30: 'M', 31: '' } },
  { id: 6, name: 'Indah Sari', nip: '2024009', department: 'QC', shifts: { 1: 'P', 2: 'P', 3: 'P', 4: 'P', 5: 'P', 6: 'L', 7: 'L', 8: 'P', 9: 'P', 10: 'P', 11: 'P', 12: 'P', 13: 'L', 14: 'L', 15: 'P', 16: 'P', 17: 'P', 18: 'P', 19: 'P', 20: 'L', 21: 'L', 22: 'P', 23: 'P', 24: 'P', 25: 'P', 26: 'P', 27: 'L', 28: 'L', 29: 'P', 30: 'P', 31: 'P' } },
  { id: 7, name: 'Kartika Dewi', nip: '2024011', department: 'Gudang', shifts: { 1: 'P', 2: 'P', 3: 'P', 4: 'P', 5: 'P', 6: 'L', 7: 'L', 8: 'P', 9: 'P', 10: 'P', 11: 'P', 12: 'P', 13: 'L', 14: 'L', 15: 'P', 16: 'P', 17: 'P', 18: 'P', 19: 'P', 20: 'L', 21: 'L', 22: 'P', 23: 'P', 24: 'P', 25: 'P', 26: 'P', 27: 'L', 28: 'L', 29: 'P', 30: 'P', 31: 'P' } },
  { id: 8, name: 'Maya Anggraini', nip: '2024013', department: 'QC', shifts: { 1: 'P', 2: 'P', 3: 'P', 4: 'P', 5: 'P', 6: 'L', 7: 'L', 8: 'P', 9: 'P', 10: 'P', 11: 'P', 12: 'P', 13: 'L', 14: 'L', 15: 'P', 16: 'P', 17: 'P', 18: 'P', 19: 'P', 20: 'L', 21: 'L', 22: 'P', 23: 'P', 24: 'P', 25: 'P', 26: 'P', 27: 'L', 28: 'L', 29: 'P', 30: 'P', 31: 'P' } },
])

const daysInMonth = computed(() => {
  const [year, month] = selectedMonth.value.split('-').map(Number)
  return new Date(year, month, 0).getDate()
})

const dayNumbers = computed(() => Array.from({ length: daysInMonth.value }, (_, i) => i + 1))

const months = [
  { value: '2026-01', label: 'Januari 2026' },
  { value: '2026-02', label: 'Februari 2026' },
  { value: '2026-03', label: 'Maret 2026' },
  { value: '2026-04', label: 'April 2026' },
  { value: '2026-05', label: 'Mei 2026' },
  { value: '2026-06', label: 'Juni 2026' },
]

function shiftBadge(code) {
  switch (code) {
    case 'P': return 'success'
    case 'M': return 'primary'
    case 'S': return 'warning'
    case 'L': return 'neutral'
    default: return 'neutral'
  }
}

function shiftLabel(code) {
  switch (code) {
    case 'P': return 'Pagi'
    case 'M': return 'Malam'
    case 'S': return 'Siang'
    case 'L': return 'Libur'
    default: return '-'
  }
}
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h3 class="text-xl font-semibold text-(--text-main)">Roster Tim</h3>
        <p class="text-sm text-(--text-muted) mt-1">Jadwal shift tim Anda</p>
      </div>
      <div class="flex items-center gap-3">
        <div class="flex items-center gap-1 bg-(--bg-elevated) rounded-md p-1">
          <button
            :class="[
              'px-3 py-1.5 rounded-md text-xs font-medium transition-colors',
              viewMode === 'table' ? 'bg-(--bg-card) text-(--text-main) shadow-sm' : 'text-(--text-muted) hover:text-(--text-main)'
            ]"
            @click="viewMode = 'table'"
          >Tabel</button>
          <button
            :class="[
              'px-3 py-1.5 rounded-md text-xs font-medium transition-colors',
              viewMode === 'calendar' ? 'bg-(--bg-card) text-(--text-main) shadow-sm' : 'text-(--text-muted) hover:text-(--text-main)'
            ]"
            @click="viewMode = 'calendar'"
          >Kalender</button>
        </div>
        <SelectInput
          :model-value="selectedMonth"
          :options="months"
          placeholder="Pilih bulan"
          @update:model-value="selectedMonth = $event"
        />
        <BaseButton variant="secondary" size="sm">
          <template #icon-left>
            <IconDownload class="w-4 h-4" />
          </template>
          Export
        </BaseButton>
      </div>
    </div>

    <div class="flex items-center gap-4 text-xs text-(--text-muted)">
      <div class="flex items-center gap-1.5">
        <span class="w-3 h-3 rounded-sm bg-(--success)"></span> Pagi
      </div>
      <div class="flex items-center gap-1.5">
        <span class="w-3 h-3 rounded-sm bg-(--primary)"></span> Malam
      </div>
      <div class="flex items-center gap-1.5">
        <span class="w-3 h-3 rounded-sm bg-(--warning)"></span> Siang
      </div>
      <div class="flex items-center gap-1.5">
        <span class="w-3 h-3 rounded-sm bg-(--bg-elevated) border border-(--border-soft)"></span> Libur
      </div>
    </div>

    <BaseCard v-if="viewMode === 'table'">
      <div class="overflow-x-auto">
        <table class="w-full border-collapse">
          <thead>
            <tr class="bg-(--bg-elevated)">
              <th class="px-4 py-3 text-left text-xs font-semibold text-(--text-muted) uppercase tracking-wider sticky left-0 bg-(--bg-elevated) z-10">Nama</th>
              <th
                v-for="day in dayNumbers"
                :key="day"
                class="px-2 py-3 text-center text-xs font-semibold text-(--text-muted) uppercase tracking-wider min-w-[32px]"
                :class="{ 'bg-(--primary)/5': (day % 7 === 0 || day % 7 === 6) }"
              >
                {{ day }}
              </th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in rosterData" :key="row.id" class="border-b border-(--border-soft) hover:bg-(--bg-elevated)/30 transition-colors">
              <td class="px-4 py-3 text-sm text-(--text-main) sticky left-0 bg-(--bg-card) z-10 font-medium">{{ row.name }}</td>
              <td
                v-for="day in dayNumbers"
                :key="day"
                class="px-2 py-3 text-center"
                :class="{ 'bg-(--primary)/5': (day % 7 === 0 || day % 7 === 6) }"
              >
                <Badge v-if="row.shifts[day]" :variant="shiftBadge(row.shifts[day])" size="sm">
                  {{ row.shifts[day] }}
                </Badge>
                <span v-else class="text-xs text-(--text-soft)">-</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </BaseCard>

    <BaseCard v-else>
      <template #title>Tampilan Kalender</template>
      <template #subtitle>{{ new Date(selectedMonth.value + '-01').toLocaleDateString('id-ID', { month: 'long', year: 'numeric' }) }}</template>
      <div class="overflow-x-auto">
        <table class="w-full border-collapse">
          <thead>
            <tr class="bg-(--bg-elevated)">
              <th class="px-4 py-3 text-left text-xs font-semibold text-(--text-muted) uppercase tracking-wider">Karyawan</th>
              <th
                v-for="day in dayNumbers"
                :key="day"
                class="px-2 py-3 text-center text-xs font-semibold text-(--text-muted) uppercase tracking-wider min-w-[36px]"
                :class="{ 'bg-(--primary)/5': (day % 7 === 0 || day % 7 === 6) }"
              >
                <div>{{ day }}</div>
                <div class="text-[10px] opacity-60">{{ ['Min','Sen','Sel','Rab','Kam','Jum','Sab'][(day - 1) % 7] }}</div>
              </th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in rosterData" :key="row.id" class="border-b border-(--border-soft)">
              <td class="px-4 py-3 text-sm font-medium text-(--text-main)">{{ row.name }}</td>
              <td
                v-for="day in dayNumbers"
                :key="day"
                class="px-2 py-3 text-center"
                :class="{ 'bg-(--primary)/5': (day % 7 === 0 || day % 7 === 6) }"
              >
                <span
                  v-if="row.shifts[day]"
                  :class="[
                    'inline-flex items-center justify-center w-7 h-7 rounded-md text-xs font-medium',
                    row.shifts[day] === 'P' ? 'bg-(--success)/10 text-(--success)' : '',
                    row.shifts[day] === 'M' ? 'bg-(--primary)/10 text-(--primary)' : '',
                    row.shifts[day] === 'S' ? 'bg-(--warning)/10 text-(--warning)' : '',
                    row.shifts[day] === 'L' ? 'bg-(--bg-elevated) text-(--text-muted)' : '',
                  ]"
                >
                  {{ row.shifts[day] }}
                </span>
                <span v-else class="text-xs text-(--text-soft)">-</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </BaseCard>
  </div>
</template>
