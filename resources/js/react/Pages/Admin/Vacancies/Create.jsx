import { useI18n as useTextI18n } from "../../../I18nContext";
import React, { useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import axios from 'axios';
import Layout from '../../../Components/Layout';
import { Alert, Field, errorText, inputClass, primaryButtonClass } from '../../../Components/Form';

export default function VacancyCreate() {
    const { t: _t } = useTextI18n();
    const [form, setForm] = useState({ title: '', description: '', type: '' }); const [saving, setSaving] = useState(false); const [error, setError] = useState('');
    const submit = async (event) => { event.preventDefault(); setSaving(true); setError(''); try { await axios.post('/api/admin/vacancies', form); window.location.assign('/admin/vacancies'); } catch (requestError) { setError(errorText(requestError)); } finally { setSaving(false); } };
    return <Layout admin title={_t("Добавить вакансию")} actions={<Link href="/admin/vacancies" className="rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold">{_t("Назад")}</Link>}><Head title={_t("Добавить вакансию")} /><div className="mx-auto max-w-3xl px-4 py-8"><form className="space-y-4 rounded-md border border-gray-200 bg-white p-5 shadow-sm" onSubmit={submit}><Alert>{_t(error)}</Alert><Field label={_t("Название вакансии")}><input required className={inputClass} value={form.title} onChange={(e) => setForm({ ...form, title: e.target.value })} /></Field><Field label={_t("Описание")}><textarea required rows="5" className={inputClass} value={form.description} onChange={(e) => setForm({ ...form, description: e.target.value })} /></Field><Field label={_t("Тип")}><select required className={inputClass} value={form.type} onChange={(e) => setForm({ ...form, type: e.target.value })}><option value="">{_t("Выберите тип")}</option><option value="staff">{_t("АУП")}</option><option value="pps">{_t("ППС")}</option></select></Field><button className={`${primaryButtonClass} w-full`} disabled={saving}>{_t(saving ? 'Сохранение...' : 'Сохранить вакансию')}</button></form></div></Layout>;
}
