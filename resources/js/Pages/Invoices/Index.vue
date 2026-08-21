<script setup>
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import {
    AlertTriangle,
    CheckCircle2,
    Clock,
    Eye,
    FileText,
    Receipt,
    RefreshCw,
    RotateCcw,
    Search,
    Wallet,
} from '@lucide/vue';
import { computed, reactive, watch } from 'vue';
import ActionButton from '@/Components/Shared/ActionButton.vue';
import ActionGroup from '@/Components/Shared/ActionGroup.vue';
import InvoiceStatusBadge from '@/Components/Invoices/InvoiceStatusBadge.vue';
import PageHeader from '@/Components/Shared/PageHeader.vue';
import Pagination from '@/Components/Shared/Pagination.vue';
import StatTile from '@/Components/Shared/StatTile.vue';
import { confirm } from '@/Composables/useConfirm';
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
    invoices: { type: Object, required: true },
    customers: { type: Array, required: true },
    filters: { type: Object, default: () => ({}) },
    stats: { type: Object, default: null },
});

const page = usePage();
const canMarkOverdue = computed(
    () =>
        page.props.auth?.user?.is_superadmin ||
        page.props.permissions?.['sales.invoice']?.update,
);

const ALL = 'all';
const filters = reactive({
    q: props.filters.q ?? '',
    status: props.filters.status || ALL,
    customer_id: props.filters.customer_id ? String(props.filters.customer_id) : ALL,
    year: props.filters.year || ALL,
});

let timer = null;
watch(filters, () => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get(
            route('invoices.index'),
            {
                q: filters.q,
                status: filters.status === ALL ? '' : filters.status,
                customer_id: filters.customer_id === ALL ? '' : filters.customer_id,
                year: filters.year === ALL ? '' : filters.year,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }, 300);
});

function reset() {
    filters.q = '';
    filters.status = ALL;
    filters.customer_id = ALL;
    filters.year = ALL;
}

async function markOverdueNow() {
    if (
        !(await confirm({
            title: 'Jalankan check overdue sekarang?',
            description: 'Semua invoice open/partial_paid yang due_date lewat akan di-mark overdue.',
        }))
    )
        return;
    useForm({}).post(route('invoices.mark-overdue'), { preserveScroll: true });
}

const currentYear = new Date().getFullYear();
const years = computed(() => [currentYear, currentYear - 1, currentYear - 2]);

function formatDate(value) {
    if (!value) return '—';
    return new Date(value).toLocaleString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
}

function fmtRp(v) {
    return 'Rp ' + new Intl.NumberFormat('id-ID').format(Number(v) || 0);
}

function daysUntilDue(dueDate) {
    if (!dueDate) return null;
    const days = Math.ceil((new Date(dueDate) - new Date()) / (1000 * 60 * 60 * 24));
    return days;
}
</script>

