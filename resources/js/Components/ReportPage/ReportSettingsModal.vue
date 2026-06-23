<template>
  <Teleport to="body">
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40" @click.self="$emit('close')">
      <div class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-hidden m-4 flex flex-col">

        <!-- Header -->
        <div class="flex items-center justify-between px-6 py-4 border-b border-(--border-soft) shrink-0">
          <div class="flex items-center gap-3">
            <i class="bx bx-cog text-2xl text-(--text-muted)"></i>
            <div>
              <h2 class="text-lg font-semibold text-(--text-main)">Pengaturan Laporan</h2>
              <p class="text-sm text-(--text-muted)">{{ reportLabel }}</p>
            </div>
          </div>
          <button @click="$emit('close')" class="p-1.5 rounded-lg hover:bg-(--bg-hover) transition-colors">
            <i class="bx bx-x text-xl text-(--text-muted)"></i>
          </button>
        </div>

        <!-- Body -->
        <div class="px-6 py-4 space-y-6 overflow-y-auto flex-1">

          <!-- Loading -->
          <div v-if="loading" class="text-center py-8 text-(--text-muted)">
            <div class="w-8 h-8 border-4 border-(--primary)/30 border-t-(--primary) rounded-full animate-spin mx-auto mb-3"></div>
            <p>Memuat pengaturan...</p>
          </div>

          <template v-else>
            <!-- Section 1: Group Karyawan -->
            <div>
              <h3 class="text-sm font-semibold mb-3 flex items-center gap-2 text-(--text-main)">
                <i class="bx bx-group text-lg"></i> Group Karyawan yang Ditampilkan
              </h3>
              <p class="text-xs text-(--text-muted) mb-3">
                Pilih group karyawan yang akan muncul di laporan ini.
              </p>

              <div class="flex flex-wrap gap-3">
                <label
                  v-for="group in availableGroups"
                  :key="group"
                  class="flex items-center gap-2 px-3 py-2 rounded-lg border cursor-pointer transition-colors select-none"
                  :class="selectedGroups.includes(group)
                    ? 'bg-blue-50 border-blue-300 dark:bg-blue-900/20 dark:border-blue-700'
                    : 'border-(--border-soft) hover:bg-(--bg-hover)'"
                >
                  <input
                    type="checkbox"
                    :value="group"
                    v-model="selectedGroups"
                    class="w-4 h-4 rounded text-(--primary) focus:ring-(--primary-glow) border-(--border-soft)"
                  />
                  <span class="text-sm font-medium text-(--text-main)">{{ group }}</span>
                </label>
              </div>

              <p v-if="selectedGroups.length === 0 && !loading" class="text-xs text-amber-600 mt-2 flex items-center gap-1">
                <i class="bx bx-error-circle"></i> Tidak ada group dipilih — laporan mungkin kosong.
              </p>
            </div>

            <hr class="border-(--border-soft)" />

            <!-- Section 2: Config Spesifik Laporan (SLOT) -->
            <div>
              <h3 class="text-sm font-semibold mb-3 flex items-center gap-2 text-(--text-main)">
                <i class="bx bx-slider text-lg"></i> Pengaturan Spesifik
              </h3>
              <slot name="config" :config="localConfig" :update-config="updateLocalConfig" :extra-data="extraData" />
            </div>
          </template>

        </div>

        <!-- Footer -->
        <div class="flex items-center justify-between px-6 py-4 border-t border-(--border-soft) bg-(--bg-soft) rounded-b-xl shrink-0">
          <div v-if="lastUpdated" class="text-xs text-(--text-muted)">
            Terakhir diubah: {{ lastUpdated }}
            <span v-if="lastUpdatedBy"> oleh {{ lastUpdatedBy }}</span>
          </div>
          <div v-else></div>

          <div class="flex items-center gap-2">
            <button
              @click="$emit('close')"
              class="px-4 py-2 text-sm rounded-lg border border-(--border-soft) hover:bg-(--bg-hover) transition-colors"
              :disabled="saving"
            >
              Batal
            </button>
            <button
              @click="save"
              class="px-4 py-2 text-sm rounded-lg bg-blue-600 text-white hover:bg-blue-700 disabled:opacity-50 transition-colors flex items-center gap-1.5"
              :disabled="saving"
            >
              <i v-if="saving" class="bx bx-loader-alt animate-spin"></i>
              {{ saving ? 'Menyimpan...' : '💾 Simpan' }}
            </button>
          </div>
        </div>

      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useApi } from '@/composables/useApi';

const { get, put } = useApi();

const props = defineProps({
  reportType: { type: String, required: true },
  reportLabel: { type: String, required: true },
  availableGroups: { type: Array, default: () => [] },
  extraData: { type: Object, default: () => ({}) }, // { periods, spcEmployees, ... }
});

const emit = defineEmits(['close', 'saved']);

const loading = ref(true);
const saving = ref(false);
const selectedGroups = ref([]);
const localConfig = ref({});
const lastUpdated = ref('');
const lastUpdatedBy = ref('');

onMounted(async () => {
  try {
    const res = await get(`/api/v1/settings/report-configs/${props.reportType}`);
    const data = res.data || res;
    selectedGroups.value = data.employee_groups || [];
    localConfig.value = data.config || {};
    lastUpdated.value = data.updated_at || '';
    lastUpdatedBy.value = data.updated_by || '';
  } catch (e) {
    console.error('Gagal memuat pengaturan:', e);
    // Fallback: pakai availableGroups sebagai default
    selectedGroups.value = [...props.availableGroups];
  } finally {
    loading.value = false;
  }
});

function updateLocalConfig(newConfig) {
  localConfig.value = { ...localConfig.value, ...newConfig };
}

async function save() {
  saving.value = true;
  try {
    const res = await put(`/api/v1/settings/report-configs/${props.reportType}`, {
      employee_groups: selectedGroups.value,
      config: localConfig.value,
    });
    lastUpdated.value = res.updated_at || new Date().toLocaleString('id-ID');
    lastUpdatedBy.value = res.updated_by || '';
    emit('saved', {
      employee_groups: selectedGroups.value,
      config: localConfig.value,
    });
  } catch (e) {
    console.error('Gagal menyimpan pengaturan:', e);
    alert('Gagal menyimpan pengaturan. Silakan coba lagi.');
  } finally {
    saving.value = false;
  }
}
</script>
