import { useI18n as useTextI18n } from "../../I18nContext";
import React, { useState } from 'react';
import { Head } from '@inertiajs/react';
import axios from 'axios';
import Layout from '../../Components/Layout';
import { Alert, AuthCard, Field, errorText, inputClass, primaryButtonClass } from '../../Components/Form';

export default function ResetPassword() {
    const { t: _t } = useTextI18n();
    const query = new URLSearchParams(window.location.search);
    const [form, setForm] = useState({ email: query.get('email') || '', password: '', password_confirmation: '' });
    const [loading, setLoading] = useState(false); const [status, setStatus] = useState(''); const [error, setError] = useState('');
    const update = (key) => (event) => setForm((current) => ({ ...current, [key]: event.target.value }));
    const submit = async (event) => { event.preventDefault(); const token = query.get('token'); if (!token || !form.email) { setError('Некорректная ссылка для сброса пароля.'); return; } setLoading(true); setError(''); try { const { data } = await axios.post('/api/password/reset', { ...form, token }); setStatus(data.message); window.setTimeout(() => window.location.assign('/login'), 1200); } catch (requestError) { setError(errorText(requestError, 'Не удалось сбросить пароль.')); } finally { setLoading(false); } };
    return <Layout><Head title={_t("Сброс пароля")} /><AuthCard title={_t("Сброс пароля")}><form className="space-y-4" onSubmit={submit}><Alert type="success">{_t(status)}</Alert><Alert>{_t(error)}</Alert><Field label={_t("Email")}><input className={inputClass} type="email" required value={form.email} onChange={update('email')} /></Field><Field label={_t("Новый пароль")}><input className={inputClass} type="password" minLength="4" required value={form.password} onChange={update('password')} /></Field><Field label={_t("Подтвердите пароль")}><input className={inputClass} type="password" minLength="4" required value={form.password_confirmation} onChange={update('password_confirmation')} /></Field><button className={`${primaryButtonClass} w-full`} disabled={loading}>{_t(loading ? 'Сохранение...' : 'Сменить пароль')}</button></form></AuthCard></Layout>;
}

