<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-xl font-semibold text-(--text-main)">Laporan Lembur & Uang Makan</h1>
        <p class="text-sm text-(--text-muted) mt-1">Rincian gaji & overtime — detail karyawan & resume per bagian</p>
      </div>
    </div>

    <!-- Tab Bar -->
    <div class="border-b border-(--border-soft) mb-6">
      <nav class="flex gap-0 -mb-px">
        <button
          :class="[
            'px-4 py-2.5 text-sm font-medium transition-colors border-b-2 rounded-t-md',
            activeTab === 'detail'
              ? 'text-(--primary) border-(--primary)'
              : 'text-(--text-muted) border-transparent hover:text-(--text-main) hover:border-(--border-soft)',
          ]"
          @click="activeTab = 'detail'"
        >
          Detail
        </button>
        <button
          :class="[
            'px-4 py-2.5 text-sm font-medium transition-colors border-b-2 rounded-t-md',
            activeTab === 'resume'
              ? 'text-(--primary) border-(--primary)'
              : 'text-(--text-muted) border-transparent hover:text-(--text-main) hover:border-(--border-soft)',
          ]"
          @click="activeTab = 'resume'"
        >
          Resume
        </button>
      </nav>
    </div>

    <!-- Group Filters -->
    <BaseCard class="mb-6">
      <div class="flex flex-wrap items-center gap-4">
        <span class="text-sm font-semibold text-(--text-muted) uppercase tracking-wider">Filter Grup:</span>
        <label
          v-for="group in availableGroups"
          :key="group.code"
          class="flex items-center gap-2 cursor-pointer group select-none"
        >
          <input
            type="checkbox"
            :value="group.code"
            v-model="selectedGroups"
            class="w-4 h-4 rounded text-(--primary) focus:ring-(--primary-glow) border-(--border-soft)"
          />
          <span class="text-sm font-medium text-(--text-main) group-hover:text-(--primary) transition-colors">
            {{ group.name }}
          </span>
        </label>
      </div>
    </BaseCard>

    <!-- Tab Content -->
    <TabDetail v-if="activeTab === 'detail'" :groups="selectedGroups" />
    <TabResume v-if="activeTab === 'resume'" :groups="selectedGroups" />
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useApi } from '../../../../composables/useApi'
import BaseCard from '../../../../Components/BaseCard.vue'
import TabDetail from './TabDetail.vue'
import TabResume from './TabResume.vue'

const { get } = useApi()

const activeTab = ref('detail')
const selectedGroups = ref([])
const availableGroups = ref([])

onMounted(async () => {
  try {
    const res = await get('/api/v1/settings/employee-data/groups')
    const allGroups = res.data || []
    availableGroups.value = allGroups
      .filter(g => g.group_label === 'Imported Shift/Group')
      .map(g => ({ code: g.code, name: g.name }))

    // Default: PS1 checked
    selectedGroups.value = availableGroups.value
      .filter(g => g.code === 'GRP-PS1')
      .map(g => g.code)
  } catch (err) {
    console.error('Gagal fetch groups:', err)
  }
})
</script>
