import React, { useEffect, useId, useRef, useState } from 'react';
import { Link, usePage } from '@inertiajs/react';
import { ChevronDown, LogOut, UserRound } from 'lucide-react';
import { useAuth } from '../AuthContext';
import { useI18n } from '../I18nContext';

export default function ProfileMenu({ sidebar = false, onNavigate }) {
    const { t } = useI18n();
    const { logout } = useAuth();
    const { url } = usePage();
    const [open, setOpen] = useState(false);
    const [loggingOut, setLoggingOut] = useState(false);
    const root = useRef(null);
    const trigger = useRef(null);
    const panelId = useId();
    useEffect(() => { setOpen(false); }, [url]);
    useEffect(() => {
        if (!open) return;
        const closeOutside = event => { if (!root.current?.contains(event.target)) setOpen(false); };
        const escape = event => {
            if (event.key === 'Escape') { setOpen(false); trigger.current?.focus(); }
        };
        document.addEventListener('pointerdown', closeOutside);
        document.addEventListener('keydown', escape);
        return () => {
            document.removeEventListener('pointerdown', closeOutside);
            document.removeEventListener('keydown', escape);
        };
    }, [open]);
    return <div ref={root} data-admin-guide="profile" className={`relative ${sidebar ? 'w-full' : ''}`}>
        <button ref={trigger} type="button" aria-expanded={open} aria-controls={panelId}
            className={`flex items-center gap-2 rounded-md py-2 text-sm font-semibold ${sidebar ? 'w-full justify-between px-3 text-gray-600 hover:bg-[#f3f6fb] hover:text-[#174a9c]' : 'px-2 text-[#174a9c] hover:bg-blue-50'}`}
            onClick={() => setOpen(value => !value)}>
            <span>{t('profile')}</span><ChevronDown size={15} aria-hidden="true" className={`shrink-0 transition-transform ${open ? 'rotate-180' : ''}`} />
        </button>
        {open && <div id={panelId} className={`absolute z-50 rounded-md border border-gray-200 bg-white p-1 shadow-lg ${sidebar ? 'bottom-full left-0 mb-2 w-full' : 'right-0 top-full mt-2 w-48 max-w-[calc(100vw-2rem)]'}`}>
            <Link href="/profile" className="flex items-center gap-2 rounded px-3 py-2.5 text-sm font-semibold text-gray-700 hover:bg-blue-50 focus-visible:bg-blue-50" onClick={() => { setOpen(false); onNavigate?.(); }}>
                <UserRound size={16} aria-hidden="true" />{t('Открыть профиль')}
            </Link>
            <div className="my-1 border-t border-gray-100" />
            <button type="button" disabled={loggingOut} className="flex w-full items-center gap-2 rounded px-3 py-2.5 text-left text-sm font-semibold text-red-600 hover:bg-red-50 focus-visible:bg-red-50 disabled:opacity-50" onClick={async () => {
                setLoggingOut(true);
                try { await logout(); } finally { setLoggingOut(false); }
            }}><LogOut size={16} aria-hidden="true" />{t('logout')}</button>
        </div>}
    </div>;
}
