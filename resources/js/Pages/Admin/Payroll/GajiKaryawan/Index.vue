<template>
  <div class="space-y-6">
    <!-- Header Section -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-(--text-main)">Gaji Karyawan</h1>
        <p class="text-sm text-(--text-muted) mt-1">Rekap penggajian karyawan per periode</p>
      </div>
      
      <!-- Actions Toolbar -->
      <div class="flex flex-wrap items-center gap-3">
        <!-- Period Select -->
        <select
          v-model="selectedPeriodId"
          class="h-10 px-3 rounded-md border border-(--border-soft) bg-(--bg-card) text-(--text-main) text-sm focus:ring-2 focus:ring-(--primary) focus:border-transparent outline-none transition-all cursor-pointer"
          @change="onPeriodChange"
        >
          <option value="">Pilih Periode</option>
          <option v-for="p in periods" :key="p.id" :value="p.id">
            {{ p.name }}
          </option>
        </select>

        <!-- Generate Awal Button -->
        <BaseButton
          variant="secondary"
          :disabled="!selectedPeriodId || generating || isManajemen"
          :loading="generating"
          @click="handleGenerate"
        >
          <template #icon-left>
            <IconRefresh class="w-4 h-4" />
          </template>
          Generate Awal
        </BaseButton>

        <!-- Kalkulasi & Kunci Button -->
        <BaseButton
          v-if="hasUnlockedRecaps"
          variant="primary"
          :disabled="isManajemen"
          @click="isApproveModalOpen = true"
        >
          <template #icon-left>
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
              <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
            </svg>
          </template>
          Kalkulasi & Kunci
        </BaseButton>

        <!-- Export Button -->
        <BaseButton
          variant="success"
          :disabled="!selectedPeriodId || records.length === 0"
          @click="handleExport"
        >
          <template #icon-left>
            <IconDownload class="w-4 h-4" />
          </template>
          Export
        </BaseButton>

        <!-- Setting Button -->
        <button
          @click="showSettings = true"
          class="h-10 px-3 text-sm rounded-lg border border-(--border-soft) hover:bg-(--bg-hover) flex items-center gap-1.5 transition-colors"
          title="Pengaturan Tampilan"
        >
          <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="3"></circle>
            <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
          </svg>
          <span class="hidden sm:inline">Setting</span>
        </button>
      </div>
    </div>

    <!-- Period Info Banner & Segment Switcher -->
    <div v-if="selectedPeriod" class="px-4 py-3 rounded-md bg-(--primary)/5 border border-(--primary)/20 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 shadow-sm">
      <div class="flex flex-wrap items-center gap-4 text-sm">
        <span class="font-bold text-(--primary)">{{ selectedPeriod.name }}</span>
        <span class="text-(--text-muted) font-medium">{{ selectedPeriod.date_range }}</span>
        <Badge :variant="selectedPeriod.is_split ? 'warning' : 'success'">
          {{ selectedPeriod.is_split ? 'Split Periode' : 'Periode Normal' }}
        </Badge>
        <span class="text-(--text-muted) font-medium">{{ records.length }} Karyawan</span>
      </div>

      <!-- Segment selector (only if split) -->
      <div v-if="selectedPeriod.is_split" class="flex gap-2">
        <button
          v-for="seg in ['A', 'B']"
          :key="seg"
          @click="switchSegment(seg)"
          class="h-8 px-4 rounded-md text-xs font-semibold transition-all cursor-pointer"
          :class="activeSegment === seg
            ? 'bg-(--primary) text-white shadow-sm'
            : 'bg-(--bg-card) text-(--text-muted) hover:text-(--text-main) border border-(--border-soft)'"
        >
          Seg-{{ seg === 'A' ? '1' : '2' }}
        </button>
      </div>
    </div>

    <!-- Summary Cards -->
    <div v-if="records.length > 0" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-4">
      <!-- Take Home Pay Card -->
      <div class="bg-(--bg-card) border border-(--border-soft) rounded-md p-4 flex flex-col justify-between shadow-sm">
        <div class="flex items-center justify-between mb-2">
          <span class="text-xs font-semibold text-(--text-muted) uppercase tracking-wider">Take Home Pay</span>
          <div class="w-7 h-7 rounded-md bg-(--primary)/10 text-(--primary) flex items-center justify-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <line x1="12" y1="1" x2="12" y2="23"/>
              <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
            </svg>
          </div>
        </div>
        <div>
          <p class="text-lg font-bold text-(--primary) tracking-tight">
            Rp {{ formatCurrency(totals.all_gaji_bersih, true) }}
          </p>
          <p class="text-[10px] text-(--text-soft) mt-0.5">Total Gaji Bersih</p>
        </div>
      </div>

      <!-- Gaji Pokok Card -->
      <div class="bg-(--bg-card) border border-(--border-soft) rounded-md p-4 flex flex-col justify-between shadow-sm">
        <div class="flex items-center justify-between mb-2">
          <span class="text-xs font-semibold text-(--text-muted) uppercase tracking-wider">Gaji Pokok</span>
          <div class="w-7 h-7 rounded-md bg-(--info)/10 text-(--info) flex items-center justify-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <rect x="2" y="2" width="20" height="8" rx="2" ry="2"/>
              <rect x="2" y="14" width="20" height="8" rx="2" ry="2"/>
              <line x1="6" y1="6" x2="6.01" y2="6"/>
              <line x1="6" y1="18" x2="6.01" y2="18"/>
            </svg>
          </div>
        </div>
        <div>
          <p class="text-lg font-bold text-(--text-main) tracking-tight">
            Rp {{ formatCurrency(totals.all_gaji_pokok, true) }}
          </p>
          <p class="text-[10px] text-(--text-soft) mt-0.5">Akumulasi Gapok</p>
        </div>
      </div>

      <!-- Lembur Card -->
      <div class="bg-(--bg-card) border border-(--border-soft) rounded-md p-4 flex flex-col justify-between shadow-sm">
        <div class="flex items-center justify-between mb-2">
          <span class="text-xs font-semibold text-(--text-muted) uppercase tracking-wider">Lembur</span>
          <div class="w-7 h-7 rounded-md bg-(--warning)/10 text-(--warning) flex items-center justify-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="12" cy="12" r="10"/>
              <polyline points="12 6 12 12 16 14"/>
            </svg>
          </div>
        </div>
        <div>
          <p class="text-lg font-bold text-(--text-main) tracking-tight">
            Rp {{ formatCurrency(totals.all_upah_lembur, true) }}
          </p>
          <p class="text-[10px] text-(--text-soft) mt-0.5">Total Upah Lembur</p>
        </div>
      </div>

      <!-- BPJS Card -->
      <div class="bg-(--bg-card) border border-(--border-soft) rounded-md p-4 flex flex-col justify-between shadow-sm">
        <div class="flex items-center justify-between mb-2">
          <span class="text-xs font-semibold text-(--text-muted) uppercase tracking-wider">BPJS</span>
          <div class="w-7 h-7 rounded-md bg-(--success)/10 text-(--success) flex items-center justify-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
            </svg>
          </div>
        </div>
        <div>
          <p class="text-lg font-bold text-(--text-main) tracking-tight">
            Rp {{ formatCurrency(totals.all_bpjs, true) }}
          </p>
          <p class="text-[10px] text-(--text-soft) mt-0.5">TK + KES + PEN</p>
        </div>
      </div>

      <!-- Potongan Card -->
      <div class="bg-(--bg-card) border border-(--border-soft) rounded-md p-4 flex flex-col justify-between shadow-sm">
        <div class="flex items-center justify-between mb-2">
          <span class="text-xs font-semibold text-(--text-muted) uppercase tracking-wider">Potongan</span>
          <div class="w-7 h-7 rounded-md bg-(--danger)/10 text-(--danger) flex items-center justify-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <line x1="18" y1="6" x2="6" y2="18"/>
              <line x1="6" y1="6" x2="18" y2="18"/>
            </svg>
          </div>
        </div>
        <div>
          <p class="text-lg font-bold text-(--danger) tracking-tight">
            Rp {{ formatCurrency(totals.all_potongan, true) }}
          </p>
          <p class="text-[10px] text-(--text-soft) mt-0.5">BPJS + Bon + PPh</p>
        </div>
      </div>

      <!-- Staff Card -->
      <div class="bg-(--bg-card) border border-(--border-soft) rounded-md p-4 flex flex-col justify-between shadow-sm">
        <div class="flex items-center justify-between mb-2">
          <span class="text-xs font-semibold text-(--text-muted) uppercase tracking-wider">Total Staff</span>
          <div class="w-7 h-7 rounded-md bg-(--text-soft)/10 text-(--text-muted) flex items-center justify-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
              <circle cx="9" cy="7" r="4"/>
              <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
              <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
            </svg>
          </div>
        </div>
        <div>
          <p class="text-lg font-bold text-(--text-main) tracking-tight">
            {{ totals.all_employees }} Orang
          </p>
          <p class="text-[10px] text-(--text-soft) mt-0.5">Tercatat di Payroll</p>
        </div>
      </div>
    </div>

    <!-- Section Tables -->
    <template v-if="selectedPeriod && sections.length > 0">
      <!-- Search & Filter Controls (above sections) -->
      <BaseCard padding="p-0" class="overflow-hidden border border-(--border-soft) bg-(--bg-card) rounded-md shadow-sm">
        <div class="px-4 py-3 border-b border-(--border-soft) flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3 bg-(--bg-main)/30">
          <div>
            <h3 class="text-sm font-semibold text-(--text-main)">Rincian Gaji Karyawan</h3>
            <p class="text-xs text-(--text-muted) mt-0.5">
              Menampilkan {{ filteredCount }} dari {{ records.length }} data gaji
            </p>
          </div>
          
          <div class="relative w-full sm:w-72">
            <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-(--text-soft)">
              <IconSearch class="h-4 w-4" />
            </span>
            <input
              v-model="searchQuery"
              type="text"
              placeholder="Cari nama, NIP, departemen..."
              class="w-full h-10 pl-9 pr-3 rounded-md border border-(--border-soft) bg-(--bg-card) text-(--text-main) text-sm focus:ring-2 focus:ring-(--primary) focus:border-transparent outline-none transition-all"
            />
          </div>
        </div>

        <!-- Render per Section -->
        <template v-for="section in sections" :key="section.key">
          <!-- Section Label -->
          <div class="px-4 py-2.5 bg-(--primary)/5 border-b border-(--border-soft) font-bold text-sm text-(--text-main) uppercase flex items-center justify-between">
            <span>{{ section.label }}</span>
            <span class="text-xs font-normal text-(--text-muted)">{{ section.data.length }} Karyawan</span>
          </div>

          <!-- Table -->
          <div class="overflow-x-auto max-h-[calc(100vh-380px)] overflow-y-auto rounded-b-md">
            <table class="w-full text-xs">
              <thead class="sticky top-0 z-30">
                <tr class="bg-(--bg-elevated) text-(--text-main)">
                  <th rowspan="2" class="sticky-col z-20 border border-(--border-soft) px-2.5 py-3 text-center font-semibold" style="left:0; width:36px; min-width:36px;">No</th>
                  <th rowspan="2" class="sticky-col z-20 border border-(--border-soft) px-2.5 py-3 text-center font-semibold" style="left:36px; width:72px; min-width:72px;">ID No</th>
                  <th rowspan="2" class="sticky-col-last z-20 border border-(--border-soft) px-2.5 py-3 text-left font-semibold" style="left:108px; min-width:160px;">NAMA KARYAWAN</th>
                  <th rowspan="2" class="border border-(--border-soft) px-2.5 py-3 text-center font-semibold whitespace-nowrap">L/P</th>
                  <th rowspan="2" class="border border-(--border-soft) px-2.5 py-3 text-left font-semibold whitespace-nowrap min-w-[120px]">BAGIAN</th>
                  <th rowspan="2" class="border border-(--border-soft) px-2.5 py-3 text-left font-semibold whitespace-nowrap min-w-[120px]">JABATAN</th>
                  <th rowspan="2" class="border border-(--border-soft) px-2.5 py-3 text-center font-semibold whitespace-nowrap">THN MSK</th>
                  <th rowspan="2" class="border border-(--border-soft) px-2.5 py-3 text-right font-semibold whitespace-nowrap min-w-[90px]">PREMI</th>
                  <th rowspan="2" class="border border-(--border-soft) px-2.5 py-3 text-right font-semibold whitespace-nowrap min-w-[100px]">GAJI POKOK</th>
                  <th rowspan="2" class="border border-(--border-soft) px-2.5 py-3 text-right font-semibold whitespace-nowrap min-w-[90px]">TJ. MK</th>
                  <th :colspan="5" class="border border-(--border-soft) px-2.5 py-1.5 text-center font-bold text-(--primary) bg-(--primary)/5 whitespace-nowrap">
                    {{ periodLabel }}
                  </th>
                  <th rowspan="2" class="border border-(--border-soft) px-2.5 py-3 text-right font-semibold whitespace-nowrap min-w-[90px]">REVISI</th>
                  <th rowspan="2" class="border border-(--border-soft) px-2.5 py-3 text-right font-semibold whitespace-nowrap min-w-[95px]">TUNJANGAN</th>
                  <th rowspan="2" class="border border-(--border-soft) px-2.5 py-3 text-right font-semibold whitespace-nowrap min-w-[90px]">PR. HADIR</th>
                  <th rowspan="2" class="border border-(--border-soft) px-2.5 py-3 text-right font-semibold whitespace-nowrap min-w-[80px]">PBLT</th>
                  <th rowspan="2" class="border border-(--border-soft) px-2.5 py-3 text-right font-bold whitespace-nowrap min-w-[110px]">TOTAL</th>
                  <th rowspan="2" class="border border-(--border-soft) px-2.5 py-3 text-right font-semibold text-(--danger) whitespace-nowrap min-w-[90px]">BPJS TK</th>
                  <th rowspan="2" class="border border-(--border-soft) px-2.5 py-3 text-right font-semibold text-(--danger) whitespace-nowrap min-w-[90px]">BPJS KES</th>
                  <th rowspan="2" class="border border-(--border-soft) px-2.5 py-3 text-right font-semibold text-(--danger) whitespace-nowrap min-w-[90px]">BPJS PEN</th>
                  <th rowspan="2" class="border border-(--border-soft) px-2.5 py-3 text-right font-semibold text-(--danger) whitespace-nowrap min-w-[90px]">CASH BON</th>
                  <th rowspan="2" class="border border-(--border-soft) px-2.5 py-3 text-right font-semibold text-(--danger) whitespace-nowrap min-w-[90px]">PPH</th>
                  <th rowspan="2" class="border border-(--border-soft) px-2.5 py-3 text-right font-extrabold text-(--primary) bg-(--primary)/5 whitespace-nowrap min-w-[120px]">TRIMA</th>
                  <th rowspan="2" class="border border-(--border-soft) px-2.5 py-3 text-center font-semibold whitespace-nowrap w-[60px]">AKSI</th>
                </tr>
                <tr class="bg-(--bg-elevated) text-(--text-main)">
                  <th class="border border-(--border-soft) px-2 py-1.5 text-center font-semibold whitespace-nowrap">HK</th>
                  <th class="border border-(--border-soft) px-2 py-1.5 text-center font-semibold whitespace-nowrap">LM</th>
                  <th class="border border-(--border-soft) px-2 py-1.5 text-center font-semibold whitespace-nowrap">LBR JAM</th>
                  <th class="border border-(--border-soft) px-2 py-1.5 text-right font-semibold whitespace-nowrap min-w-[95px]">GAJI</th>
                  <th class="border border-(--border-soft) px-2 py-1.5 text-right font-semibold whitespace-nowrap min-w-[90px]">LEMBUR</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="section.data.length === 0">
                  <td :colspan="27" class="px-4 py-8 text-center text-(--text-muted) text-sm bg-(--bg-card)">
                    Tidak ada data di section ini
                  </td>
                </tr>
                <tr
                  v-for="(record, idx) in section.filtered"
                  :key="record.id"
                  class="hover-row transition-colors"
                  :class="idx % 2 === 0 ? 'bg-(--bg-card)' : 'bg-(--bg-main)/40'"
                >
                  <td class="sticky-col z-10 border border-(--border-soft) px-2 py-2 text-center text-(--text-muted)">{{ section.startNo + idx }}</td>
                  <td class="sticky-col z-10 border border-(--border-soft) px-2 py-2 text-center font-mono text-xs text-(--text-muted)">{{ record.employee_code }}</td>
                  <td class="sticky-col-last z-10 border border-(--border-soft) px-2.5 py-2 font-semibold text-(--text-main)">{{ record.name }}</td>
                  <td class="border border-(--border-soft) px-2 py-2 text-center text-(--text-muted)">{{ record.gender }}</td>
                  <td class="border border-(--border-soft) px-2.5 py-2 text-(--text-main)">{{ record.department }}</td>
                  <td class="border border-(--border-soft) px-2.5 py-2 text-(--text-main)">{{ record.position }}</td>
                  <td class="border border-(--border-soft) px-2 py-2 text-center text-(--text-muted)">{{ record.join_year }}</td>
                  <td class="border border-(--border-soft) px-2 py-2 text-right font-mono text-(--text-main)">{{ formatCurrency(record.premi) }}</td>
                  <td class="border border-(--border-soft) px-2 py-2 text-right font-mono text-(--text-main)">{{ formatCurrency(record.gaji_pokok) }}</td>
                  <td class="border border-(--border-soft) px-2 py-2 text-right font-mono text-(--text-main)">{{ formatCurrency(record.tj_masa_kerja) }}</td>
                  <td class="border border-(--border-soft) px-2 py-2 text-center text-(--text-main) font-medium">{{ record.hari_kerja }}</td>
                  <td class="border border-(--border-soft) px-2 py-2 text-center text-(--text-muted)">{{ record.lm > 0 ? (record.lm / 60) + 'j' : '-' }}</td>
                  <td class="border border-(--border-soft) px-2 py-2 text-center text-(--text-muted)">{{ record.lembur_count > 0 ? (record.lembur_count / 60) + 'j' : '-' }}</td>
                  <td class="border border-(--border-soft) px-2 py-2 text-right font-mono text-(--text-main)">{{ formatCurrency(record.gaji) }}</td>
                  <td class="border border-(--border-soft) px-2 py-2 text-right font-mono text-(--text-main)">{{ formatCurrency(record.upah_lembur) }}</td>
                  <td class="border border-(--border-soft) px-2 py-2 text-right font-mono text-(--text-main)">{{ formatCurrency(record.revisi) }}</td>
                  <td class="border border-(--border-soft) px-2 py-2 text-right font-mono text-(--text-main)">{{ formatCurrency(record.tunjangan) }}</td>
                  <td class="border border-(--border-soft) px-2 py-2 text-right font-mono text-(--text-main)">{{ formatCurrency(record.premi_hadir) }}</td>
                  <td class="border border-(--border-soft) px-2 py-2 text-right font-mono text-(--text-main)">{{ formatCurrency(record.pblt) }}</td>
                  <td class="border border-(--border-soft) px-2.5 py-2 text-right font-mono font-bold text-(--text-main)">{{ formatCurrency(record.total) }}</td>
                  <td class="border border-(--border-soft) px-2 py-2 text-right font-mono text-(--danger)/80">{{ formatCurrency(record.bpjs_tk) }}</td>
                  <td class="border border-(--border-soft) px-2 py-2 text-right font-mono text-(--danger)/80">{{ formatCurrency(record.bpjs_ks) }}</td>
                  <td class="border border-(--border-soft) px-2 py-2 text-right font-mono text-(--danger)/80">{{ formatCurrency(record.bpjs_pen) }}</td>
                  <td class="border border-(--border-soft) px-2 py-2 text-right font-mono text-(--danger)/80">{{ formatCurrency(record.cashbon) }}</td>
                  <td class="border border-(--border-soft) px-2 py-2 text-right font-mono text-(--danger)/80">{{ formatCurrency(record.pph) }}</td>
                  <td class="border border-(--border-soft) px-2.5 py-2 text-right font-mono font-extrabold text-(--primary) bg-(--primary)/5">{{ formatCurrency(record.gaji_bersih) }}</td>
                  <td class="border border-(--border-soft) px-2 py-2 text-center">
                    <button
                      @click="openEditModal(record)"
                      class="text-(--primary) hover:text-(--primary-hover) transition-colors p-1 rounded hover:bg-(--primary)/10"
                      title="Edit Upah Lembur"
                    >
                      <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" />
                      </svg>
                    </button>
                  </td>
                </tr>
              </tbody>
              <!-- Section Total -->
              <tfoot v-if="section.data.length > 0">
                <tr class="bg-(--bg-elevated) font-bold text-(--text-main) border-t-2 border-(--border-soft)">
                  <td colspan="7" class="sticky-col-last z-10 border border-(--border-soft) px-2.5 py-3.5 text-right font-bold" style="left:0; min-width:160px;">TOTAL {{ section.label }}</td>
                  <td class="border border-(--border-soft) px-2 py-3.5 text-right font-mono">{{ formatCurrency(section.totals.premi, true) }}</td>
                  <td class="border border-(--border-soft) px-2 py-3.5 text-right font-mono">{{ formatCurrency(section.totals.gaji_pokok, true) }}</td>
                  <td class="border border-(--border-soft) px-2 py-3.5 text-right font-mono">{{ formatCurrency(section.totals.tj_masa_kerja, true) }}</td>
                  <td class="border border-(--border-soft) px-2 py-3.5 text-center font-bold">{{ section.totals.hari_kerja }}</td>
                  <td class="border border-(--border-soft) px-2 py-3.5 text-center text-(--text-muted)">{{ section.totals.lm > 0 ? (section.totals.lm / 60) + 'j' : '-' }}</td>
                  <td class="border border-(--border-soft) px-2 py-3.5 text-center text-(--text-muted)">{{ section.totals.lembur_count > 0 ? (section.totals.lembur_count / 60) + 'j' : '-' }}</td>
                  <td class="border border-(--border-soft) px-2 py-3.5 text-right font-mono">{{ formatCurrency(section.totals.gaji, true) }}</td>
                  <td class="border border-(--border-soft) px-2 py-3.5 text-right font-mono">{{ formatCurrency(section.totals.upah_lembur, true) }}</td>
                  <td class="border border-(--border-soft) px-2 py-3.5 text-right font-mono">{{ formatCurrency(section.totals.revisi, true) }}</td>
                  <td class="border border-(--border-soft) px-2 py-3.5 text-right font-mono">{{ formatCurrency(section.totals.tunjangan, true) }}</td>
                  <td class="border border-(--border-soft) px-2 py-3.5 text-right font-mono">{{ formatCurrency(section.totals.premi_hadir, true) }}</td>
                  <td class="border border-(--border-soft) px-2 py-3.5 text-right font-mono">{{ formatCurrency(section.totals.pblt, true) }}</td>
                  <td class="border border-(--border-soft) px-2.5 py-3.5 text-right font-mono font-bold">{{ formatCurrency(section.totals.total, true) }}</td>
                  <td class="border border-(--border-soft) px-2 py-3.5 text-right font-mono text-(--danger)">{{ formatCurrency(section.totals.bpjs_tk, true) }}</td>
                  <td class="border border-(--border-soft) px-2 py-3.5 text-right font-mono text-(--danger)">{{ formatCurrency(section.totals.bpjs_ks, true) }}</td>
                  <td class="border border-(--border-soft) px-2 py-3.5 text-right font-mono text-(--danger)">{{ formatCurrency(section.totals.bpjs_pen, true) }}</td>
                  <td class="border border-(--border-soft) px-2 py-3.5 text-right font-mono text-(--danger)">{{ formatCurrency(section.totals.cashbon, true) }}</td>
                  <td class="border border-(--border-soft) px-2 py-3.5 text-right font-mono text-(--danger)">{{ formatCurrency(section.totals.pph, true) }}</td>
                  <td class="border border-(--border-soft) px-2.5 py-3.5 text-right font-mono text-(--primary) bg-(--primary)/5">{{ formatCurrency(section.totals.gaji_bersih, true) }}</td>
                  <td class="border border-(--border-soft) px-2.5 py-3.5"></td>
                </tr>
              </tfoot>
            </table>
          </div>
        </template>

        <!-- Grand Total -->
        <div v-if="sections.some(s => s.data.length > 0)" class="px-4 py-3 bg-(--primary)/5 border-t-2 border-(--primary) flex justify-end gap-6 text-sm font-bold">
          <span class="uppercase text-(--primary)">TOTAL KESELURUHAN</span>
          <span class="text-(--text-main)">{{ filteredCount }} Karyawan</span>
          <span class="text-(--text-main)">Rp {{ formatCurrency(grandTotals.gaji_bersih, true) }}</span>
        </div>
      </BaseCard>
    </template>

    <!-- Empty State for no table data -->
    <BaseCard v-else-if="selectedPeriod" padding="p-0" class="overflow-hidden border border-(--border-soft) bg-(--bg-card) rounded-md shadow-sm">
      <div class="px-4 py-3 border-b border-(--border-soft) bg-(--bg-main)/30">
        <h3 class="text-sm font-semibold text-(--text-main)">Rincian Gaji Karyawan</h3>
      </div>
      <div class="px-4 py-12 text-center text-(--text-muted) text-sm bg-(--bg-card)">
        <div v-if="loadingRecap" class="flex items-center justify-center gap-2">
          <svg class="animate-spin h-5 w-5 text-(--primary)" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
          </svg>
          Memproses data...
        </div>
        <div v-else-if="hasUnlockedRecaps">
          Data kehadiran terdeteksi. Silakan klik tombol <strong>"Kalkulasi & Kunci Gaji"</strong> untuk memproses payroll.
        </div>
        <div v-else>
          Belum ada data gaji untuk periode ini. Klik <strong>"Generate"</strong> untuk menghitung data awal kehadiran.
        </div>
      </div>
    </BaseCard>

    <!-- Empty State: No Period Selected -->
    <BaseCard v-else class="py-16 border border-(--border-soft) bg-(--bg-card) rounded-md shadow-sm">
      <div class="text-center space-y-4 max-w-sm mx-auto">
        <div class="w-16 h-16 mx-auto rounded-full bg-(--bg-elevated) flex items-center justify-center text-(--text-muted)">
          <IconFileInvoice class="w-8 h-8" />
        </div>
        <div>
          <h3 class="text-lg font-bold text-(--text-main)">Pilih Periode</h3>
          <p class="text-sm text-(--text-muted) mt-1">Pilih periode dari dropdown di atas untuk melihat rekap gaji karyawan</p>
        </div>
      </div>
    </BaseCard>

    <!-- Modal: Kalkulasi & Kunci Gaji -->
    <BaseModal :show="isApproveModalOpen" @close="isApproveModalOpen = false" title="Kalkulasi & Kunci Gaji">
      <div class="space-y-4">
        <p class="text-sm text-(--text-main)">
          Proses ini akan mengkalkulasi ulang gaji berdasarkan data kehadiran terakhir dan <strong>mengunci</strong> data tersebut.
        </p>

        <p v-if="unlockedCount > 0" class="text-sm text-(--text-muted)">
          {{ unlockedCount }} data kehadiran siap diproses.
        </p>

        <div class="flex justify-end gap-3 mt-6">
          <BaseButton variant="ghost" @click="isApproveModalOpen = false">Batal</BaseButton>
          <BaseButton variant="primary" :loading="approvingPayroll" @click="handleApprovePayroll">Proses & Kunci Gaji</BaseButton>
        </div>
      </div>
    </BaseModal>

    <!-- Modal: Setting -->
    <BaseModal :show="showSettings" @close="showSettings = false" title="Pengaturan Gaji Karyawan">
      <div class="space-y-4">
        <GajiKaryawanSettings
          :config="payrollConfig"
          @update:config="onConfigUpdate"
        />

        <hr class="border-(--border-soft)" />

        <div class="flex items-center justify-between">
          <div v-if="configUpdatedBy" class="text-xs text-(--text-muted)">
            Terakhir diubah: {{ configUpdatedAt }} oleh {{ configUpdatedBy }}
          </div>
          <div v-else class="text-xs text-(--text-muted)">
            Menggunakan pengaturan default
          </div>
          <div class="flex gap-2">
            <BaseButton variant="ghost" @click="showSettings = false">Batal</BaseButton>
            <BaseButton variant="primary" :loading="savingConfig" @click="saveConfig">Simpan</BaseButton>
          </div>
        </div>
      </div>
    </BaseModal>

    <!-- Modal: Edit Upah Lembur -->
    <BaseModal :show="isEditModalOpen" @close="closeEditModal" title="Edit Data Lembur">
      <div v-if="editingRecord" class="space-y-4">
        <div class="bg-(--bg-soft) rounded-lg p-3 space-y-1 text-sm">
          <p class="font-semibold text-(--text-main)">{{ editingRecord.name }}</p>
          <p class="text-(--text-muted) text-xs">{{ editingRecord.employee_code }} · {{ editingRecord.position }} · {{ editingRecord.department }}</p>
        </div>

        <div class="grid grid-cols-3 gap-3">
          <div>
            <label class="block text-xs font-medium text-(--text-muted) mb-1">LM (menit)</label>
            <input
              v-model.number="editLm"
              type="number"
              min="0"
              class="w-full px-2 py-2 rounded-lg border border-(--border-soft) bg-(--bg-elevated) text-(--text-main) text-sm focus:outline-none focus:ring-1 focus:ring-(--primary) focus:border-(--primary)"
              placeholder="0"
            />
          </div>
          <div>
            <label class="block text-xs font-medium text-(--text-muted) mb-1">LM Count (menit)</label>
            <input
              v-model.number="editLmCount"
              type="number"
              min="0"
              class="w-full px-2 py-2 rounded-lg border border-(--border-soft) bg-(--bg-elevated) text-(--text-main) text-sm focus:outline-none focus:ring-1 focus:ring-(--primary) focus:border-(--primary)"
              placeholder="0"
            />
          </div>
          <div>
            <label class="block text-xs font-medium text-(--text-muted) mb-1">LBR Count (menit)</label>
            <input
              v-model.number="editLemburCount"
              type="number"
              min="0"
              class="w-full px-2 py-2 rounded-lg border border-(--border-soft) bg-(--bg-elevated) text-(--text-main) text-sm focus:outline-none focus:ring-1 focus:ring-(--primary) focus:border-(--primary)"
              placeholder="0"
            />
          </div>
        </div>

        <p class="text-xs text-(--text-muted) italic">
          💡 Upah lembur akan dikalkulasi ulang: <code>(Gapok + TJ MK + Tunjangan) / 173 × ((LM Count + LBR Count) / 60)</code> dibulatkan 100.
        </p>

        <hr class="border-(--border-soft)" />

        <div class="flex justify-end gap-3">
          <BaseButton variant="ghost" @click="closeEditModal">Batal</BaseButton>
          <BaseButton variant="primary" :loading="savingUpahLembur" @click="saveUpahLembur">Simpan</BaseButton>
        </div>
      </div>
    </BaseModal>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import BaseButton from '@/Components/BaseButton.vue'
