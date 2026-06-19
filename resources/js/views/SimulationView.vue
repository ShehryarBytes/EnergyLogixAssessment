<template>
    <div>
        <!-- Page header -->
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900">Impact Simulation</h1>
            <p class="text-sm text-gray-500 mt-1">
                Preview how activating a new formula would affect all existing contracts before committing.
            </p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

            <!-- Left: Controls -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h2 class="text-sm font-semibold text-gray-900 mb-4">Simulation Controls</h2>

                <div v-if="simStore.loadingFormulas" class="text-sm text-gray-500">
                    Loading formulas…
                </div>
                <div v-else>
                    <div class="mb-4">
                        <label for="sim-formula" class="block text-sm font-medium text-gray-700 mb-1">
                            Formula to simulate
                        </label>
                        <select
                            id="sim-formula"
                            v-model="selectedFormulaId"
                            :disabled="isRunning"
                            class="block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:opacity-50"
                        >
                            <option value="">— Select a formula —</option>
                            <option
                                v-for="formula in simStore.formulas"
                                :key="formula.id"
                                :value="formula.id"
                            >
                                {{ formula.name }} v{{ formula.version }}
                                <template v-if="formula.status === 'archived'"> (archived)</template>
                            </option>
                        </select>

                        <!-- Formula expression preview -->
                        <div v-if="selectedFormula" class="mt-3 bg-gray-50 rounded-md border border-gray-200 p-3">
                            <p class="text-xs text-gray-500 mb-1">Expression</p>
                            <p class="font-mono text-sm text-gray-900">{{ selectedFormula.expression }}</p>
                        </div>
                    </div>

                    <button
                        @click="handleRun"
                        :disabled="!canRun"
                        class="w-full bg-indigo-600 text-white px-4 py-2 rounded-md text-sm font-medium hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:opacity-50"
                    >
                        {{ isRunning ? 'Simulation running…' : 'Run Simulation' }}
                    </button>

                    <p v-if="isRunning" class="mt-2 text-xs text-gray-500 text-center">
                        Processing all contracts — this may take a moment.
                    </p>
                </div>
            </div>

            <!-- Right: Results -->
            <div>
                <!-- Idle state -->
                <div
                    v-if="simStore.simulationStatus === 'idle'"
                    class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 h-full flex items-center justify-center"
                >
                    <p class="text-sm text-gray-500 text-center">
                        Select a formula and run a simulation to see the projected impact.
                    </p>
                </div>

                <!-- Running / polling -->
                <div
                    v-else-if="isRunning"
                    class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 flex flex-col items-center justify-center gap-4"
                >
                    <span class="inline-block w-8 h-8 border-4 border-indigo-200 border-t-indigo-600 rounded-full animate-spin"></span>
                    <p class="text-sm text-gray-700 font-medium">Analysing all contracts…</p>
                    <p class="text-xs text-gray-500">Checking every two seconds for results.</p>
                </div>

                <!-- Failed -->
                <div
                    v-else-if="simStore.simulationStatus === 'failed'"
                    class="rounded-md bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-600"
                >
                    {{ simStore.error ?? 'The simulation failed. Please try again.' }}
                </div>

                <!-- Complete -->
                <div v-else-if="simStore.simulationStatus === 'complete' && simStore.simulation">

                    <!-- Stat cards 2×2 grid -->
                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Affected Contracts</p>
                            <p class="text-2xl font-bold text-gray-900">
                                {{ simStore.simulation.affected_contracts ?? '—' }}
                            </p>
                        </div>
                        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Current Total</p>
                            <p class="text-2xl font-bold text-gray-900">
                                {{ formatCurrency(simStore.simulation.current_total) }}
                            </p>
                        </div>
                        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">New Total</p>
                            <p class="text-2xl font-bold text-gray-900">
                                {{ formatCurrency(simStore.simulation.new_total) }}
                            </p>
                        </div>
                        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
                            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Difference</p>
                            <p class="text-2xl font-bold" :class="differenceClass">
                                {{ formatCurrency(simStore.simulation.difference) }}
                            </p>
                        </div>
                    </div>

                    <!-- Dry-run notice -->
                    <div class="rounded-md bg-blue-50 border border-blue-200 px-4 py-3 text-sm text-blue-700 mb-4">
                        This is a preview only — no commission records have been created or changed.
                        Contract calculations remain unchanged until you activate the formula.
                    </div>

                    <!-- Activate button -->
                    <button
                        @click="handleActivate"
                        :disabled="activating"
                        class="w-full bg-indigo-600 text-white px-4 py-2 rounded-md text-sm font-medium hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:opacity-50"
                    >
                        {{ activating ? 'Activating…' : 'Activate This Formula' }}
                    </button>
                </div>
            </div>

        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useRouter } from 'vue-router';
import { useSimulationStore } from '@/stores/simulation.js';
import { useFormulaStore }    from '@/stores/formula.js';

const router      = useRouter();
const simStore    = useSimulationStore();
const formulaStore = useFormulaStore();

const selectedFormulaId = ref('');
const activating        = ref(false);

onMounted(() => simStore.fetchFormulas());
onUnmounted(() => simStore.clearPolling());

const selectedFormula = computed(() =>
    simStore.formulas.find(f => f.id === Number(selectedFormulaId.value)) ?? null
);

const isRunning = computed(() => simStore.simulationStatus === 'running');

const canRun = computed(() =>
    selectedFormulaId.value !== '' && !isRunning.value
);

const differenceClass = computed(() => {
    const diff = Number(simStore.simulation?.difference ?? 0);
    return diff >= 0 ? 'text-green-600' : 'text-red-600';
});

async function handleRun() {
    if (!canRun.value) return;
    try {
        await simStore.runSimulation(Number(selectedFormulaId.value));
    } catch (err) {
        // runSimulation sets error state on the store
    }
}

async function handleActivate() {
    if (!window.confirm(
        `Activate "${selectedFormula.value?.name} v${selectedFormula.value?.version}"?\n` +
        'This will archive the currently active formula and cannot be undone.'
    )) return;

    activating.value = true;
    try {
        await formulaStore.activateFormula(Number(selectedFormulaId.value));
        simStore.reset();
        router.push('/formulas');
    } catch {
        // error surfaced via the formula store
    } finally {
        activating.value = false;
    }
}

function formatCurrency(value) {
    if (value === null || value === undefined) return '—';
    return '£' + Number(value).toLocaleString('en-GB', {
        minimumFractionDigits: 4,
        maximumFractionDigits: 4,
    });
}
</script>
