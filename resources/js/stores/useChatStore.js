import { defineStore } from 'pinia';
import axios from 'axios';

export const useChatStore = defineStore('chat', {
  state: () => ({
    messages: [],
    loading: false,
  }),
  actions: {
    async sendMessage(message) {
      this.loading = true;
      this.messages.push({ role: 'user', content: message });

      try {
        const response = await axios.post('/api/chat/send', { message });
        this.messages.push({ role: 'assistant', content: response.data.message });
      } catch (error) {
        this.messages.push({ role: 'assistant', content: 'Ошибка ответа от GPT, попробуйте позже.' });
      } finally {
        this.loading = false;
      }
    },
  },
});
