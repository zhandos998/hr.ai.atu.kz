import { defineStore } from 'pinia';
import axios from 'axios';

export const useAuthStore = defineStore('auth', {
    state: () => ({
        user: null,
        role: null,
        loading: true, // ✅
    }),

    actions: {
        async fetchUser() {
            try {
                const response = await axios.get('/api/user');
                this.user = response.data;
                this.role = response.data.role;
                // console.log('User loaded:', this.user);
                // console.log('Role:', this.role);
                return response.data; // ✅ добавляем возврат
            } catch (error) {
                this.user = null;
                this.role = null;
                console.error('Ошибка загрузки пользователя', error);
                return null; // ✅ возврат null в случае ошибки
            }
            finally {
                this.loading = false; // ✅ загрузка завершена
            }
        },


        logout() {
            this.user = null;
            this.role = null;
            localStorage.removeItem('token');
            delete axios.defaults.headers.common['Authorization'];
        },
    },
});
