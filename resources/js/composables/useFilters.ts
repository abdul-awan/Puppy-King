import { router } from '@inertiajs/vue3';
import { watchDebounced } from '@vueuse/core';
import { reactive } from 'vue';

type FilterValue = string | number | boolean | null | undefined;

/**
 * Keep list filters in the URL: every change reloads the page with the new
 * query string (debounced, preserving state and scroll position).
 */
export function useFilters<T extends Record<string, FilterValue>>(
    initial: T,
    url: () => string,
    debounce = 300,
) {
    const filters = reactive({ ...initial }) as T;

    const clean = (): Record<string, string | number> => {
        const query: Record<string, string | number> = {};

        for (const [key, value] of Object.entries(filters)) {
            if (
                value === null ||
                value === undefined ||
                value === '' ||
                value === false
            ) {
                continue;
            }

            query[key] = value === true ? 1 : value;
        }

        return query;
    };

    const apply = () => {
        router.get(url(), clean(), {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    watchDebounced(() => ({ ...filters }), apply, { debounce, deep: true });

    const reset = () => {
        for (const key of Object.keys(filters)) {
            (filters as Record<string, FilterValue>)[key] = undefined;
        }
    };

    return { filters, apply, reset };
}
