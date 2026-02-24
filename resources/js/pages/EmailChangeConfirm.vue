<template>
  <Layout>
    <div class="max-w-md mx-auto mt-12 bg-white rounded-xl shadow-lg border border-gray-100 p-6">
      <h1 class="text-2xl font-bold text-center mb-6 text-[#005eb8]">Подтверждение смены email</h1>

      <p v-if="status" class="mb-4 text-sm text-green-700 bg-green-50 border border-green-200 rounded-md px-3 py-2">{{ status }}</p>
      <p v-if="error" class="mb-4 text-sm text-red-700 bg-red-50 border border-red-200 rounded-md px-3 py-2">{{ error }}</p>

      <button
        @click="confirm"
        :disabled="loading"
        class="w-full bg-[#005eb8] hover:bg-blue-700 text-white font-semibold py-2 rounded-lg transition cursor-pointer disabled:opacity-60"
      >
        {{ loading ? 'Подтверждение...' : 'Подтвердить новый email' }}
      </button>
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

const status = ref('');
const error = ref('');
const loading = ref(false);

const confirm = async () => {
  const token = route.query.token;

  if (!token) {
    error.value = 'Некорректная ссылка подтверждения.';
    return;
  }

  loading.value = true;
  error.value = '';
  status.value = '';

  try {
    const response = await axios.post('/api/user/email/change-confirm', { token });

    localStorage.setItem('token', response.data.token);
    localStorage.setItem('role', response.data.user?.role || 'user');
    axios.defaults.headers.common.Authorization = `Bearer ${response.data.token}`;

    status.value = 'Email успешно изменен. Перенаправляем в профиль...';
    setTimeout(() => router.replace('/profile'), 900);
  } catch (e) {
    error.value = e?.response?.data?.message || 'Не удалось подтвердить смену email.';
  } finally {
    loading.value = false;
  }
};

onMounted(() => {
  confirm();
});
</script>

