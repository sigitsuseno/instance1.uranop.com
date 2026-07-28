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

            <!-- Section 1: Group Karyawan (ALL IN) -->
            <div>
              <h3 class="text-sm font-semibold mb-3 flex items-center gap-2 text-(--text-main)">
                <i class="bx bx-group text-lg"></i> A. Group Karyawan ALL IN
              </h3>
              <p class="text-xs text-(--text-muted) mb-3">
                Pilih group karyawan yang akan muncul di Section A (ALL IN).
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
                <i class="bx bx-error-circle"></i> Tidak ada group dipilih — Section A mungkin kosong.
              </p>
            </div>

            <hr class="border-(--border-soft)" />

            <!-- Section 2: Group untuk BULANAN PRINT -->
            <div>
              <h3 class="text-sm font-semibold mb-3 flex items-center gap-2 text-(--text-main)">
                <i class="bx bx-printer text-lg"></i> B. Group BULANAN PRINT
              </h3>
              <p class="text-xs text-(--text-muted) mb-3">
                Pilih subset dari group di atas yang termasuk Section B (BULANAN PRINT).
                <br/>Default: otomatis memilih group dengan kata "PRINT" di namanya.
              </p>

              <div class="flex flex-wrap gap-3">
                <label
                  v-for="group in availableGroups"
                  :key="'print-'+group"
                  class="flex items-center gap-2 px-3 py-2 rounded-lg border cursor-pointer transition-colors select-none"
                  :class="localPrintGroups.includes(group)
                    ? 'bg-green-50 border-green-300 dark:bg-green-900/20 dark:border-green-700'
                    : 'border-(--border-soft) hover:bg-(--bg-hover)'"
                >
                  <input
                    type="checkbox"
                    :value="group"
                    v-model="localPrintGroups"
                    class="w-4 h-4 rounded text-green-600 focus:ring-green-500 border-(--border-soft)"
                  />
                  <span class="text-sm font-medium text-(--text-main)">{{ group }}</span>
                </label>
              </div>

              <p v-if="localPrintGroups.length === 0 && !loading" class="text-xs text-amber-600 mt-2 flex items-center gap-1">
                <i class="bx bx-error-circle"></i> Tidak ada group PRINT dipilih — Section B mungkin kosong.
              </p>

              <button
                @click="autoSelectPrint"
                class="mt-3 text-xs text-(--primary) hover:underline flex items-center gap-1"
              >
                <i class="bx bx-magic-wand"></i> Auto-detect group PRINT
              </button>
            </div>

            <hr class="border-(--border-soft)" />

            <!-- Section 3: Group UANG MAKAN -->
            <div>
              <h3 class="text-sm font-semibold mb-3 flex items-center gap-2 text-(--text-main)">
                <i class="bx bx-food-menu text-lg"></i> C. Group UANG MAKAN
              </h3>
              <p class="text-xs text-(--text-muted) mb-3">
                Pilih group karyawan yang akan muncul di Section C (UANG MAKAN).
                <br/>Default: mengikuti pilihan Group ALL IN jika tidak dipilih.
              </p>

              <div class="flex flex-wrap gap-3">
                <label
                  v-for="group in availableGroups"
                  :key="'uangmakan-'+group"
                  class="flex items-center gap-2 px-3 py-2 rounded-lg border cursor-pointer transition-colors select-none"
                  :class="localUangMakanGroups.includes(group)
                    ? 'bg-amber-50 border-amber-300 dark:bg-amber-900/20 dark:border-amber-700'
                    : 'border-(--border-soft) hover:bg-(--bg-hover)'"
                >
                  <input
                    type="checkbox"
                    :value="group"
                    v-model="localUangMakanGroups"
                    class="w-4 h-4 rounded text-amber-600 focus:ring-amber-500 border-(--border-soft)"
                  />
                  <span class="text-sm font-medium text-(--text-main)">{{ group }}</span>
                </label>
              </div>

              <p v-if="localUangMakanGroups.length === 0 && !loading" class="text-xs text-amber-600 mt-2 flex items-center gap-1">
                <i class="bx bx-error-circle"></i> Tidak ada group dipilih — Section C akan mengikuti Group ALL IN.
              </p>

              <button
                @click="localUangMakanGroups = [...availableGroups]"
                class="mt-3 text-xs text-(--primary) hover:underline flex items-center gap-1"
              >
                <i class="bx bx-magic-wand"></i> Pilih Semua Group
              </button>
            </div>

            <hr class="border-(--border-soft)" />

            <!-- Section 4: Extra Employees (hanya untuk Section A) -->
            <div>
              <h3 class="text-sm font-semibold mb-3 flex items-center gap-2 text-(--text-main)">
                <i class="bx bx-user-plus text-lg"></i> Extra Employees (Section A)
              </h3>
              <p class="text-xs text-(--text-muted) mb-3">
                Pilih karyawan extra/titipan yang akan muncul di Section A (ALL IN).
              </p>

              <div v-if="extraEmployees.length === 0" class="text-xs text-(--text-muted) py-2">
                Tidak ada extra employee tersedia.
              </div>

              <div v-else class="flex flex-wrap gap-3 max-h-[200px] overflow-y-auto border border-(--border-soft) rounded-md p-3">
                <label
                  v-for="extra in extraEmployees"
                  :key="'extra-'+extra.id"
                  class="flex items-center gap-2 px-3 py-2 rounded-lg border cursor-pointer transition-colors select-none"
                  :class="localExtraIds.includes(extra.id)
                    ? 'bg-purple-50 border-purple-300 dark:bg-purple-900/20 dark:border-purple-700'
                    : 'border-(--border-soft) hover:bg-(--bg-hover)'"
                >
                  <input
                    type="checkbox"
                    :value="extra.id"
                    v-model="localExtraIds"
                    class="w-4 h-4 rounded text-purple-600 focus:ring-purple-500 border-(--border-soft)"
                  />
                  <span class="text-sm font-medium text-(--text-main)">{{ extra.nama }}</span>
                </label>
              </div>

              <div class="flex gap-2 mt-2">
                <button @click="localExtraIds = extraEmployees.map(e => e.id)" class="text-xs text-(--primary) hover:underline">
                  Pilih Semua
                </button>
                <button @click="localExtraIds = []" class="text-xs text-(--text-muted) hover:underline">
                  Kosongkan
                </button>
              </div>
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
  extraEmployees: { type: Array, default: () => [] },
  savedExtraIds: { type: Array, default: () => [] },
});

