<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { CheckCircle2, Clock, Eye, MessageSquare, RotateCw, X } from '@lucide/vue';
import { reactive, watch } from 'vue';
import PageHeader from '@/Components/Shared/PageHeader.vue';
import Pagination from '@/Components/Shared/Pagination.vue';
import StatCard from '@/Components/Shared/StatCard.vue';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    notifications: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    stats: { type: Object, default: null },
});

const ALL = 'all';
const filters = reactive({
    q: props.filters.q ?? '',
    status: props.filters.status || ALL,
    category: props.filters.category ?? '',
});

let timer = null;
watch(filters, () => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get(route('wa.notifications.index'), {
            q: filters.q,
            status: filters.status === ALL ? '' : filters.status,
            category: filters.category,
        }, { preserveState: true, preserveScroll: true, replace: true });
    }, 300);
});

function retry(id) {
    router.post(route('wa.notifications.retry', id), {}, { preserveScroll: true });
}

function fmt(v) {
    if (!v) return '—';
    return new Date(v).toLocaleString('id-ID', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' });
}
</script>

<template>
    <Head title="WhatsApp Notifications" />

    <AppLayout>
        <PageHeader title="WhatsApp Notifications" description="Log semua kirim WA + manual send + retry." :icon="MessageSquare" />

        <section v-if="stats" class="grid grid-cols-2 lg:grid-cols-5 gap-3 mb-4">
            <StatCard label="Total" :value="stats.total ?? 0" tone="brand" />
            <StatCard label="Pending" :value="stats.pending ?? 0" tone="brand-orange">
                <template #icon><Clock class="size-5" /></template>
            </StatCard>
            <StatCard label="Sent" :value="stats.sent ?? 0" tone="brand">
                <template #icon><CheckCircle2 class="size-5" /></template>
            </StatCard>
            <StatCard label="Failed" :value="stats.failed ?? 0" tone="brand">
                <template #icon><X class="size-5" /></template>
            </StatCard>
            <StatCard label="Skipped" :value="stats.skipped ?? 0" tone="brand" />
        </section>

        <section class="rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm overflow-hidden">
            <div class="border-b border-border/70 px-3 py-2.5 flex flex-wrap items-center gap-2">
                <Input v-model="filters.q" placeholder="Cari phone, nama, message..." class="w-[280px] h-9" />
                <Input v-model="filters.category" placeholder="Category" class="w-[160px] h-9" />
                <Select v-model="filters.status">
                    <SelectTrigger class="w-[140px] h-9"><SelectValue placeholder="Status" /></SelectTrigger>
                    <SelectContent>
                        <SelectItem :value="ALL">Semua</SelectItem>
                        <SelectItem value="pending">Pending</SelectItem>
                        <SelectItem value="sent">Sent</SelectItem>
                        <SelectItem value="failed">Failed</SelectItem>
                        <SelectItem value="skipped">Skipped</SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <Table>
                <TableHeader>
                    <TableRow class="[&>th]:text-[10px] [&>th]:uppercase [&>th]:text-muted-foreground [&>th]:py-2.5">
                        <TableHead class="pl-4">Tgl</TableHead>
                        <TableHead>Category</TableHead>
                        <TableHead>Recipient</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead>Skip Reason</TableHead>
                        <TableHead class="text-right pr-4">Aksi</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody class="text-sm">
                    <TableRow v-if="notifications.data.length === 0">
                        <TableCell colspan="6" class="text-center py-16 text-muted-foreground text-sm">Belum ada notifikasi.</TableCell>
                    </TableRow>
                    <TableRow v-for="n in notifications.data" :key="n.id">
                        <TableCell class="pl-4 text-xs">{{ fmt(n.created_at) }}</TableCell>
                        <TableCell class="font-mono text-xs">{{ n.category }}</TableCell>
                        <TableCell>
                            <p class="font-medium">{{ n.recipient_name ?? '—' }}</p>
                            <p class="text-[11px] text-muted-foreground font-mono">{{ n.recipient_phone }}</p>
                        </TableCell>
                        <TableCell class="text-xs">{{ n.status }}</TableCell>
                        <TableCell class="text-xs text-muted-foreground">{{ n.skip_reason ?? '—' }}</TableCell>
                        <TableCell class="text-right pr-4">
                            <Button v-if="n.status === 'failed' || n.status === 'pending'" size="sm" variant="outline" class="h-7 px-2" @click="retry(n.id)">
                                <RotateCw class="size-3.5" />
                            </Button>
                            <Button as-child size="sm" variant="ghost" class="h-7 px-2 ml-1">
                                <Link :href="route('wa.notifications.show', n.id)">
                                    <Eye class="size-3.5" />
                                </Link>
                            </Button>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>

            <div v-if="notifications.data.length > 0" class="border-t border-border/70 px-4 py-2.5">
                <Pagination :meta="notifications" />
            </div>
        </section>
    </AppLayout>
</template>
