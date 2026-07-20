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
          variant="primary"
          :disabled="!selectedPeriodId || generating"
          :loading="generating"
          @click="handleGenerate"
        >
          <template #icon-left>
            <IconRefresh class="w-4 h-4" />
          </template>
          Kalkulasi Gaji
        </BaseButton>

        <!-- Import Excel / Update Data -->
        <BaseButton
          variant="secondary"
          :disabled="!selectedPeriodId || importing"
          :loading="importing"
          @click="handleUpdateData"
        >
          <template #icon-left>
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
              <polyline points="17 8 12 3 7 8"/>
              <line x1="12" y1="3" x2="12" y2="15"/>
            </svg>
          </template>
          Update Data
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
          Export Excel
        </BaseButton>

        <!-- Print PDF Button -->
        <BaseButton
          variant="info"
          :disabled="!selectedPeriodId || records.length === 0"
          :loading="printing"
          @click="handlePrint"
        >
          <template #icon-left>
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <polyline points="6 9 6 2 18 2 18 9"/><path d="M6 12H4a2 2 0 0 0-2 2v4a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-4a2 2 0 0 0-2-2h-2"/><rect x="6" y="14" width="12" height="8"/>
            </svg>
          </template>
          Print PDF
        </BaseButton>

        <!-- Setting Button (hidden) -->
        <!--
        <button
          @click="showSettings = true"
          class="h-10 px-3 text-sm rounded-lg border border-(--border-soft) hover:bg-(--bg-hover) flex items-center gap-1.5 transition-colors"
          title="Pengaturan Tampilan"
        >
          ...
        </button>
        -->
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
      <div class="bg-(--bg-card) border border-(--border-soft) rounded-md p-4 flex flex-col justify-between shadow-sm">
        <div class="flex items-center justify-between mb-2">
          <span class="text-xs font-semibold text-(--text-muted) uppercase tracking-wider">Take Home Pay</span>
          <div class="w-7 h-7 rounded-md bg-(--primary)/10 text-(--primary) flex items-center justify-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
            </svg>
          </div>
        </div>
        <div>
          <p class="text-lg font-bold text-(--primary) tracking-tight">Rp {{ formatCurrency(totals.all_gaji_bersih, true) }}</p>
          <p class="text-[10px] text-(--text-soft) mt-0.5">Total Gaji Bersih</p>
        </div>
      </div>
      <div class="bg-(--bg-card) border border-(--border-soft) rounded-md p-4 flex flex-col justify-between shadow-sm">
        <div class="flex items-center justify-between mb-2">
          <span class="text-xs font-semibold text-(--text-muted) uppercase tracking-wider">Gaji Pokok</span>
          <div class="w-7 h-7 rounded-md bg-(--info)/10 text-(--info) flex items-center justify-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <rect x="2" y="2" width="20" height="8" rx="2" ry="2"/><rect x="2" y="14" width="20" height="8" rx="2" ry="2"/><line x1="6" y1="6" x2="6.01" y2="6"/><line x1="6" y1="18" x2="6.01" y2="18"/>
            </svg>
          </div>
        </div>
        <div>
          <p class="text-lg font-bold text-(--text-main) tracking-tight">Rp {{ formatCurrency(totals.all_gaji_pokok, true) }}</p>
          <p class="text-[10px] text-(--text-soft) mt-0.5">Akumulasi Gapok</p>
        </div>
      </div>
      <div class="bg-(--bg-card) border border-(--border-soft) rounded-md p-4 flex flex-col justify-between shadow-sm">
        <div class="flex items-center justify-between mb-2">
          <span class="text-xs font-semibold text-(--text-muted) uppercase tracking-wider">Lembur</span>
          <div class="w-7 h-7 rounded-md bg-(--warning)/10 text-(--warning) flex items-center justify-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
            </svg>
          </div>
        </div>
        <div>
          <p class="text-lg font-bold text-(--text-main) tracking-tight">Rp {{ formatCurrency(totals.all_upah_lembur, true) }}</p>
          <p class="text-[10px] text-(--text-soft) mt-0.5">Total Upah Lembur</p>
        </div>
      </div>
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
          <p class="text-lg font-bold text-(--text-main) tracking-tight">Rp {{ formatCurrency(totals.all_bpjs, true) }}</p>
          <p class="text-[10px] text-(--text-soft) mt-0.5">TK + KES + PEN</p>
        </div>
      </div>
      <div class="bg-(--bg-card) border border-(--border-soft) rounded-md p-4 flex flex-col justify-between shadow-sm">
        <div class="flex items-center justify-between mb-2">
          <span class="text-xs font-semibold text-(--text-muted) uppercase tracking-wider">Potongan</span>
          <div class="w-7 h-7 rounded-md bg-(--danger)/10 text-(--danger) flex items-center justify-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
            </svg>
          </div>
        </div>
        <div>
          <p class="text-lg font-bold text-(--danger) tracking-tight">Rp {{ formatCurrency(totals.all_potongan, true) }}</p>
          <p class="text-[10px] text-(--text-soft) mt-0.5">BPJS + Bon + PPh</p>
        </div>
      </div>
      <div class="bg-(--bg-card) border border-(--border-soft) rounded-md p-4 flex flex-col justify-between shadow-sm">
        <div class="flex items-center justify-between mb-2">
          <span class="text-xs font-semibold text-(--text-muted) uppercase tracking-wider">Total Staff</span>
          <div class="w-7 h-7 rounded-md bg-(--text-soft)/10 text-(--text-muted) flex items-center justify-center">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
            </svg>
          </div>
        </div>
        <div>
          <p class="text-lg font-bold text-(--text-main) tracking-tight">{{ totals.all_employees }} Orang</p>
          <p class="text-[10px] text-(--text-soft) mt-0.5">Tercatat di Payroll</p>
        </div>
      </div>
    </div>

    <!-- Section Tables -->
    <template v-if="selectedPeriod && sections.length > 0">
      <BaseCard padding="p-0" class="overflow-hidden border border-(--border-soft) bg-(--bg-card) rounded-md shadow-sm">
        <div class="px-4 py-3 border-b border-(--border-soft) flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3 bg-(--bg-main)/30">
          <div>
            <h3 class="text-sm font-semibold text-(--text-main)">Rincian Gaji Karyawan</h3>
            <p class="text-xs text-(--text-muted) mt-0.5">Menampilkan {{ filteredCount }} dari {{ records.length }} data gaji</p>
          </div>
          <div class="relative w-full sm:w-72">
            <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-(--text-soft)">
              <IconSearch class="h-4 w-4" />
            </span>
            <input v-model="searchQuery" type="text" placeholder="Cari nama, NIP, departemen..." class="w-full h-10 pl-9 pr-3 rounded-md border border-(--border-soft) bg-(--bg-card) text-(--text-main) text-sm focus:ring-2 focus:ring-(--primary) focus:border-transparent outline-none transition-all" />
          </div>
        </div>

        <template v-for="section in sections" :key="section.key">
          <div class="px-4 py-2.5 bg-(--primary)/5 border-b border-(--border-soft) font-bold text-sm text-(--text-main) uppercase flex items-center justify-between">
            <span>{{ section.label }}</span>
            <span class="text-xs font-normal text-(--text-muted)">{{ section.data.length }} Karyawan</span>
          </div>
          <div class="overflow-x-auto max-h-[65vh] overflow-y-auto">
            <table class="w-full text-xs">
              <thead>
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
                  <th :colspan="5" class="border border-(--border-soft) px-2.5 py-1.5 text-center font-bold text-(--primary) bg-(--primary)/5 whitespace-nowrap">{{ periodLabel }}</th>
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
                  <td :colspan="26" class="px-4 py-8 text-center text-(--text-muted) text-sm bg-(--bg-card)">Tidak ada data di section ini</td>
                </tr>
                <tr v-for="(record, idx) in section.filtered" :key="record.id" class="hover-row transition-colors" :class="idx % 2 === 0 ? 'bg-(--bg-card)' : 'bg-(--bg-main)/40'">
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
                  <td class="border border-(--border-soft) px-2 py-2 text-center text-(--text-muted)">{{ record.lm > 0 ? record.lm.toFixed(1).replace('.', ',') : '-' }}</td>
                  <td class="border border-(--border-soft) px-2 py-2 text-center text-(--text-muted)">{{ record.lembur_count > 0 ? record.lembur_count.toFixed(1).replace('.', ',') : '-' }}</td>
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
                </tr>
              </tbody>
              <tfoot v-if="section.data.length > 0">
                <tr class="bg-(--bg-elevated) font-bold text-(--text-main) border-t-2 border-(--border-soft)">
                  <td colspan="7" class="sticky-col-last z-10 border border-(--border-soft) px-2.5 py-3.5 text-right font-bold" style="left:0; min-width:160px;">TOTAL {{ section.label }}</td>
                  <td class="border border-(--border-soft) px-2 py-3.5 text-right font-mono">{{ formatCurrency(section.totals.premi, true) }}</td>
                  <td class="border border-(--border-soft) px-2 py-3.5 text-right font-mono">{{ formatCurrency(section.totals.gaji_pokok, true) }}</td>
                  <td class="border border-(--border-soft) px-2 py-3.5 text-right font-mono">{{ formatCurrency(section.totals.tj_masa_kerja, true) }}</td>
                  <td class="border border-(--border-soft) px-2 py-3.5 text-center font-bold">{{ section.totals.hari_kerja }}</td>
                  <td class="border border-(--border-soft) px-2 py-3.5 text-center text-(--text-muted)">{{ section.totals.lm > 0 ? section.totals.lm.toFixed(1).replace('.', ',') : '-' }}</td>
                  <td class="border border-(--border-soft) px-2 py-3.5 text-center text-(--text-muted)">{{ section.totals.lembur_count > 0 ? section.totals.lembur_count.toFixed(1).replace('.', ',') : '-' }}</td>
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
                </tr>
              </tfoot>
            </table>
          </div>
        </template>

        <div v-if="sections.some(s => s.data.length > 0)" class="px-4 py-3 bg-(--primary)/5 border-t-2 border-(--primary) flex justify-end gap-6 text-sm font-bold">
          <span class="uppercase text-(--primary)">TOTAL KESELURUHAN</span>
          <span class="text-(--text-main)">{{ filteredCount }} Karyawan</span>
          <span class="text-(--text-main)">Rp {{ formatCurrency(grandTotals.gaji_bersih, true) }}</span>
        </div>
      </BaseCard>
    </template>

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
        <div v-else>
          Belum ada data gaji untuk periode ini.
          <br/>Klik <strong>"Kalkulasi Gaji"</strong> untuk menghitung dari data snapshot,
          atau <strong>"Import Excel"</strong> untuk ambil dari file.
        </div>
      </div>
    </BaseCard>

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

    <!-- Modal: Setting -->
    <BaseModal :show="showSettings" @close="showSettings = false" title="Pengaturan Gaji Karyawan">
      <div class="space-y-4">
        <GajiKaryawanSettings :config="payrollConfig" @update:config="onConfigUpdate" />
        <hr class="border-(--border-soft)" />
        <div class="flex items-center justify-between">
          <div v-if="configUpdatedBy" class="text-xs text-(--text-muted)">Terakhir diubah: {{ configUpdatedAt }} oleh {{ configUpdatedBy }}</div>
          <div v-else class="text-xs text-(--text-muted)">Menggunakan pengaturan default</div>
          <div class="flex gap-2">
            <BaseButton variant="ghost" @click="showSettings = false">Batal</BaseButton>
            <BaseButton variant="primary" :loading="savingConfig" @click="saveConfig">Simpan</BaseButton>
          </div>
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
import { useNotificationStore } from '@/Stores/notification'

