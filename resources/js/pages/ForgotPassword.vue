<template>
  <Layout>
    <div class="max-w-md mx-auto mt-12 bg-white rounded-xl shadow-lg border border-gray-100 p-6">
      <h1 class="text-2xl font-bold text-center mb-6 text-[#005eb8]">Забыли пароль</h1>

      <p v-if="status" class="mb-4 text-sm text-green-700 bg-green-50 border border-green-200 rounded-md px-3 py-2">{{ status }}</p>
      <p v-if="error" class="mb-4 text-sm text-red-700 bg-red-50 border border-red-200 rounded-md px-3 py-2">{{ error }}</p>

      <form @submit.prevent="submit" class="space-y-4">
        <div>
          <label class="block mb-1 text-gray-700">Email</label>
          <input
            v-model="email"
            type="email"
            required
            placeholder="you@atu.kz"
            class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-[#005eb8] transition"
          />
        </div>

        <button
          :disabled="loading"
          type="submit"
          class="w-full bg-[#005eb8] hover:bg-blue-700 text-white font-semibold py-2 rounded-lg transition cursor-pointer disabled:opacity-60"
        >
          {{ loading ? 'Отправка...' : 'Отправить ссылку' }}
        </button>
      </form>
    </div>
  </Layout>
</template>

<script setup>
import Layout from '../components/Layout.vue';
import { ref } from 'vue';
import axios from 'axios';

const email = ref('');
const loading = ref(false);
const status = ref('');
const error = ref('');

const submit = async () => {
  loading.value = true;
  error.value = '';
  status.value = '';

  try {
    const response = await axios.post('/api/password/forgot', { email: email.value });
    status.value = response.data.message;
  } catch (e) {
    error.value = e?.response?.data?.message || 'Ошибка отправки письма.';
  } finally {
    loading.value = false;
  }
};
</script>