import BaseCard from '@/Components/BaseCard.vue'
import BaseModal from '@/Components/BaseModal.vue'
import Badge from '@/Components/Badge.vue'
import { IconDownload, IconFileInvoice, IconRefresh, IconSearch } from '@/Components/Icons/index.js'
import GajiKaryawanSettings from '@/Components/ReportPage/settings/GajiKaryawanSettings.vue'
import { useApi } from '@/composables/useApi'
import { useAuth } from '@/composables/useAuth'
import { useNotificationStore } from '@/Stores/notification'

const { isManajemen } = useAuth()
const { get, post, put } = useApi()
const notification = useNotificationStore()

const periods = ref([])
const selectedPeriodId = ref('')
const records = ref([])
const generating = ref(false)
const activeSegment = ref(null)
const searchQuery = ref('')

// Recap state
const recapRecords = ref([])
const loadingRecap = ref(false)
const approvingPayroll = ref(false)
const isApproveModalOpen = ref(false)

// Settings state
const showSettings = ref(false)
const payrollConfig = ref({ sections: { A: ['GRP-ALLIN', 'GRP-SPR'], B: ['GRP-GD', 'GRP-SS', 'GRP-PS1'] } })
const pendingConfig = ref(null)
const savingConfig = ref(false)
const configUpdatedBy = ref('')
const configUpdatedAt = ref('')

