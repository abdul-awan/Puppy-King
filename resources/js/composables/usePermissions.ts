import { usePage } from '@inertiajs/vue3';
import type { AccessLevel, ModuleKey } from '@/types/models';

/**
 * Check the current user's access to the modules of the current team.
 */
export function usePermissions() {
    const page = usePage();

    const level = (module: ModuleKey): AccessLevel =>
        page.props.permissions?.[module] ?? 'none';

    const canView = (module: ModuleKey): boolean => level(module) !== 'none';

    const canEdit = (module: ModuleKey): boolean => level(module) === 'write';

    return { level, canView, canEdit };
}