const { get, post, put } = useApi()
const notification = useNotificationStore()

const periods = ref([])
const selectedPeriodId = ref('')
const records = ref([])
const generating = ref(false)
const importing = ref(false)
const printing = ref(false)
const activeSegment = ref(null)
const searchQuery = ref('')
const recapRecords = ref([])
const loadingRecap = ref(false)

const showSettings = ref(false)
const payrollConfig = ref({ sections: { A: ['GRP-ALLIN', 'GRP-SPR'], B: ['GRP-GD', 'GRP-SS', 'GRP-PS1'] } })
const pendingConfig = ref(null)
const savingConfig = ref(false)
const configUpdatedBy = ref('')
const configUpdatedAt = ref('')

const selectedPeriod = computed(() => periods.value.find(p => p.id === selectedPeriodId.value))

const periodLabel = computed(() => {
  if (!selectedPeriod.value) return ''
  const start = new Date(selectedPeriod.value.start_date)
  const end = new Date(selectedPeriod.value.end_date)
  const months = ['JANUARI', 'FEBRUARI', 'MARET', 'APRIL', 'MEI', 'JUNI', 'JULI', 'AGUSTUS', 'SEPTEMBER', 'OKTOBER', 'NOVEMBER', 'DESEMBER']
  return `${start.getDate()} ${months[start.getMonth()]} - ${end.getDate()} ${months[end.getMonth()]} ${end.getFullYear().toString().slice(-2)}`
})

