import { useI18n as useTextI18n } from "../I18nContext";
import React from 'react';
import { primaryButtonClass, secondaryButtonClass } from './Form';

export default function Dialog({ open, title, message, confirmText = 'Подтвердить', cancelText = 'Отмена', danger = false, onConfirm, onClose }) {
    const { t: _t } = useTextI18n();
    if (!open) return null;
    return <div className="fixed inset-0 z-[70] flex items-center justify-center bg-black/50 p-4" onMouseDown={(event) => event.target === event.currentTarget && onClose()}><div className="w-full max-w-md rounded-md border border-gray-200 bg-white p-5 shadow-2xl"><h2 className="text-lg font-bold text-gray-900">{_t(title)}</h2><p className="mt-3 whitespace-pre-wrap text-sm leading-6 text-gray-600">{_t(message)}</p><div className="mt-6 flex justify-end gap-2"><button type="button" className={secondaryButtonClass} onClick={onClose}>{_t(cancelText)}</button><button type="button" className={danger ? 'rounded-md bg-red-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-red-700' : primaryButtonClass} onClick={onConfirm}>{_t(confirmText)}</button></div></div></div>;
}

