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
        <!-- Period Select (Height h-10 matching class md) -->
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

        <!-- Kalkulasi Button -->
        <BaseButton
          variant="secondary"
          :disabled="!selectedPeriodId || generating"
          :loading="generating"
          @click="handleGenerate"
        >
          <template #icon-left>
            <IconRefresh class="w-4 h-4" />
          </template>
          Generate Awal
        </BaseButton>

        <!-- Approve & Kunci Gaji Button -->
        <BaseButton
          v-if="hasUnlockedRecaps"
          variant="primary"
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

    <!-- Summary Cards (Visible only when payroll records exist) -->
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

    <!-- Salary Table & Search Panel -->
    <BaseCard v-if="selectedPeriod" padding="p-0" class="overflow-hidden border border-(--border-soft) bg-(--bg-card) rounded-md shadow-sm">
      <!-- Search & Filter Controls -->
      <div class="px-4 py-3 border-b border-(--border-soft) flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3 bg-(--bg-main)/30">
        <div>
          <h3 class="text-sm font-semibold text-(--text-main)">Rincian Gaji Karyawan</h3>
          <p class="text-xs text-(--text-muted) mt-0.5" v-if="records.length > 0">
            Menampilkan {{ filteredRecords.length }} dari {{ records.length }} data gaji
          </p>
          <p class="text-xs text-(--text-muted) mt-0.5" v-else>
            Tidak ada data untuk ditampilkan.
          </p>
        </div>
        
        <!-- Search Input (h-10 class md) -->
        <div class="relative w-full sm:w-72" v-if="records.length > 0">
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

      <!-- Table Scroll Wrapper -->
      <div class="overflow-x-auto">
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
            <tr v-if="filteredRecords.length === 0">
              <td :colspan="26" class="px-4 py-12 text-center text-(--text-muted) text-sm bg-(--bg-card)">
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
              </td>
            </tr>
            <tr
              v-for="(record, idx) in filteredRecords"
              :key="record.id"
              class="hover-row transition-colors"
              :class="idx % 2 === 0 ? 'bg-(--bg-card)' : 'bg-(--bg-main)/40'"
            >
              <td class="sticky-col z-10 border border-(--border-soft) px-2 py-2 text-center text-(--text-muted)">{{ idx + 1 }}</td>
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
              <td class="border border-(--border-soft) px-2 py-2 text-center text-(--text-muted)">{{ record.lm > 0 ? record.lm + 'j' : '-' }}</td>
              <td class="border border-(--border-soft) px-2 py-2 text-center text-(--text-muted)">{{ record.lembur_count > 0 ? record.lembur_count + 'j' : '-' }}</td>
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
          <tfoot v-if="filteredRecords.length > 0">
            <tr class="bg-(--bg-elevated) font-bold text-(--text-main) border-t-2 border-(--border-soft)">
              <td colspan="7" class="sticky-col-last z-10 border border-(--border-soft) px-2.5 py-3.5 text-right font-bold" style="left:0; min-width:160px;">TOTAL</td>
              <td class="border border-(--border-soft) px-2 py-3.5 text-right font-mono">{{ formatCurrency(totals.premi, true) }}</td>
              <td class="border border-(--border-soft) px-2 py-3.5 text-right font-mono">{{ formatCurrency(totals.gaji_pokok, true) }}</td>
              <td class="border border-(--border-soft) px-2 py-3.5 text-right font-mono">{{ formatCurrency(totals.tj_masa_kerja, true) }}</td>
              <td class="border border-(--border-soft) px-2 py-3.5 text-center font-bold">{{ totals.hari_kerja }}</td>
              <td class="border border-(--border-soft) px-2 py-3.5 text-center text-(--text-muted)">{{ totals.lm }}j</td>
              <td class="border border-(--border-soft) px-2 py-3.5 text-center text-(--text-muted)">{{ totals.lembur_count }}j</td>
              <td class="border border-(--border-soft) px-2 py-3.5 text-right font-mono">{{ formatCurrency(totals.gaji, true) }}</td>
              <td class="border border-(--border-soft) px-2 py-3.5 text-right font-mono">{{ formatCurrency(totals.upah_lembur, true) }}</td>
              <td class="border border-(--border-soft) px-2 py-3.5 text-right font-mono">{{ formatCurrency(totals.revisi, true) }}</td>
              <td class="border border-(--border-soft) px-2 py-3.5 text-right font-mono">{{ formatCurrency(totals.tunjangan, true) }}</td>
              <td class="border border-(--border-soft) px-2 py-3.5 text-right font-mono">{{ formatCurrency(totals.premi_hadir, true) }}</td>
              <td class="border border-(--border-soft) px-2 py-3.5 text-right font-mono">{{ formatCurrency(totals.pblt, true) }}</td>
              <td class="border border-(--border-soft) px-2.5 py-3.5 text-right font-mono font-bold">{{ formatCurrency(totals.total, true) }}</td>
              <td class="border border-(--border-soft) px-2 py-3.5 text-right font-mono text-(--danger)">{{ formatCurrency(totals.bpjs_tk, true) }}</td>
              <td class="border border-(--border-soft) px-2 py-3.5 text-right font-mono text-(--danger)">{{ formatCurrency(totals.bpjs_ks, true) }}</td>
              <td class="border border-(--border-soft) px-2 py-3.5 text-right font-mono text-(--danger)">{{ formatCurrency(totals.bpjs_pen, true) }}</td>
              <td class="border border-(--border-soft) px-2 py-3.5 text-right font-mono text-(--danger)">{{ formatCurrency(totals.cashbon, true) }}</td>
              <td class="border border-(--border-soft) px-2 py-3.5 text-right font-mono text-(--danger)">{{ formatCurrency(totals.pph, true) }}</td>
              <td class="border border-(--border-soft) px-2.5 py-3.5 text-right font-mono text-(--primary) bg-(--primary)/5">{{ formatCurrency(totals.gaji_bersih, true) }}</td>
            </tr>
          </tfoot>
        </table>
      </div>
    </BaseCard>

    <!-- Empty State -->
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

    <!-- Modal Kalkulasi & Kunci Gaji -->
    <BaseModal :show="isApproveModalOpen" @close="isApproveModalOpen = false" title="Kalkulasi & Kunci Gaji">
      <div class="space-y-4">
        <p class="text-sm text-(--text-main)">
          Proses ini akan mengkalkulasi ulang gaji berdasarkan data kehadiran terakhir dan <strong>mengunci</strong> data tersebut.
        </p>

        <div class="bg-(--bg-main) p-4 rounded-md border border-(--border-soft)">
          <h4 class="text-sm font-semibold text-(--text-main) mb-2">Filter Grup Bebas Lembur (Rp 0)</h4>
          <p class="text-xs text-(--text-muted) mb-4">Pilih grup karyawan yang <strong>TIDAK</strong> akan mendapatkan uang lembur pada periode ini (misal: karena diganti uang makan atau status staff).</p>
          
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 max-h-48 overflow-y-auto pr-2">
            <label v-for="group in employeeGroups" :key="group.code" class="flex items-center gap-2 cursor-pointer p-2 rounded-md hover:bg-(--bg-elevated) border border-transparent hover:border-(--border-soft) transition-all">
              <input type="checkbox" :value="group.code" v-model="selectedZeroOvertimeGroups" class="rounded border-(--border-soft) text-(--primary) focus:ring-(--primary)" />
              <div class="flex flex-col">
                <span class="text-sm font-medium text-(--text-main)">{{ group.name }}</span>
                <span class="text-[10px] text-(--text-muted)">{{ group.code }}</span>
              </div>
            </label>
          </div>
        </div>

        <div class="flex justify-end gap-3 mt-6">
          <BaseButton variant="ghost" @click="isApproveModalOpen = false">Batal</BaseButton>
          <BaseButton variant="primary" :loading="approvingPayroll" @click="handleApprovePayroll">Proses & Kunci Gaji</BaseButton>
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
import { IconDownload, IconFileInvoice, IconRefresh, IconSearch, IconAlertTriangle } from '@/Components/Icons/index.js'
import { useApi } from '@/composables/useApi'
import { useNotificationStore } from '@/Stores/notification'

