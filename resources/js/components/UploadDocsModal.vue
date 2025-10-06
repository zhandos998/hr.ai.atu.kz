<template>
    <div class="fixed inset-0 z-50">
        <!-- backdrop -->
        <div
            class="absolute inset-0 bg-black/50 backdrop-blur-sm"
            @click="$emit('close')"
        ></div>

        <!-- panel -->
        <div class="relative mx-auto mt-10 w-[95%] max-w-3xl">
            <div class="bg-white rounded-2xl shadow-2xl overflow-hidden border border-gray-100">
                <!-- header -->
                <div class="flex items-center justify-between px-5 py-4 border-b">
                    <h3 class="text-lg md:text-xl font-semibold text-[#005eb8]">
                        {{ mode === 'replace' ? 'Заменить документы' : 'Загрузить документы' }}
                    </h3>
                    <button
                        class="p-2 rounded-full hover:bg-gray-100 transition"
                        @click="$emit('close')"
                        aria-label="Закрыть"
                    >
                        <!-- X icon -->
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            class="h-5 w-5"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="1.8"
                                d="M6 18L18 6M6 6l12 12"
                            />
                        </svg>
                    </button>
                </div>

                <!-- body -->
                <div class="p-5">
                    <div class="grid gap-4 md:grid-cols-2">
                        <div
                            v-for="t in expectedTypes"
                            :key="t"
                            class="rounded-xl border border-gray-200 p-4"
                        >
                            <!-- card header -->
                            <div class="flex items-start justify-between mb-3">
                                <div class="font-medium">
                                    {{ docLabel(t) }}
                                    <span
                                        v-if="isRequired(t) && mode === 'create'"
                                        class="text-red-500"
                                    >*</span>
                                </div>
                                <span :class="[
                    'text-xs px-2 py-0.5 rounded-full',
                    has(t) ? 'bg-emerald-50 text-emerald-700 border border-emerald-200'
                           : 'bg-gray-100 text-gray-600 border border-gray-200'
                  ]">
                                    {{ has(t) ? 'Загружен' : 'Не загружен' }}
                                </span>
                            </div>

                            <!-- dropzone -->
                            <label
                                class="group relative block cursor-pointer rounded-lg border-2 border-dashed p-4 text-center"
                                :class="dragOver[t] ? 'border-[#005eb8] bg-blue-50/50' : 'border-gray-300 hover:border-[#005eb8]/60 hover:bg-gray-50'"
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
                                    <!-- upload icon -->
                                    <svg
                                        xmlns="http://www.w3.org/2000/svg"
                                        class="h-8 w-8 opacity-70"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="1.6"
                                            d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6h.6a5 5 0 011.1 9.9M12 12v9m0-9l-3 3m3-3l3 3"
                                        />
                                    </svg>

                                    <div
                                        v-if="selected[t]"
                                        class="text-sm"
                                    >
                                        <div class="font-medium truncate max-w-[16rem]">{{ selected[t].name }}</div>
                                        <div class="text-xs text-gray-500">Файл выбран — будет загружен</div>
                                    </div>
                                    <div
                                        v-else
                                        class="text-sm text-gray-600"
                                    >
                                        Перетащите файл сюда или <span class="text-[#005eb8] underline">выберите</span>
                                    </div>
                                </div>
                            </label>

                            <!-- hints & actions -->
                            <div class="mt-2 flex items-center justify-between gap-2">
                                <div class="text-xs text-gray-500">
                                    {{ hintFor(t) }}
                                </div>
                                <button
                                    v-if="selected[t]"
                                    class="text-xs px-2 py-1 rounded border hover:bg-gray-50"
                                    @click="clearFile(t)"
                                >
                                    Очистить
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- footnote -->
                    <div class="mt-3 text-xs text-gray-500">
                        <span class="text-red-500">*</span> — обязательные к загрузке поля при первичной подаче.
                    </div>
                </div>

                <!-- footer -->
                <div class="px-5 py-4 bg-gray-50 flex items-center justify-between">
                    <div class="text-xs text-gray-500">
                        После сохранения ссылки обновятся автоматически.
                    </div>
                    <div class="flex items-center gap-2">
                        <button
                            class="px-3 py-2 rounded-lg border hover:bg-white"
                            @click="$emit('close')"
                        >
                            Отмена
                        </button>
                        <button
                            class="px-3 py-2 rounded-lg text-white bg-[#005eb8] hover:bg-[#005eb8]/90 disabled:opacity-60"
                            :disabled="submitting || disableSave"
                            @click="submit"
                        >
                            <span v-if="submitting">Загрузка…</span>
                            <span v-else>{{ mode === 'replace' ? 'Заменить' : 'Загрузить' }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

    </div>
</template>

<script setup>
import axios from "axios";
import { reactive, ref, computed } from "vue";

const props = defineProps({
    application: { type: Object, required: true },
    mode: { type: String, default: "create" }, // 'create' | 'replace'
});
const emit = defineEmits(["close", "saved"]);

const selected = reactive({
    id_card: null,
    diploma: null,
    articles: null,
    address_certificate: null,
});
const dragOver = reactive({
    id_card: false,
    diploma: false,
    articles: false,
    address_certificate: false,
});

const docLabels = {
    id_card: "Уд. личности",
    diploma: "Диплом",
    articles: "Статьи / публикации",
    address_certificate: "Адресная справка",
};
const docLabel = (type) => docLabels[type] || type;

const has = (type) =>
    !!props.application?.documents_map &&
    !!props.application.documents_map[type];

const isPps = computed(() => props.application?.vacancy?.type === "pps");
const isStaff = computed(() => props.application?.vacancy?.type === "staff");

const expectedTypes = computed(() => {
    const base = ["id_card", "diploma"];
    if (isPps.value) base.push("articles");
    if (isStaff.value) base.push("address_certificate");
    return base;
});

const isRequired = (t) => !has(t); // обязательны только те, которых нет (для первичной подачи)

const acceptFor = (t) =>
    t === "articles" ? ".pdf,.zip" : ".pdf,.jpg,.jpeg,.png";
const hintFor = (t) =>
    t === "articles"
        ? "Форматы: PDF/ZIP · до 5 МБ"
        : "Форматы: PDF/JPG/PNG · до 2 МБ";

const onPick = (type, e) => {
    selected[type] = e.target.files?.[0] ?? null;
};
const onDrop = (type, e) => {
    const file = e.dataTransfer?.files?.[0];
    if (!file) return;
    selected[type] = file;
    dragOver[type] = false;
};
const clearFile = (t) => {
    selected[t] = null;
};

const submitting = ref(false);

// Кнопка "Сохранить" неактивна, если:
//  - режим create и не выбраны все обязательные
//  - режим replace и вообще ничего не выбрано
const disableSave = computed(() => {
    if (props.mode === "create") {
        return expectedTypes.value.some((t) => isRequired(t) && !selected[t]);
    }
    // replace
    return expectedTypes.value.every((t) => !selected[t]);
});

const submit = async () => {
    const fd = new FormData();

    if (props.mode === "create") {
        for (const t of expectedTypes.value) {
            if (isRequired(t) && !selected[t]) return; // защита от двойного клика
            if (selected[t]) fd.append(t, selected[t]);
        }
    } else {
        let any = false;
        for (const t of expectedTypes.value) {
            if (selected[t]) {
                fd.append(t, selected[t]);
                any = true;
            }
        }
        if (!any) return;
    }

    submitting.value = true;
    try {
        await axios.post(
            `/api/applications/${props.application.id}/upload-docs`,
            fd,
            {
                headers: { "Content-Type": "multipart/form-data" },
            }
        );
        emit("saved"); // родитель обновит список и закроет модалку
    } catch (e) {
        console.error(e);
        alert("Не удалось загрузить документы");
    } finally {
        submitting.value = false;
    }
};
</script>
