import React, { useEffect, useState } from 'react';
import { Head, Link, usePage } from '@inertiajs/react';
import axios from 'axios';
import { ArrowLeft, Pencil } from 'lucide-react';
import Layout from '../../../Components/Layout';
import { Alert, errorText, primaryButtonClass, secondaryButtonClass } from '../../../Components/Form';
import { useAuth } from '../../../AuthContext';
import { useI18n } from '../../../I18nContext';
import { registers, formatRegisterDate } from './config';

export default function PersonnelRegisterShow() {
    const { registerType, recordId } = usePage().props;
    const config = registers[registerType];
    const { t, locale } = useI18n();
    const { loading: authLoading, role } = useAuth();
    const [entry, setEntry] = useState(null);
    const [error, setError] = useState('');
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        if (authLoading || role !== 'admin') return;
        const controller = new AbortController();
        setLoading(true);
        setEntry(null);
        setError('');
        axios.get(`/api/admin/personnel-register/${registerType}/${recordId}`, { signal: controller.signal })
            .then(({ data }) => setEntry(data))
            .catch(requestError => { if (!axios.isCancel(requestError)) setError(errorText(requestError)); })
            .finally(() => { if (!controller.signal.aborted) setLoading(false); });
        return () => controller.abort();
    }, [registerType, recordId, authLoading, role]);

    return <Layout admin title={`${t(config.singular)} №${recordId}`} actions={<>
        <Link href={config.path} className={`${secondaryButtonClass} inline-flex items-center gap-2`}><ArrowLeft size={16} />{t('К списку')}</Link>
        {entry && <Link href={`${config.path}/${recordId}/edit`} className={`${primaryButtonClass} inline-flex items-center gap-2`}><Pencil size={16} />{t('Изменить')}</Link>}
    </>}>
        <Head title={`${t(config.singular)} №${recordId}`} />
        <div className="mx-auto max-w-4xl space-y-5 px-4 py-6">
            <Alert>{error}</Alert>
            {loading ? <div className="py-14 text-center text-sm text-gray-500">{t('Загрузка...')}</div> : entry && <dl className="divide-y divide-gray-200">
                {[
                    ['ФИО сотрудника', entry.employee_name],
                    ['Должность', entry.position_name],
                    [config.documentLabel, entry.document_number],
                    ['Дата начала', formatRegisterDate(entry.starts_on, locale)],
                    ['Дата окончания', formatRegisterDate(entry.ends_on, locale)],
                    [config.detailLabel, entry[config.detailField] || '-'],
                ].map(([label, value]) => <div key={label} className="grid gap-2 py-5 sm:grid-cols-[220px_minmax(0,1fr)] sm:gap-6">
                    <dt className="text-sm text-gray-500">{t(label)}</dt><dd className="whitespace-pre-wrap break-words text-sm font-medium leading-6 text-gray-900">{value}</dd>
                </div>)}
            </dl>}
        </div>
    </Layout>;
}
