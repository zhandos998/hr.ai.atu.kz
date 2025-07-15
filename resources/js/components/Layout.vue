<template>
    <div class="min-h-screen flex flex-col bg-gray-50">
      <!-- Header -->
      <header class="bg-white shadow-sm border-b border-gray-200">
        <div class="max-w-7xl mx-auto flex justify-between items-center px-4 py-3">
          <router-link to="/" class="flex items-center space-x-2">
            <img src="/public/logo long.png" alt="АТУ" class="h-10 w-auto" />
            <!-- <span class="text-lg md:text-xl font-semibold text-[#005eb8]">АТУ HR Chat</span> -->
          </router-link>
          <div class="space-x-4 flex items-center">
  <template v-if="authStore.loading">
    <!-- Можно добавить индикатор загрузки или ничего -->
    <span class="text-gray-400 text-sm">Загрузка...</span>
  </template>

  <template v-else>
    <router-link
      to="/vacancies"
      class="text-[#005eb8] hover:text-blue-700 font-medium transition"
    >
      Вакансии
    </router-link>

    <router-link
      v-if="authStore.role === 'admin'"
      to="/admin"
      class="text-[#005eb8] hover:text-blue-700 font-medium transition"
    >
      Админка
    </router-link>

    <template v-if="!authStore.user">
      <router-link to="/login" class="text-[#005eb8] hover:text-blue-700 font-medium transition">Вход</router-link>
      <router-link to="/register" class="text-[#005eb8] hover:text-blue-700 font-medium transition">Регистрация</router-link>
    </template>
    <template v-else>
      <router-link to="/profile" class="text-[#005eb8] hover:text-blue-700 font-medium transition">Профиль</router-link>
    </template>

  </template>
</div>
        </div>
      </header>

      <!-- Main Content -->
      <main class="flex-1">
        <slot />
      </main>

      <!-- Footer -->
      <footer class="bg-white border-t border-gray-200 text-center py-4 text-gray-500 text-sm">
        © {{ new Date().getFullYear() }} АТУ HR Chat. Все права защищены.
      </footer>
    </div>
  </template>

  <script setup>
  import { ref, onMounted } from 'vue';
  import axios from 'axios';

  import { useAuthStore } from '../stores/useAuthStore';
  const authStore = useAuthStore();
  const isAuthenticated = ref(false);
  const isAdmin = ref(false);

  authStore.fetchUser();
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
    const role = localStorage.getItem('role');
    if (role === 'admin') {
        isAdmin.value = true;
    }
  });
  </script>
