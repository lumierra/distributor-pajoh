<script setup>
import { computed } from 'vue';

const props = defineProps({
    status: { type: String, required: true },
});

const config = computed(() => {
    const map = {
        draft: { label: 'Draft', class: 'bg-muted text-muted-foreground' },
        picking: { label: 'Picking', class: 'bg-blue-50 text-blue-700 ring-blue-200' },
        packed: { label: 'Packed', class: 'bg-indigo-50 text-indigo-700 ring-indigo-200' },
        in_transit: { label: 'In Transit', class: 'bg-amber-50 text-amber-800 ring-amber-200' },
        delivered: { label: 'Delivered', class: 'bg-emerald-50 text-emerald-700 ring-emerald-200' },
        partial_returned: { label: 'Partial Return', class: 'bg-orange-50 text-orange-700 ring-orange-200' },
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
