import { router } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import type { FlashToast } from '@/types/ui';

// Paths where a "Saved" message would be wrong (sign in / out, two-step checks).
const QUIET = /\/(login|logout|two-factor|user\/confirm|pin|guest|t\/)/;

export function initializeFlashToast(): void {
    let hadToast = false;

    router.on('start', () => {
        hadToast = false;
    });

    router.on('flash', (event) => {
        const flash = (event as CustomEvent).detail?.flash;
        const data = flash?.toast as FlashToast | undefined;

        if (!data) {
            return;
        }

        hadToast = true;
        toast[data.type](data.message);
    });

    // Every successful save gets a confirmation, even where the server sent no message.
    router.on('success', (event) => {
        const visit = (event as CustomEvent).detail?.visit as
            | { method?: string; url?: URL }
            | undefined;
        const method = (visit?.method ?? 'get').toLowerCase();
        if (method === 'get' || QUIET.test(visit?.url?.pathname ?? '')) {
            return;
        }
        setTimeout(() => {
            if (!hadToast) {
                toast.success(method === 'delete' ? 'Removed.' : 'Saved.');
            }
        }, 50);
    });
}
