<template>
  <BaseCard>
    <div v-if="loading" class="p-12 flex flex-col items-center justify-center">
      <div class="w-10 h-10 border-4 border-(--primary)/30 border-t-(--primary) rounded-full animate-spin mb-4"></div>
      <p class="text-(--text-muted)">Memuat data...</p>
    </div>

    <div v-else-if="data.length === 0" class="p-12 text-center text-(--text-muted)">
      Tidak ada data uang makan untuk periode yang dipilih.
    </div>

    <div v-else class="overflow-auto max-h-[65vh]">
      <table class="min-w-full divide-y divide-(--border-soft) text-[11px] whitespace-nowrap">
        <!-- Header: Row 1 — Date groups -->
        <thead class="bg-(--bg-elevated) sticky top-0 z-20">
          <tr>
            <th rowspan="2" class="px-3 py-3 text-center font-bold text-(--text-muted) uppercase border-r border-(--border-soft) sticky left-0 bg-(--bg-elevated) z-30">No</th>
            <th rowspan="2" class="px-4 py-3 text-left font-bold text-(--text-muted) uppercase border-r border-(--border-soft) sticky left-[40px] bg-(--bg-elevated) z-30 w-48">Nama</th>
            <th rowspan="2" class="px-4 py-3 text-left font-bold text-(--text-muted) uppercase border-r border-(--border-soft)">Bagian / Jabatan</th>
            <th rowspan="2" class="px-2 py-3 text-center font-bold text-(--text-muted) uppercase border-r border-(--border-soft)">L/P</th>
            <th rowspan="2" class="px-4 py-3 text-center font-bold text-(--text-muted) uppercase border-r border-(--border-soft)">Grup UM</th>
            <th rowspan="2" class="px-4 py-3 text-right font-bold text-(--text-muted) uppercase border-r border-(--border-soft)">Rate UM</th>
            <th rowspan="2" class="px-4 py-3 text-right font-bold text-(--text-muted) uppercase border-r border-(--border-soft)">Rate<br>Sabtu/Mgg</th>
            <!-- Date group headers -->
            <th
              v-for="d in dates"
              :key="'dh-' + d.date"
              colspan="6"
              :class="['px-2 py-2 text-center font-bold text-(--text-main) border-b border-(--border-soft) uppercase', getDayBgClass(d.date)]"
            >
              {{ formatDateHeader(d.date) }}
            </th>
          </tr>

          <!-- Header: Row 2 — Sub-columns per date -->
          <tr>
            <template v-for="d in dates" :key="'sh-' + d.date">
              <th :class="['px-1 py-2 text-center font-bold text-(--text-muted) uppercase border-r border-(--border-soft) text-[10px]', getDayBgClass(d.date)]">Kode</th>
              <th :class="['px-1 py-2 text-center font-bold text-(--text-muted) uppercase border-r border-(--border-soft) text-[10px]', getDayBgClass(d.date)]">H/A</th>
              <th :class="['px-1 py-2 text-center font-bold text-(--text-muted) uppercase border-r border-(--border-soft) text-[10px]', getDayBgClass(d.date)]">Upah/Hari</th>
              <th :class="['px-1 py-2 text-center font-bold text-(--text-muted) uppercase border-r border-(--border-soft) text-[10px]', getDayBgClass(d.date)]">L/M</th>
              <th :class="['px-1 py-2 text-center font-bold text-(--text-muted) uppercase border-r border-(--border-soft) text-[10px]', getDayBgClass(d.date)]">Lembur</th>
              <th :class="['px-3 py-2 text-right font-bold text-(--text-muted) uppercase text-[10px]', getDayBgClass(d.date)]">Nominal</th>
            </template>
          </tr>
        </thead>

        <!-- Body -->
        <tbody class="divide-y divide-(--border-soft)">
          <tr
            v-for="(item, index) in data"
            :key="item.employee_id"
            class="hover:bg-(--bg-elevated) transition-colors group"
          >
            <td class="px-3 py-3 text-center text-(--text-muted) border-r border-(--border-soft) sticky left-0 bg-(--bg-card) group-hover:bg-(--bg-elevated) z-10">{{ index + 1 }}</td>
            <td class="px-4 py-3 font-bold text-(--text-main) border-r border-(--border-soft) sticky left-[40px] bg-(--bg-card) group-hover:bg-(--bg-elevated) z-10 truncate">{{ item.employee_name }}</td>
            <td class="px-4 py-3 text-(--text-main) border-r border-(--border-soft)">{{ item.position || '-' }}</td>
            <td class="px-2 py-3 text-center text-(--text-muted) border-r border-(--border-soft)">{{ item.gender === 'male' ? 'L' : (item.gender === 'female' ? 'P' : (item.gender || '-')) }}</td>
            <td class="px-4 py-3 text-center font-medium text-(--text-main) border-r border-(--border-soft)">{{ item.title || '-' }}</td>
            <td class="px-4 py-3 text-right font-medium text-(--text-main) border-r border-(--border-soft)">{{ item.um_rate ? formatNumber(item.um_rate) : '' }}</td>
            <td class="px-4 py-3 text-right font-medium text-(--text-main) border-r border-(--border-soft)">{{ item.rate_weekend ? formatNumber(item.rate_weekend) : '' }}</td>

            <!-- Daily cells -->
            <template v-for="d in dates" :key="'dc-' + item.employee_id + '-' + d.date">
              <td :class="['px-1 py-3 text-center border-r border-(--border-soft) text-xs', getDayBgClass(d.date), item.days[d.date]?.kode ? 'text-blue-600 font-medium' : 'text-gray-300']">
                {{ item.days[d.date]?.kode || '-' }}
              </td>
              <td :class="['px-1 py-3 text-center border-r border-(--border-soft) text-xs', getDayBgClass(d.date), item.days[d.date]?.ha && item.days[d.date]?.ha !== '-' ? getStatusClass(item.days[d.date].ha) : 'text-gray-300']">
                {{ item.days[d.date]?.ha || '-' }}
              </td>
              <td :class="['px-1 py-3 text-right border-r border-(--border-soft) text-xs', getDayBgClass(d.date), item.days[d.date]?.upah_per_hari > 0 ? 'text-emerald-600 font-medium' : 'text-gray-300']">
                {{ item.days[d.date]?.upah_per_hari > 0 ? formatNumber(item.days[d.date].upah_per_hari) : '-' }}
              </td>
              <td :class="['px-1 py-3 text-center border-r border-(--border-soft) text-xs', getDayBgClass(d.date), item.days[d.date]?.lm > 0 ? 'text-purple-600 font-medium' : 'text-gray-300']">
                {{ item.days[d.date]?.lm > 0 ? item.days[d.date].lm : '-' }}
              </td>
              <td :class="['px-1 py-3 text-center border-r border-(--border-soft) text-xs', getDayBgClass(d.date), item.days[d.date]?.lembur > 0 ? 'text-orange-600 font-medium' : 'text-gray-300']">
                {{ item.days[d.date]?.lembur > 0 ? item.days[d.date].lembur : '-' }}
              </td>
              <td :class="['px-3 py-3 text-right text-xs', getDayBgClass(d.date), item.days[d.date]?.nominal > 0 ? 'text-green-600 font-bold' : 'text-gray-300']">
                {{ item.days[d.date]?.nominal > 0 ? formatNumber(item.days[d.date].nominal) : '-' }}
              </td>
            </template>
          </tr>
        </tbody>
      </table>
    </div>
  </BaseCard>
