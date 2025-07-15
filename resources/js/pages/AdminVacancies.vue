<template>
    <Layout>
      <div class="max-w-4xl mx-auto py-8 px-4">
        <h1 class="text-2xl md:text-3xl font-bold text-center mb-6 text-[#005eb8]">Управление вакансиями</h1>

        <!-- Создание вакансии -->
        <form @submit.prevent="createVacancy" class="bg-white rounded-xl shadow border border-gray-100 p-4 mb-6 space-y-4">
          <h2 class="text-xl font-semibold text-[#005eb8]">Добавить вакансию</h2>
          <input v-model="newVacancy.title" type="text" placeholder="Название вакансии"
            class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-[#005eb8] transition" />
          <textarea v-model="newVacancy.description" placeholder="Описание вакансии"
            class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-[#005eb8] transition"></textarea>
          <select v-model="newVacancy.type"
            class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-[#005eb8] transition">
            <option disabled value="">Выберите тип</option>
            <option value="staff">Сотрудники</option>
            <option value="pps">ППС</option>
          </select>
          <button type="submit"
            class="bg-[#005eb8] hover:bg-blue-700 text-white font-semibold py-2 rounded-lg w-full transition cursor-pointer">
            Добавить вакансию
          </button>
        </form>

        <!-- Список вакансий -->
        <div v-if="loading" class="text-center text-gray-500">Загрузка...</div>
        <div v-else>
          <div v-if="vacancies.length === 0" class="text-center text-gray-600">Вакансий пока нет.</div>
          <div v-else class="space-y-4">
            <div v-for="vacancy in vacancies" :key="vacancy.id"
              class="bg-white rounded-xl shadow border border-gray-100 p-4">
              <div v-if="editId !== vacancy.id">
                <h3 class="text-lg font-semibold text-[#005eb8]">{{ vacancy.title }}</h3>
                <p class="text-gray-700">{{ vacancy.description }}</p>
                <p class="text-sm text-gray-500 mt-1">Тип:
                  {{ vacancy.type === 'staff' ? 'Сотрудники' : 'ППС' }}</p>
                <div class="flex gap-2 mt-2">
                  <button @click="startEdit(vacancy)" class="text-[#005eb8] hover:underline text-sm cursor-pointer">Редактировать</button>
                  <button @click="deleteVacancy(vacancy.id)" class="text-red-600 hover:underline text-sm cursor-pointer">Удалить</button>
                </div>
              </div>

              <div v-else class="space-y-2">
                <input v-model="editVacancy.title" type="text" placeholder="Название вакансии"
                  class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-[#005eb8] transition" />
                <textarea v-model="editVacancy.description" placeholder="Описание вакансии"
                  class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-[#005eb8] transition"></textarea>
                <select v-model="editVacancy.type"
                  class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-[#005eb8] transition">
                  <option disabled value="">Выберите тип</option>
                  <option value="staff">Сотрудники</option>
                  <option value="pps">ППС</option>
                </select>
                <div class="flex gap-2">
                  <button @click="updateVacancy(vacancy.id)"
                    class="bg-[#005eb8] hover:bg-blue-700 text-white font-semibold py-2 rounded-lg w-full transition cursor-pointer">
                    Сохранить
                  </button>
                  <button @click="cancelEdit"
                    class="border border-gray-300 text-gray-700 hover:bg-gray-100 font-semibold py-2 rounded-lg w-full transition cursor-pointer">
                    Отмена
                  </button>
                </div>
              </div>
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

  const vacancies = ref([]);
  const loading = ref(true);
  const newVacancy = ref({ title: '', description: '', type: '' });
  const editId = ref(null);
  const editVacancy = ref({ title: '', description: '', type: '' });

  const fetchVacancies = async () => {
    loading.value = true;
    try {
      const response = await axios.get('/api/admin/vacancies');
      vacancies.value = response.data;
    } catch (error) {
      console.error(error);
    } finally {
      loading.value = false;
    }
  };

  const createVacancy = async () => {
    try {
      await axios.post('/api/admin/vacancies', newVacancy.value);
      newVacancy.value = { title: '', description: '', type: '' };
      fetchVacancies();
    } catch (error) {
      console.error(error);
    }
  };

  const startEdit = (vacancy) => {
    editId.value = vacancy.id;
    editVacancy.value = { title: vacancy.title, description: vacancy.description, type: vacancy.type };
  };

  const cancelEdit = () => {
    editId.value = null;
    editVacancy.value = { title: '', description: '', type: '' };
  };

  const updateVacancy = async (id) => {
    try {
      await axios.put(`/api/admin/vacancies/${id}`, editVacancy.value);
      cancelEdit();
      fetchVacancies();
    } catch (error) {
      console.error(error);
    }
  };

  const deleteVacancy = async (id) => {
    if (!confirm('Удалить вакансию?')) return;
    try {
      await axios.delete(`/api/admin/vacancies/${id}`);
      fetchVacancies();
    } catch (error) {
      console.error(error);
    }
  };

  onMounted(() => {
    fetchVacancies();
  });
  </script>
