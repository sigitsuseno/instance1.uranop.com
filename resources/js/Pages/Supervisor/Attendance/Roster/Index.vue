<template>
  <div class="p-4 sm:p-6 lg:p-8">
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-(--text-main)">Roster Autolog</h1>
        <p class="text-sm text-(--text-muted) mt-1">
          Matrix kehadiran per karyawan — klik cell untuk edit check_in, check_out, lembur, LM
        </p>
      </div>
      <div class="flex items-center gap-3">
        <!-- Adjustment Feedback -->
        <span v-if="adjustmentMessage" class="text-sm font-medium"
          :class="adjustmentSuccess ? 'text-green-600' : 'text-red-600'">
          {{ adjustmentMessage }}
        </span>
        <!-- Holiday Feedback -->
        <span v-if="holidayMessage" class="text-sm font-medium"
          :class="holidaySuccess ? 'text-green-600' : 'text-red-600'">
          {{ holidayMessage }}
        </span>
        <!-- Update Jadwal Feedback -->
        <span v-if="updateMessage" class="text-sm font-medium"
          :class="updateSuccess ? 'text-green-600' : 'text-red-600'">
          {{ updateMessage }}
        </span>
        <!-- Update Cuti Button -->
        <button @click="handleAdjustment"
          :disabled="isAdjusting"
          class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg transition disabled:opacity-50 flex items-center gap-2 text-sm font-medium">
          <svg v-if="isAdjusting" class="w-4 h-4 animate-spin" viewBox="0 0 24 24" fill="none">
            <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2" stroke-dasharray="31.4 31.4" />
          </svg>
          <svg v-else class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z" />
          </svg>
          {{ isAdjusting ? 'Update Cuti...' : 'Update Cuti' }}
        </button>
        <!-- Update Holiday Button -->
        <button @click="handleHolidayUpdate"
          :disabled="isHolidaying"
          class="px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-lg transition disabled:opacity-50 flex items-center gap-2 text-sm font-medium">
          <svg v-if="isHolidaying" class="w-4 h-4 animate-spin" viewBox="0 0 24 24" fill="none">
            <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2" stroke-dasharray="31.4 31.4" />
          </svg>
          <svg v-else class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
            <line x1="16" y1="2" x2="16" y2="6" />
            <line x1="8" y1="2" x2="8" y2="6" />
            <line x1="3" y1="10" x2="21" y2="10" />
          </svg>
          {{ isHolidaying ? 'Update Holiday...' : 'Update Holiday' }}
        </button>
        <!-- Update Jadwal Button -->
        <button @click="handleScheduleUpdate"
          :disabled="isUpdating"
          class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg transition disabled:opacity-50 flex items-center gap-2 text-sm font-medium">
          <svg v-if="isUpdating" class="w-4 h-4 animate-spin" viewBox="0 0 24 24" fill="none">
            <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2" stroke-dasharray="31.4 31.4" />
          </svg>
          <svg v-else class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10" />
            <polyline points="12 6 12 12 16 14" />
          </svg>
          {{ isUpdating ? 'Update Jadwal...' : 'Update Jadwal' }}
        </button>
        <!-- Refresh Button -->
        <button @click="fetchData"
          :disabled="isLoading"
          class="px-3 py-2 border border-(--border-soft) rounded-lg text-(--text-muted) hover:bg-(--bg-elevated) transition flex items-center gap-1 text-sm"
          title="Refresh data">
          <svg class="w-4 h-4" :class="{ 'animate-spin': isLoading }" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polyline points="23 4 23 10 17 10" />
            <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10" />
          </svg>
        </button>
      </div>
    </div>

    <!-- Group Checkboxes -->
    <div class="bg-(--bg-card) border border-(--border-soft) rounded-xl shadow-sm p-4 mb-6">
      <div class="flex items-center gap-2 mb-3">
        <svg class="w-4 h-4 text-(--text-muted)" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
          <circle cx="9" cy="7" r="4" />
          <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
          <path d="M16 3.13a4 4 0 0 1 0 7.75" />
        </svg>
        <span class="text-sm font-semibold text-(--text-main)">Filter Group</span>
        <span class="text-xs text-(--text-muted)">(Imported Shift/Group)</span>
      </div>
      <div class="flex flex-wrap gap-2">
        <label v-for="group in availableGroups" :key="group.code"
          class="flex items-center gap-2 px-3 py-1.5 rounded-lg border cursor-pointer select-none transition text-sm"
          :class="selectedGroups.includes(group.code)
            ? 'bg-indigo-50 border-indigo-300 text-indigo-700 dark:bg-indigo-900/20 dark:border-indigo-700 dark:text-indigo-300'
            : 'bg-(--bg-card) border-(--border-soft) text-(--text-muted) hover:border-indigo-200'">
          <input v-model="selectedGroups" type="checkbox" :value="group.code"
            class="w-3.5 h-3.5 rounded accent-indigo-600"
            @change="fetchData" />
          {{ group.name }}
          <span class="text-xs opacity-50">{{ group.code }}</span>
        </label>
      </div>
    </div>

    <!-- Toolbar: Period + Date Navigation -->
    <div class="bg-(--bg-card) border border-(--border-soft) rounded-xl shadow-sm p-4 mb-6">
      <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
        <!-- Period Selector -->
        <div class="flex items-center gap-3">
          <label class="text-sm font-medium text-(--text-main) whitespace-nowrap">
            <svg class="w-4 h-4 inline mr-1 -mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
              <line x1="16" y1="2" x2="16" y2="6" />
              <line x1="8" y1="2" x2="8" y2="6" />
              <line x1="3" y1="10" x2="21" y2="10" />
            </svg>
            Periode
          </label>
          <select v-model="selectedPeriod"
            class="px-3 py-2 border border-(--border-soft) rounded-lg bg-(--bg-elevated) text-(--text-main) text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 min-w-[280px]"
            @change="onPeriodChange" :disabled="isLoading">
            <option v-for="p in payPeriods" :key="p.id" :value="p.id">{{ p.label }}</option>
          </select>
        </div>

        <!-- Date Navigation -->
        <div class="flex items-center gap-2">
          <button
            class="w-8 h-8 rounded-lg border border-(--border-soft) bg-(--bg-card) flex items-center justify-center text-(--text-secondary) hover:bg-(--bg-elevated) transition disabled:opacity-30"
            :disabled="dateWindowStart <= 0" @click="shiftWindow(-1)">
            <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
              <path d="M15 18l-6-6 6-6" />
            </svg>
          </button>

          <button v-for="(d, i) in visibleDates" :key="d.date"
            class="w-10 h-8 rounded-lg border text-sm font-semibold transition font-mono"
            :class="d.date === activeDate
              ? 'bg-(--text-main) text-white border-(--text-main)'
              : 'bg-(--bg-card) border-(--border-soft) text-(--text-main) hover:border-indigo-500 hover:text-indigo-600'"
            @click="activeDate = d.date">
            {{ d.day }}
          </button>

          <button
            class="w-8 h-8 rounded-lg border border-(--border-soft) bg-(--bg-card) flex items-center justify-center text-(--text-secondary) hover:bg-(--bg-elevated) transition disabled:opacity-30"
            :disabled="dateWindowEnd >= allDates.length" @click="shiftWindow(1)">
            <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
              <path d="M9 18l6-6-6-6" />
            </svg>
          </button>

          <span class="text-xs text-(--text-muted) ml-2 hidden sm:inline">
            {{ formatDateLong(activeDate) }}
          </span>
        </div>
      </div>
    </div>

    <!-- Filter Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
      <div class="flex gap-2">
        <div class="relative">
          <svg class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-(--text-soft)" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="11" cy="11" r="8" /><path d="M21 21l-4.35-4.35" />
          </svg>
          <input v-model="searchQuery" type="text" placeholder="Cari karyawan..."
            class="pl-9 pr-3 py-2 border border-(--border-soft) rounded-lg bg-(--bg-card) text-(--text-main) text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-56" />
        </div>
        <div v-if="isLoading" class="flex items-center gap-1 text-sm text-(--text-muted)">
          <svg class="w-4 h-4 animate-spin" viewBox="0 0 24 24" fill="none">
            <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2" stroke-dasharray="31.4 31.4" />
          </svg>
          Memuat...
        </div>
      </div>
      <div class="text-sm text-(--text-muted)">
        <svg class="w-4 h-4 inline mr-1 -mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="12" cy="12" r="10" /><line x1="12" y1="16" x2="12" y2="12" /><line x1="12" y1="8" x2="12.01" y2="8" />
        </svg>
        Klik cell untuk edit absensi
      </div>
    </div>

    <!-- Table -->
    <div class="overflow-auto max-h-[65vh] bg-(--bg-card) border border-(--border-soft) rounded-xl shadow-sm relative custom-scrollbar">
      <table class="w-full text-sm">
        <thead class="sticky top-0 z-20">
          <tr class="border-b border-(--border-soft) bg-(--bg-elevated)">
            <th class="sticky left-0 bg-(--bg-elevated) px-4 py-3 text-left text-xs font-medium text-(--text-muted) uppercase tracking-wider z-30 min-w-[170px]">
              Nama
            </th>
            <th v-for="d in visibleDates" :key="d.date"
              class="px-3 py-3 text-center text-xs font-medium text-(--text-muted) uppercase tracking-wider min-w-[100px]"
              :class="{ 'bg-red-50/30 dark:bg-red-900/10': d.isWeekend, 'ring-2 ring-inset ring-indigo-500': d.date === activeDate }">
              <div>{{ d.day }} {{ monthNames[parseMonth(d.date)] }}</div>
              <div class="text-[10px]">{{ d.dayName }}</div>
            </th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="emp in filteredEmployees" :key="emp.id"
            class="border-b border-(--border-soft) hover:bg-(--bg-elevated)/30 transition">
            <td class="sticky left-0 bg-(--bg-card) px-4 py-0 font-medium text-(--text-main) z-10">
              <div class="py-2">
                <div class="text-sm font-semibold">{{ emp.name }}</div>
                <div class="text-xs text-(--text-muted)">{{ emp.nip }}</div>
                <div class="text-xs text-(--text-soft)">{{ emp.department }}</div>
              </div>
            </td>

            <!-- Date columns -->
            <td v-for="d in visibleDates" :key="d.date"
              class="px-2 py-0 text-center align-top transition border-l border-(--border-soft)/30 cursor-pointer hover:bg-indigo-50/30"
              :class="{ 'bg-red-50/20 dark:bg-red-900/5': d.isWeekend }"
              @click="openEdit(emp, d)">
              <div class="py-2 space-y-[5px]">
                <!-- Check In -->
                <div class="text-xs font-mono"
                  :class="getCellData(emp.id, d.date).check_in ? 'text-(--text-main) font-semibold' : 'text-(--text-muted)'">
                  <span class="text-[10px] text-(--text-soft) mr-0.5">in</span>
                  {{ getCellData(emp.id, d.date).check_in || '--:--' }}
                </div>
                <!-- Check Out -->
                <div class="text-xs font-mono"
                  :class="getCellData(emp.id, d.date).check_out ? 'text-(--text-main)' : 'text-(--text-muted)'">
                  <span class="text-[10px] text-(--text-soft) mr-0.5">out</span>
                  {{ getCellData(emp.id, d.date).check_out || '--:--' }}
                </div>
                <!-- Shift Row -->
                <div class="text-[10px] text-(--text-soft) font-mono leading-tight">
                  <span v-if="getCellData(emp.id, d.date).shift_start">
                    {{ getCellData(emp.id, d.date).shift_start }} - {{ getCellData(emp.id, d.date).shift_end }}
                  </span>
                  <span v-else class="text-(--text-muted)">-</span>
                </div>
                <!-- Lembur (menit) -->
                <div class="text-[10px] font-mono"
                  :class="getCellData(emp.id, d.date).lembur > 0 ? 'text-orange-500 font-semibold' : 'text-(--text-muted)'">
                  OT: {{ getCellData(emp.id, d.date).lembur || 0 }} mnt
                </div>
                <!-- LM (menit) -->
                <div class="text-[10px] font-mono"
                  :class="getCellData(emp.id, d.date).lm > 0 ? 'text-blue-500 font-semibold' : 'text-(--text-muted)'">
                  LM: {{ getCellData(emp.id, d.date).lm || 0 }} mnt
                </div>
                <!-- Status Badge -->
                <span class="inline-block px-1.5 py-0.5 text-[10px] rounded-full font-semibold"
                  :class="statusBadgeClass(getCellData(emp.id, d.date).status)">
                  {{ statusLabel(getCellData(emp.id, d.date).status) }}
                </span>
                <!-- Lock icon -->
                <svg v-if="getCellData(emp.id, d.date).is_locked" class="w-3 h-3 inline text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
                  <path d="M7 11V7a5 5 0 0 1 10 0v4" />
                </svg>
              </div>
            </td>
          </tr>

          <tr v-if="filteredEmployees.length === 0">
            <td :colspan="visibleDates.length + 1" class="px-4 py-12 text-center text-(--text-muted)">
              <svg class="w-8 h-8 mx-auto mb-2 opacity-40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <path d="M20 13V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v7m16 0v5a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-5m16 0h-2.586a1 1 0 0 0-.707.293l-2.414 2.414a1 1 0 0 1-.707.293h-3.172a1 1 0 0 1-.707-.293l-2.414-2.414A1 1 0 0 0 6.586 13H4" />
              </svg>
              {{ isLoading ? 'Memuat...' : 'Tidak ada data. Pilih group dan periode terlebih dahulu.' }}
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Footer -->
    <div class="mt-4 flex justify-between items-center text-sm text-(--text-muted)">
      <span>{{ isLoading ? 'Memuat...' : `Menampilkan ${filteredEmployees.length} karyawan` }}</span>
    </div>

    <!-- Edit Modal -->
    <BaseModal v-if="editingCell" :show="!!editingCell" title="Edit Autolog" size="lg" @close="closeEdit">
      <div class="p-3 bg-(--bg-elevated) rounded-lg mb-4">
        <p class="text-sm font-semibold text-(--text-main)">{{ editingCell.employee.name }}</p>
        <p class="text-xs text-(--text-muted) mt-1">
          <svg class="w-3.5 h-3.5 inline mr-1 -mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
            <line x1="16" y1="2" x2="16" y2="6" />
            <line x1="8" y1="2" x2="8" y2="6" />
            <line x1="3" y1="10" x2="21" y2="10" />
          </svg>
          {{ formatDateLong(editingCell.date.date) }} — {{ editingCell.date.dayName }}
        </p>
        <!-- Shift info -->
        <p class="text-xs text-(--text-soft) mt-0.5" v-if="editForm.shift_start">
          Jadwal: {{ editForm.shift_start }} - {{ editForm.shift_end }}
        </p>
        <!-- Locked warning -->
        <div v-if="editForm.is_locked" class="mt-2 p-2 rounded bg-yellow-50 border border-yellow-200 text-yellow-800 text-xs flex items-center gap-1">
          <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
            <path d="M7 11V7a5 5 0 0 1 10 0v4" />
          </svg>
          Record ini terkunci. Tidak dapat diedit.
        </div>
      </div>

      <!-- Error alert -->
      <div v-if="editError" class="mb-4 p-3 rounded-lg text-sm font-medium bg-red-50 border border-red-200 text-red-800">
        {{ editError }}
      </div>

      <div class="space-y-4">
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-(--text-main) mb-1">Check-in</label>
            <input v-model="editForm.check_in" type="time"
              class="w-full px-3 py-2 border border-(--border-soft) rounded-lg bg-(--bg-card) text-(--text-main) text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
              :disabled="editForm.is_locked || !editForm.id" />
          </div>
          <div>
            <label class="block text-sm font-medium text-(--text-main) mb-1">Check-out</label>
            <input v-model="editForm.check_out" type="time"
              class="w-full px-3 py-2 border border-(--border-soft) rounded-lg bg-(--bg-card) text-(--text-main) text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
              :disabled="editForm.is_locked || !editForm.id" />
          </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-(--text-main) mb-1">Lembur (menit)</label>
            <input v-model.number="editForm.lembur" type="number" min="0" step="1"
              class="w-full px-3 py-2 border border-(--border-soft) rounded-lg bg-(--bg-card) text-(--text-main) text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
              :disabled="editForm.is_locked || !editForm.id" />
          </div>
          <div>
            <label class="block text-sm font-medium text-(--text-main) mb-1">LM (menit)</label>
            <input v-model.number="editForm.lm" type="number" min="0" step="1"
              class="w-full px-3 py-2 border border-(--border-soft) rounded-lg bg-(--bg-card) text-(--text-main) text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
              :disabled="editForm.is_locked || !editForm.id" />
          </div>
        </div>

        <!-- Status -->
        <div>
          <label class="block text-sm font-medium text-(--text-main) mb-1">Status</label>
          <select v-model="editForm.status"
            class="w-full px-3 py-2 border border-(--border-soft) rounded-lg bg-(--bg-card) text-(--text-main) text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
            :disabled="editForm.is_locked || !editForm.id">
            <option value="present">Hadir</option>
            <option value="absent">Absen</option>
            <option value="leave">Cuti</option>
            <option value="permit">Izin</option>
            <option value="holiday">Libur</option>
            <option value="off">Off</option>
            <option value="pending">Pending</option>
          </select>
        </div>
      </div>

      <template #footer>
        <div class="flex gap-3 w-full">
          <button @click="closeEdit"
            class="px-4 py-2 border border-(--border-soft) rounded-lg text-(--text-muted) hover:bg-(--bg-elevated) transition">
            Tutup
          </button>
          <span class="flex-1"></span>
          <button v-if="editForm.id && !editForm.is_locked" @click="handleSaveEdit"
            class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg transition disabled:opacity-50 flex items-center gap-2"
            :disabled="isSaving">
            <svg v-if="isSaving" class="w-4 h-4 animate-spin" viewBox="0 0 24 24" fill="none">
              <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2" stroke-dasharray="31.4 31.4" />
            </svg>
            <svg v-else class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z" />
              <polyline points="17 21 17 13 7 13 7 21" />
              <polyline points="7 3 7 8 15 8" />
            </svg>
            Simpan
          </button>
        </div>
      </template>
    </BaseModal>

    <!-- Update Jadwal Modal -->
    <BaseModal v-if="showScheduleModal" :show="showScheduleModal" title="Update Jadwal" size="lg" @close="closeScheduleModal">
      <div class="p-3 bg-(--bg-elevated) rounded-lg mb-4">
        <p class="text-sm text-(--text-muted)">
          Pilih karyawan untuk memperbarui jadwal absensi dari roster
          (check_in, check_out, actual_in, actual_out) pada periode
          <span class="font-medium text-(--text-main)">{{ formatDateLong(startDate) }}</span> —
          <span class="font-medium text-(--text-main)">{{ formatDateLong(endDate) }}</span>.
        </p>
      </div>

      <!-- Search -->
      <div class="relative mb-3">
        <svg class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-(--text-soft)" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="11" cy="11" r="8" /><path d="M21 21l-4.35-4.35" />
        </svg>
        <input v-model="scheduleSearchQuery" type="text" placeholder="Cari karyawan..."
          class="pl-9 pr-3 py-2 border border-(--border-soft) rounded-lg bg-(--bg-card) text-(--text-main) text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 w-full" />
      </div>

      <!-- Select all / counter -->
      <div class="flex items-center justify-between mb-2 text-xs text-(--text-muted)">
        <label class="flex items-center gap-2 cursor-pointer select-none">
          <input type="checkbox"
            :checked="scheduleFilteredEmployees.length > 0 && scheduleFilteredEmployees.every(e => scheduleSelectedIds.includes(e.id))"
            @change="toggleScheduleSelectAll"
            class="w-3.5 h-3.5 rounded accent-emerald-600" />
          Pilih semua ({{ scheduleFilteredEmployees.length }})
        </label>
        <span>Terpilih: {{ scheduleSelectedIds.length }}</span>
      </div>

      <!-- Employee checklist -->
      <div class="max-h-[45vh] overflow-y-auto border border-(--border-soft) rounded-lg custom-scrollbar divide-y divide-(--border-soft)/50">
        <label v-for="emp in scheduleFilteredEmployees" :key="emp.id"
          class="flex items-start gap-3 px-3 py-2 cursor-pointer hover:bg-(--bg-elevated)/50 transition">
          <input type="checkbox" :value="emp.id" v-model="scheduleSelectedIds"
            class="w-3.5 h-3.5 rounded accent-emerald-600 mt-0.5" />
          <span class="min-w-0">
            <span class="block text-sm font-medium text-(--text-main) truncate">{{ emp.name }}</span>
            <span class="block text-xs text-(--text-muted)">{{ emp.nip }} · {{ emp.department }}</span>
          </span>
        </label>
        <div v-if="scheduleFilteredEmployees.length === 0" class="px-4 py-8 text-center text-sm text-(--text-muted)">
          Tidak ada karyawan.
        </div>
      </div>

      <template #footer>
        <div class="flex gap-3 w-full">
          <button @click="closeScheduleModal"
            class="px-4 py-2 border border-(--border-soft) rounded-lg text-(--text-muted) hover:bg-(--bg-elevated) transition">
            Tutup
          </button>
          <span class="flex-1"></span>
          <button @click="submitScheduleUpdate"
            :disabled="isSubmittingSchedule || scheduleSelectedIds.length === 0"
            class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg transition disabled:opacity-50 flex items-center gap-2">
            <svg v-if="isSubmittingSchedule" class="w-4 h-4 animate-spin" viewBox="0 0 24 24" fill="none">
              <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2" stroke-dasharray="31.4 31.4" />
            </svg>
            <svg v-else class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z" />
              <polyline points="17 21 17 13 7 13 7 21" />
              <polyline points="7 3 7 8 15 8" />
            </svg>
            Update
          </button>
        </div>
      </template>
    </BaseModal>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { useApi } from '../../../../composables/useApi'
