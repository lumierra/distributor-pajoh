<script setup>
defineProps({
    title: { type: String, required: true },
    subtitle: { type: String, default: '' },
});
</script>

<template>
    <section
        class="gradient-brand-orange text-white rounded-xl px-6 py-5 sm:px-8 sm:py-6 shadow-md mb-6 relative overflow-hidden"
    >
        <!-- Decorative blobs -->
        <div
            class="absolute -top-12 -right-10 w-44 h-44 rounded-full bg-white/8 blur-2xl pointer-events-none"
        ></div>
        <div
            class="absolute -bottom-16 right-32 w-32 h-32 rounded-full bg-warning/20 blur-2xl pointer-events-none"
        ></div>

        <div class="relative flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="min-w-0">
                <h2 class="text-xl sm:text-2xl font-bold flex items-center gap-2">
                    <span>{{ title }}</span>
                    <slot name="badge" />
                </h2>
                <p v-if="subtitle" class="text-sm text-white/85 mt-1">{{ subtitle }}</p>
            </div>
            <div v-if="$slots.right" class="flex-shrink-0">
                <slot name="right" />
            </div>
        </div>
    </section>
</template>
