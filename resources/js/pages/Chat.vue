<template>
    <Layout>
        <div class="max-w-2xl mx-auto py-8 px-4">
            <h1 class="text-2xl md:text-3xl font-bold text-center mb-6 text-[#005eb8]">HR Chat АТУ</h1>

            <div
                ref="chatContainer"
                class="bg-white border border-gray-200 rounded-xl shadow-inner p-4 h-[500px] overflow-y-auto mb-4"
            >
                <div
                    v-for="(msg, index) in chatStore.messages"
                    :key="index"
                    class="mb-3 flex"
                    :class="msg.role === 'user' ? 'justify-end' : 'justify-start'"
                >
                    <div
                        :class="msg.role === 'user'
      ? 'bg-[#005eb8] text-white'
      : 'bg-gray-100 text-gray-900'"
                        class="max-w-[70%] rounded-xl px-4 py-2 shadow-sm"
                    >
                        <!-- текст -->
                        <div class="whitespace-pre-line">{{ msg.content }}</div>

                        <!-- кнопки (только у ассистента) -->
                        <div
                            v-if="msg.role !== 'user' && msg.buttons && msg.buttons.length"
                            class="mt-3 flex flex-wrap gap-2"
                        >
                            <a
                                v-for="(btn, i) in msg.buttons"
                                :key="i"
                                :href="btn.url"
                                target="_blank"
                                class="inline-flex items-center px-3 py-1.5 rounded-lg border border-[#005eb8] text-[#005eb8] hover:bg-[#005eb8] hover:text-white transition text-sm"
                            >
                                {{ btn.label }}
                            </a>
                        </div>
                    </div>
                </div>
                <div
                    v-if="chatStore.loading"
                    class="flex items-center justify-center space-x-1 text-[#005eb8] h-6"
                >
                    <span class="sr-only">GPT думает...</span>
                    <span class="w-2 h-2 bg-[#005eb8] rounded-full animate-bounce"></span>
                    <span
                        class="w-2 h-2 bg-[#005eb8] rounded-full animate-bounce"
                        style="animation-delay: 0.2s"
                    ></span>
                    <span
                        class="w-2 h-2 bg-[#005eb8] rounded-full animate-bounce"
                        style="animation-delay: 0.4s"
                    ></span>
                </div>

            </div>

            <form
                @submit.prevent="send"
                class="flex gap-2"
            >
                <input
                    v-model="message"
                    type="text"
                    placeholder="Введите сообщение..."
                    class="flex-1 border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-[#005eb8] transition"
                />
                <button
                    type="submit"
                    class="bg-[#005eb8] hover:bg-blue-700 text-white font-semibold px-4 py-2 rounded-lg transition cursor-pointer"
                >
                    Отправить
                </button>
            </form>
        </div>
    </Layout>
</template>

  <script setup>
import Layout from "../components/Layout.vue";
import { ref, watch, nextTick } from "vue";
import { useChatStore } from "../stores/useChatStore";

const message = ref("");
const chatStore = useChatStore();
const chatContainer = ref(null);

const send = () => {
    if (message.value.trim() !== "") {
        chatStore.sendMessage(message.value.trim());
        message.value = "";
    }
};

// Автопрокрутка вниз при новом сообщении
watch(
    () => chatStore.messages.length,
    async () => {
        await nextTick();
        if (chatContainer.value) {
            chatContainer.value.scrollTop = chatContainer.value.scrollHeight;
        }
    }
);
</script>
