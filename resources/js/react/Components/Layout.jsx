import { useI18n as useTextI18n } from "../I18nContext";
import React, { useEffect, useMemo, useState } from "react";
import { Link, usePage } from "@inertiajs/react";
import { ChevronDown, Undo2 } from "lucide-react";
import { useAuth } from "../AuthContext";
import { useI18n } from "../I18nContext";
import TechnicalEmailWarning from "./TechnicalEmailWarning";

const requirementsUrl = "https://library.atu.edu.kz/files/doc/231020231/";
const menuGroups = {
    personnelRegister: [
        { href: "/admin/sick-leaves", label: "sickLeaves" },
        { href: "/admin/leaves", label: "leaves" },
    ],
    personnel: [
        { href: "/admin/personnel-requests?employee_type=pps", label: "personnelTeachers" },
        { href: "/admin/personnel-requests?employee_type=staff", label: "personnelStaff" },
        {
            href: "/admin/personnel-approval-routes",
            label: "defaultCommission",
        },
    ],
    vacancies: [
        { href: "/admin/competitions", label: "competitions" },
        { href: "/admin/applications?type=pps", label: "teacherApplications" },
        { href: "/admin/applications?type=staff", label: "staffApplications" },
        { href: "/admin/commission-members", label: "commission" },
    ],
    directory: [
        { href: "/admin/departments", label: "departments" },
        { href: "/admin/academic-units", label: "academicUnits" },
        { href: "/admin/pps-positions", label: "ppsPositions" },
        { href: "/admin/positions", label: "positions" },
        { href: "/admin/structure-tree", label: "structure" },
    ],
};
const roleLabels = {
    admin: "Администратор",
    lawyer: "Юрист / комплаенс",
    science_director: "Департамент науки",
    digital_director: "Департамент цифровизации",
    strategy_director: "Стратегическое развитие",
    academic_director: "Академическое развитие",
    library_director: "Научная библиотека",
    professional_development_director: "Повышение квалификации",
    economist: "Экономист",
    commission: "Член комиссии",
    user: "Пользователь",
};
const roleHome = {
    admin: "/admin",
    lawyer: "/compliance/applications",
    science_director: "/science/applications",
    digital_director: "/digital/applications",
    strategy_director: "/strategy/applications",
    academic_director: "/academic/applications",
    library_director: "/library/applications",
    professional_development_director: "/professional-development/applications",
    economist: "/personnel/requests",
    commission: "/commission/applications",
};

function workspaceHref(user, role) {
    if (roleHome[role]) return roleHome[role];
    if (user?.is_commission_member) return "/commission/applications";
    if (user?.has_personnel_approvals) return "/personnel/requests";
    return null;
}

function LanguageSwitch({ locale, setLocale }) {
    const { t: _t } = useTextI18n();
    return (
        <div
            className="inline-flex rounded-md border border-blue-100 bg-[#f5f8ff] p-1"
            aria-label={_t("Language")}
        >
            {["ru", "kk"].map((value) => (
                <button
                    key={value}
                    type="button"
                    className={`rounded px-2 py-1 text-xs font-bold uppercase sm:px-2.5 ${locale === value ? "bg-[#005eb8] text-white" : "text-[#174a9c]"}`}
                    onClick={() => setLocale(value)}
                >
                    {_t(value)}
                </button>
            ))}
        </div>
    );
}

function matchesMenuLink(currentUrl, href) {
    const [path, query = ""] = currentUrl.split("?");
    if (
        path.startsWith("/admin/applications/") &&
        new URLSearchParams(query).has("competition_id")
    ) {
        return href === "/admin/competitions";
    }
    const [targetPath, targetQuery = ""] = href.split("?");
    if (path !== targetPath && !path.startsWith(`${targetPath}/`)) return false;
    const targetParams = new URLSearchParams(targetQuery);
    const currentParams = new URLSearchParams(query);
    return [['type', 'pps'], ['employee_type', 'staff']].every(([key, fallback]) =>
        !targetParams.has(key) || (currentParams.get(key) || fallback) === targetParams.get(key)
    );
}

function MenuLink({ href, active, onClick, children }) {
    const { t: _t } = useTextI18n();
    return (
        <Link
            href={href}
            aria-current={active ? "page" : undefined}
            className={`flex items-center rounded-md px-3 py-2 text-sm font-semibold transition ${active ? "bg-[#eaf2ff] text-[#174a9c]" : "text-gray-600 hover:bg-[#f3f6fb] hover:text-[#174a9c]"}`}
            onClick={onClick}
        >
            {_t(children)}
        </Link>
    );
}