import BaseModal from '@/Components/BaseModal.vue'

const { get, post } = useApi()

// ── State ──
const isLoading = ref(true)
const isSaving = ref(false)
const isAdjusting = ref(false)
const adjustmentMessage = ref('')
const adjustmentSuccess = ref(false)
const isHolidaying = ref(false)
const holidayMessage = ref('')
const holidaySuccess = ref(false)
const isUpdating = ref(false)
const updateMessage = ref('')
const updateSuccess = ref(false)
const showScheduleModal = ref(false)
const scheduleSelectedIds = ref([])
const scheduleSearchQuery = ref('')
const isSubmittingSchedule = ref(false)
const editError = ref(null)

const availableGroups = ref([])
const selectedGroups = ref([])
const employees = ref([])
const autologData = ref({})
const allDates = ref([])
const payPeriods = ref([])
const selectedPeriod = ref('')
const startDate = ref('')
const endDate = ref('')

const activeDate = ref(new Date().toISOString().split('T')[0])
const dateWindowStart = ref(0)
const searchQuery = ref('')
const editingCell = ref(null)

const editForm = ref({
  id: null,
  check_in: '',
  check_out: '',
  lembur: 0,
  lm: 0,
  status: 'present',
  shift_start: null,
  shift_end: null,
  is_locked: false,
})

