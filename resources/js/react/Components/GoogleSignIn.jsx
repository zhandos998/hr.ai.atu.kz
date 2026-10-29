import React, { useEffect, useRef, useState } from 'react';
import axios from 'axios';
import { useAuth } from '../AuthContext';
import { useI18n } from '../I18nContext';
import { errorText } from './Form';
import FloatingNotification from './FloatingNotification';

let sdkPromise;
function loadGoogle() {
    if (window.google?.accounts?.id) return Promise.resolve(window.google.accounts.id);
    if (!sdkPromise) {
        sdkPromise = new Promise((resolve, reject) => {
            const script = document.createElement('script');
            const timeout = window.setTimeout(() => fail(), 15000);
            const fail = () => {
                window.clearTimeout(timeout);
                script.remove();
                sdkPromise = null;
                reject(new Error('Google SDK unavailable'));
            };
            script.src = 'https://accounts.google.com/gsi/client';
            script.async = true;
            script.onload = () => {
                window.clearTimeout(timeout);
                if (window.google?.accounts?.id) resolve(window.google.accounts.id);
                else fail();
            };
            script.onerror = fail;
            document.head.appendChild(script);
        });
    }
    return sdkPromise;
}

export default function GoogleSignIn({ button = true }) {
    const { user, loading, setSession } = useAuth();
    const { locale, t } = useI18n();
    const target = useRef(null);
    const [enabled, setEnabled] = useState(false);
    const [error, setError] = useState('');
    const [busy, setBusy] = useState(false);

    useEffect(() => {
        if (loading || user) return;
        let active = true;
        let google;
        let submitting = false;
        let refreshTimer;
        const initialize = async (prompt = true) => {
            const { data } = await axios.get('/api/auth/google/challenge');
            if (!active || !data.enabled) return;
            setEnabled(true);
            google = await loadGoogle();
            if (!active) return;
            google.initialize({
                client_id: data.client_id,
                nonce: data.nonce,
                auto_select: false,
                context: 'signin',
                callback: async ({ credential }) => {
                    if (!active || submitting || window.localStorage.getItem('token')) return;
                    submitting = true;
                    window.clearTimeout(refreshTimer);
                    setBusy(true);
                    setError('');
                    try {
                        const response = await axios.post('/api/auth/google', { credential, nonce: data.nonce });
                        if (!active) return;
                        localStorage.removeItem('impersonator_token');
                        localStorage.removeItem('impersonator_user');
                        setSession(response.data.token, response.data.user);
                        const next = new URLSearchParams(window.location.search).get('next') || '/';
                        window.location.assign(next.startsWith('/') && !next.startsWith('//') && !next.includes('\\') ? next : '/');
                    } catch (requestError) {
                        if (!active) return;
                        setError(errorText(requestError, t('Не удалось войти через Google. Используйте email и пароль.')));
                        submitting = false;
                        setBusy(false);
                        initialize(false).catch(unavailable);
                    }
                },
            });
            await new Promise(window.requestAnimationFrame);
            if (!active) return;
            if (button && target.current) {
                target.current.replaceChildren();
                google.renderButton(target.current, {
                    type: 'standard', theme: 'outline', size: 'large', text: 'signin_with',
                    shape: 'rectangular', locale: locale === 'kk' ? 'kk' : 'ru',
                    width: Math.min(360, target.current.clientWidth),
                });
            }
            if (prompt) google.prompt();
            // A tab left open must not keep submitting an expired browser challenge.
            refreshTimer = window.setTimeout(() => initialize(false).catch(unavailable), 240000);
        };
        const unavailable = () => {
            if (active) setError(t('Не удалось войти через Google. Используйте email и пароль.'));
        };
        initialize().catch(unavailable);
        return () => {
            active = false;
            window.clearTimeout(refreshTimer);
            google?.cancel();
        };
    }, [button, loading, user, locale, setSession]);

    if (user || loading || (!enabled && !error)) return null;
    return <>
        <FloatingNotification title="Не удалось войти" message={error} onClose={() => setError('')} />
        {button && enabled && <div className="mt-5 space-y-3" aria-busy={busy}>
            <div className="flex items-center gap-3 text-xs text-gray-400"><span className="h-px flex-1 bg-gray-200" />{t('или')}<span className="h-px flex-1 bg-gray-200" /></div>
            <div ref={target} className={`flex min-h-10 w-full justify-center ${busy ? 'pointer-events-none opacity-60' : ''}`} />
            {busy && <p className="text-center text-sm text-gray-500" role="status">{t('Вход...')}</p>}
        </div>}
    </>;
}
