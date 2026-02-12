<script setup lang="ts">
import { computed } from 'vue';

interface Props {
    currentPage: number;
    lastPage: number;
    offset?: number;
}

const props = withDefaults(defineProps<Props>(), {
    offset: 2,
});

const emit = defineEmits<{
    (e: 'change', page: number): void;
}>();

const pages = computed(() => {
    const lastPage = props.lastPage;
    const currentPage = props.currentPage;
    const offset = props.offset;

    const pages: (number | string)[] = [];

    for (let i = 1; i <= lastPage; i++) {
        if (
            i === 1 ||
            i === lastPage ||
            (i >= currentPage - offset && i <= currentPage + offset)
        ) {
            pages.push(i);
        } else if (
            i === currentPage - offset - 1 ||
            i === currentPage + offset + 1
        ) {
            pages.push('...');
        }
    }

    const result: (number | string)[] = [];
    pages.forEach((p) => {
        if (p === '...' && result[result.length - 1] === '...') return;
        result.push(p);
    });

    return result;
});
</script>

<template>
    <nav
        v-if="lastPage > 1"
        class="flex items-center justify-center gap-2"
        aria-label="Pagination"
    >
        <template v-for="(page, index) in pages" :key="index">
            <button
                v-if="typeof page === 'number'"
                type="button"
                @click="emit('change', page)"
                class="h-9 w-9 cursor-pointer rounded-lg text-sm font-medium transition"
                :class="
                    page === currentPage
                        ? 'bg-blue-600 text-white shadow-sm'
                        : 'border border-gray-200 bg-white text-gray-600 hover:bg-gray-50 active:bg-gray-100'
                "
            >
                {{ page }}
            </button>
            <span
                v-else
                class="flex h-9 w-9 items-center justify-center text-sm text-gray-400"
            >
                {{ page }}
            </span>
        </template>
    </nav>
</template>
