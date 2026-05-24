<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { CheckCircle2, Clock, Eye, MapPin, Search, X, XCircle } from '@lucide/vue';
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
    visits: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    salesUsers: { type: Array, default: () => [] },
    stats: { type: Object, default: null },
});

const ALL = 'all';
const filters = reactive({
    sales_id: props.filters.sales_id ? String(props.filters.sales_id) : ALL,
    status: props.filters.status || ALL,
    from: props.filters.from ?? '',
    to: props.filters.to ?? '',
});

let timer = null;
watch(filters, () => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get(route('sales-visits.index'), {
            sales_id: filters.sales_id === ALL ? '' : filters.sales_id,
            status: filters.status === ALL ? '' : filters.status,
            from: filters.from, to: filters.to,
        }, { preserveState: true, preserveScroll: true, replace: true });
    }, 300);
});

function fmt(v) {
    if (!v) return '—';
    return new Date(v).toLocaleString('id-ID', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' });
}

function fmtDuration(min) {
    if (min === null || min === undefined) return '—';
    const h = Math.floor(min / 60);
    const m = min % 60;
    return h > 0 ? `${h}j ${m}m` : `${m}m`;
}

function statusBadge(s) {
    return {
        active: 'bg-blue-50 text-blue-700 ring-blue-200',
        completed: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
        cancelled: 'bg-red-50 text-red-700 ring-red-200',
    }[s] ?? 'bg-muted text-muted-foreground';
}
</script>

<template>
    <Head title="Sales Visits" />

    <AppLayout>
        <PageHeader title="Sales Visits" description="Catatan check-in/out sales di outlet customer." :icon="MapPin" />

        <section v-if="stats" class="grid grid-cols-2 lg:grid-cols-5 gap-3 mb-4">
            <StatCard label="Total" :value="stats.total ?? 0" tone="brand" />
            <StatCard label="Active" :value="stats.active ?? 0" tone="brand-orange">
                <template #icon><Clock class="size-5" /></template>
            </StatCard>
            <StatCard label="Completed" :value="stats.completed ?? 0" tone="brand">
                <template #icon><CheckCircle2 class="size-5" /></template>
            </StatCard>
            <StatCard label="Cancelled" :value="stats.cancelled ?? 0" tone="brand">
                <template #icon><XCircle class="size-5" /></template>
            </StatCard>
            <StatCard label="Auto-Checkout" :value="stats.auto_checkout ?? 0" tone="brand" />
        </section>

        <section class="rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm overflow-hidden">
            <div class="border-b border-border/70 px-3 py-2.5 flex flex-wrap items-center gap-2">
                <Input v-model="filters.from" type="date" class="w-[150px] h-9" />
                <span class="text-xs text-muted-foreground">→</span>
                <Input v-model="filters.to" type="date" class="w-[150px] h-9" />
                <Select v-model="filters.sales_id">
                    <SelectTrigger class="w-[180px] h-9"><SelectValue placeholder="Sales" /></SelectTrigger>
                    <SelectContent>
                        <SelectItem :value="ALL">Semua Sales</SelectItem>
                        <SelectItem v-for="s in salesUsers" :key="s.id" :value="String(s.id)">{{ s.name }}</SelectItem>
                    </SelectContent>
                </Select>
                <Select v-model="filters.status">
                    <SelectTrigger class="w-[140px] h-9"><SelectValue placeholder="Status" /></SelectTrigger>
                    <SelectContent>
                        <SelectItem :value="ALL">Semua</SelectItem>
                        <SelectItem value="active">Active</SelectItem>
                        <SelectItem value="completed">Completed</SelectItem>
                        <SelectItem value="cancelled">Cancelled</SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <Table>
                <TableHeader>
                    <TableRow class="[&>th]:text-[10px] [&>th]:uppercase [&>th]:text-muted-foreground [&>th]:py-2.5">
                        <TableHead class="pl-4">Check-in</TableHead>
                        <TableHead>Sales</TableHead>
                        <TableHead>Customer</TableHead>
                        <TableHead class="text-right">SO</TableHead>
                        <TableHead class="text-right">Durasi</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead class="text-right pr-4">Detail</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody class="text-sm">
                    <TableRow v-if="visits.data.length === 0">
                        <TableCell colspan="7" class="text-center py-12 text-muted-foreground text-sm">Belum ada visit.</TableCell>
                    </TableRow>
                    <TableRow v-for="v in visits.data" :key="v.id">
                        <TableCell class="pl-4 text-xs">{{ fmt(v.checked_in_at) }}</TableCell>
                        <TableCell>{{ v.sales?.name ?? '—' }}</TableCell>
                        <TableCell>
                            <p>{{ v.customer?.name ?? '—' }}</p>
                            <p class="text-[11px] text-muted-foreground font-mono">{{ v.customer?.code }}</p>
                        </TableCell>
                        <TableCell class="text-right">{{ v.so_count }}</TableCell>
                        <TableCell class="text-right text-xs">{{ fmtDuration(v.duration_minutes) }}</TableCell>
                        <TableCell>
                            <span :class="['inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-medium ring-1 capitalize', statusBadge(v.status)]">
                                {{ v.status }}
                            </span>
                        </TableCell>
                        <TableCell class="text-right pr-4">
                            <Button as-child size="sm" variant="ghost" class="h-7 px-2">
                                <Link :href="route('sales-visits.show', v.id)">
                                    <Eye class="size-3.5" />
                                </Link>
                            </Button>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>

            <div v-if="visits.data.length > 0" class="border-t border-border/70 px-4 py-2.5">
                <Pagination :meta="visits" />
            </div>
        </section>
    </AppLayout>
</template>
