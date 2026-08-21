<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    CheckCircle2,
    Clock,
    Eye,
    Inbox,
    Plus,
    RotateCcw,
    Search,
    X,
} from '@lucide/vue';
import { computed, reactive, watch } from 'vue';
import ActionButton from '@/Components/Shared/ActionButton.vue';
import ActionGroup from '@/Components/Shared/ActionGroup.vue';
import PageHeader from '@/Components/Shared/PageHeader.vue';
import Pagination from '@/Components/Shared/Pagination.vue';
import PaymentRequestStatusBadge from '@/Components/Payments/PaymentRequestStatusBadge.vue';
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
    paymentRequests: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    stats: { type: Object, default: null },
});

const page = usePage();
const canCreate = computed(
    () =>
        page.props.auth?.user?.is_superadmin ||
        page.props.permissions?.['finance.payment_request']?.create,
);

const ALL = 'all';
const filters = reactive({
    q: props.filters.q ?? '',
    status: props.filters.status || ALL,
    method: props.filters.method || ALL,
});

let timer = null;
watch(filters, () => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get(
            route('payment-requests.index'),
            {
                q: filters.q,
                status: filters.status === ALL ? '' : filters.status,
                method: filters.method === ALL ? '' : filters.method,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }, 300);
});

function reset() {
    filters.q = '';
    filters.status = ALL;
    filters.method = ALL;
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
    <Head title="Payment Request" />

    <AppLayout>
        <PageHeader title="Payment Request" description="Sales lapor pembayaran customer → Kasir verifikasi → apply ke Invoice." :icon="Inbox">
            <template #actions>
                <Button v-if="canCreate" as-child size="default" variant="secondary">
                    <Link :href="route('payment-requests.create')">
                        <Plus class="size-4" /> Buat Request
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <section v-if="stats" class="grid grid-cols-2 lg:grid-cols-5 gap-3 mb-4">
            <StatCard label="Total" :value="stats.total ?? 0" tone="brand">
                <template #icon><Inbox class="size-5" /></template>
            </StatCard>
            <StatCard label="Draft" :value="stats.draft ?? 0" tone="brand">
                <template #icon><Clock class="size-5" /></template>
            </StatCard>
            <StatCard label="Submitted" :value="stats.submitted ?? 0" tone="brand-orange">
                <template #icon><Clock class="size-5" /></template>
            </StatCard>
            <StatCard label="Verified" :value="stats.verified ?? 0" tone="brand">
                <template #icon><CheckCircle2 class="size-5" /></template>
            </StatCard>
            <StatCard label="Rejected" :value="stats.rejected ?? 0" tone="brand">
                <template #icon><X class="size-5" /></template>
            </StatCard>
        </section>

        <section class="rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm overflow-hidden">
            <div class="border-b border-border/70 px-3 py-2.5 flex flex-col sm:flex-row sm:items-center gap-2">
                <div class="relative flex-1">
                    <Search class="absolute left-2.5 top-1/2 -translate-y-1/2 size-3.5 text-muted-foreground" />
                    <Input v-model="filters.q" placeholder="Cari no invoice, customer, atau referensi…" class="pl-8 h-9 rounded-md" />
                </div>
                <div class="flex items-center gap-2 flex-wrap">
                    <Select v-model="filters.status">
                        <SelectTrigger class="w-[140px] h-9 rounded-md"><SelectValue placeholder="Status" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="ALL">Semua Status</SelectItem>
                            <SelectItem value="draft">Draft</SelectItem>
                            <SelectItem value="submitted">Submitted</SelectItem>
                            <SelectItem value="verified">Verified</SelectItem>
                            <SelectItem value="rejected">Rejected</SelectItem>
                            <SelectItem value="cancelled">Cancelled</SelectItem>
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
                    <Button type="button" variant="outline" size="default" @click="reset">
                        <RotateCcw class="size-3.5" /> Reset
                    </Button>
                </div>
            </div>

            <Table>
                <TableHeader>
                    <TableRow class="[&>th]:text-[11px] [&>th]:font-semibold [&>th]:uppercase [&>th]:tracking-wider [&>th]:text-muted-foreground [&>th]:py-2.5">
                        <TableHead class="pl-4">Invoice</TableHead>
                        <TableHead>Customer</TableHead>
                        <TableHead>Sales</TableHead>
                        <TableHead>Method</TableHead>
                        <TableHead class="text-right">Amount</TableHead>
                        <TableHead>Tgl Bayar</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead class="text-right pr-4">Aksi</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody class="text-sm">
                    <TableRow v-if="paymentRequests.data.length === 0">
                        <TableCell colspan="8" class="text-center py-16">
                            <div class="flex flex-col items-center gap-2 text-muted-foreground">
                                <Inbox class="size-7 opacity-40" />
                                <p class="text-sm">Belum ada payment request.</p>
                            </div>
                        </TableCell>
                    </TableRow>
                    <TableRow v-for="req in paymentRequests.data" :key="req.id" class="hover:bg-muted/30 transition-colors">
                        <TableCell class="pl-4 py-2.5 font-mono text-xs">
                            <Link :href="route('payment-requests.show', req.id)" class="hover:text-primary">
                                {{ req.invoice?.invoice_number }}
                            </Link>
                        </TableCell>
                        <TableCell>
                            <p class="font-medium">{{ req.customer?.name ?? '—' }}</p>
                            <p class="text-[12px] text-muted-foreground font-mono">{{ req.customer?.code }}</p>
                        </TableCell>
                        <TableCell class="text-xs">{{ req.sales?.name ?? '—' }}</TableCell>
                        <TableCell class="text-xs">
                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[11px] font-semibold uppercase bg-muted text-muted-foreground">
                                {{ req.method }}
                            </span>
                        </TableCell>
                        <TableCell class="text-right font-mono">{{ fmtRp(req.amount) }}</TableCell>
                        <TableCell class="text-xs">{{ formatDate(req.paid_at) }}</TableCell>
                        <TableCell>
                            <PaymentRequestStatusBadge :status="req.status" />
                        </TableCell>
                        <TableCell class="py-3 px-4 text-right whitespace-nowrap">
                            <ActionGroup>
                                <ActionButton :icon="Eye" label="Detail" as-child tone="brand">
                                    <Link :href="route('payment-requests.show', req.id)">
                                        <Eye class="w-4 h-4" />
                                    </Link>
                                </ActionButton>
                            </ActionGroup>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>

            <div v-if="paymentRequests.data.length > 0" class="border-t border-border/70 px-4 py-2.5">
                <Pagination :meta="paymentRequests" />
            </div>
        </section>
    </AppLayout>
</template>
