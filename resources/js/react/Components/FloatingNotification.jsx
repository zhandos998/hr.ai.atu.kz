import React from 'react';
import { createPortal } from 'react-dom';
import { CircleAlert, X } from 'lucide-react';
import { useI18n } from '../I18nContext';

export default function FloatingNotification({ title, message, onClose }) {
    const { t } = useI18n();
    if (!message || typeof document === 'undefined') return null;

    return createPortal(
        <div className="pointer-events-none fixed inset-x-4 z-[10000] sm:left-auto sm:w-[420px]" style={{ top: 'max(1rem, env(safe-area-inset-top))' }}>
            <div role="alert" aria-atomic="true" className="pointer-events-auto flex items-start gap-3 rounded-lg border border-red-200 border-l-4 border-l-red-500 bg-white p-4 shadow-xl shadow-gray-900/10">
                <span className="flex size-9 shrink-0 items-center justify-center rounded-md bg-red-50 text-red-600" aria-hidden="true">
                    <CircleAlert size={20} />
                </span>
                <div className="min-w-0 flex-1">
                    <p className="text-sm font-semibold leading-5 text-gray-900">{t(title)}</p>
                    <p className="mt-1 break-words text-sm leading-6 text-gray-600">{t(message)}</p>
                </div>
                <button type="button" onClick={onClose} aria-label={t('Закрыть уведомление')} title={t('Закрыть уведомление')} className="-mr-1 -mt-1 flex size-8 shrink-0 items-center justify-center rounded-md text-gray-400 transition hover:bg-gray-100 hover:text-gray-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600">
                    <X size={18} aria-hidden="true" />
                </button>
            </div>
        </div>,
        document.body,
    );
}
