<template>
  <AppLayout>
      <h2 class="font-semibold text-gray-700 mb-4">Подключить Яндекс</h2>

      <div v-if="$page.props.flash?.success"
           class="bg-green-50 border border-green-200 text-green-700 rounded-lg px-4 py-2 text-sm mb-4">
        {{ $page.props.flash.success }}
      </div>

      <!-- Статус синхронизации -->
      <div v-if="setting?.sync_status" class="mb-6 p-4 rounded-lg border text-sm"
           :class="{
             'bg-blue-50 border-blue-200 text-blue-700': setting.sync_status === 'syncing' || setting.sync_status === 'pending',
             'bg-green-50 border-green-200 text-green-700': setting.sync_status === 'completed',
             'bg-red-50 border-red-200 text-red-700': setting.sync_status === 'failed'
           }">
        <div class="flex items-center gap-2 font-medium mb-1">
          <span v-if="setting.sync_status === 'syncing' || setting.sync_status === 'pending'">⏳ Синхронизация...</span>
          <span v-else-if="setting.sync_status === 'completed'">✅ Синхронизация завершена</span>
          <span v-else-if="setting.sync_status === 'failed'">❌ Ошибка синхронизации</span>
        </div>
        <p v-if="setting.sync_error" class="text-xs mt-1">{{ setting.sync_error }}</p>
        <p v-if="setting.last_synced_at" class="text-xs opacity-75 mt-1">
          Последнее обновление: {{ new Date(setting.last_synced_at).toLocaleString('ru-RU') }}
        </p>
      </div>

      <form @submit.prevent="save">
        <label class="block text-sm text-gray-500 mb-1">
          Укажите ссылку на Яндекс, пример
          <span class="text-blue-400 text-xs">
            https://yandex.ru/maps/org/samoye_populyarnoye_kafe/1010501395/reviews/
          </span>
        </label>

        <input
          v-model="form.maps_url"
          type="url"
          placeholder="https://yandex.ru/maps/org/samoye_populyarnoye_kafe/1010501395/reviews/"
          class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm text-black focus:outline-none focus:ring-2 focus:ring-blue-400 mb-1"
          :class="{ 'border-red-400': errors.maps_url }"
        />

        <p v-if="errors.maps_url" class="text-red-500 text-xs mb-2">{{ errors.maps_url }}</p>

        <button
          type="submit" :disabled="loading"
          class="bg-brand-blue hover:bg-blue-700 cursor-pointer disabled:opacity-50 text-white text-sm font-medium px-5 py-2 rounded-lg transition mt-3"
        >
          {{ loading ? 'Сохранение...' : 'Сохранить' }}
        </button>
      </form>
  </AppLayout>
</template>

<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { usePolling } from '@/composables/usePolling';
import AppLayout from '@/layouts/AppLayout.vue';
import { settings } from '@/routes';

const props = defineProps<{
    setting: {
        maps_url: string | null;
        sync_status: string;
        sync_error: string | null;
        last_synced_at: string | null;
    } | null;
}>();

const form = ref({ maps_url: props.setting?.maps_url ?? '' });
const errors = ref<Record<string, any>>({});
const loading = ref(false);

const isSyncing = computed(() => {
    return !!(
        props.setting &&
        ['pending', 'syncing'].includes(props.setting.sync_status)
    );
});

usePolling(isSyncing, { only: ['setting'] });

function save() {
    errors.value = {};
    loading.value = true;

    router.post(settings.url(), form.value, {
        onError: (e) => {
            errors.value = e;
        },
        onFinish: () => {
            loading.value = false;
        },
    });
}
</script>
