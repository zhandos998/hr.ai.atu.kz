import { useI18n as useTextI18n } from "../../I18nContext";
import React, { useEffect, useState } from 'react';
import { Head } from '@inertiajs/react';
import axios from 'axios';
import Layout from '../../Components/Layout';
import { Alert, AuthCard, errorText, primaryButtonClass } from '../../Components/Form';
import { useAuth } from '../../AuthContext';

export default function EmailChangeConfirm() {
    const { t: _t } = useTextI18n();
    const { setSession } = useAuth(); const [loading, setLoading] = useState(false); const [status, setStatus] = useState(''); const [error, setError] = useState('');
    const confirm = async () => { const token = new URLSearchParams(window.location.search).get('token'); if (!token) { setError('Некорректная ссылка подтверждения.'); return; } setLoading(true); setError(''); try { const { data } = await axios.post('/api/user/email/change-confirm', { token }); setSession(data.token, data.user); setStatus('Email успешно изменен. Перенаправляем в профиль...'); window.setTimeout(() => window.location.assign('/profile'), 900); } catch (requestError) { setError(errorText(requestError, 'Не удалось подтвердить смену email.')); } finally { setLoading(false); } };
    useEffect(() => { confirm(); }, []);
    return <Layout><Head title={_t("Подтверждение смены email")} /><AuthCard title={_t("Подтверждение смены email")}><div className="space-y-4"><Alert type="success">{_t(status)}</Alert><Alert>{_t(error)}</Alert><button type="button" className={`${primaryButtonClass} w-full`} disabled={loading} onClick={confirm}>{_t(loading ? 'Подтверждение...' : 'Подтвердить новый email')}</button></div></AuthCard></Layout>;
}
