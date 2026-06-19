import { defineStore } from 'pinia';
import api from '@/services/api.js';

export const useAuthStore = defineStore('auth', {
    state: () => ({
        user: null,
    }),

    actions: {
        async fetchUser() {
            try {
                const { data } = await api.get('/user');
                this.user = data;
            } catch {
                this.user = null;
            }
        },
    },
});