const filteredRecords = computed(() => {
  if (!searchQuery.value) return records.value
  const q = searchQuery.value.toLowerCase()
  return records.value.filter(r => r.name.toLowerCase().includes(q) || r.employee_code.toLowerCase().includes(q) || r.department.toLowerCase().includes(q) || r.position.toLowerCase().includes(q))
})

const filteredCount = computed(() => filteredRecords.value.length)

const sections = computed(() => {
  const mapping = payrollConfig.value?.sections || {}
  const sectionsA = mapping.A || ['GRP-ALLIN', 'GRP-SPR']
  const sectionsB = mapping.B || ['GRP-GD', 'GRP-SS', 'GRP-PS1']
  const secA = { key: 'A', label: 'A. KARYAWAN ALL IN', data: [], filtered: [], totals: null, startNo: 1 }
  const secB = { key: 'B', label: 'B. KARYAWAN BULANAN PRINT', data: [], filtered: [], totals: null, startNo: 1 }
  for (const record of filteredRecords.value) {
    const groups = record.groups || []
    if (groups.some(g => sectionsA.includes(g))) { secA.data.push(record) }
    else if (groups.some(g => sectionsB.includes(g))) { secB.data.push(record) }
  }
  secA.filtered = secA.data
  secB.filtered = secB.data
  secA.totals = computeSectionTotals(secA.data)
  secB.totals = computeSectionTotals(secB.data)
  secB.startNo = secA.data.length + 1
  return [secA, secB]
})

