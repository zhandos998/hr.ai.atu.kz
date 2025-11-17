<template>
    <Layout>
      <div class="max-w-lg mx-auto mt-12 bg-white shadow-lg rounded-xl p-6 border border-gray-100">
        <h1 class="text-2xl font-bold text-center mb-6 text-[#005eb8]">Профиль пользователя</h1>

        <div v-if="loading" class="text-center text-gray-500">Загрузка...</div>

        <div v-else class="space-y-4">
          <div class="flex items-center justify-between border-b pb-2">
            <span class="text-gray-600">ФИО:</span>
            <span class="font-medium text-gray-900">{{ user.name }}</span>
          </div>
          <div class="flex items-center justify-between border-b pb-2">
            <span class="text-gray-600">Email:</span>
            <span class="font-medium text-gray-900">{{ user.email }}</span>
          </div>
          <div class="flex items-center justify-between border-b pb-2">
            <span class="text-gray-600">Телефон:</span>
            <span class="font-medium text-gray-900">{{ user.phone ?? 'Не указано' }}</span>
          </div>

          <button
            @click="logout"
            class="w-full bg-[#005eb8] hover:bg-blue-700 text-white font-semibold py-2 rounded-lg transition cursor-pointer"
          >
            Выйти из аккаунта
          </button>
        </div>

      </div>
        <UserApplications />
    </Layout>
  </template>

  <script setup>
import UserApplications from '../components/UserApplications.vue';
  import Layout from '../components/Layout.vue';
  import { useAuthStore } from '../stores/useAuthStore';
  import { ref, onMounted } from 'vue';
  import axios from 'axios';
  import { useRouter } from 'vue-router';

  const user = ref({});
  const loading = ref(true);
  const router = useRouter();

  onMounted(async () => {
    try {
    const response = await axios.get('/api/user');
    user.value = response.data;
} catch (error) {
    console.error('Ошибка загрузки профиля:', error);
    router.push('/login');
} finally {
    loading.value = false;
}

  });

  const authStore = useAuthStore();

const logout = async () => {
    try {
        await axios.post('/api/logout');
    } catch (error) {
        console.error(error);
    } finally {
        authStore.logout();
        router.push('/login');
    }
};
  </script>
