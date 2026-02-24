<template>
  <Layout>
    <div class="max-w-md mx-auto mt-12 bg-white rounded-xl shadow-lg border border-gray-100 p-6">
      <h1 class="text-2xl font-bold text-center mb-6 text-[#005eb8]">Сброс пароля</h1>

      <p v-if="status" class="mb-4 text-sm text-green-700 bg-green-50 border border-green-200 rounded-md px-3 py-2">{{ status }}</p>
      <p v-if="error" class="mb-4 text-sm text-red-700 bg-red-50 border border-red-200 rounded-md px-3 py-2">{{ error }}</p>

      <form @submit.prevent="submit" class="space-y-4">
        <div>
          <label class="block mb-1 text-gray-700">Email</label>
          <input
            v-model="email"
            type="email"
            required
            class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-[#005eb8] transition"
          />
        </div>

        <div>
          <label class="block mb-1 text-gray-700">Новый пароль</label>
          <input
            v-model="password"
            type="password"
            required
            minlength="6"
            class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-[#005eb8] transition"
          />
        </div>

        <div>
          <label class="block mb-1 text-gray-700">Подтвердите пароль</label>
          <input
            v-model="password_confirmation"
            type="password"
            required
            minlength="6"
            class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-[#005eb8] transition"
          />
        </div>

        <button
          :disabled="loading"
          type="submit"
          class="w-full bg-[#005eb8] hover:bg-blue-700 text-white font-semibold py-2 rounded-lg transition cursor-pointer disabled:opacity-60"
        >
          {{ loading ? 'Сохранение...' : 'Сменить пароль' }}
        </button>
      </form>
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

const email = ref('');
const password = ref('');
const password_confirmation = ref('');
const loading = ref(false);
const status = ref('');
const error = ref('');

onMounted(() => {
  email.value = String(route.query.email || '');
});

const submit = async () => {
  const token = route.query.token;

  if (!token || !email.value) {
    error.value = 'Некорректная ссылка для сброса пароля.';
    return;
  }

  loading.value = true;
  error.value = '';
  status.value = '';

  try {
    const response = await axios.post('/api/password/reset', {
      email: email.value,
      token,
      password: password.value,
      password_confirmation: password_confirmation.value,
    });

    status.value = response.data.message;
    setTimeout(() => router.replace('/login'), 1200);
  } catch (e) {
    error.value = e?.response?.data?.message || 'Не удалось сбросить пароль.';
  } finally {
    loading.value = false;
  }
};
</script>