const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des']

// ── Computed ──
const WINDOW_SIZE = 5
const dateWindowEnd = computed(() => Math.min(dateWindowStart.value + WINDOW_SIZE, allDates.value.length))
const visibleDates = computed(() => allDates.value.slice(dateWindowStart.value, dateWindowEnd.value))

const filteredEmployees = computed(() => {
  let result = employees.value
  if (searchQuery.value) {
    const q = searchQuery.value.toLowerCase()
    result = result.filter(e => e.name.toLowerCase().includes(q) || e.nip.toLowerCase().includes(q))
  }
  return result
})

const scheduleFilteredEmployees = computed(() => {
  const q = scheduleSearchQuery.value.toLowerCase().trim()
  if (!q) return employees.value
  return employees.value.filter(e =>
    e.name.toLowerCase().includes(q) || (e.nip || '').toLowerCase().includes(q)
  )
})

// ── API ──
async function fetchPayPeriods() {
  try {
    const res = await get('/api/v1/payroll/periods')
    const list = res.data || []
    payPeriods.value = list.map(p => ({
      id: p.id,
      name: p.name,
      start_date: p.start_date,
      end_date: p.end_date,
      label: `${p.name} (${p.start_date} - ${p.end_date})`,
    }))
    if (payPeriods.value.length > 0 && !selectedPeriod.value) {
      selectedPeriod.value = payPeriods.value[0].id
      const p = payPeriods.value[0]
      startDate.value = p.start_date
      endDate.value = p.end_date
    }
  } catch (e) {
    console.error('Gagal fetch pay periods:', e)
  }
}