function MenuGroup({
    id,
    label,
    items,
    currentPath,
    openGroup,
    setOpenGroup,
    onNavigate,
}) {
    const { t: _t } = useTextI18n();
    const active = items.some((item) =>
        matchesMenuLink(currentPath, item.href),
    );
    const open = openGroup === id;
    return (
        <div className="space-y-1">
            <button
                type="button"
                className={`flex w-full items-center justify-between rounded-md px-3 py-2 text-sm font-semibold transition ${active ? "bg-[#eaf2ff] text-[#174a9c]" : "text-gray-600 hover:bg-[#f3f6fb] hover:text-[#174a9c]"}`}
                aria-expanded={open}
                onClick={() => setOpenGroup(open ? "" : id)}
            >
                <span>{_t(label)}</span>
                <ChevronDown
                    aria-hidden="true"
                    size={16}
                    strokeWidth={1.75}
                    className={`shrink-0 transition-transform ${open ? "rotate-180" : ""}`}
                />
            </button>
            {open && (
                <div className="space-y-1 border-l border-blue-100 pl-3">
                    {items.map((item) => (
                        <MenuLink
                            key={item.href}
                            href={item.href}
                            active={matchesMenuLink(currentPath, item.href)}
                            onClick={onNavigate}
                        >
                            {_t(item.label)}
                        </MenuLink>
                    ))}
                </div>
            )}
        </div>
    );
}

function PageHeading({ title, description, actions }) {
    const { t: _t } = useTextI18n();
    if (!title) return null;
    return (
        <header className="border-b border-gray-200 bg-white">
            <div className="mx-auto flex max-w-7xl flex-col gap-3 px-4 py-6 md:flex-row md:items-center md:justify-between">
                <div className="min-w-0 flex-1">
                    <h1 className="text-xl font-bold [overflow-wrap:anywhere]">
                        {_t(title)}
                    </h1>
                    {description && (
                        <p className="mt-1 text-sm text-gray-500">
                            {_t(description)}
                        </p>
                    )}
                </div>
                {actions && (
                    <div className="flex shrink-0 flex-wrap items-center gap-3">
                        {_t(actions)}
                    </div>
                )}
            </div>
        </header>
    );
}

function ImpersonationBar({ user, impersonator, onReturn }) {
    const { t: _t } = useTextI18n();
    const [returning, setReturning] = useState(false);

    const handleReturn = async () => {
        setReturning(true);
        await onReturn();
    };

    return (
        <div className="border-t border-amber-200 bg-amber-50">
            <div className="mx-auto flex max-w-7xl items-center justify-between gap-3 px-4 py-2">
                <p className="min-w-0 truncate text-sm text-amber-950">
                    <span className="font-semibold">{_t("Режим пользователя:")}</span>{" "}
                    {user?.name}
                    {impersonator?.name && (
                        <span className="hidden text-amber-800 sm:inline">
                            {" "}{_t("(администратор:")}{impersonator.name})
                        </span>
                    )}
                </p>
                <button
                    type="button"
                    className="inline-flex shrink-0 items-center gap-2 rounded-md bg-[#174a9c] px-3 py-2 text-sm font-semibold text-white transition hover:bg-[#123d82] disabled:cursor-wait disabled:opacity-70"
                    disabled={returning}
                    onClick={handleReturn}
                >
                    <Undo2 aria-hidden="true" size={16} />
                    <span>{_t(returning ? "Возвращаемся..." : "Вернуться в админку")}</span>
                </button>
            </div>
        </div>
    );
}