function computeSectionTotals(data) {
  const sum = (key) => data.reduce((acc, r) => acc + (parseFloat(r[key]) || 0), 0)
  return {
    hari_kerja: data.reduce((acc, r) => acc + (parseInt(r.hari_kerja) || 0), 0),
    lm: data.reduce((acc, r) => acc + (parseInt(r.lm) || 0), 0),
    lembur_count: data.reduce((acc, r) => acc + (parseFloat(r.lembur_count) || 0), 0),
    gaji: sum('gaji'), upah_lembur: sum('upah_lembur'), revisi: sum('revisi'),
    tunjangan: sum('tunjangan'), premi_hadir: sum('premi_hadir'), pblt: sum('pblt'),
    total: sum('total'), bpjs_tk: sum('bpjs_tk'), bpjs_ks: sum('bpjs_ks'),
    bpjs_pen: sum('bpjs_pen'), cashbon: sum('cashbon'), pph: sum('pph'),
    gaji_bersih: sum('gaji_bersih'),
  }
}

const grandTotals = computed(() => computeSectionTotals(filteredRecords.value))

const totals = computed(() => {
  const sum = (key) => records.value.reduce((acc, r) => acc + (parseFloat(r[key]) || 0), 0)
  return {
    all_gaji_bersih: sum('gaji_bersih'), all_gaji_pokok: sum('gaji_pokok'),
    all_upah_lembur: sum('upah_lembur'), all_bpjs: sum('bpjs_tk') + sum('bpjs_ks') + sum('bpjs_pen'),
    all_potongan: sum('bpjs_tk') + sum('bpjs_ks') + sum('bpjs_pen') + sum('cashbon') + sum('pph'),
    all_employees: records.value.length,
  }
})

