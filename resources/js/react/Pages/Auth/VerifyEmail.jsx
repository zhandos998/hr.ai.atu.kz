import { useI18n as useTextI18n } from "../../I18nContext";
import React, { useEffect, useState } from 'react';
import { Head } from '@inertiajs/react';
import axios from 'axios';
import Layout from '../../Components/Layout';
import { Alert, AuthCard, errorText, primaryButtonClass } from '../../Components/Form';
import { useAuth } from '../../AuthContext';

export default function VerifyEmail() {
    const { t: _t } = useTextI18n();
    const { setSession } = useAuth(); const [loading, setLoading] = useState(false); const [status, setStatus] = useState(''); const [error, setError] = useState('');
    const verify = async () => { const query = new URLSearchParams(window.location.search); const email = query.get('email'); const token = query.get('token'); if (!email || !token) { setError('Некорректная ссылка подтверждения.'); return; } setLoading(true); setError(''); try { const { data } = await axios.post('/api/email/verify', { email, token }); setSession(data.token, data.user); setStatus('Email подтвержден. Перенаправляем в профиль...'); window.setTimeout(() => window.location.assign('/profile'), 800); } catch (requestError) { setError(errorText(requestError, 'Не удалось подтвердить email.')); } finally { setLoading(false); } };
    useEffect(() => { verify(); }, []);
    return <Layout><Head title={_t("Подтверждение email")} /><AuthCard title={_t("Подтверждение email")}><div className="space-y-4"><Alert type="success">{_t(status)}</Alert><Alert>{_t(error)}</Alert><button type="button" className={`${primaryButtonClass} w-full`} disabled={loading} onClick={verify}>{_t(loading ? 'Проверка...' : 'Подтвердить email')}</button></div></AuthCard></Layout>;
}

