<template>
  <AppLayout>
    <div class="flex gap-6">
      <!-- Список отзывов -->
      <div class="flex-1">

        <!-- Бейдж -->
        <div class="flex items-center gap-2 mb-4">
          <span class="inline-flex items-center gap-1.5 bg-red-50 text-red-600 text-sm font-medium px-3 py-1 rounded-full">
            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
              <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5S10.62 6.5 12 6.5s2.5 1.12 2.5 2.5S13.38 11.5 12 11.5z"/>
            </svg>
            Яндекс Карты
          </span>
        </div>

        <!-- Идёт синхронизация -->
        <div v-if="isSyncing" class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4 text-blue-700 text-sm">
          ⏳ Отзывы загружаются с Яндекс Карт, это может занять несколько минут...
        </div>

        <!-- Нет данных -->
        <div v-else-if="!reviews.data.length" class="text-center py-16 text-gray-400">
          <p v-if="!setting?.maps_url">
            Добавьте ссылку в
            <Link :href="settings.url()" class="text-blue-500 underline">настройках</Link>
          </p>
          <p v-else>Отзывы не найдены</p>
        </div>

        <!-- Сортировка -->
        <div v-if="reviews.data.length" class="flex justify-end mb-3">
          <select
            :value="sort"
            @change="e => router.get(reviewsRoute.url({ query: { sort: e.target.value } }), { preserveState: true })"
            class="text-sm border border-gray-200 rounded-lg px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-blue-300"
          >
            <option value="newest">Сначала новые</option>
            <option value="oldest">Сначала старые</option>
          </select>
        </div>

        <!-- Карточки -->
        <div class="flex flex-col gap-3">
          <div
            v-for="review in reviews.data" :key="review.id"
            class="bg-white rounded-xl p-5 shadow-sm border border-gray-100"
          >
            <div class="flex items-start justify-between mb-2">
              <div class="flex items-center gap-3 text-sm text-gray-500">
                <span>{{ review.published_at }}</span>
                <span v-if="review.branch_name" class="flex items-center gap-1">
                  {{ review.branch_name }}
                  <svg class="w-3.5 h-3.5 text-red-500" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z"/>
                  </svg>
                </span>
              </div>
              <div class="flex gap-0.5">
                <svg v-for="i in 5" :key="i" class="w-4 h-4"
                  :class="i <= review.rating ? 'text-yellow-400' : 'text-gray-200'"
                  fill="currentColor" viewBox="0 0 20 20">
                  <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                </svg>
              </div>
            </div>

            <div class="flex items-center gap-3 mb-2">
              <span class="font-semibold text-gray-800 text-sm">{{ review.author_name }}</span>
              <span v-if="review.author_phone" class="text-gray-400 text-sm">{{ review.author_phone }}</span>
            </div>

            <p class="text-gray-600 text-sm leading-relaxed">{{ review.text }}</p>
          </div>
        </div>

        <!-- Пагинация -->
        <div v-if="reviews.last_page > 1" class="flex justify-center gap-2 mt-6">
          <button
            v-for="page in reviews.last_page" :key="page"
            @click="router.get(reviewsRoute.url({ query: { page, sort } }), { preserveState: true })"
            class="w-9 h-9 rounded-lg text-sm font-medium transition"
            :class="page === reviews.current_page
              ? 'bg-blue-600 text-white'
              : 'bg-white text-gray-600 border border-gray-200 hover:bg-gray-50'"
          >{{ page }}</button>
        </div>
      </div>

      <!-- Блок рейтинга -->
      <div v-if="setting?.maps_url" class="w-52 shrink-0">
        <div class="bg-white rounded-xl p-5 shadow-sm border border-gray-100 sticky top-6">
          <div class="flex items-center gap-2 mb-2">
            <span class="text-4xl font-bold text-gray-900">{{ setting.rating }}</span>
            <div class="flex gap-0.5 mt-1">
              <svg v-for="i in 5" :key="i" class="w-4 h-4"
                :class="i <= Math.round(setting.rating) ? 'text-yellow-400' : 'text-gray-200'"
                fill="currentColor" viewBox="0 0 20 20">
                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
              </svg>
            </div>
          </div>
          <p class="text-sm text-gray-500">
            Всего отзывов: <strong class="text-gray-700">{{ setting.reviews_count?.toLocaleString('ru-RU') }}</strong>
          </p>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { router, Link } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import { settings, reviews as reviewsRoute } from '@/routes'

defineProps({
  reviews:   Object,
  setting:   Object,
  sort:      String,
  isSyncing: Boolean,
})
</script>
