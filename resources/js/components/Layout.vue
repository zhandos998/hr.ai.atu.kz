<template>
    <div
        v-if="isAdminLayout"
        class="min-h-screen bg-[#f3f6fb] text-gray-900"
    >
        <aside class="hidden lg:fixed lg:inset-y-0 lg:left-0 lg:z-30 lg:flex lg:h-screen lg:w-72 lg:flex-col lg:border-r lg:border-gray-200 lg:bg-white">
            <div class="shrink-0 px-5 py-6">
                <router-link
                    to="/admin"
                    class="block"
                >
                    <img
                        src="/public/logo long.png"
                        alt="АТУ"
                        class="h-10 w-auto"
                    />
                </router-link>
                <div class="mt-5 rounded-md border border-blue-100 bg-[#f5f8ff] px-3 py-3">
                    <div class="truncate text-sm font-bold text-[#174a9c]">
                        {{ authStore.user?.name || t("role.admin") }}
                    </div>
                    <div class="mt-1 truncate text-xs text-gray-600">
                        {{ adminRoleLabel }}
                    </div>
                </div>
            </div>

            <nav class="min-h-0 flex-1 space-y-1 overflow-y-auto px-3 py-5">
                <router-link
                    v-for="item in adminNavItems"
                    :key="item.to"
                    :to="item.to"
                    :class="adminNavLinkClass(item)"
                >
                    <span>{{ t(item.labelKey) }}</span>
                </router-link>
            </nav>

            <div class="shrink-0 border-t border-gray-200 px-4 py-4">
                <div class="mb-5 inline-flex rounded-md border border-blue-100 bg-[#f5f8ff] p-1">
                    <button
                        type="button"
                        :class="languageButtonClass('ru')"
                        @click="setLocale('ru')"
                    >
                        RU
                    </button>
                    <button
                        type="button"
                        :class="languageButtonClass('kk')"
                        @click="setLocale('kk')"
                    >
                        KK
                    </button>
                </div>

                <nav class="space-y-1">
                    <a
                        :href="qualificationRequirementsUrl"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="flex items-center rounded-md px-3 py-2 text-sm font-semibold text-gray-600 transition hover:bg-[#f3f6fb] hover:text-[#174a9c]"
                    >
                        <span>{{ t("layout.qualificationRequirements") }}</span>
                    </a>
                    <router-link
                        to="/profile"
                        class="flex items-center rounded-md px-3 py-2 text-sm font-semibold text-gray-600 transition hover:bg-[#f3f6fb] hover:text-[#174a9c]"
                    >
                        <span>{{ t("common.profile") }}</span>
                    </router-link>
                    <button
                        type="button"
                        class="flex w-full items-center rounded-md px-3 py-2 text-left text-sm font-semibold text-gray-600 transition hover:bg-[#f3f6fb] hover:text-[#174a9c]"
                        @click="logout"
                    >
                        {{ t("layout.logout") }}
                    </button>
                </nav>
            </div>
        </aside>

        <div class="flex min-w-0 flex-1 flex-col lg:pl-72">
            <header class="border-b border-gray-200 bg-white px-4 py-3 lg:hidden">
                <div class="flex items-center justify-between gap-3">
                    <router-link
                        to="/admin"
                        class="min-w-0"
                    >
                        <img
                            src="/public/logo long.png"
                            alt="АТУ"
                            class="h-8 w-auto"
                        />
                    </router-link>
                    <button
                        type="button"
                        class="inline-flex items-center justify-center rounded-lg border border-gray-200 p-2 text-[#005eb8]"
                        :aria-expanded="mobileMenuOpen"
                        aria-label="Открыть меню"
                        @click="toggleMobileMenu"
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            class="h-5 w-5"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M4 6h16M4 12h16M4 18h16"
                            />
                        </svg>
                    </button>
                </div>

                <nav
                    v-if="mobileMenuOpen"
                    class="mt-3 space-y-1 border-t border-gray-200 pt-3"
                >
                    <router-link
                        v-for="item in adminNavItems"
                        :key="`mobile-${item.to}`"
                        :to="item.to"
                        :class="adminNavLinkClass(item)"
                        @click="closeMobileMenu"
                    >
                        <span>{{ t(item.labelKey) }}</span>
                    </router-link>
                    <div class="border-t border-gray-200 pt-3">
                        <div class="mb-3 inline-flex rounded-md border border-blue-100 bg-[#f5f8ff] p-1">
                            <button
                                type="button"
                                :class="languageButtonClass('ru')"
                                @click="setLocale('ru')"
                            >
                                RU
                            </button>
                            <button
                                type="button"
                                :class="languageButtonClass('kk')"
                                @click="setLocale('kk')"
                            >
                                KK
                            </button>
                        </div>
                        <a
                            :href="qualificationRequirementsUrl"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="flex items-center rounded-md px-3 py-2 text-sm font-semibold text-gray-600 transition hover:bg-[#f3f6fb] hover:text-[#174a9c]"
                            @click="closeMobileMenu"
                        >
                            {{ t("layout.qualificationRequirements") }}
                        </a>
                        <router-link
                            to="/profile"
                            class="flex items-center rounded-md px-3 py-2 text-sm font-semibold text-gray-600 transition hover:bg-[#f3f6fb] hover:text-[#174a9c]"
                            @click="closeMobileMenu"
                        >
                            {{ t("common.profile") }}
                        </router-link>
                        <button
                            type="button"
                            class="flex w-full items-center rounded-md px-3 py-2 text-left text-sm font-semibold text-gray-600 transition hover:bg-[#f3f6fb] hover:text-[#174a9c]"
                            @click="logout"
                        >
                            {{ t("layout.logout") }}
                        </button>
                    </div>
                </nav>
            </header>

            <header class="sticky top-0 z-20 border-b border-gray-200 bg-white shadow-sm">
                <div class="mx-auto flex max-w-7xl flex-col gap-4 px-4 py-6 md:flex-row md:items-center md:justify-between">
                    <div class="min-w-0">
                        <h1 class="text-xl font-bold text-gray-900">
                            {{ adminPageTitle }}
                        </h1>
                        <p
                            v-if="adminPageDescription"
                            class="mt-1 text-sm text-gray-500"
                        >
                            {{ adminPageDescription }}
                        </p>
                    </div>
                    <div
                        v-if="$slots.adminHeaderActions"
                        class="flex shrink-0 flex-wrap items-center gap-3"
                    >
                        <slot name="adminHeaderActions" />
                    </div>
                </div>
            </header>

            <div
                v-if="authStore.impersonator"
                class="border-b border-amber-200 bg-amber-50 px-4 py-3"
            >
                <div class="mx-auto flex max-w-7xl flex-col gap-3 text-sm text-amber-900 sm:flex-row sm:items-center sm:justify-between">
                    <div class="min-w-0">
                        {{ t("impersonation.banner", {
                            user: authStore.user?.name || "",
                            admin: authStore.impersonator?.name || "",
                        }) }}
                    </div>
                    <button
                        type="button"
                        class="inline-flex shrink-0 items-center justify-center rounded-md bg-[#2f5fb8] px-3 py-1.5 text-xs font-bold text-white shadow-sm transition hover:bg-[#244f9d]"
                        @click="returnToImpersonator"
                    >
                        {{ t("impersonation.return") }}
                    </button>
                </div>
            </div>

            <main class="min-w-0 flex-1 bg-[#f3f6fb]">
                <slot />
            </main>
        </div>
    </div>

    <div
        v-else
        class="min-h-screen flex flex-col bg-gray-50"
    >
        <header class="bg-white shadow-sm border-b border-gray-200">
            <div class="max-w-7xl mx-auto flex justify-between items-center px-4 py-3 gap-3">
                <router-link
                    to="/"
                    class="flex items-center space-x-2"
                >
                    <img
                        src="/public/logo long.png"
                        alt="АТУ"
                        class="h-8 sm:h-10 w-auto"
                    />
                </router-link>

                <div class="flex items-center">
                    <button
                        v-if="!authStore.loading"
                        type="button"
                        class="md:hidden inline-flex items-center justify-center rounded-lg border border-gray-200 p-2 text-[#005eb8]"
                        :aria-expanded="mobileMenuOpen"
                        aria-label="Открыть меню"
                        @click="toggleMobileMenu"
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            class="h-5 w-5"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M4 6h16M4 12h16M4 18h16"
                            />
                        </svg>
                    </button>

                    <template v-if="!authStore.loading">
                        <nav class="hidden md:flex flex-wrap items-center gap-x-4 gap-y-2 md:gap-x-6">
                            <!--
                            <router-link
                                to="/vacancies"
                                class="text-[#005eb8] hover:text-blue-700 font-medium transition"
                            >Вакансии</router-link>
                            -->
                            <a
                                :href="qualificationRequirementsUrl"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="inline-flex items-center justify-center rounded-lg border border-[#005eb8] px-3 py-1.5 text-sm font-semibold text-[#005eb8] hover:bg-[#005eb8] hover:text-white transition"
                            >
                                {{ t("layout.qualificationRequirements") }}
                            </a>
                            <!--
                            <router-link
                                to="/chat"
                                class="text-[#005eb8] hover:text-blue-700 font-medium transition"
                            >Чат</router-link>
                            -->
                            <router-link
                                v-if="authStore.role === 'admin'"
                                to="/admin"
                                class="text-[#005eb8] hover:text-blue-700 font-medium transition"
                            >{{ t("common.admin") }}</router-link>
                            <router-link
                                v-if="authStore.role === 'science_director'"
                                to="/science/applications"
                                class="text-[#005eb8] hover:text-blue-700 font-medium transition"
                            >{{ t("nav.science") }}</router-link>
                            <router-link
                                v-if="authStore.role === 'digital_director'"
                                to="/digital/applications"
                                class="text-[#005eb8] hover:text-blue-700 font-medium transition"
                            >{{ t("nav.digital") }}</router-link>
                            <router-link
                                v-if="authStore.role === 'strategy_director'"
                                to="/strategy/applications"
                                class="text-[#005eb8] hover:text-blue-700 font-medium transition"
                            >{{ t("nav.strategy") }}</router-link>
                            <router-link
                                v-if="authStore.role === 'academic_director'"
                                to="/academic/applications"
                                class="text-[#005eb8] hover:text-blue-700 font-medium transition"
                            >{{ t("nav.academic") }}</router-link>
                            <router-link
                                v-if="authStore.role === 'library_director'"
                                to="/library/applications"
                                class="text-[#005eb8] hover:text-blue-700 font-medium transition"
                            >{{ t("nav.library") }}</router-link>
                            <router-link
                                v-if="authStore.role === 'professional_development_director'"
                                to="/professional-development/applications"
                                class="text-[#005eb8] hover:text-blue-700 font-medium transition"
                            >{{ t("nav.professionalDevelopment") }}</router-link>
                            <router-link
                                v-if="hasLegalAccess"
                                to="/compliance/applications"
                                class="text-[#005eb8] hover:text-blue-700 font-medium transition"
                            >{{ t("nav.compliance") }}</router-link>
                            <router-link
                                v-if="authStore.user?.is_commission_member"
                                to="/commission/applications"
                                class="text-[#005eb8] hover:text-blue-700 font-medium transition"
                            >{{ t("common.commission") }}</router-link>

                            <template v-if="!authStore.user">
                                <router-link
                                    to="/login"
                                    class="text-[#005eb8] hover:text-blue-700 font-medium transition"
                                >{{ t("layout.login") }}</router-link>
                            </template>
                            <template v-else>
                                <router-link
                                    to="/profile"
                                    class="text-[#005eb8] hover:text-blue-700 font-medium transition"
                                >{{ t("common.profile") }}</router-link>
                            </template>
                        </nav>
                    </template>
                </div>
            </div>

            <div
                v-if="!authStore.loading && mobileMenuOpen"
                class="md:hidden border-t border-gray-200 px-4 py-3"
            >
                <nav class="flex flex-col gap-2">
                    <!--
                    <router-link
                        to="/vacancies"
                        class="text-[#005eb8] hover:text-blue-700 font-medium transition py-1"
                        @click="closeMobileMenu"
                    >Вакансии</router-link>
                    -->
                    <a
                        :href="qualificationRequirementsUrl"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex items-center justify-center rounded-lg border border-[#005eb8] px-3 py-2 text-center text-sm font-semibold text-[#005eb8] hover:bg-[#005eb8] hover:text-white transition"
                        @click="closeMobileMenu"
                    >
                        {{ t("layout.qualificationRequirements") }}
                    </a>
                    <!--
                    <router-link
                        to="/chat"
                        class="text-[#005eb8] hover:text-blue-700 font-medium transition py-1"
                        @click="closeMobileMenu"
                    >Чат</router-link>
                    -->
                    <router-link
                        v-if="authStore.role === 'admin'"
                        to="/admin"
                        class="text-[#005eb8] hover:text-blue-700 font-medium transition py-1"
                        @click="closeMobileMenu"
                    >{{ t("common.admin") }}</router-link>
                    <router-link
                        v-if="authStore.role === 'science_director'"
                        to="/science/applications"
                        class="text-[#005eb8] hover:text-blue-700 font-medium transition py-1"
                        @click="closeMobileMenu"
                    >{{ t("nav.science") }}</router-link>
                    <router-link
                        v-if="authStore.role === 'digital_director'"
                        to="/digital/applications"
                        class="text-[#005eb8] hover:text-blue-700 font-medium transition py-1"
                        @click="closeMobileMenu"
                    >{{ t("nav.digital") }}</router-link>
                    <router-link
                        v-if="authStore.role === 'strategy_director'"
                        to="/strategy/applications"
                        class="text-[#005eb8] hover:text-blue-700 font-medium transition py-1"
                        @click="closeMobileMenu"
                    >{{ t("nav.strategy") }}</router-link>
                    <router-link
                        v-if="authStore.role === 'academic_director'"
                        to="/academic/applications"
                        class="text-[#005eb8] hover:text-blue-700 font-medium transition py-1"
                        @click="closeMobileMenu"
                    >{{ t("nav.academic") }}</router-link>
                    <router-link
                        v-if="authStore.role === 'library_director'"
                        to="/library/applications"
                        class="text-[#005eb8] hover:text-blue-700 font-medium transition py-1"
                        @click="closeMobileMenu"
                    >{{ t("nav.library") }}</router-link>
                    <router-link
                        v-if="authStore.role === 'professional_development_director'"
                        to="/professional-development/applications"
                        class="text-[#005eb8] hover:text-blue-700 font-medium transition py-1"
                        @click="closeMobileMenu"
                    >{{ t("nav.professionalDevelopment") }}</router-link>
                    <router-link
                        v-if="hasLegalAccess"
                        to="/compliance/applications"
                        class="text-[#005eb8] hover:text-blue-700 font-medium transition py-1"
                        @click="closeMobileMenu"
                    >{{ t("nav.compliance") }}</router-link>
                    <router-link
                        v-if="authStore.user?.is_commission_member"
                        to="/commission/applications"
                        class="text-[#005eb8] hover:text-blue-700 font-medium transition py-1"
                        @click="closeMobileMenu"
                    >{{ t("common.commission") }}</router-link>

                    <template v-if="!authStore.user">
                        <router-link
                            to="/login"
                            class="text-[#005eb8] hover:text-blue-700 font-medium transition py-1"
                            @click="closeMobileMenu"
                        >{{ t("layout.login") }}</router-link>
                    </template>
                    <template v-else>
                        <router-link
                            to="/profile"
                            class="text-[#005eb8] hover:text-blue-700 font-medium transition py-1"
                            @click="closeMobileMenu"
                        >{{ t("common.profile") }}</router-link>
                    </template>
                </nav>
            </div>
        </header>

        <div
            v-if="authStore.impersonator"
            class="border-b border-amber-200 bg-amber-50 px-4 py-3"
        >
            <div class="mx-auto flex max-w-7xl flex-col gap-3 text-sm text-amber-900 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0">
                    {{ t("impersonation.banner", {
                        user: authStore.user?.name || "",
                        admin: authStore.impersonator?.name || "",
                    }) }}
                </div>
                <button
                    type="button"
                    class="inline-flex shrink-0 items-center justify-center rounded-md bg-[#2f5fb8] px-3 py-1.5 text-xs font-bold text-white shadow-sm transition hover:bg-[#244f9d]"
                    @click="returnToImpersonator"
                >
                    {{ t("impersonation.return") }}
                </button>
            </div>
        </div>

        <main class="flex-1">
            <slot />
        </main>

        <footer class="bg-white border-t border-gray-200 text-center py-4 text-gray-500 text-sm">
            © {{ new Date().getFullYear() }} ATU HR Chat. {{ t("layout.footerRights") }}
        </footer>
    </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from "vue";
import axios from "axios";
import { useRoute, useRouter } from "vue-router";
import { useAuthStore } from "../stores/useAuthStore";
import { isLegalRole } from "../utils/roles";
import { useI18n } from "../i18n";

const authStore = useAuthStore();
const route = useRoute();
const router = useRouter();
const { locale, setLocale, t } = useI18n();
const mobileMenuOpen = ref(false);
const hasLegalAccess = computed(() => isLegalRole(authStore.role));
const isAdminLayout = computed(() => route.path.startsWith("/admin"));
const qualificationRequirementsUrl = "https://library.atu.edu.kz/files/doc/231020231/";
const adminRoleLabel = computed(() => t(`role.${authStore.role || "admin"}`));
const adminPageMeta = {
    AdminDashboard: {
        titleKey: "admin.pages.dashboard.title",
        descriptionKey: "admin.pages.dashboard.description",
    },
    AdminApplications: {
        titleKey: "admin.pages.applications.title",
        descriptionKey: "admin.pages.applications.description",
    },
    AdminApplicationDetails: {
        title: (currentRoute) => t("admin.pages.applicationDetails.title", { id: currentRoute.params.id }),
        descriptionKey: "admin.pages.applicationDetails.description",
    },
    AdminVacancies: {
        titleKey: "admin.pages.vacancies.title",
        descriptionKey: "admin.pages.vacancies.description",
    },
    AdminVacancyCreate: {
        titleKey: "admin.pages.vacancyCreate.title",
        descriptionKey: "admin.pages.vacancyCreate.description",
    },
    AdminDepartments: {
        titleKey: "admin.pages.departments.title",
        descriptionKey: "admin.pages.departments.description",
    },
    AdminDepartmentCreate: {
        titleKey: "admin.pages.departmentCreate.title",
        descriptionKey: "admin.pages.departmentCreate.description",
    },
    AdminDepartmentEdit: {
        titleKey: "admin.pages.departmentEdit.title",
        descriptionKey: "admin.pages.departmentEdit.description",
    },
    AdminPositions: {
        titleKey: "admin.pages.positions.title",
        descriptionKey: "admin.pages.positions.description",
    },
    AdminPositionCreate: {
        titleKey: "admin.pages.positionCreate.title",
        descriptionKey: "admin.pages.positionCreate.description",
    },
    AdminStructureTree: {
        titleKey: "admin.pages.structure.title",
        descriptionKey: "admin.pages.structure.description",
    },
    AdminCommissionMembers: {
        titleKey: "admin.pages.commissionMembers.title",
        descriptionKey: "admin.pages.commissionMembers.description",
    },
    AdminUsers: {
        titleKey: "admin.pages.users.title",
        descriptionKey: "admin.pages.users.description",
    },
};
const adminCurrentPageMeta = computed(() => adminPageMeta[String(route.name || "")] || {
    titleKey: "admin.pages.dashboard.title",
    description: "",
});
const adminPageTitle = computed(() => {
    const meta = adminCurrentPageMeta.value;
    if (typeof meta.title === "function") return meta.title(route);
    return meta.titleKey ? t(meta.titleKey) : meta.title;
});
const adminPageDescription = computed(() => {
    const meta = adminCurrentPageMeta.value;
    return meta.descriptionKey ? t(meta.descriptionKey) : meta.description || "";
});
const adminNavItems = [
    { to: "/admin", labelKey: "admin.nav.dashboard", exact: true },
    { to: "/admin/applications", labelKey: "admin.nav.applications" },
    // Раздел вакансий временно скрыт: заявки создаются через департаменты и должности.
    // { to: "/admin/vacancies", label: "Вакансии" },
    { to: "/admin/departments", labelKey: "admin.nav.departments" },
    { to: "/admin/positions", labelKey: "admin.nav.positions" },
    { to: "/admin/structure-tree", labelKey: "admin.nav.structure" },
    { to: "/admin/commission-members", labelKey: "admin.nav.commissionMembers" },
    { to: "/admin/users", labelKey: "admin.nav.users" },
];

const isAdminNavActive = (item) => (
    item.exact ? route.path === item.to : route.path.startsWith(item.to)
);

const adminNavLinkClass = (item) => [
    "flex items-center rounded-md px-3 py-2 text-sm font-semibold transition",
    isAdminNavActive(item)
        ? "bg-[#eaf2ff] text-[#174a9c]"
        : "text-gray-600 hover:bg-[#f3f6fb] hover:text-[#174a9c]",
];

const languageButtonClass = (language) => [
    "rounded-md px-3 py-1.5 text-xs font-bold transition",
    locale.value === language
        ? "bg-[#2f5fb8] text-white shadow-sm"
        : "text-[#2f5fb8] hover:bg-white",
];

const toggleMobileMenu = () => {
    mobileMenuOpen.value = !mobileMenuOpen.value;
};

const closeMobileMenu = () => {
    mobileMenuOpen.value = false;
};

const logout = async () => {
    try {
        await axios.post("/api/logout");
    } finally {
        authStore.logout();
        closeMobileMenu();
        router.push("/login");
    }
};

const returnToImpersonator = async () => {
    try {
        await axios.post("/api/logout");
    } catch (error) {
        // Возврат к исходному администратору важнее, чем очистка уже текущего токена.
    }

    const user = await authStore.returnToImpersonator();
    closeMobileMenu();

    if (user?.role === "admin") {
        router.push("/admin/users");
        return;
    }

    router.push("/");
};

watch(
    () => route.fullPath,
    () => {
        closeMobileMenu();
    }
);

onMounted(async () => {
    const token = localStorage.getItem("token");
    if (token) {
        axios.defaults.headers.common.Authorization = `Bearer ${token}`;
    }
    await authStore.fetchUser();
});
</script>