async function fetchData() {
  if (selectedGroups.value.length === 0) return
  isLoading.value = true
  try {
    const params = new URLSearchParams()
    if (startDate.value) params.append('start_date', startDate.value)
    if (endDate.value) params.append('end_date', endDate.value)
    selectedGroups.value.forEach(g => params.append('group_codes[]', g))
    if (searchQuery.value) params.append('search', searchQuery.value)

    const res = await get(`/api/v1/supervisor/attendance/roster?${params.toString()}`)

    employees.value = res.employees || []
    allDates.value = res.dates || []
    autologData.value = res.autologData || {}
    availableGroups.value = res.groups || []

    // Set all groups selected if none
    if (selectedGroups.value.length === 0 && availableGroups.value.length > 0) {
      selectedGroups.value = availableGroups.value.map(g => g.code)
    }

    // Active date handling
    if (allDates.value.length > 0) {
      dateWindowStart.value = 0
      // Try to find today
      const today = new Date().toISOString().split('T')[0]
      const todayIdx = allDates.value.findIndex(d => d.date === today)
      if (todayIdx >= 0) {
        dateWindowStart.value = Math.max(0, todayIdx - Math.floor(WINDOW_SIZE / 2))
        activeDate.value = today
      } else {
        activeDate.value = allDates.value[0].date
      }
    }
  } catch (e) {
    console.error('Gagal fetch roster data:', e)
  } finally {
    isLoading.value = false
  }
}

