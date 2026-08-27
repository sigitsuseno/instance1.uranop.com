<template>
  <div class="py-2 space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold tracking-tight text-(--text-main) flex items-center gap-2.5">
          <span class="w-9 h-9 rounded-lg bg-(--primary)/10 border border-(--primary)/15 flex items-center justify-center text-(--primary)">
            <IconChartBar class="w-5 h-5" />
          </span>
          Statistik
        </h1>
        <p class="text-sm text-(--text-muted) mt-1">Preview kehadiran (att_prepare) & payroll (pay_records) — harian, bulanan, semester</p>
      </div>
      <div class="flex items-center gap-2">
        <BaseButton variant="secondary" size="md" @click="loadAll" :loading="loadingKehadiran || loadingPayroll">
          <template #icon-left><IconRefresh class="w-4 h-4" :class="{ 'animate-spin': loadingKehadiran || loadingPayroll }" /></template>
          Refresh
        </BaseButton>
      </div>
    </div>

    <!-- Tabs Kehadiran -->
    <BaseCard>
      <template #title>
        <div class="flex items-center gap-2.5">
          <span class="w-8 h-8 rounded-lg bg-(--success)/10 border border-(--success)/15 flex items-center justify-center text-(--success)"><IconCalendarCheck class="w-4 h-4" /></span>
          <div>
            <div class="text-[13px] font-bold tracking-tight text-(--text-main)">Statistik Kehadiran</div>
            <div class="text-[11px] text-(--text-muted) font-medium">Sumber: att_prepares</div>
          </div>
        </div>
      </template>
      <template #actions>
        <div class="flex items-center gap-2">
          <div class="inline-flex rounded-full border border-(--border-soft) bg-(--bg-elevated) p-1">
            <button v-for="r in ranges" :key="r.value" @click="range = r.value" :class="['px-3 py-1 rounded-full text-xs font-bold transition-colors', range===r.value ? 'bg-(--primary) text-white shadow' : 'text-(--text-muted) hover:text-(--text-main)']">{{ r.label }}</button>
          </div>
        </div>
      </template>

      <!-- Date controls per range -->
      <div class="flex flex-wrap items-center gap-3 mb-4">
        <template v-if="range==='harian'">
          <label class="text-xs font-semibold text-(--text-muted)">Tanggal</label>
          <input type="date" v-model="dateHarian" @change="loadKehadiran" class="px-3 py-1.5 rounded-md border border-(--border-soft) bg-(--bg-card) text-sm text-(--text-main)" />
          <span class="text-xs text-(--text-soft)">Preview 1 hari</span>
        </template>
        <template v-else-if="range==='bulanan'">
          <label class="text-xs font-semibold text-(--text-muted)">Bulan</label>
          <input type="month" v-model="monthBulanan" @change="loadKehadiran" class="px-3 py-1.5 rounded-md border border-(--border-soft) bg-(--bg-card) text-sm text-(--text-main)" />
          <span class="text-xs text-(--text-soft)">{{ kehadiran?.month_label || '' }}</span>
        </template>
        <template v-else>
          <label class="text-xs font-semibold text-(--text-muted)">Anchor (6 bln terakhir)</label>
          <input type="month" v-model="monthSemester" @change="loadKehadiran" class="px-3 py-1.5 rounded-md border border-(--border-soft) bg-(--bg-card) text-sm text-(--text-main)" />
          <span class="text-xs text-(--text-soft)">{{ kehadiran?.label || '' }}</span>
        </template>
        <span v-if="loadingKehadiran" class="text-xs text-(--text-muted) animate-pulse">Memuat...</span>
      </div>

      <div v-if="loadingKehadiran" class="py-14 text-center text-sm text-(--text-muted)">Memuat statistik kehadiran...</div>
      <div v-else-if="!kehadiran" class="py-14 text-center text-sm text-(--text-muted)">Belum ada data.</div>
      <template v-else>
        <!-- Summary cards -->
        <div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-5">
          <div v-for="c in kehadiranSummaryCards" :key="c.label" class="rounded-xl border border-(--border-soft) bg-(--bg-elevated)/40 p-3.5">
            <div class="text-[11px] font-bold tracking-widest uppercase" :style="{ color: c.color }">{{ c.label }}</div>
            <div class="text-2xl font-extrabold text-(--text-main) mt-1">{{ c.value }}</div>
            <div class="text-[11px] text-(--text-muted) mt-1">{{ c.sub }}</div>
          </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
          <!-- Chart barang -->
          <div class="lg:col-span-2">
            <div class="rounded-xl border border-(--border-soft)/50 bg-(--bg-elevated)/20 p-4">
              <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-bold tracking-widest uppercase text-(--text-muted)">Distribusi</span>
                <span class="text-[11px] font-semibold text-(--text-soft) bg-(--bg-card) border border-(--border-soft) px-2 py-0.5 rounded-full">{{ kehadiran.summary.total }} records</span>
              </div>

              <!-- Bar horizontal distribusi status -->
              <div class="space-y-2.5">
                <div v-for="bar in kehadiran.chart" :key="bar.label" class="flex items-center gap-3">
                  <span class="w-16 text-xs font-semibold text-(--text-main) text-right shrink-0">{{ bar.label }}</span>
                  <div class="flex-1 h-6 rounded-full bg-(--bg-card) border border-(--border-soft) overflow-hidden relative">
                    <div class="h-full rounded-full transition-all duration-500 flex items-center justify-end pr-2" :style="{ width: barPct(bar.value) + '%', background: bar.color }">
                      <span v-if="bar.value>0" class="text-[11px] font-bold text-white drop-shadow">{{ bar.value }}</span>
                    </div>
                  </div>
                  <span class="w-12 text-xs font-medium text-(--text-muted) shrink-0">{{ barPct(bar.value) }}%</span>
                </div>
              </div>

              <!-- Trend -->
              <div class="mt-6">
                <div class="text-xs font-bold tracking-widest uppercase text-(--text-muted) mb-3">
                  <span v-if="range==='harian'">Detail hari {{ kehadiran.date }}</span>
                  <span v-else-if="range==='bulanan'">Trend Harian — {{ kehadiran.month_label }}</span>
                  <span v-else>Trend Bulanan — {{ kehadiran.label }}</span>
                </div>

                <!-- Harian: cuti breakdown + totals -->
                <template v-if="range==='harian'">
                  <div class="grid grid-cols-3 gap-2 text-xs">
                    <div class="rounded-lg bg-(--bg-card) border border-(--border-soft) p-2.5 text-center">
                      <div class="text-(--text-muted)">Overtime</div>
                      <div class="font-bold text-(--text-main)">{{ kehadiran.totals.overtime_minutes }} m</div>
                    </div>
                    <div class="rounded-lg bg-(--bg-card) border border-(--border-soft) p-2.5 text-center">
                      <div class="text-(--text-muted)">LM</div>
                      <div class="font-bold text-(--text-main)">{{ kehadiran.totals.lm_minutes }} m</div>
                    </div>
                    <div class="rounded-lg bg-(--bg-card) border border-(--border-soft) p-2.5 text-center">
                      <div class="text-(--text-muted)">Late</div>
                      <div class="font-bold text-amber-600">{{ kehadiran.totals.late_minutes }} m</div>
                    </div>
                  </div>
                </template>

                <!-- Bulanan: bar vertikal harian -->
                <template v-else-if="range==='bulanan'">
                  <div class="overflow-x-auto -mx-1 px-1">
                    <div class="flex items-end gap-[2px] min-w-max h-[140px] pb-6 border-b border-(--border-soft)/60">
                      <div v-for="d in kehadiran.daily_trend" :key="d.date" class="flex flex-col items-center gap-1 w-[18px] shrink-0 group">
                        <div class="flex flex-col justify-end items-center gap-[1px] h-[90px] w-full">
                          <div v-if="d.hadir>0" class="w-full rounded-t-sm bg-[#10b981]" :style="{ height: Math.max(2, (d.hadir / maxDaily) * 70) + 'px' }" :title="`${d.date}: Hadir ${d.hadir}`"></div>
                          <div v-if="d.absent>0" class="w-full bg-[#ef4444]" :style="{ height: Math.max(2, (d.absent / maxDaily) * 70) + 'px' }" :title="`${d.date}: Absent ${d.absent}`"></div>
                          <div v-if="d.cuti>0" class="w-full bg-[#3b82f6]" :style="{ height: Math.max(2, (d.cuti / maxDaily) * 70) + 'px' }" :title="`${d.date}: Cuti ${d.cuti}`"></div>
                          <div v-if="d.total===0" class="w-full h-[4px] bg-(--border-soft)/60 rounded-sm"></div>
                        </div>
                        <span class="text-[9px] font-medium" :class="d.total>0 ? 'text-(--text-muted)' : 'text-(--text-soft)'">{{ d.label }}</span>
                      </div>
                    </div>
                    <div class="flex items-center gap-3 mt-2 text-[11px]">
                      <span class="inline-flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-[#10b981]"></span>Hadir</span>
                      <span class="inline-flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-[#ef4444]"></span>Absent</span>
                      <span class="inline-flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-[#3b82f6]"></span>Cuti</span>
                    </div>
                  </div>
                </template>

                <!-- Semester: bar vertikal bulanan -->
                <template v-else>
                  <div class="flex items-end justify-between gap-2 h-[140px] pb-6 border-b border-(--border-soft)/60">
                    <div v-for="m in kehadiran.monthly_trend" :key="m.ym" class="flex-1 flex flex-col items-center gap-1 group">
                      <div class="flex flex-col justify-end items-center gap-[1px] h-[90px] w-full max-w-[48px]">
                        <div v-if="m.hadir>0" class="w-full rounded-t-sm bg-[#10b981]" :style="{ height: Math.max(2, (m.hadir / maxMonthly) * 70) + 'px' }"></div>
                        <div v-if="m.absent>0" class="w-full bg-[#ef4444]" :style="{ height: Math.max(2, (m.absent / maxMonthly) * 70) + 'px' }"></div>
                        <div v-if="m.cuti>0" class="w-full bg-[#3b82f6]" :style="{ height: Math.max(2, (m.cuti / maxMonthly) * 70) + 'px' }"></div>
                        <div v-if="m.total===0" class="w-full h-[4px] bg-(--border-soft)/60 rounded-sm"></div>
                      </div>
                      <span class="text-[10px] font-bold text-(--text-main)">{{ m.label }}</span>
                      <span class="text-[10px] text-(--text-muted)">{{ m.total }} rec</span>
                    </div>
                  </div>
                  <div class="flex items-center gap-3 mt-2 text-[11px]">
                    <span class="inline-flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-[#10b981]"></span>Hadir</span>
                    <span class="inline-flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-[#ef4444]"></span>Absent</span>
                    <span class="inline-flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-[#3b82f6]"></span>Cuti</span>
                  </div>
                </template>
              </div>
            </div>
          </div>

          <!-- Cuti breakdown + top -->
          <div class="space-y-4">
            <div class="rounded-xl border border-(--border-soft) bg-(--bg-card) p-4">
              <div class="text-xs font-bold tracking-widest uppercase text-(--text-muted) mb-3">Breakdown Cuti (Top 10)</div>
              <div v-if="!kehadiran.cuti_breakdown || kehadiran.cuti_breakdown.length===0" class="text-sm text-(--text-soft) py-4 text-center">Tidak ada cuti</div>
              <div v-else class="space-y-2">
                <div v-for="c in kehadiran.cuti_breakdown" :key="c.status" class="flex items-center justify-between py-1.5 border-b border-(--border-soft)/40 last:border-0">
                  <span class="text-sm font-semibold text-(--text-main)">{{ c.label }}</span>
                  <span class="text-xs font-bold bg-(--primary)/10 text-(--primary) px-2 py-0.5 rounded-full">{{ c.count }}</span>
                </div>
              </div>
            </div>
            <div v-if="range==='harian' && kehadiran.top_overtime && kehadiran.top_overtime.length>0" class="rounded-xl border border-(--border-soft) bg-(--bg-card) p-4">
              <div class="text-xs font-bold tracking-widest uppercase text-(--text-muted) mb-3">Top Overtime Hari Ini</div>
              <div class="space-y-2">
                <div v-for="(t,i) in kehadiran.top_overtime" :key="i" class="flex items-center justify-between text-sm">
                  <span class="truncate font-medium text-(--text-main)">{{ t.employee }}</span>
                  <span class="text-xs font-bold text-amber-600">{{ t.overtime }}m</span>
                </div>
              </div>
            </div>
            <div class="rounded-xl border border-dashed border-(--border-soft) bg-(--bg-elevated)/20 p-3">
              <div class="text-[11px] font-semibold text-(--text-muted) leading-relaxed">Preview only. Data dari <code class="bg-(--bg-card) border border-(--border-soft) px-1 rounded">att_prepares</code>. Untuk export/laporan resmi pakai menu Laporan → Kehadiran.</div>
            </div>
          </div>
        </div>
      </template>
    </BaseCard>

    <!-- Payroll -->
    <BaseCard>
      <template #title>
        <div class="flex items-center gap-2.5">
          <span class="w-8 h-8 rounded-lg bg-(--primary)/10 border border-(--primary)/15 flex items-center justify-center text-(--primary)"><IconDollarSign class="w-4 h-4" /></span>
          <div>
            <div class="text-[13px] font-bold tracking-tight text-(--text-main)">Statistik Payroll</div>
            <div class="text-[11px] text-(--text-muted) font-medium">Sumber: pay_records</div>
          </div>
        </div>
      </template>
      <template #actions>
        <div class="inline-flex rounded-full border border-(--border-soft) bg-(--bg-elevated) p-1">
          <button @click="payrollRange='bulanan'" :class="['px-3 py-1 rounded-full text-xs font-bold transition-colors', payrollRange==='bulanan' ? 'bg-(--primary) text-white shadow' : 'text-(--text-muted) hover:text-(--text-main)']">Bulanan</button>
          <button @click="payrollRange='semester'" :class="['px-3 py-1 rounded-full text-xs font-bold transition-colors', payrollRange==='semester' ? 'bg-(--primary) text-white shadow' : 'text-(--text-muted) hover:text-(--text-main)']">Semester</button>
        </div>
      </template>

      <div v-if="loadingPayroll" class="py-14 text-center text-sm text-(--text-muted)">Memuat statistik payroll...</div>
      <div v-else-if="!payroll || payroll.periods.length===0" class="py-14 text-center text-sm text-(--text-muted)">Belum ada data pay_records.</div>
      <template v-else>
        <!-- Summary -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-5">
          <div class="rounded-xl border border-(--border-soft) bg-(--bg-elevated)/40 p-3.5">
            <div class="text-[11px] font-bold tracking-widest uppercase text-(--text-muted)">Total Kotor ({{ payroll.summary.period_count }} periode)</div>
            <div class="text-lg font-extrabold text-(--text-main) mt-1">{{ formatRupiah(payroll.summary.total_kotor) }}</div>
            <div class="text-[11px] text-(--text-soft) mt-1">Avg {{ formatRupiah(payroll.summary.avg_kotor) }}</div>
          </div>
          <div class="rounded-xl border border-(--border-soft) bg-(--bg-elevated)/40 p-3.5">
            <div class="text-[11px] font-bold tracking-widest uppercase text-emerald-600">Total Bersih</div>
            <div class="text-lg font-extrabold text-emerald-600 mt-1">{{ formatRupiah(payroll.summary.total_bersih) }}</div>
            <div class="text-[11px] text-(--text-soft) mt-1">Avg {{ formatRupiah(payroll.summary.avg_bersih) }}</div>
          </div>
          <div class="rounded-xl border border-(--border-soft) bg-(--bg-elevated)/40 p-3.5">
            <div class="text-[11px] font-bold tracking-widest uppercase text-(--text-muted)">Total Records</div>
            <div class="text-lg font-extrabold text-(--text-main) mt-1">{{ payroll.summary.count }}</div>
            <div class="text-[11px] text-(--text-soft) mt-1">{{ payroll.summary.period_count }} periode</div>
          </div>
          <div class="rounded-xl border border-(--border-soft) bg-(--bg-elevated)/40 p-3.5">
            <div class="text-[11px] font-bold tracking-widest uppercase text-(--text-muted)">Potongan terbesar</div>
            <div class="text-sm font-bold text-(--text-main) mt-1">{{ topPotongan.label }} — {{ formatRupiah(topPotongan.value) }}</div>
            <div class="text-[11px] text-(--text-soft) mt-1">BPJS+PPh+Kasbon</div>
          </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
          <!-- Bar chart kotor vs bersih -->
          <div class="lg:col-span-2 rounded-xl border border-(--border-soft)/50 bg-(--bg-elevated)/20 p-4">
            <div class="flex items-center justify-between mb-3">
              <span class="text-xs font-bold tracking-widest uppercase text-(--text-muted)">Trend {{ payrollRange==='semester' ? 'Semester (6 bln)' : '6 Periode Terakhir' }}</span>
              <span class="hidden sm:inline-flex text-[10px] font-bold tracking-widest uppercase bg-(--bg-card) text-(--text-muted) border border-(--border-soft) px-2.5 py-1 rounded-full">PAY_RECORDS</span>
            </div>
            <div class="flex items-center gap-3 text-xs mb-3">
              <span class="inline-flex items-center gap-1.5 font-medium text-(--text-main)"><span class="w-2.5 h-2.5 rounded-full bg-[#2563eb]"></span>Kotor</span>
              <span class="inline-flex items-center gap-1.5 font-medium text-(--text-main)"><span class="w-2.5 h-2.5 rounded-full bg-[#10b981]"></span>Bersih</span>
              <span class="ml-auto text-[11px] font-semibold text-(--text-muted) bg-(--bg-card) border border-(--border-soft) px-2 py-0.5 rounded-full">max {{ formatRupiahShort(payMax) }}</span>
            </div>
            <div class="relative bg-(--bg-card)/50 rounded-xl border border-(--border-soft)/40 p-4">
              <div class="relative flex items-stretch justify-between gap-2 flex-1 min-h-[180px]">
                <div v-for="p in payroll.chart" :key="p.period_id" class="flex-1 flex flex-col items-center justify-end min-w-0 group">
                  <div class="relative flex items-end justify-center gap-1 w-full flex-1 min-h-[140px]">
                    <div class="absolute inset-x-0 top-6 bottom-0 pointer-events-none flex flex-col justify-between">
                      <div class="border-t border-dashed border-(--border-soft)/40"></div>
                      <div class="border-t border-dashed border-(--border-soft)/40"></div>
                      <div class="border-t border-dashed border-(--border-soft)/40"></div>
                      <div class="border-t border-(--border-soft)/30"></div>
                    </div>
                    <div class="pointer-events-none absolute top-0 left-1/2 -translate-x-1/2 hidden group-hover:flex flex-col items-center gap-0.5 z-10">
                      <div class="bg-(--bg-card) border border-(--border-soft) rounded-lg shadow-lg px-2.5 py-1.5 whitespace-nowrap">
                        <div class="text-[11px] font-bold text-[#2563eb]">Kotor {{ formatRupiahShort(p.total_kotor) }}</div>
                        <div class="text-[11px] font-bold text-[#059669]">Bersih {{ formatRupiahShort(p.total_bersih) }}</div>
                        <div class="text-[11px] text-(--text-muted)">{{ p.count }} org</div>
                      </div>
                      <div class="w-2 h-2 rotate-45 bg-(--bg-card) border-r border-b border-(--border-soft) -mt-1.5"></div>
                    </div>
                    <div class="flex flex-col items-center justify-end w-[22px] h-full">
                      <div v-if="p.total_kotor>0" class="w-full rounded-t-md bg-[#2563eb] bg-gradient-to-t from-[#1d4ed8] to-[#3b82f6] shadow-[0_2px_8px_rgba(37,99,235,0.28)]" :style="{ height: payBarPx(p.total_kotor) }"></div>
                      <div v-else class="w-full rounded-t-md border border-dashed border-(--border-soft) bg-(--bg-card)/50" style="height:8px"></div>
                    </div>
                    <div class="flex flex-col items-center justify-end w-[22px] h-full">
                      <div v-if="p.total_bersih>0" class="w-full rounded-t-md bg-[#059669] bg-gradient-to-t from-[#059669] to-[#34d399] shadow-[0_2px_8px_rgba(16,185,129,0.24)]" :style="{ height: payBarPx(p.total_bersih) }"></div>
                      <div v-else class="w-full rounded-t-md border border-dashed border-(--border-soft) bg-(--bg-card)/50" style="height:8px"></div>
                    </div>
                  </div>
                  <div class="text-center leading-tight mt-3">
                    <div class="text-[11px] font-bold text-(--text-main)">{{ p.label }}</div>
                    <div class="text-[10px] font-medium mt-0.5" :class="p.count>0 ? 'text-(--text-muted)' : 'text-(--text-soft)'">{{ p.count>0 ? p.count+' org' : '—' }}</div>
                  </div>
                </div>
              </div>
            </div>
            <div class="flex items-center justify-between pt-3 mt-3 border-t border-(--border-soft)/40">
              <span class="text-xs text-(--text-muted)">Total bersih {{ formatRupiahShort(payroll.summary.total_bersih) }} · {{ payroll.summary.count }} records</span>
              <router-link to="/admin/payroll/gaji-karyawan" class="inline-flex items-center gap-1 text-xs font-bold text-(--primary) hover:gap-1.5 transition-all">Detail Gaji <IconChevronRight class="w-3.5 h-3.5" /></router-link>
            </div>
          </div>

          <!-- Komposisi gaji per jabatan (donut) -->
          <div class="rounded-xl border border-(--border-soft) bg-(--bg-card) p-4">
            <div class="flex items-center justify-between gap-2 mb-3">
              <div class="text-xs font-bold tracking-widest uppercase text-(--text-muted)">Komposisi Gaji per Jabatan</div>
              <select v-model="komposisiBulan" @change="loadKomposisi" class="px-2 py-1 rounded-md border border-(--border-soft) bg-(--bg-elevated) text-xs font-semibold text-(--text-main) focus:outline-none">
                <option v-for="m in komposisiMonths" :key="m.ym" :value="m.ym">{{ m.label }}</option>
              </select>
            </div>

            <div v-if="loadingKomposisi" class="py-10 text-center text-sm text-(--text-muted)">Memuat...</div>
            <div v-else-if="!pieSegments.length" class="py-10 text-center text-sm text-(--text-soft)">Belum ada data pay_records bulan ini.</div>
            <template v-else>
              <!-- Donut -->
              <div class="relative w-full max-w-[200px] mx-auto">
                <svg viewBox="0 0 120 120" class="w-full">
                  <g transform="rotate(-90 60 60)">
                    <circle v-for="(seg,i) in pieSegments" :key="i" cx="60" cy="60" :r="PIE_R" fill="none"
                      :stroke="seg.color" :stroke-width="PIE_STROKE"
                      :stroke-dasharray="`${seg.len} ${seg.gap}`" :stroke-dashoffset="seg.offset"
                      class="transition-[stroke-dasharray,stroke-dashoffset] duration-500" />
                  </g>
                </svg>
                <div class="absolute inset-0 flex flex-col items-center justify-center text-center">
                  <div class="text-[10px] font-bold tracking-widest uppercase text-(--text-muted)">Total Gaji</div>
                  <div class="text-[13px] font-extrabold text-(--text-main) leading-tight" dir="ltr">{{ formatRupiahShort(komposisiTotal) }}</div>
                  <div class="text-[10px] text-(--text-soft) mt-0.5">{{ komposisi?.month_label }}</div>
                </div>
              </div>

              <!-- Legend -->
              <div class="mt-4 space-y-2 max-h-[190px] overflow-y-auto pr-1">
                <div v-for="(seg,i) in pieSegments" :key="i" class="flex items-center justify-between gap-2 text-sm">
                  <span class="flex items-center gap-2 font-medium text-(--text-main) min-w-0">
                    <span class="w-2.5 h-2.5 rounded-full shrink-0" :style="{ background: seg.color }"></span>
                    <span class="truncate">{{ seg.position }}</span>
                    <span class="text-[13px] font-semibold text-(--text-main) shrink-0">({{ seg.count }})</span>
                  </span>
                  <span class="font-bold text-(--text-main) shrink-0" dir="ltr">{{ formatRupiahShort(seg.gaji) }}</span>
                </div>
              </div>
            </template>

            <div class="mt-4 rounded-lg bg-(--bg-elevated)/30 border border-dashed border-(--border-soft) p-3">
              <div class="text-[11px] font-semibold text-(--text-muted) leading-relaxed">Preview only. Data dari <code class="bg-(--bg-card) border border-(--border-soft) px-1 rounded">pay_records</code> — total gaji kotor per jabatan.</div>
            </div>
          </div>
        </div>
      </template>
    </BaseCard>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useApi } from '../../../composables/useApi.js'
