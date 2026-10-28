import { useI18n as useTextI18n } from "../../I18nContext";
import React, { useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import axios from 'axios';
import Layout from '../../Components/Layout';
import { Alert, AuthCard, Field, errorText, inputClass, primaryButtonClass } from '../../Components/Form';
import { useAuth } from '../../AuthContext';

export default function Login() {
    const { t: _t } = useTextI18n();
    const { setSession } = useAuth();
    const [form, setForm] = useState({ login: '', password: '' });
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState('');
    const submit = async (event) => {
        event.preventDefault(); setLoading(true); setError('');
        try {
            const { data } = await axios.post('/api/login', form);
            localStorage.removeItem('impersonator_token'); localStorage.removeItem('impersonator_user');
            setSession(data.token, data.user);
            const next = new URLSearchParams(window.location.search).get('next') || '/';
            window.location.assign(next.startsWith('/') && !next.startsWith('//') ? next : '/');
        } catch (requestError) { setError(errorText(requestError, 'Ошибка входа. Проверьте данные.')); }
        finally { setLoading(false); }
    };
    return <Layout><Head title={_t("Вход")} /><AuthCard title={_t("Вход в HR-портал АТУ")}><form className="space-y-4" onSubmit={submit}><Alert>{_t(error)}</Alert><Field label={_t("Логин или email")}><input className={inputClass} type="text" name="login" required maxLength={255} autoComplete="username" autoCapitalize="none" spellCheck={false} value={form.login} onChange={(e) => setForm({ ...form, login: e.target.value })} /></Field><Field label={_t("Пароль")}><input className={inputClass} type="password" required autoComplete="current-password" value={form.password} onChange={(e) => setForm({ ...form, password: e.target.value })} /></Field><button className={`${primaryButtonClass} w-full`} disabled={loading}>{_t(loading ? 'Вход...' : 'Войти')}</button><div className="text-center text-sm"><Link href="/forgot-password" className="text-[#174a9c] hover:underline">{_t("Забыли пароль?")}</Link></div></form></AuthCard></Layout>;
}

