import { useI18n as useTextI18n } from '../I18nContext';
import React, { useEffect, useState } from 'react';
import { Head } from '@inertiajs/react';
import axios from 'axios';
import Layout from '../Components/Layout';
import SearchSelect from '../Components/SearchSelect';
import { Alert, AuthCard, Field, errorText, primaryButtonClass } from '../Components/Form';

export default function Apply() {
    const { t: _t } = useTextI18n();
    const vacancyId = new URLSearchParams(window.location.search).get('vacancy_id');
    const [vacancy, setVacancy] = useState(null);
    const [faculties, setFaculties] = useState([]);
    const [facultyId, setFacultyId] = useState(null);
    const [chairId, setChairId] = useState(null);
    const [file, setFile] = useState(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState('');
    useEffect(() => {
        if (!vacancyId) { window.location.assign('/vacancies'); return; }
        const controller = new AbortController();
        const load = async () => {
            try {
                const { data } = await axios.get(`/api/vacancies/${vacancyId}`, { signal: controller.signal });
                setVacancy(data);
                if (data.type === 'uvp') {
                    const response = await axios.get('/api/faculties', { signal: controller.signal });
                    setFaculties(response.data.data);
                }
            } catch (error) { if (!controller.signal.aborted) setError(errorText(error)); }
        };
        load();
        return () => controller.abort();
    }, [vacancyId]);
    const isUvp = vacancy?.type === 'uvp';
    const chairs = faculties.find(faculty => Number(faculty.id) === Number(facultyId))?.departments || [];
    const submit = async event => {
        event.preventDefault();
        if (!file) return;
        setLoading(true); setError('');
        const payload = new FormData();
        payload.append('vacancy_id', vacancyId); payload.append('resume', file);
        if (isUvp) {
            payload.append('uvp_faculty_id', facultyId); payload.append('uvp_academic_unit_id', chairId);
            payload.append('uvp_position_id', vacancy.uvp_position_id);
        }
        try { await axios.post('/api/applications', payload); window.location.assign('/profile'); }
        catch (error) { setError(errorText(error, 'Ошибка при отправке отклика.')); }
        finally { setLoading(false); }
    };
    return <Layout requireAuth><Head title={_t('Отклик на вакансию')} /><AuthCard title={_t('Отклик на вакансию')}>
        <Alert>{_t(error)}</Alert>
        {!vacancy ? <div className="text-center text-sm text-gray-500">{_t('Загрузка вакансии...')}</div> : <form className="space-y-4" onSubmit={submit}>
            <div><h2 className="font-bold text-[#174a9c]">{vacancy.uvp_position?.name || vacancy.title}</h2><p className="mt-2 whitespace-pre-wrap text-sm text-gray-600">{vacancy.description}</p></div>
            {isUvp && <>
                <Field label={_t('Факультет')}><SearchSelect value={facultyId} options={faculties} onChange={value => { setFacultyId(value); setChairId(null); }} placeholder={_t('Найти факультет')} /></Field>
                <Field label={_t('Кафедра')}><SearchSelect key={facultyId} value={chairId} options={chairs} onChange={setChairId} disabled={!facultyId} placeholder={_t('Найти кафедру')} /></Field>
            </>}
            <Field label={_t('Резюме')} hint={_t('PDF, DOCX, JPG или PNG до 2 МБ')}><input type="file" required accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" className="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm" onChange={event => setFile(event.target.files?.[0] || null)} /></Field>
            <button className={`${primaryButtonClass} w-full`} disabled={loading || (isUvp && (!facultyId || !chairId || !vacancy.uvp_position_id))}>{_t(loading ? 'Отправка...' : 'Отправить отклик')}</button>
        </form>}
    </AuthCard></Layout>;
}

