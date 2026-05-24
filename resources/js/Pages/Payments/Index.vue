<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import {
    Banknote,
    CheckCircle2,
    Clock,
    Eye,
    RotateCcw,
    Search,
    X,
} from '@lucide/vue';
import { reactive, watch } from 'vue';
import ActionButton from '@/Components/Shared/ActionButton.vue';
import ActionGroup from '@/Components/Shared/ActionGroup.vue';
import PageHeader from '@/Components/Shared/PageHeader.vue';
import Pagination from '@/Components/Shared/Pagination.vue';
import PaymentStatusBadge from '@/Components/Payments/PaymentStatusBadge.vue';
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
    payments: { type: Object, required: true },
    customers: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    stats: { type: Object, default: null },
});

const ALL = 'all';
const filters = reactive({
    q: props.filters.q ?? '',
    status: props.filters.status || ALL,
    method: props.filters.method || ALL,
    customer_id: props.filters.customer_id || ALL,
});

let timer = null;
watch(filters, () => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get(
            route('payments.index'),
            {
                q: filters.q,
                status: filters.status === ALL ? '' : filters.status,
                method: filters.method === ALL ? '' : filters.method,
                customer_id: filters.customer_id === ALL ? '' : filters.customer_id,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }, 300);
});

function reset() {
    filters.q = '';
    filters.status = ALL;
    filters.method = ALL;
    filters.customer_id = ALL;
}

function formatDate(v) {
    if (!v) return '—';
    return new Date(v).toLocaleString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
}

function fmtRp(v) {
    return 'Rp ' + new Intl.NumberFormat('id-ID').format(Number(v) || 0);
}
</script>

<template>
    <Head title="Payments" />

    <AppLayout>
        <PageHeader title="Payments" description="Pembayaran customer yang sudah terverifikasi & ter-apply ke invoice." :icon="Banknote" />

        <section v-if="stats" class="grid grid-cols-2 lg:grid-cols-5 gap-3 mb-4">
            <StatCard label="Total" :value="stats.total ?? 0" tone="brand">
                <template #icon><Banknote class="size-5" /></template>
            </StatCard>
            <StatCard label="Posted" :value="stats.posted ?? 0" tone="brand">
                <template #icon><CheckCircle2 class="size-5" /></template>
            </StatCard>
            <StatCard label="Pending Clearing" :value="stats.pending_clearing ?? 0" tone="brand-orange">
                <template #icon><Clock class="size-5" /></template>
            </StatCard>
            <StatCard label="Cleared" :value="stats.cleared ?? 0" tone="brand">
                <template #icon><CheckCircle2 class="size-5" /></template>
            </StatCard>
            <StatCard label="Bounced" :value="stats.bounced ?? 0" tone="brand">
                <template #icon><X class="size-5" /></template>
            </StatCard>
        </section>

        <section class="rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm overflow-hidden">
            <div class="border-b border-border/70 px-3 py-2.5 flex flex-col sm:flex-row sm:items-center gap-2">
                <div class="relative flex-1">
                    <Search class="absolute left-2.5 top-1/2 -translate-y-1/2 size-3.5 text-muted-foreground" />
                    <Input v-model="filters.q" placeholder="Cari no payment, invoice, customer…" class="pl-8 h-9 rounded-md" />
                </div>
                <div class="flex items-center gap-2 flex-wrap">
                    <Select v-model="filters.status">
                        <SelectTrigger class="w-[150px] h-9 rounded-md"><SelectValue placeholder="Status" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="ALL">Semua Status</SelectItem>
                            <SelectItem value="posted">Posted</SelectItem>
                            <SelectItem value="pending_clearing">Pending Clearing</SelectItem>
                            <SelectItem value="cleared">Cleared</SelectItem>
                            <SelectItem value="bounced">Bounced</SelectItem>
                        </SelectContent>
                    </Select>
                    <Select v-model="filters.method">
                        <SelectTrigger class="w-[130px] h-9 rounded-md"><SelectValue placeholder="Metode" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="ALL">Semua Metode</SelectItem>
                            <SelectItem value="cash">Cash</SelectItem>
                            <SelectItem value="transfer">Transfer</SelectItem>
                            <SelectItem value="giro">Giro</SelectItem>
                        </SelectContent>
                    </Select>
                    <Select v-model="filters.customer_id">
                        <SelectTrigger class="w-[180px] h-9 rounded-md"><SelectValue placeholder="Customer" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="ALL">Semua Customer</SelectItem>
                            <SelectItem v-for="c in customers" :key="c.id" :value="String(c.id)">
                                {{ c.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <Button type="button" variant="outline" size="default" @click="reset">
                        <RotateCcw class="size-3.5" /> Reset
                    </Button>
                </div>
            </div>

            <Table>
                <TableHeader>
                    <TableRow class="[&>th]:text-[10px] [&>th]:font-semibold [&>th]:uppercase [&>th]:tracking-wider [&>th]:text-muted-foreground [&>th]:py-2.5">
                        <TableHead class="pl-4">No. Payment</TableHead>
                        <TableHead>Invoice</TableHead>
                        <TableHead>Customer</TableHead>
                        <TableHead>Method</TableHead>
                        <TableHead class="text-right">Amount</TableHead>
                        <TableHead class="text-right">Applied</TableHead>
                        <TableHead>Tgl Bayar</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead class="text-right pr-4">Aksi</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody class="text-sm">
                    <TableRow v-if="payments.data.length === 0">
                        <TableCell colspan="9" class="text-center py-16">
                            <div class="flex flex-col items-center gap-2 text-muted-foreground">
                                <Banknote class="size-7 opacity-40" />
                                <p class="text-sm">Belum ada payment.</p>
                            </div>
                        </TableCell>
                    </TableRow>
                    <TableRow v-for="p in payments.data" :key="p.id" class="hover:bg-muted/30 transition-colors">
                        <TableCell class="pl-4 py-2.5 font-mono text-xs">
                            <Link :href="route('payments.show', p.id)" class="hover:text-primary">
                                {{ p.payment_number }}
                            </Link>
                        </TableCell>
                        <TableCell class="font-mono text-xs">{{ p.invoice?.invoice_number ?? '—' }}</TableCell>
                        <TableCell>
                            <p class="font-medium">{{ p.customer?.name ?? '—' }}</p>
                            <p class="text-[11px] text-muted-foreground font-mono">{{ p.customer?.code }}</p>
                        </TableCell>
                        <TableCell class="text-xs">
                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold uppercase bg-muted text-muted-foreground">
                                {{ p.method }}
                            </span>
                        </TableCell>
                        <TableCell class="text-right font-mono">{{ fmtRp(p.amount) }}</TableCell>
                        <TableCell class="text-right font-mono">{{ fmtRp(p.applied_amount) }}</TableCell>
                        <TableCell class="text-xs">{{ formatDate(p.paid_at) }}</TableCell>
                        <TableCell>
                            <PaymentStatusBadge :status="p.status" />
                        </TableCell>
                        <TableCell class="py-3 px-4 text-right whitespace-nowrap">
                            <ActionGroup>
                                <ActionButton :icon="Eye" label="Detail" as-child tone="brand">
                                    <Link :href="route('payments.show', p.id)">
                                        <Eye class="w-4 h-4" />
                                    </Link>
                                </ActionButton>
                            </ActionGroup>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>

            <div v-if="payments.data.length > 0" class="border-t border-border/70 px-4 py-2.5">
                <Pagination :meta="payments" />
            </div>
        </section>
    </AppLayout>
</template>
