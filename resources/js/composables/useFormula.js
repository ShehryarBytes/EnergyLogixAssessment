import api from '@/services/api.js';

export function useFormula() {
    const fetchFormulas = () => api.get('/formulas');
    const fetchFormula = (id) => api.get(`/formulas/${id}`);
    const createFormula = (data) => api.post('/formulas', data);
    const activateFormula = (id) => api.post(`/formulas/${id}/activate`);
    const validateFormula = (id, data) => api.post(`/formulas/${id}/validate`, data);

    return { fetchFormulas, fetchFormula, createFormula, activateFormula, validateFormula };
}
