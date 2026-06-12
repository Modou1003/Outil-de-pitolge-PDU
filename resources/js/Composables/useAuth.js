import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

export function useAuth() {
    const page = usePage();

    const user = computed(() => page.props.auth?.user ?? null);
    const roles = computed(() => page.props.auth?.roles ?? []);
    const permissions = computed(() => page.props.auth?.permissions ?? []);

    const hasRole = (role) => {
        const r = Array.isArray(role) ? role : [role];
        return r.some((x) => roles.value.includes(x));
    };

    const hasPermission = (perm) => {
        // L'administrateur a tous les droits.
        if (roles.value.includes('admin')) return true;
        const p = Array.isArray(perm) ? perm : [perm];
        return p.some((x) => permissions.value.includes(x));
    };

    const primaryRole = computed(() => {
        const order = ['admin', 'directeur', 'chef_projet', 'comite_pilotage', 'agent_financier', 'visiteur'];
        for (const r of order) {
            if (roles.value.includes(r)) return r;
        }
        return roles.value[0] ?? null;
    });

    const roleLabel = computed(() => {
        const labels = {
            admin: 'Administrateur',
            directeur: 'Resp. UGP',
            chef_projet: 'Chef de projet',
            comite_pilotage: 'Comité de pilotage',
            agent_financier: 'Agent financier',
            visiteur: 'Visiteur',
        };
        return labels[primaryRole.value] ?? primaryRole.value ?? '';
    });

    const initials = computed(() => {
        if (!user.value?.name) return '?';
        return user.value.name
            .split(' ')
            .map((s) => s.charAt(0))
            .slice(0, 2)
            .join('')
            .toUpperCase();
    });

    return {
        user,
        roles,
        permissions,
        hasRole,
        hasPermission,
        primaryRole,
        roleLabel,
        initials,
    };
}