function formatCurrency(value, showZero = false) {
  if (!value && value !== 0) return '-'
  if (parseFloat(value) === 0 && !showZero) return '-'
  return new Intl.NumberFormat('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(value)
}

async function fetchPeriods() {
  try { const res = await get('/api/v1/payroll/periods'); periods.value = res.data || [] } catch (error) { console.error('Error fetching periods', error) }
}

async function fetchPayrollConfig() {
  try {
    const res = await get('/api/v1/payroll/configs/gaji_karyawan')
    payrollConfig.value = res.config || { sections: { A: ['GRP-ALLIN', 'GRP-SPR'], B: ['GRP-GD', 'GRP-SS', 'GRP-PS1'] } }
    configUpdatedBy.value = res.updated_by || ''
    configUpdatedAt.value = res.updated_at || ''
  } catch (error) { console.error('Error fetching payroll config', error) }
}

function onConfigUpdate(newConfig) { pendingConfig.value = newConfig }

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
  } catch (error) { notification.error('Gagal menyimpan pengaturan.') }
  finally { savingConfig.value = false }
}

// ─── SUPERVISOR API ENDPOINTS ───

async function fetchRecords() {
  if (!selectedPeriodId.value) { records.value = []; return }
  try {
    let url = `/api/v1/supervisor/payroll/breakdown?period_id=${selectedPeriodId.value}`
    if (activeSegment.value) url += `&segment=${activeSegment.value}`
    const res = await get(url)
    records.value = res.data || []
  } catch (error) { console.error('Error fetching records', error); records.value = [] }
}

async function fetchRecapRecords() {
  if (!selectedPeriodId.value) { recapRecords.value = []; return }
  loadingRecap.value = true
  try {
    let all = []; let page = 1; let lastPage = 1
    do {
      const res = await get(`/api/v1/attendance/recap?period_id=${selectedPeriodId.value}&per_page=100&page=${page}`)
      all = [...all, ...(res.data || [])]
      lastPage = res.last_page || 1; page++
    } while (page <= lastPage)
    recapRecords.value = all
  } catch (error) { recapRecords.value = [] }
  finally { loadingRecap.value = false }
}

async function switchSegment(seg) { activeSegment.value = seg; await fetchRecords() }

async function onPeriodChange() {
  const period = periods.value.find(p => p.id === selectedPeriodId.value)
  activeSegment.value = period?.is_split ? 'A' : null
  searchQuery.value = ''
  await fetchRecords()
}

async function handleGenerate() {
  if (!selectedPeriodId.value) return
  generating.value = true
  try {
    const res = await post(`/api/v1/supervisor/payroll/breakdown/calculate`, { period_id: selectedPeriodId.value })
    notification.success(res.message || 'Berhasil mengkalkulasi breakdown gaji.')
    await fetchRecords()
  } catch (error) { notification.error(error.message || 'Gagal mengkalkulasi breakdown.') }
  finally { generating.value = false }
}

