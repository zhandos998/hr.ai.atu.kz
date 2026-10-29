import React, { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';
import axios from 'axios';

const AuthContext = createContext(null);

const normalizeRole = (role) => ({
    compliance: 'lawyer',
    commission_member: 'commission',
}[role] || role || 'user');

const applyToken = (token) => {
    if (token) axios.defaults.headers.common.Authorization = `Bearer ${token}`;
    else delete axios.defaults.headers.common.Authorization;
};

const storedImpersonator = () => {
    if (typeof window === 'undefined') return null;

    try {
        return JSON.parse(window.localStorage.getItem('impersonator_user') || 'null');
    } catch {
        return null;
    }
};

export function AuthProvider({ children }) {
    const [user, setUser] = useState(null);
    const [impersonator, setImpersonator] = useState(storedImpersonator);
    const [loading, setLoading] = useState(true);

    const setSession = useCallback((token, nextUser) => {
        window.localStorage.setItem('token', token);
        window.localStorage.setItem('role', normalizeRole(nextUser?.role));
        applyToken(token);
        setUser(nextUser);
        setImpersonator(storedImpersonator());
    }, []);

    const clearSession = useCallback(() => {
        window.localStorage.removeItem('token');
        window.localStorage.removeItem('role');
        window.localStorage.removeItem('impersonator_token');
        window.localStorage.removeItem('impersonator_user');
        applyToken(null);
        setUser(null);
        setImpersonator(null);
    }, []);

    const refreshUser = useCallback(async () => {
        const { data } = await axios.get('/api/user');
        setUser(data);
        window.localStorage.setItem('role', normalizeRole(data?.role));
        return data;
    }, []);

    useEffect(() => {
        const token = window.localStorage.getItem('token');
        applyToken(token);
        if (!token) {
            setLoading(false);
            return;
        }

        refreshUser().catch(clearSession).finally(() => setLoading(false));
    }, [clearSession, refreshUser]);

    const returnFromImpersonation = useCallback(async () => {
        const adminToken = window.localStorage.getItem('impersonator_token');
        if (!adminToken) {
            clearSession();
            window.location.assign('/login');
            return;
        }

        try {
            await axios.post('/api/logout');
        } catch {
            // The original administrator session can still be restored.
        }

        applyToken(adminToken);

        try {
            const { data } = await axios.get('/api/user');
            window.localStorage.removeItem('impersonator_token');
            window.localStorage.removeItem('impersonator_user');
            setSession(adminToken, data);
            window.location.assign('/admin/users');
        } catch {
            clearSession();
            window.location.assign('/login');
        }
    }, [clearSession, setSession]);

    const value = useMemo(() => ({
        user,
        role: normalizeRole(user?.role),
        loading,
        impersonator,
        isImpersonating: Boolean(impersonator),
        setSession,
        clearSession,
        refreshUser,
        returnFromImpersonation,
        logout: async () => {
            window.google?.accounts?.id?.disableAutoSelect();
            try {
                await axios.post('/api/logout');
            } finally {
                clearSession();
                window.location.assign('/login');
            }
        },
    }), [clearSession, impersonator, loading, refreshUser, returnFromImpersonation, setSession, user]);

    return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth() {
    const context = useContext(AuthContext);
    if (!context) throw new Error('useAuth must be used inside AuthProvider');
    return context;
}