// Edit Upah Lembur state
const isEditModalOpen = ref(false)
const editingRecord = ref(null)
const editLm = ref(0)
const editLmCount = ref(0)
const editLemburCount = ref(0)
const savingUpahLembur = ref(false)

const selectedPeriod = computed(() => {
  return periods.value.find(p => p.id === selectedPeriodId.value)
})

const periodLabel = computed(() => {
  if (!selectedPeriod.value) return ''
  const start = new Date(selectedPeriod.value.start_date)
  const end = new Date(selectedPeriod.value.end_date)
  const months = ['JANUARI', 'FEBRUARI', 'MARET', 'APRIL', 'MEI', 'JUNI', 'JULI', 'AGUSTUS', 'SEPTEMBER', 'OKTOBER', 'NOVEMBER', 'DESEMBER']
  return `${start.getDate()} ${months[start.getMonth()]} - ${end.getDate()} ${months[end.getMonth()]} ${end.getFullYear().toString().slice(-2)}`
})

const unlockedCount = computed(() => {
  return recapRecords.value.filter(r => r.status !== 'locked').length
})

const hasUnlockedRecaps = computed(() => {
  return selectedPeriod.value && unlockedCount.value > 0
})

const filteredRecords = computed(() => {
  if (!searchQuery.value) return records.value
  const q = searchQuery.value.toLowerCase()
  return records.value.filter(r => 
    r.name.toLowerCase().includes(q) || 
    r.employee_code.toLowerCase().includes(q) || 
    r.department.toLowerCase().includes(q) || 
    r.position.toLowerCase().includes(q)
  )
})

