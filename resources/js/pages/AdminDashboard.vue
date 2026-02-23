<template>
  <Layout>
    <div class="max-w-6xl mx-auto py-8 px-4">
      <h1 class="text-2xl md:text-3xl font-bold text-[#005eb8] text-center mb-6">
        Админ-панель HR Chat ATU
      </h1>

      <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
        <router-link
          to="/admin/vacancies"
          class="bg-[#005eb8] hover:bg-blue-700 text-white font-semibold p-5 rounded-xl transition shadow min-h-[92px] flex items-center justify-center text-center"
        >
          Управление вакансиями
        </router-link>

        <router-link
          to="/admin/applications"
          class="bg-[#005eb8] hover:bg-blue-700 text-white font-semibold p-5 rounded-xl transition shadow min-h-[92px] flex items-center justify-center text-center"
        >
          Управление заявками
        </router-link>

        <router-link
          to="/admin/departments"
          class="bg-[#005eb8] hover:bg-blue-700 text-white font-semibold p-5 rounded-xl transition shadow min-h-[92px] flex items-center justify-center text-center"
        >
          Управление департаментами
        </router-link>

        <router-link
          to="/admin/positions"
          class="bg-[#005eb8] hover:bg-blue-700 text-white font-semibold p-5 rounded-xl transition shadow min-h-[92px] flex items-center justify-center text-center"
        >
          Управление должностями
        </router-link>

        <router-link
          to="/admin/structure-tree"
          class="bg-[#005eb8] hover:bg-blue-700 text-white font-semibold p-5 rounded-xl transition shadow min-h-[92px] flex items-center justify-center text-center"
        >
          Просмотр структуры
        </router-link>

        <router-link
          to="/admin/commission-members"
          class="bg-[#005eb8] hover:bg-blue-700 text-white font-semibold p-5 rounded-xl transition shadow min-h-[92px] flex items-center justify-center text-center"
        >
          Члены комиссии
        </router-link>
      </div>
    </div>
  </Layout>
</template>

<script setup>
import Layout from '../components/Layout.vue';
</script>
