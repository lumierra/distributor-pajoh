<script setup>
import { computed } from 'vue';

const props = defineProps({
    status: { type: String, required: true },
});

const config = computed(() => {
    const map = {
        draft: { label: 'Draft', class: 'bg-muted text-muted-foreground' },
        approved: { label: 'Approved', class: 'bg-emerald-50 text-emerald-700 ring-emerald-200' },
        partial_received: { label: 'Partial', class: 'bg-blue-50 text-blue-700 ring-blue-200' },
        closed: { label: 'Closed', class: 'bg-gray-100 text-gray-700 ring-gray-300' },
        cancelled: { label: 'Cancelled', class: 'bg-red-50 text-red-700 ring-red-200' },
    };
    return map[props.status] ?? { label: props.status, class: 'bg-muted text-muted-foreground' };
});
</script>

<template>
    <span :class="['inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-medium ring-1', config.class]">
        {{ config.label }}
    </span>
</template>