const filteredCount = computed(() => filteredRecords.value.length)

// ─── Sections ───

const sections = computed(() => {
  const mapping = payrollConfig.value?.sections || {}
  const sectionsA = mapping.A || ['GRP-ALLIN', 'GRP-SPR']
  const sectionsB = mapping.B || ['GRP-GD', 'GRP-SS', 'GRP-PS1']

  const secA = { key: 'A', label: 'A. KARYAWAN ALL IN', data: [], filtered: [], totals: null, startNo: 1 }
  const secB = { key: 'B', label: 'B. KARYAWAN BULANAN PRINT', data: [], filtered: [], totals: null, startNo: 1 }

  for (const record of filteredRecords.value) {
    const groups = record.groups || []
    if (groups.some(g => sectionsA.includes(g))) {
      secA.data.push(record)
    } else if (groups.some(g => sectionsB.includes(g))) {
      secB.data.push(record)
    }
    // Skip if not in any section
  }

  // Apply search to each section
  secA.filtered = secA.data
  secB.filtered = secB.data

  // Compute section totals
  secA.totals = computeSectionTotals(secA.data)
  secB.totals = computeSectionTotals(secB.data)

  // Start numbering
  secB.startNo = secA.data.length + 1

  return [secA, secB]
})

function computeSectionTotals(data) {
  const sum = (key) => data.reduce((acc, r) => acc + (parseFloat(r[key]) || 0), 0)
  return {
    hari_kerja: data.reduce((acc, r) => acc + (parseInt(r.hari_kerja) || 0), 0),
    lm: data.reduce((acc, r) => acc + (parseInt(r.lm) || 0), 0),
    lembur_count: data.reduce((acc, r) => acc + (parseInt(r.lembur_count) || 0), 0),
    gaji: sum('gaji'),
    upah_lembur: sum('upah_lembur'),
    revisi: sum('revisi'),
    tunjangan: sum('tunjangan'),
    premi_hadir: sum('premi_hadir'),
    pblt: sum('pblt'),
    total: sum('total'),
    bpjs_tk: sum('bpjs_tk'),
    bpjs_ks: sum('bpjs_ks'),
    bpjs_pen: sum('bpjs_pen'),
    cashbon: sum('cashbon'),
    pph: sum('pph'),
    gaji_bersih: sum('gaji_bersih'),
  }
}

