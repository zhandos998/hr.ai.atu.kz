<template>
  <Layout>
    <div class="max-w-lg mx-auto py-8 px-4">
      <h1 class="text-2xl md:text-3xl font-bold text-center mb-6 text-[#005eb8]">Отклик на вакансию</h1>

      <div v-if="loadingVacancy" class="text-center text-gray-500 mb-4">Загрузка вакансии...</div>
      <div v-else>
        <p class="text-center text-lg font-medium mb-4 text-gray-700">
          Вакансия: <span class="text-[#005eb8]">{{ vacancy.title }}</span>
        </p>

        <form @submit.prevent="submit">
          <div class="mb-4">
            <label class="block mb-1 text-gray-700 font-medium">Загрузите резюме (PDF, DOCX, JPG, PNG, до 2 МБ)</label>
            <input
              type="file"
              @change="handleFileUpload"
              class="border border-gray-300 rounded-lg px-3 py-2 w-full"
              accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"
              required
            />
          </div>

          <button
            type="submit"
            :disabled="loading"
            class="bg-[#005eb8] hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-lg w-full transition"
          >
            {{ loading ? 'Отправка...' : 'Отправить отклик' }}
          </button>
        </form>
      </div>
    </div>
  </Layout>
</template>

<script setup>
import Layout from '../components/Layout.vue';
import { ref, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import axios from 'axios';

const route = useRoute();
const router = useRouter();

const vacancy = ref({});
const loadingVacancy = ref(true);
const loading = ref(false);
const resumeFile = ref(null);

const fetchVacancy = async () => {
    try {
        const response = await axios.get(`/api/vacancies/${route.query.vacancy_id}`);
        vacancy.value = response.data;
    } catch (error) {
        console.error('Ошибка загрузки вакансии', error);
        alert('Вакансия не найдена.');
        router.push('/vacancies');
    } finally {
        loadingVacancy.value = false;
    }
};

onMounted(() => {
    if (!route.query.vacancy_id) {
        alert('Вакансия не выбрана.');
        router.push('/vacancies');
        return;
    }
    fetchVacancy();
});

const handleFileUpload = (e) => {
    resumeFile.value = e.target.files[0];
};

const submit = async () => {
    if (!resumeFile.value) {
        alert('Пожалуйста, выберите файл резюме.');
        return;
    }

    loading.value = true;
    const formData = new FormData();
    formData.append('vacancy_id', route.query.vacancy_id);
    formData.append('resume', resumeFile.value);

    try {
        await axios.post('/api/applications', formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        alert('Вы успешно откликнулись на вакансию!');
        router.push('/profile');
    } catch (error) {
        console.error(error);
        alert('Ошибка при отправке отклика. Попробуйте ещё раз.');
    } finally {
        loading.value = false;
    }
};
</script>
