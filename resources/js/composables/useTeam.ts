import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

/**
 * Access the current team (company workspace) and its settings.
 */
export function useTeam() {
    const page = usePage();

    const team = computed(() => page.props.currentTeam);
    const slug = computed(() => page.props.currentTeam?.slug ?? '');
    const currency = computed(() => page.props.company?.currency ?? 'PKR');

    return { team, slug, currency };
}
