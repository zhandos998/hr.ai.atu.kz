import { useI18n as useTextI18n } from "../I18nContext";
import React, { useEffect, useState } from 'react';
import { Head } from '@inertiajs/react';
import axios from 'axios';
import Layout from '../Components/Layout';
import { Alert, AuthCard, Field, errorText, primaryButtonClass } from '../Components/Form';

export default function Apply() {
    const { t: _t } = useTextI18n();
    const vacancyId = new URLSearchParams(window.location.search).get('vacancy_id');
    const [vacancy, setVacancy] = useState(null); const [file, setFile] = useState(null); const [loading, setLoading] = useState(false); const [error, setError] = useState('');
    useEffect(() => { if (!vacancyId) { window.location.assign('/vacancies'); return; } axios.get(`/api/vacancies/${vacancyId}`).then(({ data }) => setVacancy(data)).catch(() => window.location.assign('/vacancies')); }, [vacancyId]);
    const submit = async (event) => { event.preventDefault(); if (!file) return; setLoading(true); setError(''); const payload = new FormData(); payload.append('vacancy_id', vacancyId); payload.append('resume', file); try { await axios.post('/api/applications', payload); window.location.assign('/profile'); } catch (requestError) { setError(errorText(requestError, 'Ошибка при отправке отклика.')); } finally { setLoading(false); } };
    return <Layout requireAuth><Head title={_t("Отклик на вакансию")} /><AuthCard title={_t("Отклик на вакансию")}>{!vacancy ? <div className="text-center text-sm text-gray-500">{_t("Загрузка вакансии...")}</div> : <form className="space-y-4" onSubmit={submit}><div><h2 className="font-bold text-[#174a9c]">{_t(vacancy.title)}</h2><p className="mt-2 whitespace-pre-wrap text-sm text-gray-600">{_t(vacancy.description)}</p></div><Alert>{_t(error)}</Alert><Field label={_t("Резюме")} hint={_t("PDF, DOCX, JPG или PNG до 2 МБ")}><input type="file" required accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" className="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm" onChange={(event) => setFile(event.target.files?.[0] || null)} /></Field><button className={`${primaryButtonClass} w-full`} disabled={loading}>{_t(loading ? 'Отправка...' : 'Отправить отклик')}</button></form>}</AuthCard></Layout>;
}

