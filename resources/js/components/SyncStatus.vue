<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    status?: string | null;
    error?: string | null;
    lastSyncedAt?: string | null;
    businessName?: string | null;
    showCompleted?: boolean;
}>();

const shouldShow = computed(() => {
    if (!props.status) return false;
    if (props.status === 'completed' && !props.showCompleted) return false;
    return true;
});
</script>

<template>
    <div
        v-if="shouldShow"
        class="mb-4 rounded-lg border p-4 text-sm"
        :class="{
            'border-blue-200 bg-blue-50 text-blue-700':
                status === 'syncing' || status === 'pending',
            'border-green-200 bg-green-50 text-green-700':
                status === 'completed',
            'border-red-200 bg-red-50 text-red-700': status === 'failed',
            'border-yellow-200 bg-yellow-50 text-yellow-700':
                status === 'aborted',
        }"
    >
        <div class="mb-1 flex items-center justify-between">
            <div class="flex items-center gap-2 font-medium">
                <template v-if="status === 'syncing' || status === 'pending'">
                    <span>⏳ Синхронизация...</span>
                </template>
                <template v-else-if="status === 'completed'">
                    <span>✅ Синхронизация завершена</span>
                </template>
                <template v-else-if="status === 'failed'">
                    <span>❌ Ошибка синхронизации</span>
                </template>
                <template v-else-if="status === 'aborted'">
                    <span>⚠️ Синхронизация прервана</span>
                </template>
            </div>
            <div v-if="businessName" class="font-bold">
                {{ businessName }}
            </div>
        </div>

        <!-- Подробности для состояний загрузки -->
        <p
            v-if="status === 'syncing' || status === 'pending'"
            class="mt-1 text-xs opacity-90"
        >
            Отзывы загружаются с Яндекс Карт, это может занять несколько
            минут...
        </p>

        <p v-if="status === 'aborted'" class="mt-1 text-xs opacity-90">
            Синхронизация была прервана Яндекс Картами (возможно, из-за частых
            запросов). Часть отзывов могла быть не загружена. Попробуйте позже.
        </p>

        <!-- Ошибка -->
        <p
            v-if="error"
            class="mt-1 rounded bg-white/50 p-1 font-mono text-xs text-red-800"
        >
            {{ error }}
        </p>

        <!-- Дата последнего обновления -->
        <p
            v-if="lastSyncedAt && status === 'completed'"
            class="mt-1 text-xs opacity-75"
        >
            Последнее обновление:
            {{ new Date(lastSyncedAt).toLocaleString('ru-RU') }}
        </p>
    </div>
</template>
