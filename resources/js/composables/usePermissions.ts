import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

/**
 * Hides UI the user cannot use. The server still checks every request —
 * this is only for a cleaner screen, never for security.
 */
export function usePermissions() {
    const page = usePage();
    const permissions = computed<string[]>(
        () => page.props.auth.user?.permissions ?? [],
    );

    const can = (permission: string): boolean =>
        permissions.value.includes(permission);

    return { can, permissions };
}
