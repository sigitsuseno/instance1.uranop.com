<template>
  <div>
    <!-- Rates table -->
    <p class="text-xs text-(--text-muted) mb-3">
      Atur nominal uang makan per group. Nilai ini digunakan untuk perhitungan laporan.
    </p>

    <div class="overflow-x-auto rounded-lg border border-(--border-soft)">
      <table class="w-full text-sm">
        <thead>
          <tr class="bg-(--bg-soft)">
            <th class="text-left px-3 py-2.5 font-semibold text-(--text-muted) border-b border-(--border-soft)">Group</th>
            <th class="text-right px-3 py-2.5 font-semibold text-(--text-muted) border-b border-(--border-soft) w-28">Weekday</th>
            <th class="text-right px-3 py-2.5 font-semibold text-(--text-muted) border-b border-(--border-soft) w-28">Sabtu 2j</th>
            <th class="text-right px-3 py-2.5 font-semibold text-(--text-muted) border-b border-(--border-soft) w-28">Sabtu Full</th>
            <th class="text-right px-3 py-2.5 font-semibold text-(--text-muted) border-b border-(--border-soft) w-28">Minggu Half</th>
            <th class="text-right px-3 py-2.5 font-semibold text-(--text-muted) border-b border-(--border-soft) w-28">Minggu Full</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="(rates, groupName) in groupRates"
            :key="groupName"
            class="border-b border-(--border-soft) hover:bg-(--bg-hover) transition-colors"
          >
            <td class="px-3 py-2 font-medium text-(--text-main)">{{ groupName }}</td>
            <td class="px-1 py-1">
              <input
                type="number"
                :value="rates.weekday"
                @input="updateRate(groupName, 'weekday', $event)"
                class="w-full text-right px-2 py-1.5 rounded border border-(--border-soft) bg-(--bg-elevated) text-(--text-main) text-sm focus:outline-none focus:ring-1 focus:ring-(--primary) focus:border-(--primary)"
                min="0"
              />
            </td>
            <td class="px-1 py-1">
              <input
                type="number"
                :value="rates.sabtu_dua"
                @input="updateRate(groupName, 'sabtu_dua', $event)"
                class="w-full text-right px-2 py-1.5 rounded border border-(--border-soft) bg-(--bg-elevated) text-(--text-main) text-sm focus:outline-none focus:ring-1 focus:ring-(--primary) focus:border-(--primary)"
                min="0"
              />
            </td>
            <td class="px-1 py-1">
              <input
                type="number"
                :value="rates.sabtu_full"
                @input="updateRate(groupName, 'sabtu_full', $event)"
                class="w-full text-right px-2 py-1.5 rounded border border-(--border-soft) bg-(--bg-elevated) text-(--text-main) text-sm focus:outline-none focus:ring-1 focus:ring-(--primary) focus:border-(--primary)"
                min="0"
              />
            </td>
            <td class="px-1 py-1">
              <input
                type="number"
                :value="rates.minggu_half"
                @input="updateRate(groupName, 'minggu_half', $event)"
                class="w-full text-right px-2 py-1.5 rounded border border-(--border-soft) bg-(--bg-elevated) text-(--text-main) text-sm focus:outline-none focus:ring-1 focus:ring-(--primary) focus:border-(--primary)"
                min="0"
              />
            </td>
            <td class="px-1 py-1">
              <input
                type="number"
                :value="rates.minggu_full"
                @input="updateRate(groupName, 'minggu_full', $event)"
                class="w-full text-right px-2 py-1.5 rounded border border-(--border-soft) bg-(--bg-elevated) text-(--text-main) text-sm focus:outline-none focus:ring-1 focus:ring-(--primary) focus:border-(--primary)"
                min="0"
              />
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <p class="text-xs text-(--text-muted) mt-2">
      💡 <strong>Weekday:</strong> nominal tetap per hari lembur (min. 2 jam).<br />
      💡 <strong>Sabtu/Minggu:</strong> nominal berdasarkan durasi lembur (2j/Full atau 4j/Half-8j/Full).
    </p>

    <!-- JKT Settings -->
    <hr class="border-(--border-soft) my-5" />

    <div>
      <h4 class="text-sm font-semibold mb-3 text-(--text-main)">
        🏢 A. KARYAWAN JAKARTA (GRP-JKT)
      </h4>

      <div class="mb-4">
        <label class="block text-xs font-medium text-(--text-muted) mb-1.5">
          Pengecualian Uang Lembur
        </label>
        <p class="text-xs text-(--text-muted) mb-2">
          Pilih karyawan yang <strong>tidak</strong> mendapatkan uang lembur.
        </p>
        <div class="border border-(--border-soft) rounded-lg p-3 max-h-48 overflow-y-auto bg-(--bg-soft)">
          <div v-if="jktEmployees.length === 0" class="text-xs text-(--text-muted) italic">
            Tidak ada karyawan dengan group GRP-JKT.
          </div>
          <div v-else class="space-y-2">
            <label
              v-for="emp in jktEmployees"
              :key="emp.id"
              class="flex items-center space-x-2 cursor-pointer"
            >
              <input
                type="checkbox"
                :value="emp.id"
                :checked="(config.jkt_no_overtime_employees || []).includes(emp.id)"
                @change="toggleJktNoOvertime(emp.id, $event.target.checked)"
                class="rounded border-(--border-soft) text-(--primary) focus:ring-(--primary)"
              />
              <span class="text-sm text-(--text-main)">{{ emp.name }} <span class="text-xs text-(--text-muted)">({{ emp.nip }})</span></span>
            </label>
          </div>
        </div>
      </div>
    </div>

    <!-- ALL IN Settings -->
    <hr class="border-(--border-soft) my-5" />

    <div>
      <h4 class="text-sm font-semibold mb-3 text-(--text-main)">
        🏢 B. KARYAWAN ALL IN (GRP-ALLIN/GRP-GD/GRP-SPR)
      </h4>

      <div class="mb-4">
        <label class="block text-xs font-medium text-(--text-muted) mb-1.5">
          Pengecualian Uang Lembur
        </label>
        <p class="text-xs text-(--text-muted) mb-2">
          Pilih karyawan yang <strong>tidak</strong> mendapatkan uang lembur.
        </p>
        <div class="border border-(--border-soft) rounded-lg p-3 max-h-48 overflow-y-auto bg-(--bg-soft)">
          <div v-if="allinEmployees.length === 0" class="text-xs text-(--text-muted) italic">
            Tidak ada karyawan dengan group ALL IN.
          </div>
          <div v-else class="space-y-2">
            <label
              v-for="emp in allinEmployees"
              :key="emp.id"
              class="flex items-center space-x-2 cursor-pointer"
            >
              <input
                type="checkbox"
                :value="emp.id"
                :checked="(config.allin_no_overtime_employees || []).includes(emp.id)"
                @change="toggleAllinNoOvertime(emp.id, $event.target.checked)"
                class="rounded border-(--border-soft) text-(--primary) focus:ring-(--primary)"
              />
              <span class="text-sm text-(--text-main)">{{ emp.name }} <span class="text-xs text-(--text-muted)">({{ emp.nip }})</span></span>
            </label>
          </div>
        </div>
      </div>

      <!-- Driver Overtime Manual -->
      <div class="mb-4">
        <label class="block text-xs font-medium text-(--text-muted) mb-1.5">
          Uang Lembur Manual Driver
        </label>
        <p class="text-xs text-(--text-muted) mb-2">
          Input nominal uang lembur tambahan untuk driver (di luar uang makan standar).
        </p>
        <div class="border border-(--border-soft) rounded-lg overflow-hidden">
          <div v-if="allinDrivers.length === 0" class="text-xs text-(--text-muted) italic p-3">
            Tidak ada driver di group ALL IN.
          </div>
          <table v-else class="w-full text-xs">
            <thead>
              <tr class="bg-(--bg-soft)">
                <th class="text-left px-2 py-1.5 font-medium text-(--text-muted) border-b border-(--border-soft)">Nama</th>
                <th class="text-right px-2 py-1.5 font-medium text-(--text-muted) border-b border-(--border-soft) w-36">Uang Lembur (Rp)</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="driver in allinDrivers"
                :key="driver.id"
                class="border-b border-(--border-soft)"
              >
                <td class="px-2 py-1 text-(--text-main)">{{ driver.name }} <span class="text-(--text-muted)">({{ driver.nip }})</span></td>
                <td class="px-1 py-0.5">
                  <input
                    type="number"
                    :value="(config.allin_driver_overtime || {})[driver.id] ?? ''"
                    @input="updateDriverOvertime(driver.id, $event.target.value)"
                    class="w-full text-right px-2 py-1 rounded border border-(--border-soft) bg-(--bg-elevated) text-(--text-main) text-xs focus:outline-none focus:ring-1 focus:ring-(--primary) focus:border-(--primary)"
                    min="0"
                    placeholder="0"
                  />
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- TKN Settings -->
    <hr class="border-(--border-soft) my-5" />

    <div>
      <h4 class="text-sm font-semibold mb-3 text-(--text-main)">
        🔧 C. KARYAWAN TEKNISI (KRY-TKN)
      </h4>
      <p class="text-xs text-(--text-muted) mb-3">
        Formula uang makan khusus teknisi. BUKAN section terpisah — karyawan tetap di section GRP aslinya (Jakarta / ALL IN).
      </p>

      <!-- TKN: Weekday Flat -->
      <div class="mb-4">
        <label class="block text-xs font-medium text-(--text-muted) mb-1.5">
          Nominal Flat Weekday (Rp)
        </label>
        <input
          type="number"
          :value="config.tkn_weekday_flat ?? 15000"
          @input="emitConfig({ tkn_weekday_flat: $event.target.value === '' ? null : parseInt($event.target.value) })"
          class="w-full max-w-xs px-3 py-2 rounded-lg border border-(--border-soft) bg-(--bg-elevated) text-(--text-main) text-sm focus:outline-none focus:ring-1 focus:ring-(--primary) focus:border-(--primary)"
          min="0"
        />
        <p class="text-xs text-(--text-muted) mt-1">
          Diberikan jika total jam lembur ≥ 11 jam di hari weekday.
        </p>
      </div>

      <!-- TKN: Saturday Rate -->
      <div class="mb-4">
        <label class="block text-xs font-medium text-(--text-muted) mb-1.5">
          Rate Sabtu (per 7 jam) — Rp
        </label>
        <input
          type="number"
          :value="config.tkn_saturday_rate ?? 100000"
          @input="emitConfig({ tkn_saturday_rate: $event.target.value === '' ? null : parseInt($event.target.value) })"
          class="w-full max-w-xs px-3 py-2 rounded-lg border border-(--border-soft) bg-(--bg-elevated) text-(--text-main) text-sm focus:outline-none focus:ring-1 focus:ring-(--primary) focus:border-(--primary)"
          min="0"
        />
        <p class="text-xs text-(--text-muted) mt-1">
          Formula: <code>lemburTotal × (Rate ÷ 7)</code>. Default 100.000 → ≈14.286/jam.
        </p>
      </div>

      <!-- TKN: Sunday/Holiday Rate -->
      <div class="mb-4">
        <label class="block text-xs font-medium text-(--text-muted) mb-1.5">
          Rate Minggu / Holiday (per 7 jam) — Rp
        </label>
        <input
          type="number"
          :value="config.tkn_holiday_rate ?? 200000"
          @input="emitConfig({ tkn_holiday_rate: $event.target.value === '' ? null : parseInt($event.target.value) })"
          class="w-full max-w-xs px-3 py-2 rounded-lg border border-(--border-soft) bg-(--bg-elevated) text-(--text-main) text-sm focus:outline-none focus:ring-1 focus:ring-(--primary) focus:border-(--primary)"
          min="0"
        />
        <p class="text-xs text-(--text-muted) mt-1">
          Formula: <code>lemburTotal × (Rate ÷ 7)</code>. Default 200.000 → ≈28.571/jam.
        </p>
      </div>

      <!-- TKN: Employee List -->
      <div>
        <p class="text-xs font-medium text-(--text-muted) mb-2">
          👥 Karyawan KRY-TKN ({{ tknEmployees.length }} orang)
        </p>
        <div v-if="tknEmployees.length === 0" class="text-xs text-(--text-muted) italic">
          Tidak ada karyawan dengan group KRY-TKN.
        </div>
        <div v-else class="overflow-x-auto rounded-lg border border-(--border-soft) max-h-48 overflow-y-auto">
          <table class="w-full text-xs">
            <thead>
              <tr class="bg-(--bg-soft) sticky top-0">
                <th class="text-left px-2 py-1.5 font-medium text-(--text-muted) border-b border-(--border-soft)">NIP</th>
                <th class="text-left px-2 py-1.5 font-medium text-(--text-muted) border-b border-(--border-soft)">Nama</th>
                <th class="text-left px-2 py-1.5 font-medium text-(--text-muted) border-b border-(--border-soft)">Jabatan</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="emp in tknEmployees"
                :key="emp.id"
                class="border-b border-(--border-soft)"
              >
                <td class="px-2 py-1 text-(--text-main)">{{ emp.nip }}</td>
                <td class="px-2 py-1 text-(--text-main)">{{ emp.name }}</td>
                <td class="px-2 py-1 text-(--text-muted)">{{ emp.jabatan }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- SPC Settings -->
    <hr class="border-(--border-soft) my-5" />

    <div>
      <h4 class="text-sm font-semibold mb-3 text-(--text-main)">
        ⚙️ D. KARYAWAN SPESIFIK (KRY-SPC)
      </h4>

      <!-- SPC: Periode Mulai -->
      <div class="mb-4">
        <label class="block text-xs font-medium text-(--text-muted) mb-1.5">
          Mulai Periode
        </label>
        <select
          :value="config.spc_start_period_id ?? ''"
          @change="updateSpcConfig('spc_start_period_id', $event.target.value === '' ? null : parseInt($event.target.value))"
          class="w-full max-w-xs px-3 py-2 rounded-lg border border-(--border-soft) bg-(--bg-elevated) text-(--text-main) text-sm focus:outline-none focus:ring-1 focus:ring-(--primary) focus:border-(--primary)"
        >
          <option value="">-- Semua Periode --</option>
          <option
            v-for="p in sortedPeriods"
            :key="p.id"
            :value="p.id"
          >
            {{ p.name }} ({{ p.start_date }} - {{ p.end_date }})
          </option>
        </select>
        <p class="text-xs text-(--text-muted) mt-1">
          Section D hanya muncul mulai periode yang dipilih. Kosongkan untuk tampil di semua periode.
        </p>
      </div>

      <!-- SPC: Base Salary -->
      <div class="mb-4">
        <label class="block text-xs font-medium text-(--text-muted) mb-1.5">
          Base Salary (Rp)
        </label>
        <input
          type="number"
          :value="config.spc_base_salary ?? ''"
          @input="updateSpcConfig('spc_base_salary', $event.target.value === '' ? null : parseInt($event.target.value))"
          class="w-full max-w-xs px-3 py-2 rounded-lg border border-(--border-soft) bg-(--bg-elevated) text-(--text-main) text-sm focus:outline-none focus:ring-1 focus:ring-(--primary) focus:border-(--primary)"
          min="0"
          placeholder="Kosong = pakai gaji pokok"
        />
        <p class="text-xs text-(--text-muted) mt-1">
          Digunakan untuk hitungan: <code>Base Salary / 173 = hourly rate</code>. Kosongkan untuk fallback ke gaji pokok / 173.
        </p>
      </div>

      <!-- SPC: Employee List -->
      <div>
        <p class="text-xs font-medium text-(--text-muted) mb-2">
          👥 Karyawan KRY-SPC ({{ spcEmployees.length }} orang)
        </p>
        <div v-if="spcEmployees.length === 0" class="text-xs text-(--text-muted) italic">
          Tidak ada karyawan dengan group KRY-SPC.
        </div>
        <div v-else class="overflow-x-auto rounded-lg border border-(--border-soft) max-h-48 overflow-y-auto">
          <table class="w-full text-xs">
            <thead>
              <tr class="bg-(--bg-soft) sticky top-0">
                <th class="text-left px-2 py-1.5 font-medium text-(--text-muted) border-b border-(--border-soft)">NIP</th>
                <th class="text-left px-2 py-1.5 font-medium text-(--text-muted) border-b border-(--border-soft)">Nama</th>
                <th class="text-left px-2 py-1.5 font-medium text-(--text-muted) border-b border-(--border-soft)">Jabatan</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="emp in spcEmployees"
                :key="emp.id"
                class="border-b border-(--border-soft)"
              >
                <td class="px-2 py-1 text-(--text-main)">{{ emp.nip }}</td>
                <td class="px-2 py-1 text-(--text-main)">{{ emp.name }}</td>
                <td class="px-2 py-1 text-(--text-muted)">{{ emp.jabatan }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- E. DATA INSENTIF (EMPLOYEE RESERVES) -->
  <hr class="border-(--border-soft) my-5" />

  <div>
    <h4 class="text-sm font-semibold mb-3 text-(--text-main)">
      💰 E. DATA INSENTIF (EMPLOYEE RESERVES)
    </h4>
    <p class="text-xs text-(--text-muted) mb-3">
      Atur data insentif / bonus untuk sopir atau karyawan lain per periode payroll.
    </p>

    <!-- Period Selector -->
    <div class="mb-4">
      <label class="block text-xs font-medium text-(--text-muted) mb-1.5">
        Pilih Periode Payroll
      </label>
      <div class="flex items-center gap-2">
        <select
          v-model="selectedReservePeriod"
          class="w-full max-w-sm px-3 py-2 rounded-lg border border-(--border-soft) bg-(--bg-elevated) text-(--text-main) text-sm focus:outline-none focus:ring-1 focus:ring-(--primary) focus:border-(--primary)"
        >
          <option value="">-- Pilih Periode --</option>
          <option
            v-for="p in sortedPeriods"
            :key="p.id"
            :value="p.id"
          >
            {{ p.name }} ({{ p.start_date }} - {{ p.end_date }})
          </option>
        </select>
        <button
          @click="loadReserves"
          class="px-3 py-2 text-sm rounded-lg border border-(--border-soft) hover:bg-(--bg-hover) transition-colors flex items-center gap-1"
        >
          <i class="bx bx-refresh"></i> Muat
        </button>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loadingReserves" class="text-center py-4 text-(--text-muted) text-sm">
      <i class="bx bx-loader-alt animate-spin"></i> Memuat data...
    </div>

    <!-- Reserve Table -->
    <template v-if="!loadingReserves && selectedReservePeriod">
      <div v-if="reserves.length === 0" class="text-xs text-(--text-muted) italic py-3">
        Belum ada data insentif untuk periode ini.
      </div>

      <div v-else class="overflow-x-auto rounded-lg border border-(--border-soft)">
        <table class="w-full text-xs">
          <thead>
            <tr class="bg-(--bg-soft)">
              <th class="text-left px-2 py-1.5 font-medium text-(--text-muted) border-b border-(--border-soft)">NIP</th>
              <th class="text-left px-2 py-1.5 font-medium text-(--text-muted) border-b border-(--border-soft)">Nama</th>
              <th class="text-left px-2 py-1.5 font-medium text-(--text-muted) border-b border-(--border-soft)">Komponen</th>
              <th class="text-right px-2 py-1.5 font-medium text-(--text-muted) border-b border-(--border-soft)">Total</th>
              <th class="text-center px-2 py-1.5 font-medium text-(--text-muted) border-b border-(--border-soft) w-20">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="item in reserves"
              :key="item.id"
              class="border-b border-(--border-soft) hover:bg-(--bg-hover)"
            >
              <td class="px-2 py-1.5 text-(--text-main)">{{ item.employee?.nip || '-' }}</td>
              <td class="px-2 py-1.5 text-(--text-main)">{{ item.employee?.name || '-' }}</td>
              <td class="px-2 py-1.5 text-(--text-muted)">
                <span v-if="item.komponen?.length">
                  {{ item.komponen.map(k => k.nama).join(', ') }}
                </span>
                <span v-else class="italic">-</span>
              </td>
              <td class="px-2 py-1.5 text-right font-medium text-(--text-main)">
                {{ formatRupiah(item.komponen?.reduce((sum, k) => sum + (Number(k.nilai) || 0), 0) || 0) }}
              </td>
              <td class="px-2 py-1.5 text-center">
                <div class="flex items-center justify-center gap-1">
                  <button
                    @click="editReserve(item)"
                    class="p-1 rounded hover:bg-blue-100 dark:hover:bg-blue-900/30 text-blue-600 transition-colors"
                    title="Edit"
                  >
                    <i class="bx bx-edit-alt"></i>
                  </button>
                  <button
                    @click="deleteReserve(item)"
                    class="p-1 rounded hover:bg-red-100 dark:hover:bg-red-900/30 text-red-500 transition-colors"
                    title="Hapus"
                  >
                    <i class="bx bx-trash"></i>
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Tombol Tambah -->
      <button
        @click="openReserveForm()"
        class="mt-3 px-3 py-1.5 text-xs rounded-lg border border-dashed border-(--border-soft) text-(--text-muted) hover:text-(--primary) hover:border-(--primary) transition-colors flex items-center gap-1"
      >
        <i class="bx bx-plus"></i> Tambah Data Insentif
      </button>
    </template>

    <!-- Form Tambah/Edit -->
    <div v-if="showReserveForm && selectedReservePeriod" class="mt-4 border border-(--border-soft) rounded-lg p-4 bg-(--bg-soft)">
      <h5 class="text-xs font-semibold mb-3 text-(--text-main)">
        {{ editingReserve ? 'Edit Data Insentif' : 'Tambah Data Insentif' }}
      </h5>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-3">
        <div>
          <label class="block text-xs font-medium text-(--text-muted) mb-1">Karyawan</label>
          <select
            v-model="reserveForm.employee_id"
            class="w-full px-2 py-1.5 rounded border border-(--border-soft) bg-(--bg-elevated) text-(--text-main) text-xs focus:outline-none focus:ring-1 focus:ring-(--primary)"
            :disabled="!!editingReserve"
          >
            <option value="">-- Pilih Karyawan --</option>
            <option
              v-for="emp in allEmployees"
              :key="emp.id"
              :value="emp.id"
            >
              {{ emp.name }} ({{ emp.nip }})
            </option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-medium text-(--text-muted) mb-1">Periode</label>
          <input
            :value="selectedPeriodName"
            class="w-full px-2 py-1.5 rounded border border-(--border-soft) bg-(--bg-soft) text-(--text-muted) text-xs cursor-not-allowed"
            disabled
          />
        </div>
      </div>

      <!-- Komponen Items -->
      <div>
        <label class="block text-xs font-medium text-(--text-muted) mb-1.5">
          Komponen Insentif
        </label>
        <div class="space-y-2">
          <div
            v-for="(komp, idx) in reserveForm.komponen"
            :key="idx"
            class="flex items-start gap-2 bg-(--bg-elevated) p-2 rounded border border-(--border-soft)"
          >
            <div class="flex-1">
              <input
                v-model="komp.nama"
                placeholder="Nama komponen (mis: Insentif Sopir)"
                class="w-full px-2 py-1 rounded border border-(--border-soft) bg-(--bg-elevated) text-(--text-main) text-xs focus:outline-none focus:ring-1 focus:ring-(--primary) mb-1"
              />
              <div class="flex gap-2">
                <input
                  v-model.number="komp.nilai"
                  type="number"
                  placeholder="Nilai (Rp)"
                  class="flex-1 px-2 py-1 rounded border border-(--border-soft) bg-(--bg-elevated) text-(--text-main) text-xs focus:outline-none focus:ring-1 focus:ring-(--primary)"
                  min="0"
                />
                <input
                  v-model="komp.keterangan"
                  placeholder="Ket (opsional)"
                  class="flex-1 px-2 py-1 rounded border border-(--border-soft) bg-(--bg-elevated) text-(--text-main) text-xs focus:outline-none focus:ring-1 focus:ring-(--primary)"
                />
              </div>
            </div>
            <button
              @click="removeKomponen(idx)"
              class="p-1 rounded hover:bg-red-100 dark:hover:bg-red-900/30 text-red-400 transition-colors mt-1"
              title="Hapus komponen"
            >
              <i class="bx bx-x"></i>
            </button>
          </div>
        </div>
        <button
          @click="addKomponen"
          class="mt-2 px-2 py-1 text-xs rounded border border-dashed border-(--border-soft) text-(--text-muted) hover:text-(--primary) hover:border-(--primary) transition-colors"
        >
          + Tambah Komponen
        </button>
      </div>

      <!-- Form Actions -->
      <div class="flex items-center gap-2 mt-4 pt-3 border-t border-(--border-soft)">
        <button
          @click="saveReserve"
          class="px-3 py-1.5 text-xs rounded-lg bg-blue-600 text-white hover:bg-blue-700 disabled:opacity-50 transition-colors flex items-center gap-1"
          :disabled="savingReserve || !reserveForm.employee_id || reserveForm.komponen.length === 0"
        >
          <i v-if="savingReserve" class="bx bx-loader-alt animate-spin"></i>
          {{ savingReserve ? 'Menyimpan...' : '💾 Simpan' }}
        </button>
        <button
          @click="cancelReserveForm"
          class="px-3 py-1.5 text-xs rounded-lg border border-(--border-soft) hover:bg-(--bg-hover) transition-colors"
        >
          Batal
        </button>
      </div>
    </div>
  </div>
  </template>

<script setup>
import { reactive, computed, watch, ref } from 'vue';
import { useApi } from '@/composables/useApi';

const props = defineProps({
  config: { type: Object, default: () => ({}) },
  extraData: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['update:config']);

// Reactive copy of config excluding SPC-specific keys (they go flat with group rates)
const localRates = reactive({ ...props.config });

// Remove SPC, JKT, ALLIN, and TKN config keys from rates (they're handled separately)
delete localRates.spc_start_period_id;
delete localRates.spc_base_salary;
delete localRates.jkt_no_overtime_employees;
delete localRates.allin_no_overtime_employees;
delete localRates.allin_driver_overtime;
delete localRates.tkn_weekday_flat;
delete localRates.tkn_saturday_rate;
delete localRates.tkn_holiday_rate;

// Expose only the group rate entries
const groupRates = computed(() => localRates);

const { get, post, put, del } = useApi();

// ─── Employee Reserves ───

const reserves = ref([]);
const selectedReservePeriod = ref('');
const loadingReserves = ref(false);
const showReserveForm = ref(false);
const editingReserve = ref(null);
const savingReserve = ref(false);
const reserveForm = reactive({
  employee_id: '',
  komponen: [{ nama: '', nilai: 0, keterangan: '' }],
});

const allEmployees = computed(() => {
  const all = [
    ...(props.extraData?.spcEmployees || []),
    ...(props.extraData?.jktEmployees || []),
    ...(props.extraData?.allinEmployees || []),
    ...(props.extraData?.tknEmployees || []),
  ];
  const seen = new Set();
  return all.filter(e => {
    if (seen.has(e.id)) return false;
    seen.add(e.id);
    return true;
  });
});

const selectedPeriodName = computed(() => {
  const periods = props.extraData?.periods || [];
  const p = periods.find(p => p.id === selectedReservePeriod.value);
  return p ? `${p.name} (${p.start_date} - ${p.end_date})` : '-';
});

async function loadReserves() {
  if (!selectedReservePeriod.value) return;
  loadingReserves.value = true;
  try {
    const res = await get(`/api/v1/settings/employee-reserves?pay_periode_id=${selectedReservePeriod.value}`);
    reserves.value = res.data || [];
  } catch (err) {
    console.error('Gagal muat data insentif:', err);
    reserves.value = [];
  } finally {
    loadingReserves.value = false;
  }
}

function openReserveForm() {
  editingReserve.value = null;
  reserveForm.employee_id = '';
  reserveForm.komponen = [{ nama: '', nilai: 0, keterangan: '' }];
  showReserveForm.value = true;
}

function editReserve(item) {
  editingReserve.value = item;
  reserveForm.employee_id = item.employee_id;
  reserveForm.komponen = (item.komponen || []).map(k => ({
    nama: k.nama,
    nilai: k.nilai,
    keterangan: k.keterangan || '',
  }));
  showReserveForm.value = true;
}

function addKomponen() {
  reserveForm.komponen.push({ nama: '', nilai: 0, keterangan: '' });
}

function removeKomponen(idx) {
  reserveForm.komponen.splice(idx, 1);
}

function cancelReserveForm() {
  showReserveForm.value = false;
  editingReserve.value = null;
}

async function saveReserve() {
  if (!reserveForm.employee_id || reserveForm.komponen.length === 0) return;
  savingReserve.value = true;
  try {
    const payload = {
      pay_periode_id: Number(selectedReservePeriod.value),
      employee_id: reserveForm.employee_id,
      komponen: reserveForm.komponen.map(k => ({
        nama: k.nama,
        nilai: Number(k.nilai) || 0,
        keterangan: k.keterangan || '',
      })),
    };

    if (editingReserve.value) {
      await put(`/api/v1/settings/employee-reserves/${editingReserve.value.id}`, payload);
    } else {
      await post('/api/v1/settings/employee-reserves', payload);
    }

    showReserveForm.value = false;
    editingReserve.value = null;
    await loadReserves();
  } catch (err) {
    console.error('Gagal simpan data insentif:', err);
    if (err.response?.data?.message) {
      alert(err.response.data.message);
    } else {
      alert('Gagal menyimpan data insentif. Silakan coba lagi.');
    }
  } finally {
    savingReserve.value = false;
  }
}

async function deleteReserve(item) {
  if (!confirm(`Yakin ingin menghapus data insentif ${item.employee?.name || ''}?`)) return;
  try {
    await del(`/api/v1/settings/employee-reserves/${item.id}`);
    await loadReserves();
  } catch (err) {
    console.error('Gagal hapus data insentif:', err);
    alert('Gagal menghapus data insentif.');
  }
}

function formatRupiah(value) {
  return new Intl.NumberFormat('id-ID').format(value);
}

const spcEmployees = computed(() => props.extraData?.spcEmployees || []);
const jktEmployees = computed(() => props.extraData?.jktEmployees || []);
const allinEmployees = computed(() => props.extraData?.allinEmployees || []);
const allinDrivers = computed(() =>
  allinEmployees.value.filter(emp =>
    (emp.jabatan || '').toUpperCase().includes('DRIVER')
  )
);
const tknEmployees = computed(() => props.extraData?.tknEmployees || []);
const sortedPeriods = computed(() => {
  const periods = props.extraData?.periods || [];
  return [...periods].sort((a, b) => b.start_date.localeCompare(a.start_date));
});

// Sync parent → local
watch(() => props.config, (val) => {
  Object.keys(localRates).forEach(k => delete localRates[k]);
  Object.assign(localRates, val || {});
  delete localRates.spc_start_period_id;
  delete localRates.spc_base_salary;
  delete localRates.jkt_no_overtime_employees;
  delete localRates.allin_no_overtime_employees;
  delete localRates.allin_driver_overtime;
  delete localRates.tkn_weekday_flat;
  delete localRates.tkn_saturday_rate;
  delete localRates.tkn_holiday_rate;
}, { deep: true });

function updateRate(groupName, key, event) {
  const value = parseInt(event.target.value) || 0;
  if (!localRates[groupName]) {
    localRates[groupName] = {};
  }
  localRates[groupName][key] = value;
  emitConfig();
}

function updateSpcConfig(key, value) {
  emitConfig({ [key]: value });
}

function toggleJktNoOvertime(empId, isChecked) {
  const currentList = Array.isArray(props.config.jkt_no_overtime_employees) 
    ? [...props.config.jkt_no_overtime_employees] 
    : [];
    
  if (isChecked) {
    if (!currentList.includes(empId)) currentList.push(empId);
  } else {
    const idx = currentList.indexOf(empId);
    if (idx > -1) currentList.splice(idx, 1);
  }
  
  emitConfig({ jkt_no_overtime_employees: currentList });
}

function toggleAllinNoOvertime(empId, isChecked) {
  const currentList = Array.isArray(props.config.allin_no_overtime_employees) 
    ? [...props.config.allin_no_overtime_employees] 
    : [];
    
  if (isChecked) {
    if (!currentList.includes(empId)) currentList.push(empId);
  } else {
    const idx = currentList.indexOf(empId);
    if (idx > -1) currentList.splice(idx, 1);
  }
  
  emitConfig({ allin_no_overtime_employees: currentList });
}

function updateDriverOvertime(empId, rawValue) {
  const currentMap = props.config.allin_driver_overtime 
    ? { ...props.config.allin_driver_overtime } 
    : {};
  
  const value = rawValue === '' || rawValue === null ? null : parseInt(rawValue);
  
  if (value === null || isNaN(value) || value <= 0) {
    delete currentMap[empId];
  } else {
    currentMap[empId] = value;
  }
  
  emitConfig({ allin_driver_overtime: currentMap });
}

function emitConfig(extra = {}) {
  // Merge rates + SPC config + TKN config + any extra
  const merged = {
    ...localRates,
    spc_start_period_id: props.config.spc_start_period_id ?? null,
    spc_base_salary: props.config.spc_base_salary ?? null,
    jkt_no_overtime_employees: props.config.jkt_no_overtime_employees ?? [],
    allin_no_overtime_employees: props.config.allin_no_overtime_employees ?? [],
    allin_driver_overtime: props.config.allin_driver_overtime ?? {},
    tkn_weekday_flat: props.config.tkn_weekday_flat ?? 15000,
    tkn_saturday_rate: props.config.tkn_saturday_rate ?? 100000,
    tkn_holiday_rate: props.config.tkn_holiday_rate ?? 200000,
    ...extra,
  };
  emit('update:config', { ...merged });
}
</script>
