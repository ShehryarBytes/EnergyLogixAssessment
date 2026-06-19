import api from '@/services/api.js';

export function useSimulation() {
    const runSimulation = (data) => api.post('/simulation/run', data);
    const fetchSimulation = (id) => api.get(`/simulation/${id}`);

    return { runSimulation, fetchSimulation };
}
