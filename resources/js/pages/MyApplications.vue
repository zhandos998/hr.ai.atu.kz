<template>
    <Layout>
      <div class="max-w-3xl mx-auto py-8 px-4">
        <h1 class="text-2xl md:text-3xl font-bold text-center mb-6 text-[#005eb8]">Мои заявки</h1>

        <div v-if="loading" class="text-center text-gray-500">Загрузка...</div>
        <div v-else-if="applications.length === 0" class="text-center text-gray-600">
          Вы еще не подали ни одной заявки.
        </div>

        <div v-else class="space-y-4">
          <div
            v-for="app in applications"
            :key="app.id"
            class="bg-white rounded-xl shadow border border-gray-100 p-4 flex flex-col md:flex-row md:justify-between md:items-center"
          >
            <div>
              <h2 class="text-lg font-semibold text-[#005eb8]">{{ app.vacancy_title }}</h2>
              <p class="text-gray-600 text-sm">Дата подачи: {{ formatDate(app.created_at) }}</p>
            </div>
            <div
              :class="statusClasses(app.status)"
              class="mt-2 md:mt-0 font-medium text-sm px-3 py-1 rounded-full text-center w-max"
            >
              {{ statusLabel(app.status) }}
            </div>
          </div>
        </div>
      </div>
    </Layout>
  </template>

  <script setup>
  import Layout from '../components/Layout.vue';
  import { ref, onMounted } from 'vue';
  import axios from 'axios';

  // Заглушка: в реальном проекте данные придут с API
  const applications = ref([]);
  const loading = ref(true);

  // Пример статусов: pending, accepted, rejected
  const statusLabel = (status) => {
    switch (status) {
      case 'pending':
        return 'На рассмотрении';
      case 'accepted':
        return 'Принято';
      case 'rejected':
        return 'Отказано';
      default:
        return status;
    }
  };

  const statusClasses = (status) => {
    switch (status) {
      case 'pending':
        return 'bg-yellow-100 text-yellow-800';
      case 'accepted':
        return 'bg-green-100 text-green-800';
      case 'rejected':
        return 'bg-red-100 text-red-800';
      default:
        return 'bg-gray-100 text-gray-800';
    }
  };

  const formatDate = (dateString) => {
    const date = new Date(dateString);
    return date.toLocaleDateString('ru-RU', { year: 'numeric', month: 'long', day: 'numeric' });
  };

  onMounted(async () => {
    try {
      const response = await axios.get('/api/applications');
      applications.value = response.data;
    } catch (error) {
      console.error(error);
    } finally {
      loading.value = false;
    }
  });
  </script>
