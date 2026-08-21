<script setup>
import { computed } from 'vue';

const props = defineProps({
    status: { type: String, required: true },
});

const config = computed(() => ({
    draft: { label: 'Draft', class: 'bg-muted text-muted-foreground ring-border' },
    approved: { label: 'Approved', class: 'bg-blue-50 text-blue-700 ring-blue-200' },
    sent: { label: 'Sent', class: 'bg-amber-50 text-amber-800 ring-amber-200' },
    settled: { label: 'Settled', class: 'bg-emerald-50 text-emerald-700 ring-emerald-200' },
    cancelled: { label: 'Cancelled', class: 'bg-red-50 text-red-700 ring-red-200' },
}[props.status] ?? { label: props.status, class: 'bg-muted text-muted-foreground' }));
</script>

<template>
    <span :class="['inline-flex items-center px-2 py-0.5 rounded-md text-[12px] font-medium ring-1', config.class]">
        {{ config.label }}
    </span>
</template>
