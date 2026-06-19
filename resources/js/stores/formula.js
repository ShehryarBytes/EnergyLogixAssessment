import { defineStore } from 'pinia';
import { ref } from 'vue';
import api from '@/services/api.js';

export const useFormulaStore = defineStore('formula', () => {
    const formulas = ref([]);
    const loading  = ref(false);
    const error    = ref(null);

    async function fetchFormulas() {
        loading.value = true;
        error.value   = null;
        try {
            const { data } = await api.get('/formulas');
            formulas.value = data.data; // paginated response — grab the items array
        } catch (err) {
            error.value = err.response?.data?.message ?? 'Failed to load formulas.';
            throw err;
        } finally {
            loading.value = false;
        }
    }

    async function createFormula(payload) {
        const { data } = await api.post('/formulas', payload);
        return data.data;
    }

    async function activateFormula(id) {
        const { data } = await api.post(`/formulas/${id}/activate`);
        await fetchFormulas(); // refresh list so status badges update
        return data.data;
    }

    async function validateExpression(expression, variables = []) {
        const { data } = await api.post('/formulas/validate', { expression, variables });
        return data;
    }

    return { formulas, loading, error, fetchFormulas, createFormula, activateFormula, validateExpression };
});
