<template>
    <div>
        <nav>
            <RouterLink to="/">Home</RouterLink>
            <RouterLink to="/formulas">Formulas</RouterLink>
            <RouterLink to="/formulas/builder">Builder</RouterLink>
            <RouterLink to="/commission">Commission</RouterLink>
            <RouterLink to="/simulation">Simulation</RouterLink>
            <RouterLink to="/audit">Audit Trail</RouterLink>
        </nav>
        <main>
            <RouterView />
        </main>
    </div>
</template>

<script setup>
</script>