import BaseCard from '../../../Components/BaseCard.vue'
import BaseButton from '../../../Components/BaseButton.vue'
import { IconChartBar, IconCalendarCheck, IconDollarSign, IconRefresh, IconChevronRight } from '../../../Components/Icons/index.js'

const { get } = useApi()

const ranges = [
  { label: 'Harian', value: 'harian' },
  { label: 'Bulanan', value: 'bulanan' },
  { label: 'Semester', value: 'semester' },
]
const range = ref('harian')
const dateHarian = ref(new Date().toISOString().slice(0,10))
const monthBulanan = ref(new Date().toISOString().slice(0,7))
const monthSemester = ref(new Date().toISOString().slice(0,7))

const loadingKehadiran = ref(false)
const kehadiran = ref(null)

const payrollRange = ref('bulanan')
const loadingPayroll = ref(false)
const payroll = ref(null)

const komposisi = ref(null)
const komposisiBulan = ref(new Date().toISOString().slice(0,7))
const loadingKomposisi = ref(false)

function formatRupiah(n) {
  if (!n) return 'Rp 0'
  return 'Rp ' + new Intl.NumberFormat('id-ID').format(Math.round(n))
}
function formatRupiahShort(n) {
  if (!n) return 'Rp 0'
  if (n >= 1_000_000_000) return 'Rp ' + (n/1_000_000_000).toFixed(1).replace('.', ',') + ' M'
  if (n >= 1_000_000) return 'Rp ' + Math.round(n/1_000_000) + ' Jt'
  if (n >= 1000) return 'Rp ' + Math.round(n/1000) + ' Rb'
  return 'Rp ' + new Intl.NumberFormat('id-ID').format(Math.round(n))
}