<template>
    <Head title="Faktur" />

    <AppLayout>
        <PageHeader title="Faktur" description="Invoice auto-generated dari DO delivered. View-only — koreksi via Credit Note." :icon="Receipt">
            <template #actions>
                <Button v-if="canMarkOverdue" type="button" size="default" variant="outline" class="rounded-full" @click="markOverdueNow">
                    <RefreshCw class="size-4" /> Cek Overdue
                </Button>
            </template>
        </PageHeader>

        <section v-if="stats" class="grid grid-cols-2 lg:grid-cols-5 gap-3 mb-4">
            <StatTile label="Total Invoice" :value="stats.total ?? 0" :icon="Receipt" />
            <StatTile label="Belum Bayar" :value="stats.open ?? 0" :icon="FileText" />
            <StatTile label="Bayar Sebagian" :value="stats.partial ?? 0" :icon="Clock" />
            <StatTile label="Jatuh Tempo" :value="stats.overdue ?? 0" :icon="AlertTriangle" :tone="(stats.overdue ?? 0) > 0 ? 'warn' : 'brand'" />
            <StatTile label="Outstanding" :value="fmtRp(stats.total_outstanding)" :icon="Wallet" />
        </section>

        <section class="rounded-2xl bg-card/70 backdrop-blur-xl ring-1 ring-foreground/6 shadow-sm overflow-hidden">
            <div class="px-4 py-3 flex flex-col sm:flex-row sm:items-center gap-2">
                <div class="relative flex-1">
                    <Search class="absolute left-3 top-1/2 -translate-y-1/2 size-3.5 text-muted-foreground" />
                    <Input v-model="filters.q" placeholder="Cari no faktur, no SO, atau customer…" class="pl-8 h-9 rounded-full bg-muted/50 border-transparent focus-visible:bg-card" />
                </div>
                <div class="flex items-center gap-2 flex-wrap">
                    <Select v-model="filters.status">
                        <SelectTrigger class="w-[150px] h-9 rounded-full bg-muted/50 border-transparent">
                            <SelectValue placeholder="Status" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="ALL">Semua Status</SelectItem>
                            <SelectItem value="open">Open</SelectItem>
                            <SelectItem value="partial_paid">Partial Paid</SelectItem>
                            <SelectItem value="paid">Paid</SelectItem>
                            <SelectItem value="overdue">Overdue</SelectItem>
                        </SelectContent>
                    </Select>
                    <Select v-model="filters.customer_id">
                        <SelectTrigger class="w-[180px] h-9 rounded-full bg-muted/50 border-transparent">
                            <SelectValue placeholder="Customer" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="ALL">Semua Customer</SelectItem>
                            <SelectItem v-for="c in customers" :key="c.id" :value="String(c.id)">
                                {{ c.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <Select v-model="filters.year">
                        <SelectTrigger class="w-[110px] h-9 rounded-full bg-muted/50 border-transparent">
                            <SelectValue placeholder="Tahun" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="ALL">Semua Tahun</SelectItem>
                            <SelectItem v-for="y in years" :key="y" :value="String(y)">{{ y }}</SelectItem>
                        </SelectContent>
                    </Select>
                    <Button type="button" variant="ghost" size="default" class="rounded-full" @click="reset">
                        <RotateCcw class="size-3.5" /> Reset
                    </Button>
                </div>
            </div>

            <Table class="border-t border-foreground/5">
                <TableHeader>
                    <TableRow class="[&>th]:text-[11px] [&>th]:font-semibold [&>th]:uppercase [&>th]:tracking-wider [&>th]:text-muted-foreground [&>th]:py-2.5">
                        <TableHead class="pl-4">No Faktur</TableHead>
                        <TableHead>Customer</TableHead>
                        <TableHead>Tgl</TableHead>
                        <TableHead>Jatuh Tempo</TableHead>
                        <TableHead class="text-right">Total</TableHead>
                        <TableHead class="text-right">Sisa</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead class="text-center pr-4">Aksi</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody class="text-sm">
                    <TableRow v-if="invoices.data.length === 0">
                        <TableCell colspan="8" class="text-center py-16">
                            <div class="flex flex-col items-center gap-2 text-muted-foreground">
                                <Receipt class="size-7 opacity-40" />
                                <p class="text-sm">Belum ada faktur.</p>
                            </div>
                        </TableCell>
                    </TableRow>
                    <TableRow v-for="inv in invoices.data" :key="inv.id" class="hover:bg-foreground/2.5 transition-colors border-foreground/5">
                        <TableCell class="pl-4 py-2.5">
                            <div class="flex items-center gap-2.5">
                                <div class="size-9 rounded-full bg-primary text-primary-foreground flex items-center justify-center shrink-0">
                                    <Receipt class="size-4" />
                                </div>
                                <div>
                                    <Link :href="route('invoices.show', inv.id)" class="font-medium text-foreground font-mono text-xs hover:text-primary transition-colors block">
                                        {{ inv.invoice_number }}
                                    </Link>
                                    <p class="text-[11px] text-muted-foreground">
                                        SO: <span class="font-mono">{{ inv.sales_order?.so_number }}</span>
                                    </p>
                                </div>
                            </div>
                        </TableCell>
                        <TableCell>
                            <p class="font-medium">{{ inv.customer?.name ?? '—' }}</p>
                            <p class="text-[12px] text-muted-foreground font-mono">{{ inv.customer?.code }}</p>
                        </TableCell>
                        <TableCell class="text-xs">{{ formatDate(inv.invoice_date) }}</TableCell>
                        <TableCell class="text-xs">
                            {{ formatDate(inv.due_date) }}
                            <span v-if="inv.status !== 'paid' && daysUntilDue(inv.due_date) !== null">
                                <span v-if="daysUntilDue(inv.due_date) < 0" class="block text-[11px] text-red-700">
                                    Lewat {{ Math.abs(daysUntilDue(inv.due_date)) }} hari
                                </span>
                                <span v-else-if="daysUntilDue(inv.due_date) <= 7" class="block text-[11px] text-amber-700">
                                    H-{{ daysUntilDue(inv.due_date) }}
                                </span>
                            </span>
                        </TableCell>
                        <TableCell class="text-right font-mono">{{ fmtRp(inv.total) }}</TableCell>
                        <TableCell class="text-right font-mono">
                            <span v-if="Number(inv.outstanding) > 0" class="text-red-700">{{ fmtRp(inv.outstanding) }}</span>
                            <span v-else class="text-emerald-700">—</span>
                        </TableCell>
                        <TableCell>
                            <InvoiceStatusBadge :status="inv.status" />
                            <span v-if="inv.is_cash" class="ml-1 text-[11px] text-muted-foreground">CASH</span>
                        </TableCell>
                        <TableCell class="py-3 pr-4 text-center">
                            <div class="flex justify-center">
                                <ActionGroup class="rounded-full">
                                    <ActionButton :icon="Eye" label="Detail" as-child tone="brand">
                                        <Link :href="route('invoices.show', inv.id)">
                                            <Eye class="w-4 h-4" />
                                        </Link>
                                    </ActionButton>
                                    <ActionButton :icon="FileText" label="PDF" as-child tone="blue">
                                        <a :href="route('invoices.pdf', inv.id)" target="_blank" rel="noopener">
                                            <FileText class="w-4 h-4" />
                                        </a>
                                    </ActionButton>
                                </ActionGroup>
                            </div>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>

            <div v-if="invoices.data.length > 0" class="border-t border-foreground/5 px-4 py-3">
                <Pagination :meta="invoices" />
            </div>
        </section>
    </AppLayout>
</template>
