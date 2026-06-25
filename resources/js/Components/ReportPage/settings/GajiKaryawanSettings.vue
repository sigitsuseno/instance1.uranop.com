<template>
  <div class="space-y-5">
    <p class="text-xs text-(--text-muted)">
      Atur pengelompokan karyawan ke Section A dan B untuk tampilan tabel Gaji Karyawan.
      Karyawan yang tidak termasuk di kedua section tidak akan ditampilkan.
    </p>

    <!-- Section Preview -->
    <div class="grid grid-cols-2 gap-4">
      <div class="bg-(--primary)/5 border border-(--primary)/20 rounded-lg p-3">
        <h4 class="text-sm font-bold text-(--primary) mb-2">A. KARYAWAN ALL IN</h4>
        <div class="flex flex-wrap gap-1.5">
          <span
            v-for="g in sectionA"
            :key="g"
            class="px-2 py-0.5 text-xs font-medium bg-(--primary)/10 text-(--primary) rounded"
          >{{ g }}</span>
          <span v-if="sectionA.length === 0" class="text-xs text-(--text-muted) italic">Belum ada grup</span>
        </div>
      </div>
      <div class="bg-(--success)/5 border border-(--success)/20 rounded-lg p-3">
        <h4 class="text-sm font-bold text-(--success) mb-2">B. KARYAWAN BULANAN PRINT</h4>
        <div class="flex flex-wrap gap-1.5">
          <span
            v-for="g in sectionB"
            :key="g"
            class="px-2 py-0.5 text-xs font-medium bg-(--success)/10 text-(--success) rounded"
          >{{ g }}</span>
          <span v-if="sectionB.length === 0" class="text-xs text-(--text-muted) italic">Belum ada grup</span>
        </div>
      </div>
    </div>

    <hr class="border-(--border-soft)" />

    <!-- Group Assignment -->
    <div>
      <h4 class="text-sm font-semibold mb-3 text-(--text-main)">Assign Grup ke Section</h4>
      <div class="overflow-hidden rounded-lg border border-(--border-soft)">
        <table class="w-full text-sm">
          <thead>
            <tr class="bg-(--bg-soft)">
              <th class="text-left px-4 py-2.5 font-semibold text-(--text-muted) border-b border-(--border-soft)">Grup</th>
              <th class="text-center px-4 py-2.5 font-semibold text-(--text-muted) border-b border-(--border-soft) w-36">Section</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="group in allGroups"
              :key="group"
              class="border-b border-(--border-soft) hover:bg-(--bg-hover) transition-colors"
            >
              <td class="px-4 py-2.5 font-medium text-(--text-main)">{{ group }}</td>
              <td class="px-4 py-2 text-center">
                <select
                  :value="getSection(group)"
                  @change="setSection(group, $event.target.value)"
                  class="px-3 py-1.5 rounded border border-(--border-soft) bg-(--bg-elevated) text-(--text-main) text-sm focus:outline-none focus:ring-1 focus:ring-(--primary)"
                >
                  <option value="A">Section A</option>
                  <option value="B">Section B</option>
                </select>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'

const props = defineProps({
  config: { type: Object, default: () => ({}) },
})

const emit = defineEmits(['update:config'])

const allGroups = ['GRP-ALLIN', 'GRP-SPR', 'GRP-GD', 'GRP-SS', 'GRP-PS1']

const localSections = ref({
  A: [...(props.config?.sections?.A || ['GRP-ALLIN', 'GRP-SPR'])],
  B: [...(props.config?.sections?.B || ['GRP-GD', 'GRP-SS', 'GRP-PS1'])],
})

const sectionA = computed(() => localSections.value.A)
const sectionB = computed(() => localSections.value.B)

// Sync parent → local
watch(() => props.config, (val) => {
  if (val?.sections) {
    localSections.value.A = [...(val.sections.A || [])]
    localSections.value.B = [...(val.sections.B || [])]
  }
}, { deep: true })

function getSection(group) {
  if (localSections.value.A.includes(group)) return 'A'
  if (localSections.value.B.includes(group)) return 'B'
  return 'A' // default
}

function setSection(group, section) {
  // Remove from both
  localSections.value.A = localSections.value.A.filter(g => g !== group)
  localSections.value.B = localSections.value.B.filter(g => g !== group)
  // Add to target
  localSections.value[section].push(group)

  emit('update:config', {
    sections: {
      A: [...localSections.value.A],
      B: [...localSections.value.B],
    },
  })
}
</script>
