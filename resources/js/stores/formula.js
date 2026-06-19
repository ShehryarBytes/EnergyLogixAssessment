import { defineStore } from 'pinia';

export const useFormulaStore = defineStore('formula', {
    state: () => ({
        formulas: [],
        activeFormula: null,
    }),
});
