<template>
    <Layout>
        <div class="max-w-md mx-auto mt-12 bg-white rounded-xl shadow-lg border border-gray-100 p-6">
            <h1 class="text-2xl font-bold text-center mb-6 text-[#005eb8]">Регистрация в HR Chat АТУ</h1>

            <form
                @submit.prevent="register"
                class="space-y-4"
            >
                <div>
                    <label class="block mb-1 text-gray-700">ФИО</label>
                    <input
                        v-model="name"
                        type="text"
                        required
                        placeholder="Иванов Иван"
                        class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-[#005eb8] transition"
                    />
                </div>
                <div>
                    <label class="block mb-1 text-gray-700">Email</label>
                    <input
                        v-model="email"
                        type="email"
                        required
                        placeholder="you@atu.kz"
                        class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-[#005eb8] transition"
                    />
                </div>
                <div>
                    <label class="block mb-1 text-gray-700">Телефон</label>
                    <input
                        v-model="phone"
                        type="text"
                        placeholder="87771112233"
                        class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-[#005eb8] transition"
                    />
                </div>
                <div>
                    <label class="block mb-1 text-gray-700">Пароль</label>
                    <input
                        v-model="password"
                        type="password"
                        required
                        placeholder="••••••••"
                        class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-[#005eb8] transition"
                    />
                </div>
                <div>
                    <label class="block mb-1 text-gray-700">Подтверждение пароля</label>
                    <input
                        v-model="password_confirmation"
                        type="password"
                        required
                        placeholder="••••••••"
                        class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-[#005eb8] transition"
                    />
                </div>
                <button
                    type="submit"
                    class="w-full bg-[#005eb8] hover:bg-blue-700 text-white font-semibold py-2 rounded-lg transition cursor-pointer"
                >
                    Зарегистрироваться
                </button>
            </form>

            <p class="text-center text-gray-600 mt-4">
                Уже есть аккаунт?
                <router-link
                    to="/login"
                    class="text-[#005eb8] hover:underline"
                >Войти</router-link>
            </p>
        </div>
    </Layout>
</template>

  <script setup>
import Layout from "../components/Layout.vue";
import { ref } from "vue";
import { useRouter } from "vue-router";
import axios from "axios";

const name = ref("");
const email = ref("");
const phone = ref("");
const password = ref("");
const password_confirmation = ref("");
const router = useRouter();

const register = async () => {
    try {
        const response = await axios.post("/api/register", {
            name: name.value,
            email: email.value,
            phone: phone.value,
            password: password.value,
            password_confirmation: password_confirmation.value,
        });
        localStorage.setItem("token", response.data.token);
        axios.defaults.headers.common[
            "Authorization"
        ] = `Bearer ${response.data.token}`;

        const next = route.query.next || "/";
        const safeNext = String(next).startsWith("/") ? String(next) : "/";
        router.replace(safeNext);
    } catch (error) {
        alert("Ошибка регистрации. Проверьте введенные данные.");
    }
};
</script>
