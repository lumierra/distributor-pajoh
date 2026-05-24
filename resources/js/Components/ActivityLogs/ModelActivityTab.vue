<script setup>
import { onMounted, ref } from 'vue';

const props = defineProps({
    modelType: { type: String, required: true },
    modelId: { type: [Number, String], required: true },
});

const logs = ref([]);
const loading = ref(true);

async function load() {
    loading.value = true;
    try {
        const url = route('activity-logs.for-model', { model_type: props.modelType, model_id: props.modelId });
        const res = await fetch(url, {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' },
        });
        logs.value = await res.json();
    } finally {
        loading.value = false;
    }
}

onMounted(load);

function fmt(v) {
    if (!v) return '—';
    return new Date(v).toLocaleString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}
</script>

<template>
    <div class="space-y-2">
        <h3 class="text-sm font-semibold">Activity Log</h3>
        <div v-if="loading" class="text-xs text-muted-foreground">Loading…</div>
        <div v-else-if="logs.length === 0" class="text-xs text-muted-foreground">Belum ada aktivitas.</div>
        <ul v-else class="text-sm space-y-1 max-h-96 overflow-y-auto">
            <li v-for="log in logs" :key="log.id" class="flex items-start gap-2 py-1 border-b last:border-0">
                <div class="flex-1">
                    <p class="text-xs font-medium">{{ log.action }}</p>
                    <p class="text-[11px] text-muted-foreground">
                        oleh {{ log.user?.name ?? log.user_name_snapshot ?? '—' }} • {{ fmt(log.created_at) }}
                    </p>
                </div>
            </li>
        </ul>
    </div>
</template>