async function handleUpdateData() {
  if (!selectedPeriodId.value) return
  importing.value = true
  try {
    const body = { period_id: selectedPeriodId.value }
    if (activeSegment.value) body.segment = activeSegment.value

    const res = await post('/api/v1/supervisor/payroll/breakdown/import', body)
    notification.success(res.message || 'Data berhasil diupdate dari file.')
    await fetchRecords()
  } catch (error) { notification.error(error.message || 'Gagal update data.') }
  finally { importing.value = false }
}

function handleExport() {
  const token = localStorage.getItem('token')
  const params = new URLSearchParams()
  if (selectedPeriodId.value) params.append('period_id', selectedPeriodId.value)
  if (activeSegment.value) params.append('segment', activeSegment.value)
  const url = `/api/v1/supervisor/payroll/breakdown/export?${params.toString()}`
  notification.info('Sedang menyiapkan file Excel...')
  fetch(url, { headers: { 'Authorization': `Bearer ${token}` } })
    .then(r => { if (!r.ok) throw new Error('Gagal export Excel'); return r.blob() })
    .then(blob => {
      const downloadUrl = URL.createObjectURL(blob)
      const link = document.createElement('a'); link.href = downloadUrl
      link.setAttribute('download', `Laporan_Gaji_Karyawan.xlsx`)
      document.body.appendChild(link); link.click(); document.body.removeChild(link)
      URL.revokeObjectURL(downloadUrl)
    })
    .catch(err => { console.error(err); notification.error('Gagal export Excel') })
}

async function handlePrint() {
  if (!selectedPeriodId.value) return
  printing.value = true
  try {
    const token = localStorage.getItem('token')
    const params = new URLSearchParams()
    if (selectedPeriodId.value) params.append('period_id', selectedPeriodId.value)
    if (activeSegment.value) params.append('segment', activeSegment.value)
    const url = `/api/v1/supervisor/payroll/breakdown/print?${params.toString()}`

    const response = await fetch(url, {
      headers: {
        'Accept': 'application/pdf',
        'Authorization': `Bearer ${token}`
      }
    })
    if (!response.ok) throw new Error('Gagal generate PDF')
    const blob = await response.blob()

    let filename = 'Laporan_Gaji_Karyawan.pdf'
    const disposition = response.headers.get('Content-Disposition')
    if (disposition) {
      const match = disposition.match(/filename\*?=(?:UTF-8''|")?([^";]+)/)
      if (match) filename = decodeURIComponent(match[1])
    }

    const a = document.createElement('a')
    a.href = URL.createObjectURL(blob)
    a.download = filename
    document.body.appendChild(a)
    a.click()
    a.remove()
    notification.success('PDF berhasil di-download.')
  } catch (err) {
    console.error(err)
    notification.error('Gagal generate PDF: ' + err.message)
  } finally {
    printing.value = false
  }
}

onMounted(() => { fetchPeriods(); fetchPayrollConfig() })
</script>

<style scoped>
.sticky-col { position: sticky; background-color: var(--bg-card); }
.sticky-col-last { position: sticky; border-right: 2px solid var(--border-soft) !important; box-shadow: 4px 0 8px -4px rgba(0, 0, 0, 0.08); background-color: var(--bg-card); }
tr:nth-child(even) .sticky-col, tr:nth-child(even) .sticky-col-last { background-color: var(--bg-main) !important; }
tr:hover .sticky-col, tr:hover .sticky-col-last { background-color: var(--bg-elevated) !important; }
thead tr .sticky-col, thead tr .sticky-col-last { background-color: var(--bg-elevated) !important; }
.hover-row:hover { background-color: rgba(var(--primary-glow), 0.05) !important; }
::-webkit-scrollbar { height: 6.5px; width: 6.5px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb { background: var(--text-soft); border-radius: 3px; }
::-webkit-scrollbar-thumb:hover { background: var(--text-muted); }
</style>