onMounted(async () => {
  await fetchPayPeriods()
  // Fetch groups dulu, baru data
  try {
    const groupsRes = await get('/api/v1/settings/employee-data/groups')
    const allGroups = groupsRes.data || []
    availableGroups.value = allGroups
      .filter(g => g.group_label === 'Imported Shift/Group')
      .map(g => ({ code: g.code, name: g.name }))
      .filter(g => g.code) // exclude empty codes
  } catch (e) {
    console.error('Gagal fetch groups:', e)
    availableGroups.value = []
  }
  if (availableGroups.value.length > 0) {
    selectedGroups.value = availableGroups.value.map(g => g.code)
    await fetchData()
  }
})

// ── Adjustment (Update Cuti ke Autolog) ──
async function handleAdjustment() {
  if (!startDate.value || !endDate.value) {
    adjustmentMessage.value = 'Periode belum dipilih.'
    adjustmentSuccess.value = false
    setTimeout(() => { adjustmentMessage.value = '' }, 5000)
    return
  }
  isAdjusting.value = true
  adjustmentMessage.value = ''
  try {
    const res = await post('/api/v1/supervisor/attendance/roster/adjustment', {
      start_date: startDate.value,
      end_date: endDate.value,
    })
    if (res.success) {
      adjustmentSuccess.value = true
      adjustmentMessage.value = res.message || 'Cuti berhasil diupdate!'
      // Refresh data setelah adjustment
      await fetchData()
    } else {
      adjustmentSuccess.value = false
      adjustmentMessage.value = res.message || 'Gagal update cuti.'
    }
  } catch (e) {
    adjustmentSuccess.value = false
    adjustmentMessage.value = e.message || 'Gagal update cuti.'
  } finally {
    isAdjusting.value = false
    setTimeout(() => { adjustmentMessage.value = '' }, 8000)
  }
}