async function loadKehadiran() {
  loadingKehadiran.value = true
  try {
    let qs = `range=${range.value}`
    if (range.value==='harian') qs += `&date=${dateHarian.value}`
    else if (range.value==='bulanan') qs += `&month=${monthBulanan.value}`
    else qs += `&month=${monthSemester.value}`
    const res = await get(`/api/v1/statistik/kehadiran?${qs}`)
    kehadiran.value = res
  } catch (e) { console.error(e) } finally { loadingKehadiran.value=false }
}

async function loadPayroll() {
  loadingPayroll.value = true
  try {
    const res = await get(`/api/v1/statistik/payroll?range=${payrollRange.value}`)
    payroll.value = res
  } catch (e) { console.error(e) } finally { loadingPayroll.value=false }
}

async function loadKomposisi() {
  loadingKomposisi.value = true
  try {
    const month = komposisiBulan.value || new Date().toISOString().slice(0,7)
    const res = await get(`/api/v1/statistik/payroll/komposisi?month=${month}`)
    komposisi.value = res
    // snap ke bulan valid kalau bulan berjalan belum ada data payroll
    if (!res.months?.some(m => m.ym === month) && res.months?.length) {
      komposisiBulan.value = res.months[0].ym
    }
  } catch (e) { console.error(e) } finally { loadingKomposisi.value=false }
}

