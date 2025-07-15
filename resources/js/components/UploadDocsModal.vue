<template>
  <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-lg p-6 w-full max-w-md">
      <h2 class="text-xl font-semibold text-center mb-4 text-[#005eb8]">
        Загрузка документов
      </h2>

      <form @submit.prevent="submit">
        <div class="mb-4">
          <label class="block mb-1 text-gray-700 font-medium">Удостоверение личности</label>
          <input type="file" @change="e => idCardFile = e.target.files[0]" class="border rounded px-3 py-2 w-full" accept=".pdf,.jpg,.jpeg,.png" required />
        </div>

        <div class="mb-4">
          <label class="block mb-1 text-gray-700 font-medium">Диплом</label>
          <input type="file" @change="e => diplomaFile = e.target.files[0]" class="border rounded px-3 py-2 w-full" accept=".pdf,.jpg,.jpeg,.png" required />
        </div>

        <div v-if="application.vacancy.type === 'pps'" class="mb-4">
          <label class="block mb-1 text-gray-700 font-medium">Научные статьи (PDF или ZIP)</label>
          <input type="file" @change="e => articlesFile = e.target.files[0]" class="border rounded px-3 py-2 w-full" accept=".pdf,.zip" required />
        </div>

        <div v-if="application.vacancy.type === 'staff'" class="mb-4">
          <label class="block mb-1 text-gray-700 font-medium">Справка с места жительства</label>
          <input type="file" @change="e => addressFile = e.target.files[0]" class="border rounded px-3 py-2 w-full" accept=".pdf,.jpg,.jpeg,.png" required />
        </div>

        <div class="flex justify-between mt-6">
          <button type="button" @click="$emit('close')" class="px-4 py-2 rounded bg-gray-300 hover:bg-gray-400 transition">
            Отмена
          </button>
          <button type="submit" :disabled="loading" class="px-4 py-2 rounded bg-[#005eb8] hover:bg-blue-700 text-white transition">
            {{ loading ? 'Загрузка...' : 'Отправить' }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import axios from 'axios';
import { useRouter } from 'vue-router';

const props = defineProps({
  application: Object
});

const emit = defineEmits(['close']);

const idCardFile = ref(null);
const diplomaFile = ref(null);
const articlesFile = ref(null);
const addressFile = ref(null);
const loading = ref(false);
const router = useRouter();

const submit = async () => {
  if (!idCardFile.value || !diplomaFile.value || (props.application.vacancy.type === 'pps' && !articlesFile.value) || (props.application.vacancy.type === 'staff' && !addressFile.value)) {
    alert('Пожалуйста, загрузите все необходимые документы.');
    return;
  }

  loading.value = true;
  const formData = new FormData();
  formData.append('id_card', idCardFile.value);
  formData.append('diploma', diplomaFile.value);

  if (props.application.vacancy.type === 'pps') {
    formData.append('articles', articlesFile.value);
  } else if (props.application.vacancy.type === 'staff') {
    formData.append('address_certificate', addressFile.value);
  }

  try {
    await axios.post(`/api/applications/${props.application.id}/upload-docs`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });
    alert('Документы успешно загружены.');
    emit('close');
    router.go(); // обновляет данные без ручного обновления страницы
  } catch (error) {
    console.error(error);
    alert('Ошибка при загрузке документов. Проверьте файлы и попробуйте снова.');
  } finally {
    loading.value = false;
  }
};
</script>
