import { defineStore } from 'pinia';

export const useCommissionStore = defineStore('commission', {
    state: () => ({
        calculations: [],
    }),
});
