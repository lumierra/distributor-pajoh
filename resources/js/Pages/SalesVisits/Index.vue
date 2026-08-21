<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { CheckCircle2, Clock, Eye, MapPin, XCircle } from '@lucide/vue';
import { reactive, watch } from 'vue';
import PageHeader from '@/Components/Shared/PageHeader.vue';
import Pagination from '@/Components/Shared/Pagination.vue';
import StatTile from '@/Components/Shared/StatTile.vue';
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

const VISIT_STATUS = {
    active: { label: 'Aktif', text: 'text-blue-700', dot: 'bg-blue-500' },
    completed: { label: 'Selesai', text: 'text-emerald-700', dot: 'bg-emerald-600' },
    cancelled: { label: 'Dibatalkan', text: 'text-red-700', dot: 'bg-red-400' },
};
function visitStatus(s) {
    return VISIT_STATUS[s] ?? { label: s, text: 'text-muted-foreground', dot: 'bg-muted-foreground/50' };
}
</script>

<template>
    <Head title="Sales Visits" />

    <AppLayout>
        <PageHeader title="Sales Visits" description="Catatan check-in/out sales di outlet customer." :icon="MapPin" />

        <section v-if="stats" class="grid grid-cols-2 lg:grid-cols-5 gap-3 mb-4">
            <StatTile label="Total" :value="stats.total ?? 0" :icon="MapPin" />
            <StatTile label="Aktif" :value="stats.active ?? 0" :icon="Clock" :tone="(stats.active ?? 0) > 0 ? 'warn' : 'brand'" />
            <StatTile label="Selesai" :value="stats.completed ?? 0" :icon="CheckCircle2" />
            <StatTile label="Dibatalkan" :value="stats.cancelled ?? 0" :icon="XCircle" />
            <StatTile label="Auto-Checkout" :value="stats.auto_checkout ?? 0" :icon="Clock" />
        </section>

        <section class="rounded-2xl bg-card/70 backdrop-blur-xl ring-1 ring-foreground/6 shadow-sm overflow-hidden">
            <div class="px-4 py-3 flex flex-wrap items-center gap-2">
                <Input v-model="filters.from" type="date" class="w-[150px] h-9 rounded-full bg-muted/50 border-transparent" />
                <span class="text-xs text-muted-foreground">→</span>
                <Input v-model="filters.to" type="date" class="w-[150px] h-9 rounded-full bg-muted/50 border-transparent" />
                <Select v-model="filters.sales_id">
                    <SelectTrigger class="w-[180px] h-9 rounded-full bg-muted/50 border-transparent"><SelectValue placeholder="Sales" /></SelectTrigger>
                    <SelectContent>
                        <SelectItem :value="ALL">Semua Sales</SelectItem>
                        <SelectItem v-for="s in salesUsers" :key="s.id" :value="String(s.id)">{{ s.name }}</SelectItem>
                    </SelectContent>
                </Select>
                <Select v-model="filters.status">
                    <SelectTrigger class="w-[140px] h-9 rounded-full bg-muted/50 border-transparent"><SelectValue placeholder="Status" /></SelectTrigger>
                    <SelectContent>
                        <SelectItem :value="ALL">Semua</SelectItem>
                        <SelectItem value="active">Aktif</SelectItem>
                        <SelectItem value="completed">Selesai</SelectItem>
                        <SelectItem value="cancelled">Dibatalkan</SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <Table class="border-t border-foreground/5">
                <TableHeader>
                    <TableRow class="[&>th]:text-[11px] [&>th]:font-semibold [&>th]:uppercase [&>th]:tracking-wider [&>th]:text-muted-foreground [&>th]:py-2.5 hover:bg-transparent">
                        <TableHead class="pl-4">Check-in</TableHead>
                        <TableHead>Sales</TableHead>
                        <TableHead>Customer</TableHead>
                        <TableHead class="text-right">SO</TableHead>
                        <TableHead class="text-right">Durasi</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead class="text-center pr-4">Detail</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody class="text-sm">
                    <TableRow v-if="visits.data.length === 0">
                        <TableCell colspan="7" class="text-center py-12 text-muted-foreground text-sm">Belum ada visit.</TableCell>
                    </TableRow>
                    <TableRow v-for="v in visits.data" :key="v.id" class="hover:bg-foreground/2.5 transition-colors border-foreground/5">
                        <TableCell class="pl-4 text-xs">{{ fmt(v.checked_in_at) }}</TableCell>
                        <TableCell>{{ v.sales?.name ?? '—' }}</TableCell>
                        <TableCell>
                            <p>{{ v.customer?.name ?? '—' }}</p>
                            <p class="text-[12px] text-muted-foreground font-mono">{{ v.customer?.code }}</p>
                        </TableCell>
                        <TableCell class="text-right">{{ v.so_count }}</TableCell>
                        <TableCell class="text-right text-xs">{{ fmtDuration(v.duration_minutes) }}</TableCell>
                        <TableCell>
                            <span :class="['inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[12px] font-medium', visitStatus(v.status).text]">
                                <span :class="['size-1.5 rounded-full', visitStatus(v.status).dot]" />
                                {{ visitStatus(v.status).label }}
                            </span>
                        </TableCell>
                        <TableCell class="text-center pr-4">
                            <div class="flex justify-center">
                                <Button as-child size="sm" variant="ghost" class="h-8 px-2 rounded-full">
                                    <Link :href="route('sales-visits.show', v.id)">
                                        <Eye class="size-3.5" />
                                    </Link>
                                </Button>
                            </div>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>

            <div v-if="visits.data.length > 0" class="border-t border-foreground/5 px-4 py-3">
                <Pagination :meta="visits" />
            </div>
        </section>
    </AppLayout>
</template>