const { get, post } = useApi()
const notification = useNotificationStore()

const periods = ref([])
const selectedPeriodId = ref('')
const records = ref([])
const generating = ref(false)
const activeSegment = ref(null)
const searchQuery = ref('')

// Recap-based state to check if payroll needs to be calculated/approved
const recapRecords = ref([])
const loadingRecap = ref(false)
const approvingPayroll = ref(false)
const isApproveModalOpen = ref(false)
const employeeGroups = ref([])
const selectedZeroOvertimeGroups = ref(['GRP-ALLIN', 'GRP-GD', 'GRP-SS', 'GRP-SPR'])

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

const totals = computed(() => {
  const sum = (key) => records.value.reduce((acc, r) => acc + (parseFloat(r[key]) || 0), 0)
  const fSum = (key) => filteredRecords.value.reduce((acc, r) => acc + (parseFloat(r[key]) || 0), 0)
  
  return {
    // Filtered totals for table footer
    hari_kerja: filteredRecords.value.reduce((acc, r) => acc + (parseInt(r.hari_kerja) || 0), 0),
    lm: filteredRecords.value.reduce((acc, r) => acc + (parseInt(r.lm) || 0), 0),
    lembur_count: filteredRecords.value.reduce((acc, r) => acc + (parseInt(r.lembur_count) || 0), 0),
    gaji: fSum('gaji'),
    upah_lembur: fSum('upah_lembur'),
    revisi: fSum('revisi'),
    tunjangan: fSum('tunjangan'),
    premi_hadir: fSum('premi_hadir'),
    pblt: fSum('pblt'),
    total: fSum('total'),
    bpjs_tk: fSum('bpjs_tk'),
    bpjs_ks: fSum('bpjs_ks'),
    bpjs_pen: fSum('bpjs_pen'),
    cashbon: fSum('cashbon'),
    pph: fSum('pph'),
    gaji_bersih: fSum('gaji_bersih'),

    // Overall sums (not filtered by search query) for summary cards
    all_gaji_bersih: sum('gaji_bersih'),
    all_gaji_pokok: sum('gaji_pokok'),
    all_upah_lembur: sum('upah_lembur'),
    all_bpjs: sum('bpjs_tk') + sum('bpjs_ks') + sum('bpjs_pen'),
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
  try {
    const res = await get('/api/v1/payroll/periods')
    periods.value = res.data || []
  } catch (error) {
    console.error('Error fetching periods', error)
  }
}

async function fetchEmployeeGroups() {
  try {
    const res = await get('/api/v1/settings/employee-data/groups')
    employeeGroups.value = res.data || []
  } catch (error) {
    console.error('Error fetching employee groups', error)
  }
}

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
    const res = await get(`/api/v1/attendance/recap?period_id=${selectedPeriodId.value}&per_page=1000`)
    recapRecords.value = res.data || []
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
    const res = await post('/api/v1/attendance/recap/approve', { 
      ids: unlockedIds,
      zero_overtime_groups: selectedZeroOvertimeGroups.value
    })
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
  notification.info('Fitur Export Excel akan segera hadir.')
}

onMounted(() => {
  fetchPeriods()
  fetchEmployeeGroups()
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

/* Ensure zebra striping and hovers work flawlessly with sticky columns */
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

/* Scrollbar customizations matching app.css style */
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
