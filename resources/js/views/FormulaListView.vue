<template>
    <div>
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Formulas</h1>
                <p class="text-sm text-gray-500 mt-1">All formula versions — only one can be active at a time.</p>
            </div>
            <RouterLink
                to="/formulas/builder"
                class="bg-indigo-600 text-white px-4 py-2 rounded-md text-sm font-medium hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500"
            >
                New formula
            </RouterLink>
        </div>

        <div class="bg-white rounded-lg shadow-sm border border-gray-200">
            <div class="flex flex-col items-center justify-center py-16 text-center">
                <p class="text-gray-900 font-medium">No formulas yet</p>
                <p class="text-sm text-gray-500 mt-1">Create your first commission formula to get started.</p>
                <RouterLink
                    to="/formulas/builder"
                    class="mt-4 bg-indigo-600 text-white px-4 py-2 rounded-md text-sm font-medium hover:bg-indigo-700"
                >
                    Create formula
                </RouterLink>
            </div>
        </div>
    </div>
</template>

<script setup>
</script>