const emit = defineEmits(['close', 'saved']);

const loading = ref(true);
const saving = ref(false);
const selectedGroups = ref([]);
const localPrintGroups = ref([]);
const localUangMakanGroups = ref([]);
const localExtraIds = ref([]);
const lastUpdated = ref('');
const lastUpdatedBy = ref('');

onMounted(async () => {
  try {
    const res = await get(`/api/v1/settings/report-configs/${props.reportType}`);
    const data = res.data || res;
    selectedGroups.value = data.employee_groups || [];

    const printFromConfig = data.config?.print_groups || [];
    if (printFromConfig.length > 0) {
      localPrintGroups.value = printFromConfig;
    } else {
      autoSelectPrint();
    }

    const extraFromConfig = data.config?.extra_employee_ids || [];
    if (extraFromConfig.length > 0) {
      localExtraIds.value = extraFromConfig;
    } else {
      // Default: pilih semua extra
      localExtraIds.value = props.extraEmployees.map(e => e.id);
    }

    const uangMakanFromConfig = data.config?.uang_makan_groups || [];
    if (uangMakanFromConfig.length > 0) {
      localUangMakanGroups.value = uangMakanFromConfig;
    } else {
      // Default: pilih semua group yang tersedia
      localUangMakanGroups.value = [...props.availableGroups];
    }

    lastUpdated.value = data.updated_at || '';
    lastUpdatedBy.value = data.updated_by || '';
  } catch (e) {
    console.error('Gagal memuat pengaturan:', e);
    selectedGroups.value = [...props.availableGroups];
    localExtraIds.value = props.extraEmployees.map(e => e.id);
    autoSelectPrint();
  } finally {
    loading.value = false;
  }
});

function autoSelectPrint() {
  localPrintGroups.value = props.availableGroups.filter(g =>
    g.toUpperCase().includes('PRINT')
  );
}

async function save() {
  saving.value = true;
  try {
    const res = await put(`/api/v1/settings/report-configs/${props.reportType}`, {
      employee_groups: selectedGroups.value,
      config: {
        print_groups: localPrintGroups.value,
        extra_employee_ids: localExtraIds.value,
        uang_makan_groups: localUangMakanGroups.value,
      },
    });
    lastUpdated.value = res.updated_at || new Date().toLocaleString('id-ID');
    lastUpdatedBy.value = res.updated_by || '';
    emit('saved', {
      employee_groups: selectedGroups.value,
      config: {
        print_groups: localPrintGroups.value,
        extra_employee_ids: localExtraIds.value,
        uang_makan_groups: localUangMakanGroups.value,
      },
    });
  } catch (e) {
    console.error('Gagal menyimpan pengaturan:', e);
    alert('Gagal menyimpan pengaturan. Silakan coba lagi.');
  } finally {
    saving.value = false;
  }
}
</script>
