export const actionOptions = [
    { value: 'hire', label: 'Приём на работу' },
    { value: 'dismissal', label: 'Увольнение' },
    { value: 'transfer', label: 'Перевод' },
    { value: 'maternity_leave', label: 'Отпуск по беременности и родам' },
];

export const emptyPersonnelForm = {
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
