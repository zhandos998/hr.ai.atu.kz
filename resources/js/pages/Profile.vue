<template>
  <Layout>
    <div class="max-w-2xl mx-auto mt-12 bg-white shadow-lg rounded-xl p-6 border border-gray-100 space-y-8">
      <h1 class="text-2xl font-bold text-center text-[#005eb8]">Профиль пользователя</h1>

      <div v-if="loading" class="text-center text-gray-500">Загрузка...</div>

      <template v-else>
        <div class="space-y-3">
          <div class="flex items-center justify-between border-b pb-2">
            <span class="text-gray-600">ФИО:</span>
            <span class="font-medium text-gray-900">{{ user.name }}</span>
          </div>
          <div class="flex items-center justify-between border-b pb-2">
            <span class="text-gray-600">Email:</span>
            <span class="font-medium text-gray-900">{{ user.email }}</span>
          </div>
          <div class="flex items-center justify-between border-b pb-2">
            <span class="text-gray-600">Телефон:</span>
            <span class="font-medium text-gray-900">{{ user.phone ?? 'Не указано' }}</span>
          </div>
          <div class="flex items-center justify-between border-b pb-2">
            <span class="text-gray-600">Статус email:</span>
            <span class="font-medium" :class="user.email_verified_at ? 'text-green-700' : 'text-red-700'">
              {{ user.email_verified_at ? 'Подтвержден' : 'Не подтвержден' }}
            </span>
          </div>
        </div>

        <div v-if="!user.email_verified_at" class="space-y-2">
          <button
            @click="resendVerification"
            :disabled="resendLoading"
            class="w-full bg-blue-100 hover:bg-blue-200 text-[#005eb8] font-semibold py-2 rounded-lg transition cursor-pointer disabled:opacity-60"
          >
            {{ resendLoading ? 'Отправка...' : 'Отправить письмо подтверждения повторно' }}
          </button>
          <p v-if="resendStatus" class="text-sm text-green-700">{{ resendStatus }}</p>
          <p v-if="resendError" class="text-sm text-red-700">{{ resendError }}</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
          <router-link
            to="/profile/change-password"
            class="text-center bg-[#005eb8] hover:bg-blue-700 text-white font-semibold py-2 rounded-lg transition cursor-pointer"
          >
            Сменить пароль
          </router-link>
          <router-link
            to="/profile/change-email"
            class="text-center bg-[#005eb8] hover:bg-blue-700 text-white font-semibold py-2 rounded-lg transition cursor-pointer"
          >
            Сменить email
          </router-link>
        </div>

        <button
          @click="logout"
          class="w-full bg-gray-200 hover:bg-gray-300 text-gray-800 font-semibold py-2 rounded-lg transition cursor-pointer"
        >
          Выйти из аккаунта
        </button>
      </template>
    </div>

    <UserApplications />
  </Layout>
</template>

<script setup>
import UserApplications from '../components/UserApplications.vue';
import Layout from '../components/Layout.vue';
import { useAuthStore } from '../stores/useAuthStore';
import { ref, onMounted } from 'vue';
import axios from 'axios';
import { useRouter } from 'vue-router';

const user = ref({});
const loading = ref(true);
const router = useRouter();
const authStore = useAuthStore();

const resendLoading = ref(false);
const resendStatus = ref('');
const resendError = ref('');

const loadProfile = async () => {
  try {
    const response = await axios.get('/api/user');
    user.value = response.data;
  } catch (error) {
    router.push('/login');
  } finally {
    loading.value = false;
  }
};

onMounted(loadProfile);

const resendVerification = async () => {
  resendLoading.value = true;
  resendStatus.value = '';
  resendError.value = '';

  try {
    const response = await axios.post('/api/email/verification-notification', {
      email: user.value.email,
    });
    resendStatus.value = response.data.message;
  } catch (e) {
    resendError.value = e?.response?.data?.message || 'Не удалось отправить письмо.';
  } finally {
    resendLoading.value = false;
  }
};

const logout = async () => {
  try {
    await axios.post('/api/logout');
  } finally {
    authStore.logout();
    router.push('/login');
  }
};
</script>