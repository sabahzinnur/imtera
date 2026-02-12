<template>
    <AppLayout>
        <div class="flex gap-6">
            <!-- Список отзывов -->
            <div class="flex-1">
                <YandexMapsBadge />

                <!-- Идёт синхронизация -->
                <div
                    v-if="isSyncing"
                    class="mb-4 rounded-lg border border-blue-200 bg-blue-50 p-4 text-sm text-blue-700"
                >
                    ⏳ Отзывы загружаются с Яндекс Карт, это может занять
                    несколько минут...
                </div>

                <!-- Нет данных -->
                <div
                    v-else-if="!reviews.data.length"
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
                <div
                    v-if="reviews.last_page > 1"
                    class="mt-6 flex justify-center gap-2"
                >
                    <button
                        v-for="page in reviews.last_page"
                        :key="page"
                        @click="
                            router.get(
                                reviewsRoute.url({ query: { page, sort } }),
                                { preserveState: true },
                            )
                        "
                        class="h-9 w-9 rounded-lg text-sm font-medium transition"
                        :class="
                            page === reviews.current_page
                                ? 'bg-blue-600 text-white'
                                : 'border border-gray-200 bg-white text-gray-600 hover:bg-gray-50'
                        "
                    >
                        {{ page }}
                    </button>
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
import ReviewCard from '@/components/ReviewCard.vue';
import { Separator } from '@/components/ui/separator/index.ts';
import YandexMapsBadge from '@/components/YandexMapsBadge.vue';
import { usePolling } from '@/composables/usePolling';
import AppLayout from '@/layouts/AppLayout.vue';
import { settings, reviews as reviewsRoute } from '@/routes';
import { type Review } from '@/types';

const props = defineProps<{
    reviews: {
        data: Review[];
        current_page: number;
        last_page: number;
    };
    setting: {
        maps_url: string | null;
        rating: number;
        reviews_count: number;
        sync_status: string;
    } | null;
    sort: string;
    isSyncing: boolean;
}>();

usePolling(() => props.isSyncing, {
    only: ['reviews', 'setting', 'isSyncing'],
});

const handleSortChange = (e: Event) => {
    const target = e.target as HTMLSelectElement;
    router.get(
        reviewsRoute.url({
            query: { sort: target.value },
        }),
        { preserveState: true },
    );
};
</script>
