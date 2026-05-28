<template>
    <div
        v-if="isAdminLayout"
        class="min-h-screen bg-gray-50"
    >
        <aside class="hidden lg:fixed lg:inset-y-0 lg:left-0 lg:z-30 lg:flex lg:h-screen lg:w-72 lg:flex-col lg:border-r lg:border-gray-200 lg:bg-white">
            <div class="shrink-0 border-b border-gray-200 px-5 py-5">
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
                <div class="mt-4">
                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-400">
                        Админ-панель
                    </div>
                    <div class="mt-1 text-sm font-medium text-gray-700">
                        {{ authStore.user?.name || "Администратор" }}
                    </div>
                </div>
            </div>

            <nav class="min-h-0 flex-1 space-y-1 overflow-y-auto px-3 py-4">
                <router-link
                    v-for="item in adminNavItems"
                    :key="item.to"
                    :to="item.to"
                    :class="adminNavLinkClass(item)"
                >
                    <span>{{ item.label }}</span>
                </router-link>
            </nav>

            <div class="shrink-0 border-t border-gray-200 px-3 py-4">
                <div class="mb-2 px-3 text-xs font-semibold uppercase tracking-wide text-gray-400">
                    Сайт
                </div>
                <nav class="space-y-1">
                    <router-link
                        to="/vacancies"
                        class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-gray-600 transition hover:bg-gray-50 hover:text-[#005eb8]"
                    >
                        <span>Вакансии</span>
                    </router-link>
                    <a
                        :href="qualificationRequirementsUrl"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-gray-600 transition hover:bg-gray-50 hover:text-[#005eb8]"
                    >
                        <span>Квалификационные требование работников</span>
                    </a>
                    <router-link
                        to="/profile"
                        class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-gray-600 transition hover:bg-gray-50 hover:text-[#005eb8]"
                    >
                        <span>Профиль</span>
                    </router-link>
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
                        <span>{{ item.label }}</span>
                    </router-link>
                    <router-link
                        to="/profile"
                        class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-gray-600 transition hover:bg-gray-50 hover:text-[#005eb8]"
                        @click="closeMobileMenu"
                    >
                        Профиль
                    </router-link>
                </nav>
            </header>

            <main class="min-w-0 flex-1">
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
                                Квалификационные требование работников
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
                            >Админка</router-link>
                            <router-link
                                v-if="authStore.role === 'science_director'"
                                to="/science/applications"
                                class="text-[#005eb8] hover:text-blue-700 font-medium transition"
                            >Наука</router-link>
                            <router-link
                                v-if="authStore.role === 'digital_director'"
                                to="/digital/applications"
                                class="text-[#005eb8] hover:text-blue-700 font-medium transition"
                            >ЦОР / МООК</router-link>
                            <router-link
                                v-if="authStore.role === 'strategy_director'"
                                to="/strategy/applications"
                                class="text-[#005eb8] hover:text-blue-700 font-medium transition"
                            >Стратегия</router-link>
                            <router-link
                                v-if="authStore.role === 'academic_director'"
                                to="/academic/applications"
                                class="text-[#005eb8] hover:text-blue-700 font-medium transition"
                            >Академразвитие</router-link>
                            <router-link
                                v-if="authStore.role === 'library_director'"
                                to="/library/applications"
                                class="text-[#005eb8] hover:text-blue-700 font-medium transition"
                            >Библиотека</router-link>
                            <router-link
                                v-if="hasLegalAccess"
                                to="/compliance/applications"
                                class="text-[#005eb8] hover:text-blue-700 font-medium transition"
                            >Право/Комплаенс</router-link>
                            <router-link
                                v-if="authStore.user?.is_commission_member"
                                to="/commission/applications"
                                class="text-[#005eb8] hover:text-blue-700 font-medium transition"
                            >Комиссия</router-link>

                            <template v-if="!authStore.user">
                                <router-link
                                    to="/login"
                                    class="text-[#005eb8] hover:text-blue-700 font-medium transition"
                                >Вход</router-link>
                            </template>
                            <template v-else>
                                <router-link
                                    to="/profile"
                                    class="text-[#005eb8] hover:text-blue-700 font-medium transition"
                                >Профиль</router-link>
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
                        Квалификационные требование работников
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
                    >Админка</router-link>
                    <router-link
                        v-if="authStore.role === 'science_director'"
                        to="/science/applications"
                        class="text-[#005eb8] hover:text-blue-700 font-medium transition py-1"
                        @click="closeMobileMenu"
                    >Наука</router-link>
                    <router-link
                        v-if="authStore.role === 'digital_director'"
                        to="/digital/applications"
                        class="text-[#005eb8] hover:text-blue-700 font-medium transition py-1"
                        @click="closeMobileMenu"
                    >ЦОР / МООК</router-link>
                    <router-link
                        v-if="authStore.role === 'strategy_director'"
                        to="/strategy/applications"
                        class="text-[#005eb8] hover:text-blue-700 font-medium transition py-1"
                        @click="closeMobileMenu"
                    >Стратегия</router-link>
                    <router-link
                        v-if="authStore.role === 'academic_director'"
                        to="/academic/applications"
                        class="text-[#005eb8] hover:text-blue-700 font-medium transition py-1"
                        @click="closeMobileMenu"
                    >Академразвитие</router-link>
                    <router-link
                        v-if="authStore.role === 'library_director'"
                        to="/library/applications"
                        class="text-[#005eb8] hover:text-blue-700 font-medium transition py-1"
                        @click="closeMobileMenu"
                    >Библиотека</router-link>
                    <router-link
                        v-if="hasLegalAccess"
                        to="/compliance/applications"
                        class="text-[#005eb8] hover:text-blue-700 font-medium transition py-1"
                        @click="closeMobileMenu"
                    >Право/Комплаенс</router-link>
                    <router-link
                        v-if="authStore.user?.is_commission_member"
                        to="/commission/applications"
                        class="text-[#005eb8] hover:text-blue-700 font-medium transition py-1"
                        @click="closeMobileMenu"
                    >Комиссия</router-link>

                    <template v-if="!authStore.user">
                        <router-link
                            to="/login"
                            class="text-[#005eb8] hover:text-blue-700 font-medium transition py-1"
                            @click="closeMobileMenu"
                        >Вход</router-link>
                    </template>
                    <template v-else>
                        <router-link
                            to="/profile"
                            class="text-[#005eb8] hover:text-blue-700 font-medium transition py-1"
                            @click="closeMobileMenu"
                        >Профиль</router-link>
                    </template>
                </nav>
            </div>
        </header>

        <main class="flex-1">
            <slot />
        </main>

        <footer class="bg-white border-t border-gray-200 text-center py-4 text-gray-500 text-sm">
            © {{ new Date().getFullYear() }} ATU HR Chat. Все права защищены.
        </footer>
    </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from "vue";
import axios from "axios";
import { useRoute } from "vue-router";
import { useAuthStore } from "../stores/useAuthStore";
import { isLegalRole } from "../utils/roles";

const authStore = useAuthStore();
const route = useRoute();
const mobileMenuOpen = ref(false);
const hasLegalAccess = computed(() => isLegalRole(authStore.role));
const isAdminLayout = computed(() => route.path.startsWith("/admin"));
const qualificationRequirementsUrl = "https://library.atu.edu.kz/files/doc/231020231/";
const adminNavItems = [
    { to: "/admin", label: "Обзор", exact: true },
    { to: "/admin/applications", label: "Заявки" },
    { to: "/admin/vacancies", label: "Вакансии" },
    { to: "/admin/departments", label: "Департаменты" },
    { to: "/admin/positions", label: "Должности" },
    { to: "/admin/structure-tree", label: "Структура" },
    { to: "/admin/commission-members", label: "Комиссия" },
    { to: "/admin/users", label: "Пользователи" },
];

const isAdminNavActive = (item) => (
    item.exact ? route.path === item.to : route.path.startsWith(item.to)
);

const adminNavLinkClass = (item) => [
    "flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition",
    isAdminNavActive(item)
        ? "bg-[#005eb8] text-white shadow-sm"
        : "text-gray-600 hover:bg-blue-50 hover:text-[#005eb8]",
];

const toggleMobileMenu = () => {
    mobileMenuOpen.value = !mobileMenuOpen.value;
};

const closeMobileMenu = () => {
    mobileMenuOpen.value = false;
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

