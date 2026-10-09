<script setup>
import { computed, ref, watch } from 'vue'
import { useApi } from '../../../../composables/useApi'
import { useNotificationStore } from '../../../../Stores/notification'
import BaseModal from '../../../../Components/BaseModal.vue'
import BaseButton from '../../../../Components/BaseButton.vue'

const props = defineProps({
  show: { type: Boolean, default: false },
  paths: { type: Array, default: () => [] },
  exclude: { type: String, default: '' },
})

const emit = defineEmits(['close', 'moved'])

const { get, post } = useApi()
const notification = useNotificationStore()

const loading = ref(false)
const saving = ref(false)
const nodes = ref([])
const target = ref('')

const itemLabel = computed(() =>
  props.paths.length === 1 ? props.paths[0].split('/').pop() : `${props.paths.length} item`
)

async function load() {
  loading.value = true
  target.value = ''
  nodes.value = []

  try {
    const params = new URLSearchParams()
    if (props.exclude) params.set('exclude', props.exclude)

    const res = await get(`/api/v1/file-manager/tree?${params.toString()}`)
    nodes.value = res.data || []
  } catch (e) {
    notification.addNotification(e.message || 'Gagal memuat daftar folder.', 'error')
  } finally {
    loading.value = false
  }
}

async function submit() {
  if (saving.value) return

  saving.value = true

  try {
    const res = await post('/api/v1/file-manager/move', {
      paths: props.paths,
      target: target.value,
    })

    notification.addNotification(res.message || 'Berhasil dipindahkan.', 'success')
    emit('moved')
    emit('close')
  } catch (e) {
    notification.addNotification(
      e.response?.data?.message || e.message || 'Gagal memindahkan item.',
      'error'
    )
  } finally {
    saving.value = false
  }
}

watch(() => props.show, (visible) => {
  if (visible) load()
})
</script>

<template>
  <BaseModal :show="show" title="Pindahkan ke Folder" size="lg" @close="emit('close')">
    <div class="space-y-4">
      <p class="text-sm text-(--text-muted)">
        Memindahkan <span class="font-semibold text-(--text-main)">{{ itemLabel }}</span> ke:
      </p>

      <div v-if="loading" class="flex justify-center py-10">
        <i class="bx bx-loader-alt bx-spin text-2xl text-(--primary)"></i>
      </div>

      <div
        v-else
        class="max-h-72 overflow-y-auto rounded-md border border-(--border-soft) bg-(--bg-card)"
      >
        <label
          v-for="node in nodes"
          :key="node.path || 'root'"
          :class="[
            'flex items-center gap-2 border-b border-(--border-soft) px-3 py-2 last:border-b-0',
            node.disabled
              ? 'cursor-not-allowed opacity-40'
              : 'cursor-pointer hover:bg-(--bg-elevated)',
          ]"
        >
          <input
            v-model="target"
            type="radio"
            name="move-target"
            :value="node.path"
            :disabled="node.disabled"
            class="h-4 w-4 accent-(--primary)"
          />
          <i
            :class="[
              node.depth === 0 ? 'bx bx-home-alt' : 'bx bxs-folder',
              'text-lg',
              node.depth === 0 ? 'text-(--text-muted)' : 'text-(--warning)',
            ]"
          ></i>
          <span class="truncate text-sm text-(--text-main)" :style="{ paddingLeft: `${node.depth * 0.75}rem` }">
            {{ node.name }}
          </span>
          <span v-if="node.disabled" class="ml-auto text-[11px] text-(--text-soft)">
            tidak tersedia
          </span>
        </label>
      </div>
    </div>

    <template #footer>
      <BaseButton variant="ghost" :disabled="saving" @click="emit('close')">Batal</BaseButton>
      <BaseButton variant="primary" :loading="saving" :disabled="loading" @click="submit">
        <template #icon-left><i class="bx bx-move text-lg"></i></template>
        Pindahkan
      </BaseButton>
    </template>
  </BaseModal>
</template>
