export const applicationTabs = [
    { value: 'all', label: 'Все' },
    { value: 'pps', label: 'Преподаватели' },
    { value: 'uvp', label: 'УВП' },
    { value: 'staff', label: 'Сотрудники' },
];

export const applicationCategoryPreferenceKey = 'applications.type';

export function applicationListType(queryType, rememberedType = 'all') {
    const type = queryType === null ? rememberedType : queryType;
    return applicationTabs.some(tab => tab.value === type) ? type : 'all';
}

export const applicationType = item => ['staff', 'uvp'].includes(item.vacancy?.type) ? item.vacancy.type : 'pps';
export const applicationTypeLabel = type => ({ pps: 'ППС', staff: 'АУП', uvp: 'УВП' })[type] || type;

export function applicationListUrl(filters = {}) {
    const params = new URLSearchParams();
    Object.entries({ ...filters, type: applicationListType(filters.type ?? null), page: filters.page || 1 }).forEach(([key, value]) => {
        if (value !== undefined && value !== null && value !== '') params.set(key, String(value));
    });
    return `/admin/applications?${params}`;
}

export function applicationTabUrl(filters, type) {
    const next = { ...filters, type, page: 1 };
    if ((type === 'pps' && next.hiring_status === 'hired') ||
        (['staff', 'uvp'].includes(type) && ['hired_1_year', 'hired_3_year'].includes(next.hiring_status))) {
        next.hiring_status = '';
    }
    return applicationListUrl(next);
}
