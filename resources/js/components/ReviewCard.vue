<script setup lang="ts">
import { type Review } from '@/types';
import YandexIcon from '@/components/YandexIcon.vue';

defineProps<{
    review: Review;
}>();
</script>

<template>
    <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
        <div class="mb-2 flex items-start justify-between">
            <div class="text-app flex items-center gap-3 text-sm font-bold">
                <span>{{ review.published_at }}</span>
                <span v-if="review.branch_name" class="flex items-center gap-1">
                    {{ review.branch_name }}
                    <YandexIcon />
                </span>
            </div>
            <div class="flex gap-0.5">
                <svg
                    v-for="i in 5"
                    :key="i"
                    class="h-4 w-4"
                    :class="
                        i <= review.rating ? 'text-yellow-400' : 'text-gray-200'
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

        <div class="mb-2 flex items-center gap-3">
            <span class="text-sm font-semibold text-gray-800">{{
                review.author_name
            }}</span>
            <span v-if="review.author_phone" class="text-sm text-gray-400">{{
                review.author_phone
            }}</span>
        </div>

        <p class="text-sm leading-relaxed text-gray-600">
            {{ review.text }}
        </p>
    </div>
</template>
