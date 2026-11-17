import React from 'react';
import { Link } from '@inertiajs/react';
import { useI18n } from '../I18nContext';
import { inputClass } from './Form';

export const directoryFormClass = 'space-y-5 rounded-md border border-gray-200 bg-white p-5 shadow-sm';

export function DirectoryPage({ children, loading = false, form = false }) {
    return <div data-directory-page data-guide-busy={loading} className={`mx-auto space-y-5 px-4 py-8 ${form ? 'max-w-3xl' : 'max-w-7xl'}`}>{children}</div>;
}

export function DirectoryFilters({ value, onChange, placeholder, children }) {
    return <section data-directory-filters className={`rounded-md border border-gray-200 bg-white p-4 shadow-sm ${children ? 'grid gap-3 md:grid-cols-[minmax(0,1fr)_280px]' : ''}`}>
        <input type="search" aria-label={placeholder} placeholder={placeholder} value={value} onChange={event => onChange(event.target.value)} className={inputClass.replace('mt-1 ', '')} />
        {children}
    </section>;
}

export function DirectoryList({ count, total, loading = false, emptyMessage, heading, label, toolbar, children, error = false }) {
    const { t } = useI18n();
    return <section data-directory-list className={`${toolbar ? '' : 'overflow-hidden'} rounded-md border border-gray-200 bg-white shadow-sm`}>
        <div className="rounded-t-md border-b border-gray-200 bg-gray-50 px-5 py-4 text-sm text-gray-500">
            {heading && <h2 className="mb-1 text-sm font-semibold text-gray-900">{heading}</h2>}
            <span data-directory-count>{t('Показано:')}{' '}<span className="font-semibold text-gray-800">{count}</span>{' '}{t('из')}{' '}{total}</span>
        </div>
        {toolbar}
        {loading ? <p className="p-10 text-center text-sm text-gray-500">{t('Загрузка...')}</p> : <>
            <div role="list" aria-label={label || heading} className="divide-y divide-gray-100">{children}</div>
            {count === 0 && !error && <p className="p-10 text-center text-sm text-gray-500">{emptyMessage || t('Ничего не найдено.')}</p>}
        </>}
    </section>;
}

export function DirectoryRow({ name, secondary, href, metrics = [], actions, children, ...props }) {
    const columns = metrics.length > 1
        ? (actions ? 'lg:grid-cols-[minmax(0,1fr)_160px_160px_auto]' : 'lg:grid-cols-[minmax(0,1fr)_190px_190px]')
        : metrics.length ? 'lg:grid-cols-[minmax(0,1fr)_190px_auto]' : 'lg:grid-cols-[minmax(0,1fr)_auto]';
    return <div role="listitem" data-directory-row {...props} className={`grid grid-cols-[minmax(0,1fr)_auto] items-center gap-3 px-5 py-4 text-sm hover:bg-gray-50 ${columns}`}>
        <div className={`min-w-0 ${metrics.length ? 'col-span-2 lg:col-span-1' : ''}`}>
            {href ? <Link href={href} className="font-semibold text-gray-900 hover:text-[#005eb8] hover:underline [overflow-wrap:anywhere]">{name}</Link> : <div className="font-semibold text-gray-900 [overflow-wrap:anywhere]">{name}</div>}
            {secondary && <div className="mt-1 text-xs text-gray-400 [overflow-wrap:anywhere]">{secondary}</div>}
            {children}
        </div>
        {metrics.map((metric, index) => <div key={index} className="col-span-2 min-w-0 sm:col-span-1">{metric}</div>)}
        {actions && <div className={`flex items-center justify-end gap-1 ${metrics.length > 1 ? 'col-span-2 lg:col-span-1' : metrics.length ? 'col-span-2 sm:col-span-1' : ''}`}>{actions}</div>}
    </div>;
}

export function DirectoryMetric({ icon: Icon, children }) {
    return <div className="flex min-w-0 items-center gap-2 text-sm text-gray-600">
        <Icon size={16} aria-hidden="true" className="shrink-0 text-gray-400" />
        <span className="[overflow-wrap:anywhere]">{children}</span>
    </div>;
}

export function DirectoryAction({ href, onClick, label, icon: Icon, danger = false, primary = false, disabled = false, ...props }) {
    const className = `flex shrink-0 items-center justify-center rounded focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#005eb8] disabled:opacity-50 ${primary ? 'h-10 w-10 bg-[#005eb8] text-white hover:bg-[#244f9d]' : `h-8 w-8 ${danger ? 'text-gray-400 hover:bg-red-50 hover:text-red-600' : 'text-gray-500 hover:bg-blue-50 hover:text-[#005eb8]'}`}`;
    const contents = <Icon size={16} aria-hidden="true" />;
    return href ? <Link href={href} title={label} aria-label={label} className={className} {...props}>{contents}</Link>
        : <button type="button" onClick={onClick} disabled={disabled} title={label} aria-label={label} className={className} {...props}>{contents}</button>;
}
