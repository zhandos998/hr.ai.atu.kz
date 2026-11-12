import React, { useEffect, useState } from 'react';
import axios from 'axios';
import SearchSelect from './SearchSelect';
import { Alert, Field, errorText, primaryButtonClass } from './Form';
import { useI18n } from '../I18nContext';

export default function UvpApplicationForm({ application, units, onChanged }) {
    const { t } = useI18n();
    const [positions, setPositions] = useState([]);
    const [form, setForm] = useState({ uvp_faculty_id: application.uvp_faculty_id, uvp_academic_unit_id: application.uvp_academic_unit_id, uvp_position_id: application.vacancy?.uvp_position_id });
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState('');
    useEffect(() => {
        const controller = new AbortController();
        axios.get('/api/admin/uvp-positions', { signal: controller.signal }).then(({ data }) => setPositions(data)).catch(error => { if (!controller.signal.aborted) setError(errorText(error)); });
        return () => controller.abort();
    }, []);
    const submit = async event => {
        event.preventDefault(); setSaving(true); setError('');
        try { const { data } = await axios.put(`/api/admin/applications/${application.id}/uvp-details`, form); onChanged(data.application); }
        catch (error) { setError(errorText(error)); }
        finally { setSaving(false); }
    };
    return <form onSubmit={submit} className="space-y-4 border-y border-gray-200 py-5">
        <h2 className="font-semibold">{t('Данные УВП')}</h2><Alert>{t(error)}</Alert>
        <div className="grid gap-4 md:grid-cols-2">
            <Field label={t('Факультет')}><SearchSelect value={form.uvp_faculty_id} options={units.filter(unit => unit.kind === 'faculty')} placeholder={t('Найти факультет')} onChange={value => setForm({ ...form, uvp_faculty_id: value, uvp_academic_unit_id: null })} /></Field>
            <Field label={t('Кафедра')}><SearchSelect key={form.uvp_faculty_id} value={form.uvp_academic_unit_id} options={units.filter(unit => unit.kind === 'chair' && Number(unit.parent_id) === Number(form.uvp_faculty_id))} placeholder={t('Найти кафедру')} disabled={!form.uvp_faculty_id} onChange={value => setForm({ ...form, uvp_academic_unit_id: value })} /></Field>
            <Field label={t('Должность УВП')}><SearchSelect value={form.uvp_position_id} options={positions} placeholder={t('Найти должность')} onChange={value => setForm({ ...form, uvp_position_id: value })} /></Field>
        </div>
        <div className="flex justify-end"><button className={primaryButtonClass} disabled={saving || Boolean(application.archived_at) || !form.uvp_faculty_id || !form.uvp_academic_unit_id || !form.uvp_position_id}>{t('Сохранить')}</button></div>
    </form>;
}
