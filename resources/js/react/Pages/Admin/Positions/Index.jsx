import React, { useEffect, useMemo, useState } from 'react';
import { Head } from '@inertiajs/react';
import axios from 'axios';
import { ChevronRight, Pencil, Plus, Trash2, Users } from 'lucide-react';
import Layout from '../../../Components/Layout';
import Dialog from '../../../Components/Dialog';
import { Alert, errorText, inputClass } from '../../../Components/Form';
import { DirectoryAction, DirectoryFilters, DirectoryList, DirectoryMetric, DirectoryPage, DirectoryRow } from '../../../Components/Directory';
import { loadPositionUnits, positionUnitKey } from '../../../lib/positionUnits';
import { useI18n } from '../../../I18nContext';

export default function Positions() {
    const { t } = useI18n();
    const [positions, setPositions] = useState([]);
    const [departments, setDepartments] = useState([]);
    const [loading, setLoading] = useState(true);
    const [search, setSearch] = useState('');
    const [departmentId, setDepartmentId] = useState('');
    const [error, setError] = useState('');
    const [removing, setRemoving] = useState(null);
    const [deleting, setDeleting] = useState(false);
    useEffect(() => {
        let active = true;
        Promise.all([axios.get('/api/admin/positions', { params: { scope: 'staff' } }), loadPositionUnits({ includeAcademic: false })])
            .then(([response, units]) => { if (active) { setPositions(response.data); setDepartments(units); } })
            .catch(error => { if (active) setError(errorText(error)); })
            .finally(() => { if (active) setLoading(false); });
        return () => { active = false; };
    }, []);
    const departmentName = key => departments.find(item => item.id === key)?.name || t('Не указано');
    const filtered = useMemo(() => {
        const query = search.trim().toLocaleLowerCase();
        return positions.filter(position => (!departmentId || positionUnitKey(position) === departmentId)
            && [position.name, departmentName(positionUnitKey(position))].some(value => String(value).toLocaleLowerCase().includes(query)));
    }, [departmentId, departments, positions, search]);
    const remove = async () => {
        if (!removing || deleting) return;
        setDeleting(true);
        setError('');
        try {
            await axios.delete('/api/admin/positions/' + removing.id);
            setPositions(current => current.filter(position => position.id !== removing.id));
        } catch (error) { setError(errorText(error)); }
        finally { setDeleting(false); setRemoving(null); }
    };
    return <Layout admin title={t('Должности АУП')} description={t('Список должностей подразделений АУП.')} actions={<DirectoryAction primary href="/admin/positions/create" icon={Plus} label={t('Добавить должность')} />}>
        <Head title={t('Должности АУП')} />
        <DirectoryPage loading={loading}>
            <Alert>{error}</Alert>
            <DirectoryFilters value={search} onChange={setSearch} placeholder={t('Поиск по должности или департаменту')}>
                <select value={departmentId} aria-label={t('Департамент')} className={inputClass.replace('mt-1 ', '')} onChange={event => setDepartmentId(event.target.value)}>
                    <option value="">{t('Все департаменты')}</option>
                    {departments.map(department => <option key={department.id} value={department.id}>{department.name}</option>)}
                </select>
            </DirectoryFilters>
            <DirectoryList count={filtered.length} total={positions.length} loading={loading} error={Boolean(error)} emptyMessage={t('Должности не найдены.')}>
                {filtered.map(position => <DirectoryRow key={position.id} name={position.name} href={'/admin/positions/' + position.id} secondary={departmentName(positionUnitKey(position))}
                    metrics={[<DirectoryMetric icon={Users}>{t('Сотрудники') + ': ' + (position.users?.length || 0)}</DirectoryMetric>]}
                    actions={<>
                        <DirectoryAction href={'/admin/positions/' + position.id} label={t('Открыть') + ': ' + position.name} icon={ChevronRight} />
                        <DirectoryAction href={'/admin/positions/' + position.id} label={t('Изменить') + ': ' + position.name} icon={Pencil} />
                        <DirectoryAction onClick={() => setRemoving(position)} label={t('Удалить') + ': ' + position.name} icon={Trash2} danger disabled={deleting} />
                    </>} />)}
            </DirectoryList>
        </DirectoryPage>
        <Dialog open={Boolean(removing)} title={t('Удалить должность?')} message={removing?.name || ''} confirmText={t('Удалить')} danger onClose={() => { if (!deleting) setRemoving(null); }} onConfirm={remove} />
    </Layout>;
}
