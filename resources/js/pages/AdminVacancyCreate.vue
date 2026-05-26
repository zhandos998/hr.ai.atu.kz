<template>
  <Layout>
    <div class="max-w-3xl mx-auto py-8 px-4 space-y-6">
      <div class="flex items-center justify-between gap-3">
        <h1 class="text-2xl md:text-3xl font-bold text-[#005eb8]">Добавить вакансию</h1>
        <router-link
          to="/admin/vacancies"
          class="border border-gray-300 text-gray-700 hover:bg-gray-100 font-semibold py-2 px-4 rounded-lg transition"
        >
          Назад
        </router-link>
      </div>

      <section class="bg-white rounded-xl shadow border border-gray-100 p-4">
        <form @submit.prevent="createVacancy" class="space-y-4">
          <label class="space-y-2 block">
            <span class="text-sm font-medium text-gray-700">Название вакансии</span>
            <input
              v-model="form.title"
              type="text"
              placeholder="Название вакансии"
              class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-[#005eb8] transition"
            />
          </label>

          <label class="space-y-2 block">
            <span class="text-sm font-medium text-gray-700">Описание</span>
            <textarea
              v-model="form.description"
              rows="5"
              placeholder="Описание вакансии"
              class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-[#005eb8] transition"
            ></textarea>
          </label>

          <label class="space-y-2 block">
            <span class="text-sm font-medium text-gray-700">Тип вакансии</span>
            <select
              v-model="form.type"
              class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-[#005eb8] transition"
            >
              <option disabled value="">Выберите тип</option>
              <option value="staff">ОУП</option>
              <option value="pps">ППС</option>
            </select>
          </label>

          <button
            type="submit"
            :disabled="submitting"
            class="bg-[#005eb8] hover:bg-blue-700 text-white font-semibold py-2 rounded-lg w-full transition cursor-pointer disabled:opacity-70"
          >
            {{ submitting ? 'Сохранение...' : 'Сохранить вакансию' }}
          </button>
        </form>
      </section>
    </div>
  </Layout>
</template>

<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import axios from 'axios';
import Layout from '../components/Layout.vue';

const router = useRouter();
const submitting = ref(false);
const form = ref({ title: '', description: '', type: '' });

const errorText = (error) => error?.response?.data?.message || 'Ошибка. Попробуйте снова.';

const createVacancy = async () => {
  if (!form.value.title.trim()) {
    alert('Введите название вакансии.');
    return;
  }

  if (!form.value.description.trim()) {
    alert('Введите описание вакансии.');
    return;
  }

  if (!form.value.type) {
    alert('Выберите тип вакансии.');
    return;
  }

  submitting.value = true;
  try {
    await axios.post('/api/admin/vacancies', {
      title: form.value.title,
      description: form.value.description,
      type: form.value.type,
    });
    alert('Вакансия добавлена.');
    router.push('/admin/vacancies');
  } catch (error) {
    alert(errorText(error));
  } finally {
    submitting.value = false;
  }
};
</script>
