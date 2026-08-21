<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, MessageSquare } from '@lucide/vue';
import PageHeader from '@/Components/Shared/PageHeader.vue';
import { Button } from '@/Components/ui/button';
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps({
    notification: { type: Object, required: true },
});

function fmt(v) {
    if (!v) return '—';
    return new Date(v).toLocaleString('id-ID');
}
</script>

<template>
    <Head :title="`WA #${notification.id}`" />

    <AppLayout>
        <PageHeader :title="`WA #${notification.id}`" :description="notification.category" :icon="MessageSquare">
            <template #actions>
                <Button as-child variant="outline">
                    <Link :href="route('wa.notifications.index')">
                        <ArrowLeft class="size-4" /> Kembali
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <section class="lg:col-span-2 rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm p-4 space-y-3">
                <h3 class="text-sm font-semibold">Detail</h3>
                <dl class="grid grid-cols-2 gap-3 text-sm">
                    <div><dt class="text-[12px] uppercase text-muted-foreground">Category</dt><dd>{{ notification.category }}</dd></div>
                    <div><dt class="text-[12px] uppercase text-muted-foreground">Status</dt><dd class="font-semibold">{{ notification.status }}</dd></div>
                    <div><dt class="text-[12px] uppercase text-muted-foreground">Recipient</dt><dd>{{ notification.recipient_name ?? '—' }} ({{ notification.recipient_phone }})</dd></div>
                    <div><dt class="text-[12px] uppercase text-muted-foreground">Created</dt><dd>{{ fmt(notification.created_at) }}</dd></div>
                    <div v-if="notification.sent_at"><dt class="text-[12px] uppercase text-muted-foreground">Sent</dt><dd>{{ fmt(notification.sent_at) }}</dd></div>
                    <div v-if="notification.retry_count"><dt class="text-[12px] uppercase text-muted-foreground">Retry Count</dt><dd>{{ notification.retry_count }}</dd></div>
                </dl>
                <div>
                    <h4 class="text-xs font-semibold uppercase text-muted-foreground mb-1">Message</h4>
                    <pre class="text-sm bg-muted/30 p-3 rounded whitespace-pre-wrap font-sans">{{ notification.message }}</pre>
                </div>
                <div v-if="notification.error_message">
                    <h4 class="text-xs font-semibold uppercase text-red-600 mb-1">Error</h4>
                    <p class="text-xs text-red-700">{{ notification.error_message }}</p>
                </div>
            </section>

            <aside class="rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm p-4 text-xs space-y-2">
                <h3 class="text-sm font-semibold mb-1">Context</h3>
                <pre class="bg-muted/30 p-2 rounded overflow-auto">{{ JSON.stringify(notification.context_data, null, 2) }}</pre>
            </aside>
        </div>
    </AppLayout>
</template>
