<script setup>
defineProps({
    tabs: {
        type: Array,
        required: true,
        // [{ value: 'admin', label: 'Admin Bisnis', icon: ComponentOrNull }, ...]
    },
    modelValue: { type: [String, Number], required: true },
});

defineEmits(['update:modelValue']);
</script>

<template>
    <div
        class="inline-flex items-center gap-1 p-1 rounded-lg bg-card ring-1 ring-foreground/10 shadow-xs"
    >
        <button
            v-for="t in tabs"
            :key="t.value"
            type="button"
            :class="[
                'inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-md text-sm font-medium transition-all',
                modelValue === t.value
                    ? 'bg-background text-foreground shadow-sm ring-1 ring-foreground/5'
                    : 'text-muted-foreground hover:text-foreground hover:bg-muted',
            ]"
            @click="$emit('update:modelValue', t.value)"
        >
            <component :is="t.icon" v-if="t.icon" class="size-3.5" />
            {{ t.label }}
        </button>
    </div>
</template>
