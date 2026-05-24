<script setup>
import { computed } from 'vue';

const props = defineProps({
    status: { type: String, required: true },
});

const config = computed(() => {
    const map = {
        draft: { label: 'Draft', class: 'bg-muted text-muted-foreground' },
        submitted: { label: 'Submitted', class: 'bg-blue-50 text-blue-700 ring-blue-200' },
        pending_credit_review: { label: 'Pending Credit', class: 'bg-warning-soft text-amber-800 ring-warning/30' },
        approved: { label: 'Approved', class: 'bg-emerald-50 text-emerald-700 ring-emerald-200' },
        rejected: { label: 'Rejected', class: 'bg-red-50 text-red-700 ring-red-200' },
        partially_delivered: { label: 'Partial Delivery', class: 'bg-blue-50 text-blue-700 ring-blue-200' },
        delivered: { label: 'Delivered', class: 'bg-emerald-50 text-emerald-700 ring-emerald-200' },
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