// ── Holiday Update ──
async function handleHolidayUpdate() {
  if (!startDate.value || !endDate.value) {
    holidayMessage.value = 'Periode belum dipilih.'
    holidaySuccess.value = false
    setTimeout(() => { holidayMessage.value = '' }, 5000)
    return
  }
  isHolidaying.value = true
  holidayMessage.value = ''
  try {
    const res = await post('/api/v1/supervisor/attendance/roster/holiday', {
      start_date: startDate.value,
      end_date: endDate.value,
    })
    if (res.success) {
      holidaySuccess.value = true
      holidayMessage.value = res.message || 'Holiday berhasil diupdate!'
      // Refresh data setelah update
      await fetchData()
    } else {
      holidaySuccess.value = false
      holidayMessage.value = res.message || 'Gagal update holiday.'
    }
  } catch (e) {
    holidaySuccess.value = false
    holidayMessage.value = e.message || 'Gagal update holiday.'
  } finally {
    isHolidaying.value = false
    setTimeout(() => { holidayMessage.value = '' }, 8000)
  }
}

// ── Update Jadwal ──
function handleScheduleUpdate() {
  if (!startDate.value || !endDate.value) {
    updateMessage.value = 'Periode belum dipilih.'
    updateSuccess.value = false
    setTimeout(() => { updateMessage.value = '' }, 5000)
    return
  }
  scheduleSelectedIds.value = []
  scheduleSearchQuery.value = ''
  showScheduleModal.value = true
}

