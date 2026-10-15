import { useI18n as useTextI18n } from "../../I18nContext";
import React, { useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import axios from 'axios';
import Layout from '../../Components/Layout';
import { Alert, AuthCard, Field, errorText, inputClass, primaryButtonClass } from '../../Components/Form';

export default function Register() {
    const { t: _t } = useTextI18n();
    const [form, setForm] = useState({ name: '', email: '', phone: '', password: '', password_confirmation: '' });
    const [loading, setLoading] = useState(false); const [status, setStatus] = useState(''); const [error, setError] = useState('');
    const update = (key) => (event) => setForm((current) => ({ ...current, [key]: event.target.value }));
    const submit = async (event) => { event.preventDefault(); setLoading(true); setStatus(''); setError(''); try { const { data } = await axios.post('/api/register', form); setStatus(data.message || 'Регистрация успешна. Подтвердите email.'); window.setTimeout(() => window.location.assign('/login'), 1200); } catch (requestError) { setError(errorText(requestError, 'Ошибка регистрации. Проверьте данные.')); } finally { setLoading(false); } };
    return <Layout><Head title={_t("Регистрация")} /><AuthCard title={_t("Регистрация в HR-портале АТУ")}><form className="space-y-4" onSubmit={submit}><Alert type="success">{_t(status)}</Alert><Alert>{_t(error)}</Alert><Field label={_t("ФИО")}><input className={inputClass} required value={form.name} onChange={update('name')} /></Field><Field label={_t("Email")}><input className={inputClass} type="email" required value={form.email} onChange={update('email')} /></Field><Field label={_t("Телефон")}><input className={inputClass} value={form.phone} onChange={update('phone')} /></Field><Field label={_t("Пароль")}><input className={inputClass} type="password" minLength="4" required value={form.password} onChange={update('password')} /></Field><Field label={_t("Подтверждение пароля")}><input className={inputClass} type="password" minLength="4" required value={form.password_confirmation} onChange={update('password_confirmation')} /></Field><button className={`${primaryButtonClass} w-full`} disabled={loading}>{_t(loading ? 'Регистрация...' : 'Зарегистрироваться')}</button><p className="text-center text-sm text-gray-600">{_t("Уже есть аккаунт?")}<Link href="/login" className="text-[#174a9c] hover:underline">{_t("Войти")}</Link></p></form></AuthCard></Layout>;
}