function loadAll(){ loadKehadiran(); loadPayroll(); loadKomposisi() }

watch(range, loadKehadiran)
watch(payrollRange, loadPayroll)
watch(komposisiBulan, loadKomposisi)

onMounted(loadAll)

// computed helpers kehadiran
const kehadiranSummaryCards = computed(()=>{
  if (!kehadiran.value) return []
  const s = kehadiran.value.summary
  return [
    { label:'Hadir', value:s.hadir, sub:`${pct(s.hadir,s.total)}% dari total`, color:'#10b981' },
    { label:'Absent', value:s.absent, sub:`${pct(s.absent,s.total)}% dari total`, color:'#ef4444' },
    { label:'Cuti', value:s.cuti, sub:`${pct(s.cuti,s.total)}% dari total`, color:'#3b82f6' },
    { label:'Libur', value:s.libur, sub:'hari libur', color:'#9ca3af' },
    { label:'Off', value:s.off, sub:'hari off', color:'#6b7280' },
  ]
})
function pct(v,t){ if(!t) return 0; return Math.round(v/t*100) }
function barPct(v){
  const t = kehadiran.value?.summary?.total || 1
  if(!t) return 0
  return Math.round(v/t*100)
}
const maxDaily = computed(()=>{
  if(!kehadiran.value?.daily_trend) return 1
  return Math.max(1, ...kehadiran.value.daily_trend.map(d=>d.total))
})
const maxMonthly = computed(()=>{
  if(!kehadiran.value?.monthly_trend) return 1
  return Math.max(1, ...kehadiran.value.monthly_trend.map(m=>m.total))
})

