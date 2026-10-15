import { useI18n as useTextI18n } from "../../I18nContext";
import React, { useState } from 'react';
import { Head } from '@inertiajs/react';
import axios from 'axios';
import Layout from '../../Components/Layout';
import { Alert, AuthCard, Field, errorText, inputClass, primaryButtonClass } from '../../Components/Form';

export default function ForgotPassword() {
    const { t: _t } = useTextI18n();
    const [email, setEmail] = useState(''); const [loading, setLoading] = useState(false); const [status, setStatus] = useState(''); const [error, setError] = useState('');
    const submit = async (event) => { event.preventDefault(); setLoading(true); setStatus(''); setError(''); try { const { data } = await axios.post('/api/password/forgot', { email }); setStatus(data.message); } catch (requestError) { setError(errorText(requestError, 'Ошибка отправки письма.')); } finally { setLoading(false); } };
    return <Layout><Head title={_t("Восстановление пароля")} /><AuthCard title={_t("Забыли пароль")}><form className="space-y-4" onSubmit={submit}><Alert type="success">{_t(status)}</Alert><Alert>{_t(error)}</Alert><Field label={_t("Email")}><input className={inputClass} type="email" required placeholder={_t("you@atu.kz")} value={email} onChange={(e) => setEmail(e.target.value)} /></Field><button className={`${primaryButtonClass} w-full`} disabled={loading}>{_t(loading ? 'Отправка...' : 'Отправить ссылку')}</button></form></AuthCard></Layout>;
}

