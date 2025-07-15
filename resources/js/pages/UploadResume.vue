<template>
    <Layout>
      <div class="max-w-md mx-auto py-8 px-4">
        <h1 class="text-2xl md:text-3xl font-bold text-center mb-6 text-[#005eb8]">Загрузить резюме</h1>

        <form @submit.prevent="uploadResume" class="space-y-4 bg-white p-6 rounded-xl shadow border border-gray-100">
          <div>
            <label class="block mb-1 text-gray-700">Выберите файл (PDF, DOCX)</label>
            <input
              type="file"
              accept=".pdf,.doc,.docx"
              @change="handleFileUpload"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#005eb8] transition"
            />
          </div>

          <div v-if="uploading" class="w-full bg-gray-200 rounded-full h-3 overflow-hidden">
            <div
              :style="{ width: uploadProgress + '%' }"
              class="bg-[#005eb8] h-3 transition-all duration-300"
            ></div>
          </div>

          <button
            type="submit"
            :disabled="!file || uploading"
            class="w-full bg-[#005eb8] hover:bg-blue-700 text-white font-semibold py-2 rounded-lg transition disabled:opacity-50 cursor-pointer"
          >
            {{ uploading ? 'Загрузка...' : 'Отправить резюме' }}
          </button>

          <p v-if="successMessage" class="text-green-600 text-center mt-2">{{ successMessage }}</p>
          <p v-if="errorMessage" class="text-red-600 text-center mt-2">{{ errorMessage }}</p>
        </form>
      </div>
    </Layout>
  </template>

  <script setup>
  import Layout from '../components/Layout.vue';
  import { ref } from 'vue';
  import axios from 'axios';

  const file = ref(null);
  const uploadProgress = ref(0);
  const uploading = ref(false);
  const successMessage = ref('');
  const errorMessage = ref('');

  const handleFileUpload = (event) => {
    file.value = event.target.files[0];
  };

  const uploadResume = async () => {
    if (!file.value) return;

    uploading.value = true;
    uploadProgress.value = 0;
    successMessage.value = '';
    errorMessage.value = '';

    const formData = new FormData();
    formData.append('resume', file.value);

    try {
      await axios.post('/api/resumes', formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
        onUploadProgress: (progressEvent) => {
          uploadProgress.value = Math.round(
            (progressEvent.loaded * 100) / progressEvent.total
          );
        },
      });
      successMessage.value = 'Резюме успешно загружено!';
      file.value = null;
      uploadProgress.value = 0;
    } catch (error) {
      errorMessage.value = 'Ошибка загрузки. Попробуйте снова.';
    } finally {
      uploading.value = false;
    }
  };
  </script>
