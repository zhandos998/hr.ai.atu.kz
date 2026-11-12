export const actionOptions = [
    { value: 'hire', label: 'Приём на работу' },
    { value: 'dismissal', label: 'Увольнение' },
    { value: 'transfer', label: 'Перевод' },
    { value: 'maternity_leave', label: 'Отпуск по беременности и родам' },
];

export const personnelCategories = {
    staff: {
        academic: false, label: 'Сотрудник', title: 'Кадровые процессы сотрудников',
        createTitle: 'Новый кадровый процесс сотрудника', nameLabel: 'ФИО сотрудника',
        description: 'Данные сотрудника, документы и состав комиссии.', positionLabel: 'Текущая должность',
    },
    pps: {
        academic: true, label: 'Преподаватель', title: 'Кадровые процессы преподавателей',
        createTitle: 'Новый кадровый процесс преподавателя', nameLabel: 'ФИО преподавателя',
        description: 'Данные преподавателя, документы и состав комиссии.', positionLabel: 'Позиция ППС',
    },
    uvp: {
        academic: true, label: 'УВП', title: 'Кадровые процессы УВП',
        createTitle: 'Новый кадровый процесс УВП', nameLabel: 'ФИО сотрудника УВП',
        description: 'Данные сотрудника УВП, документы и состав комиссии.', positionLabel: 'Должность УВП',
    },
};

export const personnelCategory = type => Object.hasOwn(personnelCategories, type) ? personnelCategories[type] : personnelCategories.staff;
export const usesAcademicStructure = type => personnelCategory(type).academic;

export const personnelPosition = (item, target = false) => {
    const prefix = target ? 'target_' : '';
    return (item.employee_type === 'uvp' ? item[`${prefix}uvp_position`] : null) || item[`${prefix}position`];
};

export const personnelTabs = [
    { value: '', label: 'Все' },
    { value: 'pps', label: 'Преподаватели' },
    { value: 'uvp', label: 'УВП' },
    { value: 'staff', label: 'Сотрудники' },
];

export const personnelCategoryPreferenceKey = 'personnel.employee_type';

export function personnelListCategory(queryType, rememberedType = '') {
    const type = queryType === null ? rememberedType : queryType;
    return Object.hasOwn(personnelCategories, type) ? type : '';
}

export function personnelListUrl(filters, page = 1) {
    const params = new URLSearchParams({ ...filters, employee_type: filters.employee_type || 'all', page: String(page) });
    return `/admin/personnel-requests?${params}`;
}

export const emptyPersonnelForm = {
    employee_type: 'staff', faculty_id: null, academic_unit_id: null,
    target_faculty_id: null, target_academic_unit_id: null,
    uvp_position_id: null, target_uvp_position_id: null,
    employee_name: '', action_type: 'hire', department_id: null, position_id: null,
    target_department_id: null, target_position_id: null, salary: '', work_experience: '', details: '',
};

export const actionLabel = (type) => actionOptions.find((option) => option.value === type)?.label || type;
export const statusLabel = (status) => ({ pending: 'На согласовании', approved: 'Согласовано', rejected: 'Отклонено' }[status] || status);
export const statusClass = (status) => ({
    pending: 'bg-amber-50 text-amber-700',
    approved: 'bg-emerald-50 text-emerald-700',
    rejected: 'bg-red-50 text-red-700',
}[status] || 'bg-gray-100 text-gray-700');
export const decisionLabel = (decision) => ({ pending: 'Ожидает решения', approved: 'Разрешено', rejected: 'Отказано' }[decision] || decision);
export const errorText = (error) => error?.response?.data?.message
    || Object.values(error?.response?.data?.errors || {})?.[0]?.[0]
    || 'Не удалось выполнить операцию.';
export const formatDate = (value) => value
    ? new Intl.DateTimeFormat('ru-RU', { dateStyle: 'medium' }).format(new Date(value))
    : '—';
export const formatFileSize = (value) => {
    const bytes = Number(value || 0);
    if (bytes < 1024) return `${bytes} Б`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} КБ`;
    return `${(bytes / 1024 / 1024).toFixed(1)} МБ`;
};
