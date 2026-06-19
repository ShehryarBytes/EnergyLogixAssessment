<template>
    <div class="min-h-screen bg-gray-50">
        <nav class="bg-white border-b border-gray-200 px-6 py-3 flex items-center gap-6">
            <span class="font-semibold text-gray-900 mr-4">EnergyLogix</span>
            <RouterLink to="/" class="text-sm text-gray-600 hover:text-indigo-600 transition-colors">Home</RouterLink>
            <RouterLink to="/formulas" class="text-sm text-gray-600 hover:text-indigo-600 transition-colors">Formulas</RouterLink>
            <RouterLink to="/formulas/builder" class="text-sm text-gray-600 hover:text-indigo-600 transition-colors">Builder</RouterLink>
            <RouterLink to="/commission" class="text-sm text-gray-600 hover:text-indigo-600 transition-colors">Commission</RouterLink>
            <RouterLink to="/simulation" class="text-sm text-gray-600 hover:text-indigo-600 transition-colors">Simulation</RouterLink>
            <RouterLink to="/audit" class="text-sm text-gray-600 hover:text-indigo-600 transition-colors">Audit Trail</RouterLink>
            <div class="ml-auto">
                <button
                    @click="logout"
                    class="text-sm text-gray-500 hover:text-red-600 transition-colors"
                >
                    Sign out
                </button>
            </div>
        </nav>
        <main class="p-6">
            <RouterView />
        </main>
    </div>
</template>

<script setup>
import { useRouter } from 'vue-router';
import axios from 'axios';

const router = useRouter();

async function logout() {
    try {
        await axios.post('/logout', {}, { headers: { Accept: 'application/json' } });
    } finally {
        router.push('/login');
    }
}
</script>
