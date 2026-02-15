<template>
    <AppLayout>
        <div class="flex gap-6">
            <!-- Список отзывов -->
            <div class="flex-1">
                <div class="mb-4 flex items-center justify-between">
                    <YandexMapsBadge />

                    <Button
                        v-if="setting?.maps_url"
                        class="bg-brand-blue mt-3 cursor-pointer rounded-lg px-5 py-2 text-sm font-medium text-white transition hover:bg-blue-700 disabled:opacity-50"
                        size="sm"
                        :disabled="isSyncing"
                        @click="handleSync"
                    >
                        <RefreshCw
                            :class="{ 'animate-spin': isSyncing }"
                            class="mr-2 h-4 w-4"
                        />
                        Обновить
                    </Button>
                </div>

                <!-- Пагинация сверху -->
                <div v-if="reviews.data.length" class="mb-6">
                    <Pagination
                        :current-page="reviews.current_page"
                        :last-page="reviews.last_page"
                        :per-page="perPage"
                        @change="handlePageChange"
                        @perPageChange="handlePerPageChange"
                    />
                </div>

                <!-- Идёт синхронизация -->
                <div
                    v-if="isSyncing"
                    class="mb-4 rounded-lg border border-blue-200 bg-blue-50 p-4 text-sm text-blue-700"
                >
                    ⏳ Отзывы загружаются с Яндекс Карт, это может занять
                    несколько минут...
                </div>

                <!-- Прервано Яндексом -->
                <div
                    v-if="setting?.sync_status === 'aborted'"
                    class="mb-4 rounded-lg border border-yellow-200 bg-yellow-50 p-4 text-sm text-yellow-700"
                >
                    ⚠️ Синхронизация была прервана Яндекс Картами (возможно,
                    из-за частых запросов). Часть отзывов могла быть не
                    загружена. Попробуйте позже.
                </div>

                <!-- Нет данных -->
                <div
                    v-else-if="!reviews.data.length && !isSyncing"
                    class="py-16 text-center text-gray-400"
                >
                    <p v-if="!setting?.maps_url">
                        Добавьте ссылку в
                        <Link
                            :href="settings.url()"
                            class="text-blue-500 underline"
                            >настройках</Link
                        >
                    </p>
                    <p v-else>Отзывы не найдены</p>
                </div>

                <!-- Сортировка -->
                <div
                    v-if="reviews.data.length"
                    class="text-app mb-3 flex justify-end"
                >
                    <select
                        :value="sort"
                        @change="handleSortChange"
                        class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm focus:ring-2 focus:ring-blue-300 focus:outline-none"
                    >
                        <option value="newest">Сначала новые</option>
                        <option value="oldest">Сначала старые</option>
                    </select>
                </div>

                <!-- Карточки -->
                <div class="flex flex-col gap-3">
                    <ReviewCard
                        v-for="review in reviews.data"
                        :key="review.id"
                        :review="review"
                    />
                </div>

                <!-- Пагинация -->
                <div v-if="reviews.data.length" class="mt-6">
                    <Pagination
                        :current-page="reviews.current_page"
                        :last-page="reviews.last_page"
                        :per-page="perPage"
                        @change="handlePageChange"
                        @perPageChange="handlePerPageChange"
                    />
                </div>
            </div>

            <!-- Блок рейтинга -->
            <div v-if="setting?.maps_url" class="w-52 shrink-0">
                <div
                    class="sticky top-6 rounded-xl border border-gray-100 bg-white p-5 shadow-sm"
                >
                    <div class="mb-2 flex items-center gap-2">
                        <span class="text-4xl font-bold text-gray-900">{{
                            setting.rating
                        }}</span>
                        <div class="mt-1 flex gap-0.5">
                            <svg
                                v-for="i in 5"
                                :key="i"
                                class="h-4 w-4"
                                :class="
                                    i <= Math.round(setting.rating)
                                        ? 'text-yellow-400'
                                        : 'text-gray-200'
                                "
                                fill="currentColor"
                                viewBox="0 0 20 20"
                            >
                                <path
                                    d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"
                                />
                            </svg>
                        </div>
                    </div>
                    <Separator class="bg-panel mb-4 h-1" />
                    <p class="text-sm text-gray-500">
                        Всего отзывов:
                        <strong class="text-gray-700">{{
                            setting.reviews_count?.toLocaleString('ru-RU')
                        }}</strong>
                    </p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup lang="ts">
import { router, Link } from '@inertiajs/vue3';
import { RefreshCw } from 'lucide-vue-next';
import ReviewCard from '@/components/ReviewCard.vue';
import { Button } from '@/components/ui/button';
import { Pagination } from '@/components/ui/pagination';
import { Separator } from '@/components/ui/separator/index.ts';
import YandexMapsBadge from '@/components/YandexMapsBadge.vue';
import { usePolling } from '@/composables/usePolling';
import AppLayout from '@/layouts/AppLayout.vue';
import { settings, reviews as reviewsRoute } from '@/routes';
import { type Review } from '@/types';

import reviewsSync from '@/routes/reviews';

const props = defineProps<{
    reviews: {
        data: Review[];
        current_page: number;
        last_page: number;
    };
    setting: {
        maps_url: string | null;
        business_name: string | null;
        rating: number;
        reviews_count: number;
        sync_status: string;
    } | null;
    sort: string;
    perPage: number;
    isSyncing: boolean;
}>();

usePolling(() => props.isSyncing, {
    only: ['reviews', 'setting', 'isSyncing'],
    interval: 1000,
});

const handlePageChange = (page: number) => {
    router.get(
        reviewsRoute.url({
            query: { page, sort: props.sort, per_page: props.perPage },
        }),
        { preserveState: true, preserveScroll: true },
    );
};

const handlePerPageChange = (perPage: number) => {
    router.get(
        reviewsRoute.url({
            query: { page: 1, sort: props.sort, per_page: perPage },
        }),
        { preserveState: true, preserveScroll: true },
    );
};

const handleSortChange = (e: Event) => {
    const target = e.target as HTMLSelectElement;
    router.get(
        reviewsRoute.url({
            query: { page: 1, sort: target.value, per_page: props.perPage },
        }),
        { preserveState: true, preserveScroll: true },
    );
};

const handleSync = () => {
    router.post(
        reviewsSync.sync.url(),
        {},
        {
            preserveScroll: true,
        },
    );
};
</script>
