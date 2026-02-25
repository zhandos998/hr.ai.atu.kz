<template>
  <Layout>
    <div class="max-w-6xl mx-auto py-8 px-4 space-y-8">
      <h1 class="text-2xl md:text-3xl font-bold text-[#005eb8] text-center">
        Админ-панель HR Chat ATU
      </h1>

      <section class="space-y-3">
        <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Подбор</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <router-link
            to="/admin/vacancies"
            class="bg-[#005eb8] hover:bg-blue-700 text-white p-5 rounded-xl transition shadow min-h-[120px] flex flex-col justify-between"
          >
            <div class="text-lg font-semibold">Вакансии</div>
            <div class="text-sm text-blue-100 mt-2">Создание, редактирование и удаление вакансий.</div>
          </router-link>

          <router-link
            to="/admin/applications"
            class="bg-[#005eb8] hover:bg-blue-700 text-white p-5 rounded-xl transition shadow min-h-[120px] flex flex-col justify-between"
          >
            <div class="text-lg font-semibold">Заявки кандидатов</div>
            <div class="text-sm text-blue-100 mt-2">Проверка заявок и смена статусов по этапам.</div>
          </router-link>
        </div>
      </section>

      <div class="border-t border-gray-200"></div>

      <section class="space-y-3">
        <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Оргструктура</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
          <router-link
            to="/admin/departments"
            class="bg-[#005eb8] hover:bg-blue-700 text-white p-5 rounded-xl transition shadow min-h-[120px] flex flex-col justify-between"
          >
            <div class="text-lg font-semibold">Департаменты</div>
            <div class="text-sm text-blue-100 mt-2">Управление списком департаментов.</div>
          </router-link>

          <router-link
            to="/admin/positions"
            class="bg-[#005eb8] hover:bg-blue-700 text-white p-5 rounded-xl transition shadow min-h-[120px] flex flex-col justify-between"
          >
            <div class="text-lg font-semibold">Должности</div>
            <div class="text-sm text-blue-100 mt-2">Управление должностями и привязкой сотрудников.</div>
          </router-link>

          <router-link
            to="/admin/structure-tree"
            class="bg-[#005eb8] hover:bg-blue-700 text-white p-5 rounded-xl transition shadow min-h-[120px] flex flex-col justify-between"
          >
            <div class="text-lg font-semibold">Структура компании</div>
            <div class="text-sm text-blue-100 mt-2">Просмотр иерархии департаментов и должностей.</div>
          </router-link>
        </div>
      </section>

      <div class="border-t border-gray-200"></div>

      <section class="space-y-3">
        <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Комиссия и доступы</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <router-link
            to="/admin/commission-members"
            class="bg-[#005eb8] hover:bg-blue-700 text-white p-5 rounded-xl transition shadow min-h-[120px] flex flex-col justify-between"
          >
            <div class="text-lg font-semibold">Члены комиссии</div>
            <div class="text-sm text-blue-100 mt-2">Назначение и удаление членов комиссии.</div>
          </router-link>

          <router-link
            to="/admin/users"
            class="bg-[#005eb8] hover:bg-blue-700 text-white p-5 rounded-xl transition shadow min-h-[120px] flex flex-col justify-between"
          >
            <div class="text-lg font-semibold">Пользователи и роли</div>
            <div class="text-sm text-blue-100 mt-2">Просмотр данных пользователей, фильтры и управление ролями.</div>
          </router-link>
        </div>
      </section>
    </div>
  </Layout>
</template>

<script setup>
import Layout from '../components/Layout.vue';
</script>
