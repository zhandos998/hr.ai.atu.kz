<template>
  <Layout>
    <div class="max-w-lg mx-auto mt-12 bg-white rounded-xl shadow-lg border border-gray-100 p-6">
      <h1 class="text-2xl font-bold text-center mb-6 text-[#005eb8]">Сменить пароль</h1>

      <p v-if="status" class="mb-4 text-sm text-green-700 bg-green-50 border border-green-200 rounded-md px-3 py-2">{{ status }}</p>
      <p v-if="error" class="mb-4 text-sm text-red-700 bg-red-50 border border-red-200 rounded-md px-3 py-2">{{ error }}</p>

      <form @submit.prevent="changePassword" class="space-y-4">
        <div>
          <label class="block mb-1 text-gray-700">Текущий пароль</label>
          <input
            v-model="form.current_password"
            type="password"
            required
            class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-[#005eb8] transition"
          />
        </div>

        <div>
          <label class="block mb-1 text-gray-700">Новый пароль</label>
          <input
            v-model="form.password"
            type="password"
            required
            minlength="6"
            class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-[#005eb8] transition"
          />
        </div>

        <div>
          <label class="block mb-1 text-gray-700">Подтвердите новый пароль</label>
          <input
            v-model="form.password_confirmation"
            type="password"
            required
            minlength="6"
            class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-[#005eb8] transition"
          />
        </div>

        <button
          type="submit"
          :disabled="loading"
          class="w-full bg-[#005eb8] hover:bg-blue-700 text-white font-semibold py-2 rounded-lg transition cursor-pointer disabled:opacity-60"
        >
          {{ loading ? 'Сохранение...' : 'Обновить пароль' }}
        </button>

        <router-link
          to="/profile"
          class="block text-center text-[#005eb8] hover:underline"
        >
          Назад в профиль
        </router-link>
      </form>
    </div>
  </Layout>
</template>

<script setup>
import Layout from '../components/Layout.vue';
import { ref } from 'vue';
import axios from 'axios';

const form = ref({
  current_password: '',
  password: '',
  password_confirmation: '',
});

const loading = ref(false);
const status = ref('');
const error = ref('');

const changePassword = async () => {
  loading.value = true;
  status.value = '';
  error.value = '';

  try {
    const response = await axios.post('/api/user/password', form.value);
    status.value = response.data.message;
    form.value = { current_password: '', password: '', password_confirmation: '' };
  } catch (e) {
    error.value = e?.response?.data?.message || 'Не удалось обновить пароль.';
  } finally {
    loading.value = false;
  }
};
</script>