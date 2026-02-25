<template>
  <div class="fixed inset-0 z-50">
    <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="$emit('close')"></div>

    <div class="relative mx-auto mt-10 w-[95%] max-w-3xl">
      <div class="bg-white rounded-2xl shadow-2xl overflow-hidden border border-gray-100">
        <div class="flex items-center justify-between px-5 py-4 border-b">
          <h3 class="text-lg md:text-xl font-semibold text-[#005eb8]">
            {{ mode === 'replace' ? 'Дополнить документы' : 'Загрузить документы' }}
          </h3>
          <button class="p-2 rounded-full hover:bg-gray-100 transition" @click="$emit('close')" aria-label="Закрыть">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <div class="p-5">
          <div class="grid gap-4 md:grid-cols-2">
            <div v-for="t in expectedTypes" :key="t" class="rounded-xl border border-gray-200 p-4">
              <div class="flex items-start justify-between mb-3">
                <div class="font-medium">
                  {{ docLabel(t) }}
                  <span v-if="isRequired(t) && mode === 'create'" class="text-red-500">*</span>
                </div>
                <span
                  :class="[
                    'text-xs px-2 py-0.5 rounded-full',
                    has(t)
                      ? 'bg-emerald-50 text-emerald-700 border border-emerald-200'
                      : 'bg-gray-100 text-gray-600 border border-gray-200',
                  ]"
                >
                  {{ has(t) ? 'Загружен' : 'Не загружен' }}
                </span>
              </div>

              <label
                class="group relative block cursor-pointer rounded-lg border-2 border-dashed p-4 text-center"
                :class="
                  dragOver[t]
                    ? 'border-[#005eb8] bg-blue-50/50'
                    : 'border-gray-300 hover:border-[#005eb8]/60 hover:bg-gray-50'
                "
                @dragover.prevent="dragOver[t] = true"
                @dragleave.prevent="dragOver[t] = false"
                @drop.prevent="onDrop(t, $event)"
              >
                <input
                  type="file"
                  class="absolute inset-0 opacity-0 cursor-pointer"
                  :accept="acceptFor(t)"
                  @change="onPick(t, $event)"
                />
                <div class="flex flex-col items-center gap-2">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 opacity-70" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="1.6"
                      d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6h.6a5 5 0 011.1 9.9M12 12v9m0-9l-3 3m3-3l3 3"
                    />
                  </svg>

                  <div v-if="selected[t].length" class="text-sm">
                    <div class="font-medium">{{ selected[t].length }} файл(ов) выбрано</div>
                    <div class="text-xs text-gray-500">Можно добавить еще файлы</div>
                  </div>
                  <div v-else class="text-sm text-gray-600">
                    Перетащите файлы сюда или <span class="text-[#005eb8] underline">выберите</span>
                  </div>
                </div>
              </label>

              <div class="mt-2 flex items-center justify-between gap-2">
                <div class="text-xs text-gray-500">{{ hintFor(t) }}</div>
                <button v-if="selected[t].length" class="text-xs px-2 py-1 rounded border hover:bg-gray-50" @click="clearFile(t)">
                  Очистить
                </button>
              </div>

              <ul v-if="selected[t].length" class="mt-2 space-y-1">
                <li
                  v-for="(file, idx) in selected[t]"
                  :key="`${file.name}-${idx}`"
                  class="text-xs text-gray-700 flex items-center justify-between gap-2 border border-gray-200 rounded px-2 py-1"
                >
                  <span class="truncate">{{ idx + 1 }}. {{ file.name }}</span>
                  <button class="text-red-600 hover:underline" @click="removeSelectedFile(t, idx)">Убрать</button>
                </li>
              </ul>
            </div>
          </div>

          <div class="mt-3 text-xs text-gray-500">
            <span class="text-red-500">*</span> обязательные при первой подаче.
            <span v-if="mode === 'replace'" class="block mt-1">В режиме дополнения новые файлы добавляются к уже загруженным.</span>
          </div>
        </div>

        <div class="px-5 py-4 bg-gray-50 flex items-center justify-between">
          <div class="text-xs text-gray-500">Можно загружать несколько файлов на один тип документа.</div>
          <div class="flex items-center gap-2">
            <button class="px-3 py-2 rounded-lg border hover:bg-white" @click="$emit('close')">Отмена</button>
            <button
              class="px-3 py-2 rounded-lg text-white bg-[#005eb8] hover:bg-[#005eb8]/90 disabled:opacity-60"
              :disabled="submitting || disableSave"
              @click="submit"
            >
              <span v-if="submitting">Загрузка...</span>
              <span v-else>{{ mode === 'replace' ? 'Дополнить' : 'Загрузить' }}</span>
            </button>
          </div>
        </div>
      </div>
    </div>
    <AppMessageModal
      v-model="errorModalOpen"
      title="Ошибка"
      :message="errorModalMessage"
      confirm-text="Понятно"
    />
  </div>
