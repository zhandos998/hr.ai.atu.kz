<template>
    <Layout>
      <div class="max-w-4xl mx-auto py-8 px-4 text-center">
        <h1 class="text-2xl md:text-3xl font-bold text-[#005eb8] mb-6">Админ-панель HR Chat АТУ</h1>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <router-link
            to="/admin/vacancies"
            class="bg-[#005eb8] hover:bg-blue-700 text-white font-semibold py-6 rounded-xl transition shadow"
          >
            Управление вакансиями
          </router-link>

          <router-link
            to="/admin/applications"
            class="bg-[#005eb8] hover:bg-blue-700 text-white font-semibold py-6 rounded-xl transition shadow"
          >
            Управление заявками
          </router-link>
        </div>
      </div>
    </Layout>
  </template>

  <script setup>
  import Layout from '../components/Layout.vue';
  </script>
