<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-xl font-semibold text-(--text-main)">Laporan Payroll</h1>
        <p class="text-sm text-(--text-muted) mt-1">Laporan Payroll Karyawan beserta Summary/Resume dan Daftar Transfer Bank</p>
      </div>
    </div>

    <!-- Tab Bar -->
    <div class="border-b border-(--border-soft) mb-6">
      <nav class="flex gap-0 -mb-px">
        <button
          :class="[
            'px-4 py-2.5 text-sm font-medium transition-colors border-b-2 rounded-t-md',
            activeTab === 'payroll'
              ? 'text-(--primary) border-(--primary)'
              : 'text-(--text-muted) border-transparent hover:text-(--text-main) hover:border-(--border-soft)',
          ]"
          @click="activeTab = 'payroll'"
        >
          Payroll
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
        <button
          :class="[
            'px-4 py-2.5 text-sm font-medium transition-colors border-b-2 rounded-t-md',
            activeTab === 'kirim-all'
              ? 'text-(--primary) border-(--primary)'
              : 'text-(--text-muted) border-transparent hover:text-(--text-main) hover:border-(--border-soft)',
          ]"
          @click="activeTab = 'kirim-all'"
        >
          Kirim ALL
        </button>
        <button
          :class="[
            'px-4 py-2.5 text-sm font-medium transition-colors border-b-2 rounded-t-md',
            activeTab === 'kirim-all-in'
              ? 'text-(--primary) border-(--primary)'
              : 'text-(--text-muted) border-transparent hover:text-(--text-main) hover:border-(--border-soft)',
          ]"
          @click="activeTab = 'kirim-all-in'"
        >
          Kirim All In
        </button>
        <button
          :class="[
            'px-4 py-2.5 text-sm font-medium transition-colors border-b-2 rounded-t-md',
            activeTab === 'kirim-print'
              ? 'text-(--primary) border-(--primary)'
              : 'text-(--text-muted) border-transparent hover:text-(--text-main) hover:border-(--border-soft)',
          ]"
          @click="activeTab = 'kirim-print'"
        >
          Kirim Print
        </button>
        <!-- <button
          :class="[
            'px-4 py-2.5 text-sm font-medium transition-colors border-b-2 rounded-t-md',
            activeTab === 'kirim-all'
              ? 'text-(--primary) border-(--primary)'
              : 'text-(--text-muted) border-transparent hover:text-(--text-main) hover:border-(--border-soft)',
          ]"
          @click="activeTab = 'kirim-all'"
        >
          Kirim ALL
        </button> -->
      </nav>
    </div>

    <!-- Tab Content -->
    <TabPayroll v-if="activeTab === 'payroll'" />
    <TabResume v-if="activeTab === 'resume'" />
    <TabKirimAll v-if="activeTab === 'kirim-all'" />
    <TabKirimBank v-if="activeTab === 'kirim-all-in'" group="all-in" />
    <TabKirimBank v-if="activeTab === 'kirim-print'" group="print" />
  </div>
</template>

<script setup>
import { ref } from 'vue'
import TabPayroll from './TabPayroll.vue'
import TabResume from './TabResume.vue'
import TabKirimBank from './TabKirimBank.vue'
import TabKirimAll from './TabKirimAll.vue'

const activeTab = ref('payroll')
</script>
