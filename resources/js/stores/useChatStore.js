import { defineStore } from 'pinia'

export const useChatStore = defineStore('chat', {
    state: () => ({
        messages: [],
        loading: false,
    }),

    actions: {
        async sendMessage(text) {
            // пушим сообщение пользователя
            this.messages.push({ role: 'user', content: text })

            this.loading = true

            try {
                const token = localStorage.getItem('token');

                const res = await fetch('/api/chat/send', {
                    method: 'POST',
                    headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'Authorization': token ? `Bearer ${token}` : undefined,
                    },
                    body: JSON.stringify({ message: text }),
                });

                // Логируем статус и заголовки (полезно при дебаге CORS/авторизации)
                console.debug('[chat/send] status', res.status, res.statusText);
                console.debug('[chat/send] headers', Object.fromEntries(res.headers.entries()));

                let payload;
                try {
                    payload = await res.json();
                } catch (parseErr) {
                    console.error('[chat/send] JSON parse error:', parseErr);
                    const raw = await res.text().catch(() => '');
                    console.error('[chat/send] raw body:', raw);
                    throw new Error('Ответ сервера не JSON');
                }

                if (!res.ok) {
                    console.error('[chat/send] HTTP error:', res.status, payload);
                    throw new Error(payload?.message || `Ошибка ${res.status}`);
                }

                this.messages.push({
                    role: 'assistant',
                    content: payload.text ?? '',
                    buttons: Array.isArray(payload.buttons) ? payload.buttons : [],
                });
            } catch (e) {
                console.error('[chat/send] request failed:', e);
                this.messages.push({
                    role: 'assistant',
                    content: 'Кешіріңіз/Извините, сервис уақытша қолжетімсіз.',
                    buttons: []
                });
            } finally {
                this.loading = false;
            }
        }
    }
})