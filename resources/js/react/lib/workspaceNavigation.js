export const roleHome = {
    admin: '/admin',
    lawyer: '/compliance/applications',
    science_director: '/science/applications',
    digital_director: '/digital/applications',
    strategy_director: '/strategy/applications',
    academic_director: '/academic/applications',
    library_director: '/library/applications',
    professional_development_director: '/professional-development/applications',
    economist: '/personnel/requests',
    commission: '/commission/applications',
};

export function workspaceNavigation(user, role) {
    if (!user) return [];
    const links = [];
    const home = roleHome[role];
    if (home && home !== '/commission/applications') links.push({ href: home, label: 'workspace' });
    if (role === 'commission' || user.is_commission_member) links.push({ href: '/commission/applications', label: 'applications' });
    if (role !== 'admin' && user.has_personnel_approvals && !links.some(link => link.href === '/personnel/requests')) {
        links.push({ href: '/personnel/requests', label: 'personnel' });
    }
    return links;
}
