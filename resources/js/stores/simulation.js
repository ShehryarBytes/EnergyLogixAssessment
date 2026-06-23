import { defineStore } from 'pinia';
import { ref } from 'vue';
import api from '@/services/api.js';

export const useSimulationStore = defineStore('simulation', () => {
    const formulas         = ref([]);
    const loadingFormulas  = ref(false);
    const simulation       = ref(null);
    const simulationStatus = ref('idle'); // idle | running | complete | failed
    const error            = ref(null);

    // Plain JS variable — not reactive, used only for cleanup
    let pollingIntervalId = null;

    async function fetchFormulas() {
        loadingFormulas.value = true;
        try {
            const { data } = await api.get('/formulas');
            // Only show non-active formulas — simulating the currently active one is pointless
            formulas.value = data.data.filter(f => f.status !== 'active');
        } catch {
            formulas.value = [];
        } finally {
            loadingFormulas.value = false;
        }
    }

    async function runSimulation(formulaId) {
        clearPolling();
        simulation.value       = null;
        simulationStatus.value = 'running';
        error.value            = null;

        try {
            const { data } = await api.post('/simulation/run', { formula_id: formulaId });
            startPolling(data.id);
        } catch (err) {
            simulationStatus.value = 'failed';
            error.value = err.response?.data?.message || 'Failed to start simulation.';
            throw err;
        }
    }

    function startPolling(simId) {
        pollingIntervalId = setInterval(async () => {
            try {
                // 'response' holds the full Axios response object
                const { data: response } = await api.get(`/simulation/${simId}`);

                // Fix: Handle Laravel's Resource wrapping. It is usually inside response.data
                const simData = response.data ? response.data : response;

                simulation.value = simData;

                // Normalize the string to catch 'complete', 'Complete', or 'completed'
                const currentStatus = String(simData.status).toLowerCase();

                if (currentStatus === 'complete' || currentStatus === 'completed') {
                    clearPolling();
                    simulationStatus.value = 'complete';
                } else if (currentStatus === 'failed') {
                    clearPolling();
                    simulationStatus.value = 'failed';
                    error.value = 'The simulation job failed. Check the queue worker is running.';
                }
            } catch {
                clearPolling();
                simulationStatus.value = 'failed';
                error.value = 'Lost connection while waiting for simulation results.';
            }
        }, 2000);
    }

    function clearPolling() {
        if (pollingIntervalId !== null) {
            clearInterval(pollingIntervalId);
            pollingIntervalId = null;
        }
    }

    function reset() {
        clearPolling();
        simulation.value       = null;
        simulationStatus.value = 'idle';
        error.value            = null;
    }

    return {
        formulas, loadingFormulas, simulation, simulationStatus, error,
        fetchFormulas, runSimulation, clearPolling, reset,
    };
});
