import React, { createContext, useContext, useMemo, useState } from "react";
import axios from "axios";
import { translateText } from "./translations";

const I18nContext = createContext(null);

const messages = {
    ru: {
        competitions: "Конкурсы",
        teacherApplications: "Заявки преподавателей",
        staffApplications: "Заявки сотрудников",
        applications: "Заявки",
        commission: "Комиссия",
        vacancies: "Вакансии",
        directory: "Справочник",
        departments: "Департаменты",
        academicUnits: "Факультеты и кафедры",
        ppsPositions: "Позиции ППС",
        positions: "Должности АУП",
        structure: "Структура",
        users: "Пользователи",
        personnel: "Кадровые процессы",
        profile: "Профиль",
        logout: "Выйти",
        login: "Войти",
        workspace: "Рабочий раздел",
        requirements: "Квалификационные требования работников",
        personnelStatus: "Статус кадрового процесса",
        menu: "Меню",
        loading: "Загрузка...",
        defaultCommission: "Комиссия по умолчанию",
    },
    kk: {
        competitions: "Конкурстар",
        teacherApplications: "Оқытушылардың өтінімдері",
        staffApplications: "Қызметкерлердің өтінімдері",
        applications: "Өтінімдер",
        commission: "Комиссия",
        vacancies: "Вакансиялар",
        directory: "Анықтамалық",
        departments: "Департаменттер",
        academicUnits: "Факультеттер мен кафедралар",
        ppsPositions: "ОПҚ позициялары",
        positions: "ӘБП лауазымдары",
        structure: "Құрылым",
        users: "Пайдаланушылар",
        personnel: "Кадрлық процестер",
        profile: "Профиль",
        logout: "Шығу",
        login: "Кіру",
        workspace: "Жұмыс бөлімі",
        requirements: "Қызметкерлердің біліктілік талаптары",
        personnelStatus: "Кадрлық процестің мәртебесі",
        menu: "Мәзір",
        loading: "Жүктелуде...",
        defaultCommission: "Әдепкі комиссия",
    },
};

export function I18nProvider({ children }) {
    const [locale, setLocaleState] = useState(() =>
        typeof window !== "undefined" &&
        window.localStorage.getItem("hr_ai_locale") === "kk"
            ? "kk"
            : "ru",
    );
    const value = useMemo(
        () => ({
            locale,
            setLocale: (next) => {
                const normalized = next === "kk" ? "kk" : "ru";
                if (typeof window !== "undefined")
                    window.localStorage.setItem("hr_ai_locale", normalized);
                axios.defaults.headers.common["X-Locale"] = normalized;
                setLocaleState(normalized);
            },
            t: (key) => typeof key === 'string' ? messages[locale][key] || translateText(key, locale) : key,
        }),
        [locale],
    );

    return (
        <I18nContext.Provider value={value}>{children}</I18nContext.Provider>
    );
}

export function useI18n() {
    return useContext(I18nContext);
}
