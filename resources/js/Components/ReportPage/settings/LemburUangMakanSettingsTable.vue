<template>
  <div>
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
            v-for="(rates, groupName) in localRates"
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
  </div>
</template>

<script setup>
import { reactive, watch } from 'vue';

const props = defineProps({
  config: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['update:config']);

// Reactive copy
const localRates = reactive({ ...props.config });

// Sync parent → local
watch(() => props.config, (val) => {
  Object.keys(localRates).forEach(k => delete localRates[k]);
  Object.assign(localRates, val || {});
}, { deep: true });

function updateRate(groupName, key, event) {
  const value = parseInt(event.target.value) || 0;
  if (!localRates[groupName]) {
    localRates[groupName] = {};
  }
  localRates[groupName][key] = value;
  emit('update:config', { ...localRates });
}
</script>
