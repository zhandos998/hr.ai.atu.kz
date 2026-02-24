<template>
  <Layout>
    <div class="max-w-md mx-auto mt-12 bg-white rounded-xl shadow-lg border border-gray-100 p-6">
      <h1 class="text-2xl font-bold text-center mb-6 text-[#005eb8]">Вход в HR Chat ATU</h1>

      <p v-if="error" class="mb-4 text-sm text-red-700 bg-red-50 border border-red-200 rounded-md px-3 py-2">{{ error }}</p>

      <form @submit.prevent="login" class="space-y-4">
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
          <label class="block mb-1 text-gray-700">Пароль</label>
          <input
            v-model="password"
            type="password"
            required
            placeholder="••••••••"
            class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-[#005eb8] transition"
          />
        </div>

        <button
          type="submit"
          :disabled="loading"
          class="w-full bg-[#005eb8] hover:bg-blue-700 text-white font-semibold py-2 rounded-lg transition cursor-pointer disabled:opacity-60"
        >
          {{ loading ? 'Вход...' : 'Войти' }}
        </button>
      </form>

      <div class="text-sm mt-4 text-center">
        <router-link to="/forgot-password" class="text-[#005eb8] hover:underline">Забыли пароль?</router-link>
      </div>

      <p class="text-center text-gray-600 mt-4">
        Нет аккаунта?
        <router-link to="/register" class="text-[#005eb8] hover:underline">Зарегистрируйтесь</router-link>
      </p>
    </div>
  </Layout>
</template>

<script setup>
import Layout from '../components/Layout.vue';
import { ref } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import axios from 'axios';

const email = ref('');
const password = ref('');
const error = ref('');
const loading = ref(false);

const router = useRouter();
const route = useRoute();

const login = async () => {
  loading.value = true;
  error.value = '';

  try {
    const response = await axios.post('/api/login', {
      email: email.value,
      password: password.value,
    });

    const { token, user } = response.data;
    localStorage.setItem('token', token);
    localStorage.setItem('role', user.role || 'user');
    axios.defaults.headers.common.Authorization = `Bearer ${token}`;

    const next = route.query.next || '/';
    const safeNext = String(next).startsWith('/') ? String(next) : '/';
    router.replace(safeNext);
  } catch (e) {
    error.value = e?.response?.data?.message || 'Ошибка входа. Проверьте данные.';
  } finally {
    loading.value = false;
  }
};
</script>