</template>

<script setup>
import axios from 'axios';
import { reactive, ref, computed } from 'vue';
import AppMessageModal from './AppMessageModal.vue';

const props = defineProps({
  application: { type: Object, required: true },
  mode: { type: String, default: 'create' },
});
const emit = defineEmits(['close', 'saved']);

const selected = reactive({
  diploma: [],
  recommendation_letter: [],
  scientific_works: [],
});
const dragOver = reactive({
  diploma: false,
  recommendation_letter: false,
  scientific_works: false,
});

const docLabels = {
  diploma: 'Дипломы и сертификаты',
  recommendation_letter: 'Рекомендательное письмо',
  scientific_works: 'Список научных трудов',
};
const docLabel = (type) => docLabels[type] || type;

const normalizeBaseType = (type) => {
  const base = String(type).replace(/_\d+$/, '');
  if (base === 'articles') return 'scientific_works';
  return base;
};

const has = (type) => {
  if (!props.application?.documents_map) return false;
  return Object.keys(props.application.documents_map).some((key) => normalizeBaseType(key) === type);
};

const isPps = computed(() => props.application?.vacancy?.type === 'pps');
const requiredTypes = computed(() => ['diploma']);

const expectedTypes = computed(() => {
  const base = ['diploma', 'recommendation_letter'];
  if (isPps.value) base.push('scientific_works');
  return base;
});

const isRequired = (t) => requiredTypes.value.includes(t) && !has(t);

const acceptFor = (t) => (t === 'scientific_works' ? '.pdf,.zip' : '.pdf,.jpg,.jpeg,.png');
const hintFor = (t) => (t === 'scientific_works' ? 'PDF/ZIP до 5 МБ каждый' : 'PDF/JPG/PNG до 2 МБ каждый');

const onPick = (type, e) => {
  const files = Array.from(e.target.files || []);
  if (files.length) {
    selected[type].push(...files);
  }
  e.target.value = '';
};
const onDrop = (type, e) => {
  const files = Array.from(e.dataTransfer?.files || []);
  if (!files.length) return;
  selected[type].push(...files);
  dragOver[type] = false;
};
const clearFile = (t) => {
  selected[t] = [];
};
const removeSelectedFile = (type, index) => {
  selected[type].splice(index, 1);
};

const submitting = ref(false);
const errorModalOpen = ref(false);
const errorModalMessage = ref('');

const disableSave = computed(() => {
  if (props.mode === 'create') {
    return expectedTypes.value.some((t) => isRequired(t) && selected[t].length === 0);
  }
  return expectedTypes.value.every((t) => selected[t].length === 0);
});

const submit = async () => {
  const fd = new FormData();
  fd.append('mode', 'append');

  if (props.mode === 'create') {
    for (const t of expectedTypes.value) {
      if (isRequired(t) && selected[t].length === 0) return;
      for (const file of selected[t]) {
        fd.append(`${t}[]`, file);
      }
    }
  } else {
    let any = false;
    for (const t of expectedTypes.value) {
      for (const file of selected[t]) {
        fd.append(`${t}[]`, file);
        any = true;
      }
    }
    if (!any) return;
  }

  submitting.value = true;
  try {
    await axios.post(`/api/applications/${props.application.id}/upload-docs`, fd, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });
    emit('saved');
  } catch (e) {
    console.error(e);
    errorModalMessage.value =
      e?.response?.data?.message ||
      Object.values(e?.response?.data?.errors || {})?.[0]?.[0] ||
      'Не удалось загрузить документы';
    errorModalOpen.value = true;
  } finally {
    submitting.value = false;
  }
};
</script>
