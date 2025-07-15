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
              {{ msg.content }}
            </div>
          </div>
          <div v-if="chatStore.loading" class="text-center text-gray-500 animate-pulse">GPT думает...</div>
        </div>

        <form @submit.prevent="send" class="flex gap-2">
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
  import Layout from '../components/Layout.vue';
  import { ref, watch, nextTick } from 'vue';
  import { useChatStore } from '../stores/useChatStore';

  const message = ref('');
  const chatStore = useChatStore();
  const chatContainer = ref(null);

  const send = () => {
    if (message.value.trim() !== '') {
      chatStore.sendMessage(message.value.trim());
      message.value = '';
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
