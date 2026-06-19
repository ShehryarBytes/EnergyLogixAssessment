import { defineStore } from 'pinia';
import { ref } from 'vue';
import api from '@/services/api.js';

export const useCommissionStore = defineStore('commission', () => {
    const contracts       = ref([]);
    const activeFormula   = ref(null);

    const loadingContracts = ref(false);
    const loadingFormula   = ref(false);
    const calculating      = ref(false);
    const error            = ref(null);

    async function fetchContracts() {
        loadingContracts.value = true;
        error.value = null;
        try {
            const { data } = await api.get('/contracts');
            contracts.value = data.data;
        } catch (err) {
            error.value = err.response?.data?.message ?? 'Failed to load contracts.';
        } finally {
            loadingContracts.value = false;
        }
    }

    async function fetchActiveFormula() {
        loadingFormula.value = true;
        try {
            const { data } = await api.get('/formulas');
            // Scan the current page for the active formula — only one can be active at a time
            activeFormula.value = data.data.find(f => f.status === 'active') ?? null;
        } catch {
            activeFormula.value = null;
        } finally {
            loadingFormula.value = false;
        }
    }

    async function calculate(contractId) {
        calculating.value = true;
        error.value = null;
        try {
            const { data } = await api.post('/commission/calculate', { contract_id: contractId });
            return data.data; // unwrap Laravel resource envelope
        } catch (err) {
            error.value = err.response?.data?.message ?? 'Calculation failed.';
            throw err;
        } finally {
            calculating.value = false;
        }
    }

    return {
        contracts, activeFormula,
        loadingContracts, loadingFormula, calculating, error,
        fetchContracts, fetchActiveFormula, calculate,
    };
});
