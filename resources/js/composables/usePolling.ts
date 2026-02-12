import { router } from '@inertiajs/vue3';
import { onMounted, onUnmounted, watch, type WatchSource } from 'vue';

interface PollingOptions {
    only?: string[];
    interval?: number;
}

export function usePolling(
    isActive: WatchSource<boolean>,
    options: PollingOptions = {},
) {
    let timer: ReturnType<typeof setInterval> | null = null;
    const intervalTime = options.interval ?? 1000;

    const start = () => {
        if (timer) return;
        timer = setInterval(() => {
            router.reload({
                only: options.only,
            });
        }, intervalTime);
    };

    const stop = () => {
        if (timer) {
            clearInterval(timer);
            timer = null;
        }
    };

    onMounted(() => {
        const active = typeof isActive === 'function' ? isActive() : isActive.value;
        if (active) start();
    });

    watch(isActive, (active) => {
        if (active) {
            start();
        } else {
            stop();
        }
    });

    onUnmounted(stop);

    return { start, stop };
}
