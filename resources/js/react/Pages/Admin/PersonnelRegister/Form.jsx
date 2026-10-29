import React, { useEffect, useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import axios from 'axios';
import { ArrowLeft, Save } from 'lucide-react';
import Layout from '../../../Components/Layout';
import { Alert, Field, errorText, inputClass, primaryButtonClass, secondaryButtonClass } from '../../../Components/Form';
import { useAuth } from '../../../AuthContext';
import { useI18n } from '../../../I18nContext';
import { registers } from './config';

const initialForm = { employee_name: '', position_name: '', document_number: '', starts_on: '', ends_on: '', diagnosis: '', leave_type: '' };

export default function PersonnelRegisterForm() {
    const { registerType, recordId } = usePage().props;
    const config = registers[registerType];
    const { t } = useI18n();
    const { loading: authLoading, role } = useAuth();
    const [form, setForm] = useState(initialForm);
    const [loading, setLoading] = useState(Boolean(recordId));
    const [ready, setReady] = useState(!recordId);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState('');
    const [fields, setFields] = useState({});
    const title = recordId ? config.edit : config.add;
    const back = recordId ? `${config.path}/${recordId}` : config.path;

    useEffect(() => {
        if (authLoading || role !== 'admin') return;
        setForm(initialForm);
        setError('');
        setFields({});
        setReady(!recordId);
        if (!recordId) { setLoading(false); return; }
        const controller = new AbortController();
        setLoading(true);
        axios.get(`/api/admin/personnel-register/${registerType}/${recordId}`, { signal: controller.signal })
            .then(({ data }) => { setForm(Object.fromEntries(Object.keys(initialForm).map(key => [key, data[key] || '']))); setReady(true); })
            .catch(requestError => { if (!axios.isCancel(requestError)) setError(errorText(requestError)); })
            .finally(() => { if (!controller.signal.aborted) setLoading(false); });
        return () => controller.abort();
    }, [registerType, recordId, authLoading, role]);

    const change = (key, value) => { setForm(current => ({ ...current, [key]: value })); setFields(current => ({ ...current, [key]: undefined })); };
    const input = (key, label, { type = 'text', maxLength = 255, required = true, min } = {}) => <Field label={t(label)}>
        <input type={type} required={required} maxLength={type === 'text' ? maxLength : undefined} min={min} value={form[key]} name={key} className={inputClass} aria-invalid={Boolean(fields[key])} aria-describedby={fields[key] ? `error-${key}` : undefined} onChange={event => change(key, event.target.value)} />
        {fields[key] && <span id={`error-${key}`} className="mt-1 block text-xs text-red-600">{fields[key][0]}</span>}
    </Field>;
    const submit = async event => {
        event.preventDefault();
        setSaving(true);
        setError('');
        setFields({});
        const { diagnosis, leave_type, ...common } = form;
        const payload = { ...common, [config.detailField]: form[config.detailField] };
        try {
            const { data } = recordId
                ? await axios.put(`/api/admin/personnel-register/${registerType}/${recordId}`, payload)
                : await axios.post(`/api/admin/personnel-register/${registerType}`, payload);
            router.visit(`${config.path}/${data.id}`);
        } catch (requestError) {
            setError(errorText(requestError));
            setFields(requestError.response?.data?.errors || {});
        } finally { setSaving(false); }
    };

    return <Layout admin title={t(title)} actions={<Link href={back} className={`${secondaryButtonClass} inline-flex items-center gap-2`}><ArrowLeft size={16} />{t('Назад')}</Link>}>
        <Head title={t(title)} />
        <div className="mx-auto max-w-4xl px-4 py-6">
            <Alert>{error}</Alert>
            {loading ? <div className="py-14 text-center text-sm text-gray-500">{t('Загрузка...')}</div> : ready && <form className="space-y-6" onSubmit={submit}>
                <fieldset disabled={saving} className="grid gap-5 disabled:opacity-60 sm:grid-cols-2">
                    <div className="sm:col-span-2">{input('employee_name', 'ФИО сотрудника')}</div>
                    <div className="sm:col-span-2">{input('position_name', 'Должность', { maxLength: 500 })}</div>
                    <div className="sm:col-span-2">{input('document_number', config.documentLabel)}</div>
                    {input('starts_on', 'Дата начала', { type: 'date' })}
                    {input('ends_on', 'Дата окончания', { type: 'date', min: form.starts_on || undefined })}
                    <div className="sm:col-span-2">{registerType === 'sick_leave' ? <Field label={t('Диагноз')}>
                        <textarea name="diagnosis" rows={3} maxLength={2000} className={inputClass} value={form.diagnosis} aria-invalid={Boolean(fields.diagnosis)} onChange={event => change('diagnosis', event.target.value)} />
                        {fields.diagnosis && <span className="mt-1 block text-xs text-red-600">{fields.diagnosis[0]}</span>}
                    </Field> : input('leave_type', 'Вид отпуска')}</div>
                </fieldset>
                <div className="flex flex-wrap justify-end gap-3 border-t border-gray-200 pt-5">
                    <Link href={back} className={secondaryButtonClass}>{t('Отмена')}</Link>
                    <button type="submit" disabled={saving} className={`${primaryButtonClass} inline-flex items-center gap-2`}><Save size={16} />{t(saving ? 'Сохранение...' : 'Сохранить')}</button>
                </div>
            </form>}
        </div>
    </Layout>;
}