export default function Layout({
    admin = false,
    requireAuth = false,
    title,
    description,
    actions,
    children,
}) {
    const { t: _t } = useTextI18n();
    const {
        user,
        role,
        loading,
        logout,
        impersonator,
        isImpersonating,
        returnFromImpersonation,
    } = useAuth();
    const { locale, setLocale, t } = useI18n();
    const [mobileMenuOpen, setMobileMenuOpen] = useState(false);
    const [openGroup, setOpenGroup] = useState("vacancies");
    const { url: currentPath } = usePage();
    const workHref = workspaceHref(user, role);

    useEffect(() => {
        if (loading) return;
        if ((admin || requireAuth) && !user) {
            const next = encodeURIComponent(
                `${window.location.pathname}${window.location.search}`,
            );
            window.location.assign(`/login?next=${next}`);
        } else if (admin && role !== "admin")
            window.location.assign(roleHome[role] || "/");
    }, [admin, loading, requireAuth, role, user]);

    useEffect(() => {
        Object.entries(menuGroups).forEach(([id, items]) => {
            if (items.some((item) => matchesMenuLink(currentPath, item.href)))
                setOpenGroup(id);
        });
    }, [currentPath]);

    const adminMenu = useMemo(() => {
        const items = (group) =>
            menuGroups[group].map((item) => ({
                ...item,
                label: t(item.label),
            }));
        const close = () => setMobileMenuOpen(false);
        return (
            <>
                <MenuGroup
                    id="personnel"
                    label={_t(t("personnel"))}
                    items={items("personnel")}
                    currentPath={currentPath}
                    openGroup={openGroup}
                    setOpenGroup={setOpenGroup}
                    onNavigate={close}
                />
                <MenuGroup
                    id="vacancies"
                    label={_t(t("vacancies"))}
                    items={items("vacancies")}
                    currentPath={currentPath}
                    openGroup={openGroup}
                    setOpenGroup={setOpenGroup}
                    onNavigate={close}
                />
                <MenuGroup
                    id="personnelRegister"
                    label={t("personnelRegister")}
                    items={items("personnelRegister")}
                    currentPath={currentPath}
                    openGroup={openGroup}
                    setOpenGroup={setOpenGroup}
                    onNavigate={close}
                />
                <MenuGroup
                    id="directory"
                    label={_t(t("directory"))}
                    items={items("directory")}
                    currentPath={currentPath}
                    openGroup={openGroup}
                    setOpenGroup={setOpenGroup}
                    onNavigate={close}
                />
                <MenuLink
                    href="/admin/users"
                    active={currentPath.startsWith("/admin/users")}
                    onClick={close}
                >
                    {_t(t("users"))}
                </MenuLink>
            </>
        );
    }, [currentPath, locale, openGroup, t]);

    if (loading && (admin || requireAuth))
        return (
            <div className="flex min-h-screen items-center justify-center bg-[#f3f6fb] text-sm text-gray-500">
                {_t(t("loading"))}
            </div>
        );
    if ((admin || requireAuth) && !user) return null;
    if (admin && role !== "admin") return null;

    if (!admin)
        return (
            <div className="flex min-h-screen flex-col bg-[#f3f6fb] text-gray-900">
                <header className="sticky top-0 z-20 border-b border-gray-200 bg-white shadow-sm">
                    <div className="mx-auto flex min-h-16 max-w-7xl flex-wrap items-center justify-between gap-2 px-4 py-3 sm:gap-4">
                        <Link href="/" className="shrink-0">
                            <img
                                src="/logo long.png"
                                alt={_t("АТУ")}
                                className="h-7 w-auto sm:h-9"
                            />
                        </Link>
                        <nav className="ml-auto flex flex-wrap items-center justify-end gap-1 text-sm font-semibold text-[#174a9c] sm:gap-3">
                            <a
                                href={requirementsUrl}
                                target="_blank"
                                rel="noreferrer"
                                className="hidden rounded-md border border-[#005eb8] px-3 py-2 transition hover:bg-[#005eb8] hover:text-white lg:inline-flex"
                            >
                                {_t(t("requirements"))}
                            </a>
                            <Link
                                href="/personnel-status"
                                className="rounded-md px-2 py-2 hover:bg-blue-50"
                            >
                                <span className="hidden md:inline">
                                    {_t(t("personnelStatus"))}
                                </span>
                                <span className="md:hidden">{_t("Статус процесса")}</span>
                            </Link>
                            {user && workHref && (
                                <Link
                                    href={workHref}
                                    className="inline-flex rounded-md px-2 py-2 hover:bg-blue-50"
                                >
                                    {t(workHref === "/commission/applications" ? "applications" : "workspace")}
                                </Link>
                            )}
                            {user ? (
                                <>
                                    <Link
                                        href="/profile"
                                        className="rounded-md px-2 py-2 hover:bg-blue-50"
                                    >
                                        {_t(t("profile"))}
                                    </Link>
                                    <button
                                        type="button"
                                        className="rounded-md px-2 py-2 hover:bg-blue-50"
                                        onClick={logout}
                                    >
                                        {_t(t("logout"))}
                                    </button>
                                </>
                            ) : (
                                <Link
                                    href="/login"
                                    className="rounded-md bg-[#005eb8] px-3 py-2 text-white hover:bg-[#244f9d] sm:px-4"
                                >
                                    {_t(t("login"))}
                                </Link>
                            )}
                            <LanguageSwitch
                                locale={locale}
                                setLocale={setLocale}
                            />
                        </nav>
                    </div>
                    {isImpersonating && (
                        <ImpersonationBar
                            user={user}
                            impersonator={impersonator}
                            onReturn={returnFromImpersonation}
                        />
                    )}
                </header>
                <PageHeading
                    title={_t(title)}
                    description={_t(description)}
                    actions={actions}
                />
                <TechnicalEmailWarning email={user?.email} />
                <main className="min-w-0 flex-1">{_t(children)}</main>
            </div>
        );

    return (
        <div className="min-h-screen bg-[#f3f6fb] text-gray-900">
            <aside className="fixed inset-y-0 left-0 z-30 hidden h-screen w-72 flex-col border-r border-gray-200 bg-white lg:flex">
                <div className="shrink-0 px-5 py-6">
                    <Link href="/admin">
                        <img
                            src="/logo long.png"
                            alt={_t("АТУ")}
                            className="h-10 w-auto"
                        />
                    </Link>
                    <div className="mt-5 rounded-md border border-blue-100 bg-[#f5f8ff] px-3 py-3">
                        <div className="truncate text-sm font-bold text-[#174a9c]">
                            {user.name}
                        </div>
                        <div className="mt-1 truncate text-xs text-gray-600">
                            {_t(roleLabels[role] || roleLabels.user)}
                        </div>
                    </div>
                </div>
                <nav className="min-h-0 flex-1 space-y-1 overflow-y-auto px-3 py-5">
                    {_t(adminMenu)}
                </nav>
                <div className="shrink-0 border-t border-gray-200 px-4 py-4">
                    <LanguageSwitch locale={locale} setLocale={setLocale} />
                    <nav className="mt-3 space-y-1">
                        <a
                            href={requirementsUrl}
                            target="_blank"
                            rel="noreferrer"
                            className="flex rounded-md px-3 py-2 text-sm font-semibold text-gray-600 hover:bg-[#f3f6fb] hover:text-[#174a9c]"
                        >
                            {_t(t("requirements"))}
                        </a>
                        <MenuLink href="/profile">{_t(t("profile"))}</MenuLink>
                        <button
                            type="button"
                            className="flex w-full rounded-md px-3 py-2 text-left text-sm font-semibold text-gray-600 hover:bg-[#f3f6fb] hover:text-[#174a9c]"
                            onClick={logout}
                        >
                            {_t(t("logout"))}
                        </button>
                    </nav>
                </div>
            </aside>
            <div className="flex min-w-0 flex-1 flex-col lg:pl-72">
                <header className="border-b border-gray-200 bg-white px-4 py-3 lg:hidden">
                    <div className="flex items-center justify-between">
                        <Link href="/admin">
                            <img
                                src="/logo long.png"
                                alt={_t("АТУ")}
                                className="h-8 w-auto"
                            />
                        </Link>
                        <button
                            type="button"
                            className="rounded-md border border-gray-200 px-3 py-2 text-sm font-bold text-[#174a9c]"
                            onClick={() => setMobileMenuOpen((value) => !value)}
                        >
                            {_t(t("menu"))}
                        </button>
                    </div>
                    {mobileMenuOpen && (
                        <nav className="mt-3 space-y-1 border-t border-gray-200 pt-3">
                            {_t(adminMenu)}
                            <div className="pt-2">
                                <LanguageSwitch
                                    locale={locale}
                                    setLocale={setLocale}
                                />
                            </div>
                        </nav>
                    )}
                </header>
                {isImpersonating && (
                    <ImpersonationBar
                        user={user}
                        impersonator={impersonator}
                        onReturn={returnFromImpersonation}
                    />
                )}
                <div className="sticky top-0 z-20 shadow-sm">
                    <PageHeading
                        title={_t(title)}
                        description={_t(description)}
                        actions={actions}
                    />
                </div>
                <TechnicalEmailWarning email={user?.email} />
                <main className="min-w-0 flex-1">{_t(children)}</main>
            </div>
        </div>
    );
}