const grandTotals = computed(() => {
  return computeSectionTotals(filteredRecords.value)
})

// ─── Overall Totals (for summary cards) ───

const totals = computed(() => {
  const sum = (key) => records.value.reduce((acc, r) => acc + (parseFloat(r[key]) || 0), 0)
  return {
    all_gaji_bersih: sum('gaji_bersih'),
    all_gaji_pokok: sum('gaji_pokok'),
    all_upah_lembur: sum('upah_lembur'),
    all_bpjs: sum('bpjs_tk') + sum('bpjs_ks') + sum('bpjs_pen'),
    all_potongan: sum('bpjs_tk') + sum('bpjs_ks') + sum('bpjs_pen') + sum('cashbon') + sum('pph'),
    all_employees: records.value.length,
  }
})

// ─── Helpers ───

function formatCurrency(value, showZero = false) {
  if (!value && value !== 0) return '-'
  if (parseFloat(value) === 0 && !showZero) return '-'
  return new Intl.NumberFormat('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(value)
}

// ─── API: Periods ───

async function fetchPeriods() {
  try {
    const res = await get('/api/v1/payroll/periods')
    periods.value = res.data || []
  } catch (error) {
    console.error('Error fetching periods', error)
  }
}

// ─── API: Payroll Config ───

async function fetchPayrollConfig() {
  try {
    const res = await get('/api/v1/payroll/configs/gaji_karyawan')
    payrollConfig.value = res.config || { sections: { A: ['GRP-ALLIN', 'GRP-SPR'], B: ['GRP-GD', 'GRP-SS', 'GRP-PS1'] } }
    configUpdatedBy.value = res.updated_by || ''
    configUpdatedAt.value = res.updated_at || ''
  } catch (error) {
    console.error('Error fetching payroll config', error)
  }
}

function onConfigUpdate(newConfig) {
  pendingConfig.value = newConfig
}

async function saveConfig() {
  savingConfig.value = true
  try {
    const configToSave = pendingConfig.value || payrollConfig.value
    const res = await put('/api/v1/payroll/configs/gaji_karyawan', { config: configToSave })
    payrollConfig.value = res.config || configToSave
    configUpdatedBy.value = res.updated_by || ''
    configUpdatedAt.value = res.updated_at || ''
    pendingConfig.value = null
    notification.success('Pengaturan berhasil disimpan.')
    showSettings.value = false
  } catch (error) {
    console.error('Error saving config', error)
    notification.error('Gagal menyimpan pengaturan.')
  } finally {
    savingConfig.value = false
  }
}

// ─── API: Records ───

async function fetchRecords() {
  if (!selectedPeriodId.value) {
    records.value = []
    return
  }
  try {
    let url = `/api/v1/payroll/gaji-karyawan?period_id=${selectedPeriodId.value}`
    if (activeSegment.value) {
      url += `&segment=${activeSegment.value}`
    }
    const res = await get(url)
    records.value = res.data || []
  } catch (error) {
    console.error('Error fetching records', error)
    records.value = []
  }
}

async function fetchRecapRecords() {
  if (!selectedPeriodId.value) {
    recapRecords.value = []
    return
  }
  loadingRecap.value = true
  try {
    let all = []
    let page = 1
    let lastPage = 1
    do {
      const res = await get(`/api/v1/attendance/recap?period_id=${selectedPeriodId.value}&per_page=100&page=${page}`)
      all = [...all, ...(res.data || [])]
      lastPage = res.last_page || 1
      page++
    } while (page <= lastPage)
    recapRecords.value = all
  } catch (error) {
    console.error('Error fetching recap records', error)
    recapRecords.value = []
  } finally {
    loadingRecap.value = false
  }
}

async function switchSegment(seg) {
  activeSegment.value = seg
  await fetchRecords()
}

async function onPeriodChange() {
  const period = periods.value.find(p => p.id === selectedPeriodId.value)
  activeSegment.value = period?.is_split ? 'A' : null
  searchQuery.value = ''
  await Promise.all([fetchRecords(), fetchRecapRecords()])
}

async function handleGenerate() {
  if (!selectedPeriodId.value) return
  generating.value = true
  try {
    const res = await post(`/api/v1/attendance/recap/generate`, { period_id: selectedPeriodId.value })
    notification.success(res.message || 'Berhasil men-generate resume kehadiran.')
    await Promise.all([fetchRecords(), fetchRecapRecords()])
  } catch (error) {
    console.error('Error generating', error)
    notification.error(error.message || 'Gagal men-generate resume kehadiran.')
  } finally {
    generating.value = false
  }
}

async function handleApprovePayroll() {
  const unlockedIds = recapRecords.value
    .filter(r => r.status !== 'locked')
    .map(r => r.id)

  if (unlockedIds.length === 0) {
    notification.warning('Tidak ada data kehadiran yang perlu diproses.')
    return
  }

  approvingPayroll.value = true
  try {
    const res = await post('/api/v1/attendance/recap/approve', { ids: unlockedIds })
    notification.success(res.message || 'Gaji karyawan berhasil dikalkulasi dan dikunci!')
    isApproveModalOpen.value = false
    await Promise.all([fetchRecords(), fetchRecapRecords()])
  } catch (error) {
    console.error('Error approving payroll', error)
    notification.error(error.message || 'Gagal memproses payroll.')
  } finally {
    approvingPayroll.value = false
  }
}

function handleExport() {
  const token = localStorage.getItem('token')
  const params = new URLSearchParams()
  if (selectedPeriodId.value) params.append('period_id', selectedPeriodId.value)
  if (activeSegment.value) params.append('segment', activeSegment.value)

  const url = `/api/v1/payroll/gaji-karyawan/export?${params.toString()}`
  
  notification.info('Sedang menyiapkan file Excel...')
  
  fetch(url, { headers: { 'Authorization': `Bearer ${token}` } })
    .then(r => {
      if (!r.ok) throw new Error('Gagal export Excel')
      return r.blob()
    })
    .then(blob => {
      const downloadUrl = URL.createObjectURL(blob)
      const link = document.createElement('a')
      link.href = downloadUrl
      link.setAttribute('download', `Laporan_Gaji_Karyawan_${activeSegment.value ? 'Segmen_'+activeSegment.value : 'Periode'}.xlsx`)
      document.body.appendChild(link)
      link.click()
      document.body.removeChild(link)
      URL.revokeObjectURL(downloadUrl)
    })
    .catch(err => {
      console.error(err)
      notification.error('Gagal export Excel')
    })
}

// ─── Edit Upah Lembur ───

function openEditModal(record) {
  editingRecord.value = record
  editLm.value = record.lm || 0
  editLmCount.value = (record.lm_count ?? record.lm) || 0
  editLemburCount.value = record.lembur_count || 0
  isEditModalOpen.value = true
}

function closeEditModal() {
  isEditModalOpen.value = false
  editingRecord.value = null
  editLm.value = 0
  editLmCount.value = 0
  editLemburCount.value = 0
}

async function saveUpahLembur() {
  if (!editingRecord.value) return
  savingUpahLembur.value = true
  try {
    const res = await put(`/api/v1/payroll/gaji-karyawan/${editingRecord.value.id}/upah-lembur`, {
      lm: editLm.value || 0,
      lm_count: editLmCount.value || 0,
      lembur_count: editLemburCount.value || 0,
    })
    
    // Update local record
    const idx = records.value.findIndex(r => r.id === editingRecord.value.id)
    if (idx !== -1) {
      records.value[idx].lm = res.data.lm
      records.value[idx].lm_count = res.data.lm_count
      records.value[idx].lembur_count = res.data.lembur_count
      records.value[idx].upah_lembur = res.data.upah_lembur
      records.value[idx].pblt = res.data.pblt
      records.value[idx].total = res.data.total
      records.value[idx].gaji_bersih = res.data.gaji_bersih
    }
    
    notification.success(res.message || 'Data lembur berhasil diupdate.')
    closeEditModal()
  } catch (error) {
    console.error('Error updating lembur', error)
    notification.error(error.message || 'Gagal update data lembur.')
  } finally {
    savingUpahLembur.value = false
  }
}

onMounted(() => {
  fetchPeriods()
  fetchPayrollConfig()
})
</script>

<style scoped>
.sticky-col {
  position: sticky;
  background-color: var(--bg-card);
}
.sticky-col-last {
  position: sticky;
  border-right: 2px solid var(--border-soft) !important;
  box-shadow: 4px 0 8px -4px rgba(0, 0, 0, 0.08);
  background-color: var(--bg-card);
}

tr:nth-child(even) .sticky-col,
tr:nth-child(even) .sticky-col-last {
  background-color: var(--bg-main) !important;
}
tr:hover .sticky-col,
tr:hover .sticky-col-last {
  background-color: var(--bg-elevated) !important;
}
thead tr .sticky-col,
thead tr .sticky-col-last {
  background-color: var(--bg-elevated) !important;
}

.hover-row:hover {
  background-color: rgba(var(--primary-glow), 0.05) !important;
}

::-webkit-scrollbar {
  height: 6.5px;
  width: 6.5px;
}
::-webkit-scrollbar-track {
  background: transparent;
}
::-webkit-scrollbar-thumb {
  background: var(--text-soft);
  border-radius: 3px;
}
::-webkit-scrollbar-thumb:hover {
  background: var(--text-muted);
}
</style>
