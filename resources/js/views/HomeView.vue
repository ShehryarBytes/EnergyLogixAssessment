<template>
    <div>
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900">Dashboard</h1>
            <p class="text-sm text-gray-500 mt-1">EnergyLogix Dynamic Commission Engine</p>
        </div>

        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
            <RouterLink
                v-for="card in cards"
                :key="card.to"
                :to="card.to"
                class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 hover:border-indigo-300 transition-colors"
            >
                <h2 class="text-sm font-medium text-gray-900">{{ card.title }}</h2>
                <p class="text-sm text-gray-500 mt-1">{{ card.description }}</p>
            </RouterLink>
        </div>
    </div>
</template>

<script setup>
const cards = [
    { to: '/formulas',         title: 'Formulas',            description: 'Create and manage commission formula versions.' },
    { to: '/formulas/builder', title: 'Formula Builder',     description: 'Build and validate a new formula expression.' },
    { to: '/commission',       title: 'Commission Calculator', description: 'Run a commission calculation against a contract.' },
    { to: '/simulation',       title: 'Impact Simulation',   description: 'Preview how a formula change affects all contracts.' },
    { to: '/audit',            title: 'Audit Trail',         description: 'Review all historical commission calculations.' },
];
</script>
