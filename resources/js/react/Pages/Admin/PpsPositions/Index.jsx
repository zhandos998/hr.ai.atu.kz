import React, { useEffect, useMemo, useState } from 'react';
import { Head } from '@inertiajs/react';
import axios from 'axios';
import { Building2, GraduationCap, Pencil, Plus, Trash2 } from 'lucide-react';
import Layout from '../../../Components/Layout';
import Dialog from '../../../Components/Dialog';
import { Alert, errorText } from '../../../Components/Form';
import { DirectoryAction, DirectoryFilters, DirectoryList, DirectoryMetric, DirectoryPage, DirectoryRow } from '../../../Components/Directory';
import { useI18n } from '../../../I18nContext';

export default function PpsPositions() {
    const { t } = useI18n();
    const [positions, setPositions] = useState([]);
    const [loading, setLoading] = useState(true);
    const [search, setSearch] = useState('');
    const [error, setError] = useState('');
    const [removing, setRemoving] = useState(null);
    const [deleting, setDeleting] = useState(false);
    useEffect(() => {
        const controller = new AbortController();
        axios.get('/api/admin/pps-positions', { signal: controller.signal })
            .then(({ data }) => { if (!controller.signal.aborted) setPositions(data); })
            .catch(error => { if (!controller.signal.aborted) setError(errorText(error)); })
            .finally(() => { if (!controller.signal.aborted) setLoading(false); });
        return () => controller.abort();
    }, []);
    const filtered = useMemo(() => {
        const query = search.trim().toLocaleLowerCase();
        return positions.filter(position => [position.name, ...position.chairs.flatMap(chair => [chair.name, chair.faculty])]
            .filter(Boolean).some(value => value.toLocaleLowerCase().includes(query)));
    }, [positions, search]);
    const remove = async () => {
        if (!removing || deleting) return;
        setDeleting(true);
        setError('');
        try {
            await axios.delete('/api/admin/pps-positions/' + removing.id);
            setPositions(current => current.filter(position => position.id !== removing.id));
        } catch (error) { setError(errorText(error)); }
        finally { setDeleting(false); setRemoving(null); }
    };
    return <Layout admin title={t('Должности ППС')} description={t('Уникальные позиции преподавателей и их распределение по кафедрам.')} actions={<DirectoryAction primary href="/admin/pps-positions/create" icon={Plus} label={t('Добавить позицию')} />}>
        <Head title={t('Должности ППС')} />
        <DirectoryPage loading={loading}>
            <Alert>{error}</Alert>
            <DirectoryFilters value={search} onChange={setSearch} placeholder={t('Поиск по позиции, факультету или кафедре')} />
            <DirectoryList count={filtered.length} total={positions.length} loading={loading} error={Boolean(error)} emptyMessage={t('Должности ППС не найдены.')}>
                {filtered.map(position => <DirectoryRow key={position.id} name={position.name} secondary={t('Позиция ППС')} metrics={[
                    <DirectoryMetric icon={Building2}>{t('Кафедры') + ': ' + position.chairs_count}</DirectoryMetric>,
                    <DirectoryMetric icon={GraduationCap}>{t('Факультеты') + ': ' + position.faculties_count}</DirectoryMetric>,
                ]} actions={<>
                    <DirectoryAction href={'/admin/pps-positions/' + position.id + '/edit'} icon={Pencil} label={t('Изменить') + ': ' + position.name} />
                    <DirectoryAction onClick={() => setRemoving(position)} icon={Trash2} label={t('Удалить') + ': ' + position.name} danger disabled={deleting} />
                </>} />)}
            </DirectoryList>
        </DirectoryPage>
        <Dialog open={Boolean(removing)} title={t('Удалить должность?')} message={removing ? removing.name + '\n' + t('Должность будет удалена во всех кафедрах.') : ''} confirmText={t('Удалить')} danger onClose={() => { if (!deleting) setRemoving(null); }} onConfirm={remove} />
    </Layout>;
}
