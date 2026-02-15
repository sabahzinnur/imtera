<template>
  <AppLayout>
      <h2 class="font-semibold text-gray-700 mb-4">Подключить Яндекс</h2>

      <div v-if="$page.props.flash?.success"
           class="bg-green-50 border border-green-200 text-green-700 rounded-lg px-4 py-2 text-sm mb-4">
        {{ $page.props.flash.success }}
      </div>

      <!-- Статус синхронизации -->
      <SyncStatus
        v-if="setting"
        :status="setting.sync_status"
        :error="setting.sync_error"
        :last-synced-at="setting.last_synced_at"
        :business-name="setting.business_name"
        show-completed
        class="mb-6"
      />

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
import SyncStatus from '@/components/SyncStatus.vue';
import { usePolling } from '@/composables/usePolling';
import AppLayout from '@/layouts/AppLayout.vue';
import { settings } from '@/routes';

const props = defineProps<{
    setting: {
        maps_url: string | null;
        business_name: string | null;
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

usePolling(isSyncing, { only: ['setting'], interval: 1000 });

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
