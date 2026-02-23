<template>
  <Layout>
    <div class="max-w-3xl mx-auto py-8 px-4 space-y-6">
      <div class="flex items-center justify-between gap-3">
        <h1 class="text-2xl md:text-3xl font-bold text-[#005eb8]">Добавить департамент</h1>
        <router-link
          to="/admin/departments"
          class="border border-gray-300 text-gray-700 hover:bg-gray-100 font-semibold py-2 px-4 rounded-lg transition"
        >
          Назад
        </router-link>
      </div>

      <section class="bg-white rounded-xl shadow border border-gray-100 p-4 space-y-4">
        <form @submit.prevent="createDepartment" class="space-y-3">
          <input
            v-model="form.name"
            type="text"
            placeholder="Название департамента"
            class="w-full border border-gray-300 rounded-lg px-4 py-2"
          />
          <textarea
            v-model="form.description"
            placeholder="Описание (необязательно)"
            class="w-full border border-gray-300 rounded-lg px-4 py-2"
          />
          <button
            type="submit"
            :disabled="submitting"
            class="bg-[#005eb8] hover:bg-blue-700 text-white font-semibold py-2 rounded-lg w-full transition cursor-pointer disabled:opacity-70"
          >
            {{ submitting ? 'Сохранение...' : 'Сохранить департамент' }}
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
const form = ref({ name: '', description: '' });

const errorText = (error) => error?.response?.data?.message || 'Ошибка. Попробуйте снова.';

const createDepartment = async () => {
  if (!form.value.name.trim()) {
    alert('Введите название департамента.');
    return;
  }

  submitting.value = true;
  try {
    await axios.post('/api/admin/departments', form.value);
    alert('Департамент добавлен.');
    router.push('/admin/departments');
  } catch (error) {
    alert(errorText(error));
  } finally {
    submitting.value = false;
  }
};
</script>