</template>

<script setup>
import { computed } from 'vue'
import BaseCard from '../../../../Components/BaseCard.vue'

const props = defineProps({
  employees: { type: Array, default: () => [] },
  dates: { type: Array, default: () => [] },
  period: { type: Object, default: () => ({}) },
  loading: { type: Boolean, default: false },
})

// Transform employees ke format data untuk tabel
const data = computed(() => {
  return props.employees.map(emp => {
    const days = {}
    const weekdays = ['Senin','Monday','Selasa','Tuesday','Rabu','Wednesday','Kamis','Thursday','Jumat','Friday']
    const sundays = ['Minggu','Sunday']

    // Tentukan rate berdasarkan group
    const groupName = (emp.title || '').toUpperCase()
    let rateWeekday = 15000
    let rateSabtuDua = 0
    let rateSabtuFull = 0
    let rateMingguSetengah = 0
    let rateMingguFull = 0

    if (groupName.includes('KABAG')) {
      rateSabtuDua = 55000; rateSabtuFull = 110000
      rateMingguSetengah = 110000; rateMingguFull = 220000
    } else if (groupName.includes('KASHIFT') || groupName.includes('KEPALA SHIFT')) {
      rateSabtuDua = 52522; rateSabtuFull = 105000
      rateMingguSetengah = 105000; rateMingguFull = 210000
    } else if (groupName.includes('ALL IN') || groupName.includes('ALL-IN')) {
      rateSabtuDua = 50000; rateSabtuFull = 100000
      rateMingguSetengah = 100000; rateMingguFull = 200000
    }

    for (const d of props.dates) {
      const dateStr = d.date
      const day = emp.days?.[dateStr] || {}
      const dayName = d.day_name || ''
      const lembur = (day.lembur && day.lembur !== '-') ? parseFloat(day.lembur) : 0
      const isSunday = sundays.some(s => dayName.includes(s))
      const isSaturday = dayName.includes('Sabtu') || dayName.includes('Saturday')
      const isLibur = day.status === 'LIBUR'

      let kode = ''
      let ha = day.status || '-'
      let upah_per_hari = 0
      let lm = 0
      let nominal = 0

      if (lembur > 0) {
        if (isSunday || isLibur) {
          kode = 'MGG'
          if (lembur >= 8) {
            upah_per_hari = rateMingguFull
            lm = 8
            nominal = rateMingguFull
          } else if (lembur >= 4) {
            upah_per_hari = rateMingguSetengah
            lm = 4
            nominal = rateMingguSetengah
          }
        } else if (isSaturday) {
          kode = 'SBT'
          if (lembur >= 4) {
            upah_per_hari = rateSabtuFull
            lm = lembur
            nominal = rateSabtuFull
          } else if (lembur >= 2) {
            upah_per_hari = rateSabtuDua
            lm = lembur
            nominal = rateSabtuDua
          }
        } else {
          kode = 'UM'
          if (lembur >= 3) {
            upah_per_hari = rateWeekday
            lm = lembur
            nominal = rateWeekday
          }
        }
      }

      days[dateStr] = { kode: kode || '', ha, upah_per_hari, lm, lembur, nominal }
    }

    return {
      employee_id: emp.employee_id,
      employee_name: emp.employee_name,
      position: emp.position,
      department: emp.department,
      gender: emp.gender || '',
      title: emp.title,
      um_rate: rateWeekday,
      rate_weekend: rateSabtuFull,
      days,
    }
  })
})

function formatNumber(num) {
  return new Intl.NumberFormat('id-ID').format(num || 0)
}

function formatDateHeader(dateStr) {
  const d = new Date(dateStr + 'T00:00:00')
  return new Intl.DateTimeFormat('id-ID', {
    weekday: 'short',
    day: '2-digit',
    month: 'short',
  }).format(d).toUpperCase()
}

function getDayBgClass(dateStr) {
  const d = new Date(dateStr + 'T00:00:00')
  const day = d.getDay()
  if (day === 0) return 'bg-red-50/40'
  if (day === 6) return 'bg-blue-50/30'
  return ''
}

function getStatusClass(status) {
  if (!status || status === '-') return ''
  if (status === 'H') return 'text-green-600 font-bold'
  if (status === 'SAKIT') return 'text-yellow-600'
  if (status === 'I') return 'text-purple-600'
  if (status === 'CUTI') return 'text-blue-600'
  if (status === 'LIBUR') return 'text-orange-600'
  if (status === 'OFF') return 'text-gray-400'
  return 'text-red-600'
}
</script>