function closeScheduleModal() {
  showScheduleModal.value = false
}

function toggleScheduleSelectAll(e) {
  const visibleIds = scheduleFilteredEmployees.value.map(emp => emp.id)
  if (e.target.checked) {
    scheduleSelectedIds.value = [...new Set([...scheduleSelectedIds.value, ...visibleIds])]
  } else {
    const idSet = new Set(visibleIds)
    scheduleSelectedIds.value = scheduleSelectedIds.value.filter(id => !idSet.has(id))
  }
}

async function submitScheduleUpdate() {
  if (scheduleSelectedIds.value.length === 0) return
  isSubmittingSchedule.value = true
  updateMessage.value = ''
  try {
    const res = await post('/api/v1/supervisor/attendance/roster/update-schedule', {
      start_date: startDate.value,
      end_date: endDate.value,
      employee_ids: scheduleSelectedIds.value,
    })
    if (res.success) {
      updateSuccess.value = true
      updateMessage.value = res.message || 'Update Jadwal berhasil!'
      showScheduleModal.value = false
      // Refresh data setelah update
      await fetchData()
    } else {
      updateSuccess.value = false
      updateMessage.value = res.message || 'Gagal update jadwal.'
    }
  } catch (e) {
    updateSuccess.value = false
    updateMessage.value = e.message || 'Gagal update jadwal.'
  } finally {
    isSubmittingSchedule.value = false
    setTimeout(() => { updateMessage.value = '' }, 8000)
  }
}

