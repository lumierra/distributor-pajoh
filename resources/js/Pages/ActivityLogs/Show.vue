<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, ClipboardList } from '@lucide/vue';
import PageHeader from '@/Components/Shared/PageHeader.vue';
import { Button } from '@/Components/ui/button';
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps({
    log: { type: Object, required: true },
});

function fmt(v) {
    if (!v) return '—';
    return new Date(v).toLocaleString('id-ID');
}
</script>

<template>
    <Head :title="`Log #${log.id}`" />

    <AppLayout>
        <PageHeader :title="`Activity Log #${log.id}`" :description="log.action" :icon="ClipboardList">
            <template #actions>
                <Button as-child variant="outline">
                    <Link :href="route('activity-logs.index')">
                        <ArrowLeft class="size-4" /> Kembali
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <section class="rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm p-4 space-y-3">
                <h3 class="text-sm font-semibold">Detail</h3>
                <dl class="grid grid-cols-2 gap-3 text-sm">
                    <div><dt class="text-[12px] uppercase text-muted-foreground">Action</dt><dd class="font-mono">{{ log.action }}</dd></div>
                    <div><dt class="text-[12px] uppercase text-muted-foreground">User</dt><dd>{{ log.user?.name ?? log.user_name_snapshot ?? '—' }}</dd></div>
                    <div><dt class="text-[12px] uppercase text-muted-foreground">Created</dt><dd>{{ fmt(log.created_at) }}</dd></div>
                    <div><dt class="text-[12px] uppercase text-muted-foreground">Channel</dt><dd>{{ log.channel ?? '—' }}</dd></div>
                    <div><dt class="text-[12px] uppercase text-muted-foreground">Model</dt><dd class="text-xs">{{ log.model_type }}</dd></div>
                    <div><dt class="text-[12px] uppercase text-muted-foreground">Label</dt><dd>{{ log.model_label ?? '—' }}</dd></div>
                    <div><dt class="text-[12px] uppercase text-muted-foreground">IP</dt><dd class="font-mono text-xs">{{ log.ip ?? '—' }}</dd></div>
                </dl>
            </section>
            <section class="rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm p-4 space-y-2 text-xs">
                <h3 class="text-sm font-semibold mb-1">Diff</h3>
                <div>
                    <p class="text-[11px] uppercase text-muted-foreground mb-1">Before</p>
                    <pre class="bg-muted/30 p-2 rounded overflow-auto">{{ JSON.stringify(log.before, null, 2) }}</pre>
                </div>
                <div>
                    <p class="text-[11px] uppercase text-muted-foreground mb-1">After</p>
                    <pre class="bg-muted/30 p-2 rounded overflow-auto">{{ JSON.stringify(log.after, null, 2) }}</pre>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
