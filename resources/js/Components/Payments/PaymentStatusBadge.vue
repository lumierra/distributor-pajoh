<script setup>
import { computed } from 'vue';

const props = defineProps({
    status: { type: String, required: true },
});

const config = computed(() => ({
    posted: { label: 'Posted', class: 'bg-emerald-50 text-emerald-700 ring-emerald-200' },
    pending_clearing: { label: 'Pending Clearing', class: 'bg-amber-50 text-amber-800 ring-amber-200' },
    cleared: { label: 'Cleared', class: 'bg-emerald-50 text-emerald-700 ring-emerald-200' },
    bounced: { label: 'Bounced', class: 'bg-red-50 text-red-700 ring-red-200' },
}[props.status] ?? { label: props.status, class: 'bg-muted text-muted-foreground' }));
</script>

<template>
    <span :class="['inline-flex items-center px-2 py-0.5 rounded-md text-[12px] font-medium ring-1', config.class]">
        {{ config.label }}
    </span>
</template>
