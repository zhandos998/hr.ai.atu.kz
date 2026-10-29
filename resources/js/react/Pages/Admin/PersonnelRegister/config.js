export const registers = {
    sick_leave: {
        title: 'Больничные листы',
        singular: 'Больничный лист',
        add: 'Добавить больничный',
        edit: 'Изменить больничный',
        path: '/admin/sick-leaves',
        documentLabel: 'Номер листа нетрудоспособности',
        detailLabel: 'Диагноз',
        detailField: 'diagnosis',
    },
    leave: {
        title: 'Отпуска',
        singular: 'Отпуск',
        add: 'Добавить отпуск',
        edit: 'Изменить отпуск',
        path: '/admin/leaves',
        documentLabel: 'Номер приказа',
        detailLabel: 'Вид отпуска',
        detailField: 'leave_type',
    },
};

export function formatRegisterDate(value, locale) {
    if (!value) return '-';
    return new Intl.DateTimeFormat(locale === 'kk' ? 'kk-KZ' : 'ru-RU', { dateStyle: 'medium', timeZone: 'UTC' }).format(new Date(`${value.slice(0, 10)}T00:00:00Z`));
}
