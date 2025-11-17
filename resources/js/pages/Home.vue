<template>
    <Layout>
      <div class="max-w-3xl mx-auto py-12 px-4 text-center">
        <h1 class="text-3xl md:text-4xl font-bold text-[#005eb8] mb-4">Добро пожаловать в HR Chat АТУ</h1>
        <p class="text-gray-700 text-lg md:text-xl mb-6">
          Здесь вы можете узнать о вакансиях в АТУ, подать резюме и отслеживать статус своих заявок онлайн.
        </p>

        <div class="flex flex-col md:flex-row justify-center gap-4 mt-8">
          <router-link
            to="/chat"
            class="bg-[#005eb8] hover:bg-blue-700 text-white font-semibold px-6 py-3 rounded-lg transition"
          >
            Перейти в чат
          </router-link>
          <router-link
            v-if="!isAuthenticated"
            to="/register"
            class="border border-[#005eb8] text-[#005eb8] hover:bg-[#005eb8] hover:text-white font-semibold px-6 py-3 rounded-lg transition"
          >
            Зарегистрироваться
          </router-link>
          <router-link
            v-else
            to="/profile"
            class="border border-[#005eb8] text-[#005eb8] hover:bg-[#005eb8] hover:text-white font-semibold px-6 py-3 rounded-lg transition"
          >
            Перейти в профиль
          </router-link>
        </div>
      </div>
    </Layout>
  </template>

  <script setup>
  import Layout from '../components/Layout.vue';
  import { ref, onMounted } from 'vue';
  import axios from 'axios';

  const isAuthenticated = ref(false);

  onMounted(async () => {
    const token = localStorage.getItem('token');
    if (token) {
      axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
      try {
        await axios.get('/api/user');
        isAuthenticated.value = true;
      } catch {
        isAuthenticated.value = false;
      }
    }
  });
  </script>
