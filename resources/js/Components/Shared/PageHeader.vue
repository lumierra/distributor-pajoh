<script setup>
defineProps({
    title: { type: String, required: true },
    description: { type: String, default: '' },
    icon: { type: [Object, Function], default: null },
});
</script>

<template>
    <header
        class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between mb-5 rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm px-4 py-3"
    >
        <div class="flex items-center gap-3">
            <div
                v-if="icon"
                class="size-10 rounded-md bg-primary/10 text-primary flex items-center justify-center shrink-0"
            >
                <component :is="icon" class="size-5" />
            </div>
            <div>
                <h1 class="text-base font-bold tracking-tight text-foreground leading-tight">
                    {{ title }}
                </h1>
                <p v-if="description" class="text-xs text-muted-foreground mt-0.5">
                    {{ description }}
                </p>
            </div>
        </div>
        <div v-if="$slots.actions" class="flex flex-wrap items-center gap-2">
            <slot name="actions" />
        </div>
    </header>
</template>