// payroll helpers
const payMax = computed(()=>{
  if(!payroll.value?.chart?.length) return 1
  return Math.max(1, ...payroll.value.chart.map(p=>p.total_kotor))
})
const PLOT_H = 130
function payBarPx(val){
  if(!val || payMax.value<=1) return '4px'
  return Math.max(4, Math.round(val/payMax.value * PLOT_H)) + 'px'
}
function payBreakPct(v){
  const total = payroll.value?.breakdown?.reduce((s,b)=>s+b.value,0) || 1
  if(!total) return 0
  return Math.round(v/total*100)
}
const topPotongan = computed(()=>{
  if(!payroll.value?.breakdown) return {label:'-', value:0}
  const sorted = [...payroll.value.breakdown].sort((a,b)=>b.value-a.value)
  return sorted[0] || {label:'-', value:0}
})

// ===== Komposisi Gaji per Jabatan (donut) =====
const PIE_R = 40
const PIE_STROKE = 18
const PIE_COLORS = ['#2563eb','#10b981','#f59e0b','#8b5cf6','#ef4444','#06b6d4','#ec4899','#84cc16','#f97316','#64748b','#14b8a6','#a855f7','#eab308','#3b82f6','#22c55e','#f43f5e','#6366f1','#0ea5e9','#d946ef','#a3e635','#fb7185','#a78bfa','#34d399','#fbbf24']

const komposisiMonths = computed(()=> komposisi.value?.months || [])
const komposisiTotal = computed(()=> komposisi.value?.total || 0)

const pieSegments = computed(()=>{
  if(!komposisi.value?.data?.length) return []
  const total = komposisi.value.total || 1
  const C = 2 * Math.PI * PIE_R
  let running = 0
  return komposisi.value.data.map((d, i)=>{
    const frac = total > 0 ? Math.max(0, d.gaji / total) : 0
    const len = frac * C
    const seg = {
      ...d,
      color: PIE_COLORS[i % PIE_COLORS.length],
      len,
      gap: Math.max(0, C - len),
      offset: -running,
    }
    running += len
    return seg
  })
})
</script>
