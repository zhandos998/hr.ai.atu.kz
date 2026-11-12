import { useI18n as useTextI18n } from "../../../I18nContext";
import React, { useEffect, useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import axios from 'axios';
import Layout from '../../../Components/Layout';
import SearchSelect from '../../../Components/SearchSelect';
import { Alert, Field, errorText, inputClass, primaryButtonClass } from '../../../Components/Form';

export default function VacancyCreate() {
    const { t: _t } = useTextI18n();
    const [form, setForm] = useState({ title: '', description: '', type: '', uvp_position_id: null });
    const [positions, setPositions] = useState([]);
    const [loadingPositions, setLoadingPositions] = useState(false);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState('');
    useEffect(() => {
        if (form.type !== 'uvp') return;
        const controller = new AbortController();
        setLoadingPositions(true);
        axios.get('/api/admin/uvp-positions', { signal: controller.signal })
            .then(({ data }) => { if (!controller.signal.aborted) setPositions(data); })
            .catch(error => { if (!controller.signal.aborted) setError(errorText(error)); })
            .finally(() => { if (!controller.signal.aborted) setLoadingPositions(false); });
        return () => controller.abort();
    }, [form.type]);
    const submit = async (event) => {
        event.preventDefault();
        if (form.type === 'uvp' && (!form.uvp_position_id || loadingPositions)) return;
        setSaving(true); setError('');
        try {
            const { uvp_position_id, ...payload } = form;
            await axios.post('/api/admin/vacancies', { ...payload, ...(form.type === 'uvp' ? { uvp_position_id } : {}) });
            window.location.assign('/admin/vacancies');
        } catch (requestError) { setError(errorText(requestError)); }
        finally { setSaving(false); }
    };
    return <Layout admin title={_t("Добавить вакансию")} actions={<Link href="/admin/vacancies" className="rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold">{_t("Назад")}</Link>}>
        <Head title={_t("Добавить вакансию")} />
        <div className="mx-auto max-w-3xl px-4 py-8"><form className="space-y-4 rounded-md border border-gray-200 bg-white p-5 shadow-sm" onSubmit={submit}>
            <Alert>{_t(error)}</Alert>
            <Field label={_t("Название вакансии")}><input required className={inputClass} value={form.title} onChange={(e) => setForm({ ...form, title: e.target.value })} /></Field>
            <Field label={_t("Описание")}><textarea required rows="5" className={inputClass} value={form.description} onChange={(e) => setForm({ ...form, description: e.target.value })} /></Field>
            <Field label={_t("Тип")}><select required className={inputClass} value={form.type} onChange={(e) => setForm({ ...form, type: e.target.value, uvp_position_id: null })}><option value="">{_t("Выберите тип")}</option><option value="staff">{_t("АУП")}</option><option value="pps">{_t("ППС")}</option><option value="uvp">{_t("УВП")}</option></select></Field>
            {form.type === 'uvp' && <Field label={_t('Должность УВП')}><SearchSelect value={form.uvp_position_id} options={positions} onChange={value => setForm({ ...form, uvp_position_id: value })} disabled={saving || loadingPositions} placeholder={_t('Найти должность')} /></Field>}
            <button className={`${primaryButtonClass} w-full`} disabled={saving || (form.type === 'uvp' && (loadingPositions || !form.uvp_position_id))}>{_t(saving ? 'Сохранение...' : 'Сохранить вакансию')}</button>
        </form></div>
    </Layout>;
}
