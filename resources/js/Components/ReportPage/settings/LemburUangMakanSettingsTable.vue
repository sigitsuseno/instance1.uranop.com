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
</template>

<script setup>
import { reactive, computed, watch } from 'vue';

const props = defineProps({
  config: { type: Object, default: () => ({}) },
  extraData: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['update:config']);

// Reactive copy of config excluding SPC-specific keys (they go flat with group rates)
const localRates = reactive({ ...props.config });

// Remove SPC config keys from rates (they're handled separately)
delete localRates.spc_start_period_id;
delete localRates.spc_base_salary;

// Expose only the group rate entries
const groupRates = computed(() => localRates);

const periods = computed(() => props.extraData?.periods || []);
const spcEmployees = computed(() => props.extraData?.spcEmployees || []);
const sortedPeriods = computed(() =>
  [...periods.value].sort((a, b) => b.start_date.localeCompare(a.start_date))
);

// Sync parent → local
watch(() => props.config, (val) => {
  Object.keys(localRates).forEach(k => delete localRates[k]);
  Object.assign(localRates, val || {});
  delete localRates.spc_start_period_id;
  delete localRates.spc_base_salary;
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

function emitConfig(extra = {}) {
  // Merge rates + SPC config + any extra
  const merged = {
    ...localRates,
    spc_start_period_id: props.config.spc_start_period_id ?? null,
    spc_base_salary: props.config.spc_base_salary ?? null,
    ...extra,
  };
  emit('update:config', { ...merged });
}
</script>
