<template>
    <div class="min-h-screen flex flex-col bg-gray-50">
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
                            <router-link
                                to="/chat"
                                class="relative inline-flex items-center gap-2 px-4 py-1.5 rounded-full font-semibold bg-gradient-to-r from-[#005eb8] to-blue-600 text-white border border-[#005eb8]/20 hover:from-blue-700 hover:to-blue-600 transition shadow-sm"
                            >
                                <svg
                                    xmlns="http://www.w3.org/2000/svg"
                                    class="h-4 w-4"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="1.8"
                                        d="M8 10h8M8 14h5M21 12a9 9 0 10-3.53 7.11L21 21l-.89-3.53A8.97 8.97 0 0021 12z"
                                    />
                                </svg>
                                <span>Чат</span>
                            </router-link>

                            <router-link
                                to="/vacancies"
                                class="text-[#005eb8] hover:text-blue-700 font-medium transition"
                            >Вакансии</router-link>
                            <router-link
                                v-if="authStore.role === 'admin'"
                                to="/admin"
                                class="text-[#005eb8] hover:text-blue-700 font-medium transition"
                            >Админка</router-link>
                            <router-link
                                v-if="authStore.role === 'lawyer'"
                                to="/lawyer/applications"
                                class="text-[#005eb8] hover:text-blue-700 font-medium transition"
                            >Lawyer</router-link>
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
                                <router-link
                                    to="/register"
                                    class="text-[#005eb8] hover:text-blue-700 font-medium transition"
                                >Регистрация</router-link>
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
                    <router-link
                        to="/chat"
                        class="inline-flex items-center justify-center gap-2 px-4 py-2 rounded-lg font-semibold bg-gradient-to-r from-[#005eb8] to-blue-600 text-white border border-[#005eb8]/20 transition shadow-sm"
                        @click="closeMobileMenu"
                    >
                        Чат
                    </router-link>

                    <router-link
                        to="/vacancies"
                        class="text-[#005eb8] hover:text-blue-700 font-medium transition py-1"
                        @click="closeMobileMenu"
                    >Вакансии</router-link>
                    <router-link
                        v-if="authStore.role === 'admin'"
                        to="/admin"
                        class="text-[#005eb8] hover:text-blue-700 font-medium transition py-1"
                        @click="closeMobileMenu"
                    >Админка</router-link>
                    <router-link
                        v-if="authStore.role === 'lawyer'"
                        to="/lawyer/applications"
                        class="text-[#005eb8] hover:text-blue-700 font-medium transition py-1"
                        @click="closeMobileMenu"
                    >Lawyer</router-link>
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
                        <router-link
                            to="/register"
                            class="text-[#005eb8] hover:text-blue-700 font-medium transition py-1"
                            @click="closeMobileMenu"
                        >Регистрация</router-link>
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
import { onMounted, ref, watch } from "vue";
import axios from "axios";
import { useRoute } from "vue-router";
import { useAuthStore } from "../stores/useAuthStore";

const authStore = useAuthStore();
const route = useRoute();
const mobileMenuOpen = ref(false);

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