// ── Period ──
function onPeriodChange() {
  const p = payPeriods.value.find(x => x.id === selectedPeriod.value)
  if (p) {
    startDate.value = p.start_date
    endDate.value = p.end_date
    fetchData()
  }
}

// ── Date Navigation ──
function shiftWindow(dir) {
  let newStart = dateWindowStart.value + (dir * WINDOW_SIZE)
  // Clamp biar bisa sampai ujung (nggak stuck di tengah)
  newStart = Math.max(0, Math.min(newStart, allDates.value.length - WINDOW_SIZE))
  if (newStart !== dateWindowStart.value) {
    dateWindowStart.value = newStart
    if (visibleDates.value.length > 0) {
      activeDate.value = visibleDates.value[0].date
    }
  }
}

// ── Helpers ──
function getCellData(empId, dateStr) {
  return autologData.value[empId]?.[dateStr] || {}
}

function formatDateLong(dateStr) {
  if (!dateStr) return ''
  const [y, m, d] = dateStr.split('-').map(Number)
  const date = new Date(y, m - 1, d)
  const dayNames = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu']
  return `${dayNames[date.getDay()]}, ${d} ${monthNames[m - 1]} ${y}`
}

function statusLabel(status) {
  const map = { present: 'Hadir', absent: 'Absen', leave: 'Cuti', permit: 'Izin', holiday: 'Libur', off: 'Off', pending: 'Menunggu' }
  return map[status] || status || '-'
}

// Timezone-safe month parser: "2026-07-28" → 6 (July, 0-indexed)
function parseMonth(dateStr) {
  if (!dateStr) return 0
  return parseInt(dateStr.split('-')[1], 10) - 1
}

function statusBadgeClass(status) {
  const map = {
    present: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
    absent: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
    leave: 'bg-blue-100 text-blue-800',
    permit: 'bg-purple-100 text-purple-800',
    holiday: 'bg-gray-100 text-gray-800',
    off: 'bg-orange-100 text-orange-800',
    pending: 'bg-yellow-100 text-yellow-800',
  }
  return map[status] || 'bg-gray-100 text-gray-800'
}

// ── Edit Modal ──
function openEdit(emp, dateObj) {
  const cell = getCellData(emp.id, dateObj.date)
  editingCell.value = { employee: emp, date: dateObj }
  editForm.value = {
    id: cell.id || null,
    check_in: cell.check_in || '',
    check_out: cell.check_out || '',
    lembur: cell.lembur || 0,
    lm: cell.lm || 0,
    status: cell.status || 'present',
    shift_start: cell.shift_start || null,
    shift_end: cell.shift_end || null,
    is_locked: cell.is_locked || false,
  }
  editError.value = null
}

function closeEdit() {
  editingCell.value = null
}

async function handleSaveEdit() {
  isSaving.value = true
  editError.value = null
  try {
    const res = await post('/api/v1/supervisor/attendance/roster/update', {
      id: editForm.value.id,
      check_in: editForm.value.check_in || null,
      check_out: editForm.value.check_out || null,
      lembur: editForm.value.lembur,
      lm: editForm.value.lm,
      status: editForm.value.status,
    })

    if (res.success) {
      // Update local cache
      const empId = editingCell.value.employee.id
      const dateStr = editingCell.value.date.date
      if (autologData.value[empId]?.[dateStr]) {
        autologData.value[empId][dateStr].check_in = res.data.check_in
        autologData.value[empId][dateStr].check_out = res.data.check_out
        autologData.value[empId][dateStr].lembur = res.data.lembur
        autologData.value[empId][dateStr].lm = res.data.lm
        autologData.value[empId][dateStr].status = res.data.status
      }
      closeEdit()
    } else {
      editError.value = res.message || 'Gagal menyimpan.'
    }
  } catch (e) {
    editError.value = e.message || 'Gagal menyimpan data.'
  } finally {
    isSaving.value = false
  }
}
</script>
