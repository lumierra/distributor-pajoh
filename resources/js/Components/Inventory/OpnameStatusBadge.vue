<script setup>
import { computed } from 'vue';

const props = defineProps({
    status: { type: String, required: true },
});

const config = computed(() => {
    const map = {
        draft: { label: 'Draft', text: 'text-muted-foreground', dot: 'bg-muted-foreground/50' },
        posted: { label: 'Posted', text: 'text-emerald-700', dot: 'bg-emerald-500' },
        cancelled: { label: 'Dibatalkan', text: 'text-red-700', dot: 'bg-red-500' },
    };
    return map[props.status] ?? { label: props.status, text: 'text-muted-foreground', dot: 'bg-muted-foreground/50' };
});
</script>

<template>
    <span :class="['inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[12px] font-medium', config.text]">
        <span :class="['size-1.5 rounded-full', config.dot]" />
        {{ config.label }}
    </span>
</template>
