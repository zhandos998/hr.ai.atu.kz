import { useI18n as useTextI18n } from "../../I18nContext";
import React, { useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import axios from 'axios';
import Layout from '../../Components/Layout';
import { Alert, AuthCard, Field, errorText, inputClass, primaryButtonClass } from '../../Components/Form';

export default function ChangePassword() {
    const { t: _t } = useTextI18n();
    const [form, setForm] = useState({ current_password: '', password: '', password_confirmation: '' }); const [loading, setLoading] = useState(false); const [status, setStatus] = useState(''); const [error, setError] = useState('');
    const update = (key) => (event) => setForm((current) => ({ ...current, [key]: event.target.value }));
    const submit = async (event) => { event.preventDefault(); setLoading(true); setStatus(''); setError(''); try { const { data } = await axios.post('/api/user/password', form); setStatus(data.message); setForm({ current_password: '', password: '', password_confirmation: '' }); } catch (requestError) { setError(errorText(requestError, 'Не удалось обновить пароль.')); } finally { setLoading(false); } };
    return <Layout requireAuth><Head title={_t("Смена пароля")} /><AuthCard title={_t("Сменить пароль")}><form className="space-y-4" onSubmit={submit}><Alert type="success">{_t(status)}</Alert><Alert>{_t(error)}</Alert><Field label={_t("Текущий пароль")}><input className={inputClass} type="password" required value={form.current_password} onChange={update('current_password')} /></Field><Field label={_t("Новый пароль")}><input className={inputClass} type="password" minLength="4" required value={form.password} onChange={update('password')} /></Field><Field label={_t("Подтвердите новый пароль")}><input className={inputClass} type="password" minLength="4" required value={form.password_confirmation} onChange={update('password_confirmation')} /></Field><button className={`${primaryButtonClass} w-full`} disabled={loading}>{_t(loading ? 'Сохранение...' : 'Обновить пароль')}</button><Link href="/profile" className="block text-center text-sm text-[#174a9c]">{_t("Назад в профиль")}</Link></form></AuthCard></Layout>;
}

