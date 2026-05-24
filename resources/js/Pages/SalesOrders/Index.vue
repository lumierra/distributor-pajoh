<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    CheckCircle2,
    ClipboardCheck,
    Eye,
    FileText,
    Plus,
    RotateCcw,
    Search,
    Send,
    ShoppingBag,
} from '@lucide/vue';
import { computed, reactive, watch } from 'vue';
import ActionButton from '@/Components/Shared/ActionButton.vue';
import ActionGroup from '@/Components/Shared/ActionGroup.vue';
import PageHeader from '@/Components/Shared/PageHeader.vue';
import Pagination from '@/Components/Shared/Pagination.vue';
import SoStatusBadge from '@/Components/SalesOrders/SoStatusBadge.vue';
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
    salesOrders: { type: Object, required: true },
    customers: { type: Array, required: true },
    salesUsers: { type: Array, required: true },
    filters: { type: Object, default: () => ({}) },
    stats: { type: Object, default: null },
});

const page = usePage();
const canCreate = computed(
    () =>
        page.props.auth?.user?.is_superadmin ||
        page.props.permissions?.['sales.so']?.create,
);

const ALL = 'all';
const filters = reactive({
    q: props.filters.q ?? '',
    status: props.filters.status || ALL,
    customer_id: props.filters.customer_id ? String(props.filters.customer_id) : ALL,
    sales_id: props.filters.sales_id ? String(props.filters.sales_id) : ALL,
    year: props.filters.year || ALL,
});

let timer = null;
watch(filters, () => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get(
            route('sales-orders.index'),
            {
                q: filters.q,
                status: filters.status === ALL ? '' : filters.status,
                customer_id: filters.customer_id === ALL ? '' : filters.customer_id,
                sales_id: filters.sales_id === ALL ? '' : filters.sales_id,
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
    filters.sales_id = ALL;
    filters.year = ALL;
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
</script>

<template>
    <Head title="Sales Order" />

    <AppLayout>
        <PageHeader title="Sales Order" description="Pesanan penjualan dari sales ke customer. SO → DO → Invoice." :icon="ShoppingBag">
            <template #actions>
                <Button v-if="canCreate" as-child size="default" variant="secondary">
                    <Link :href="route('sales-orders.create')">
                        <Plus class="size-4" /> Buat SO
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <section v-if="stats" class="grid grid-cols-2 lg:grid-cols-5 gap-3 mb-4">
            <StatCard label="Total SO" :value="stats.total ?? 0" tone="brand">
                <template #icon><ShoppingBag class="size-5" /></template>
            </StatCard>
            <StatCard label="Draft" :value="stats.draft ?? 0" tone="brand">
                <template #icon><FileText class="size-5" /></template>
            </StatCard>
            <StatCard label="Submitted" :value="stats.submitted ?? 0" tone="brand">
                <template #icon><Send class="size-5" /></template>
            </StatCard>
            <StatCard label="Pending Credit" :value="stats.pending_credit ?? 0" tone="brand-orange">
                <template #icon><ClipboardCheck class="size-5" /></template>
            </StatCard>
            <StatCard label="Approved" :value="stats.approved ?? 0" tone="brand">
                <template #icon><CheckCircle2 class="size-5" /></template>
            </StatCard>
        </section>

        <section class="rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm overflow-hidden">
            <div class="border-b border-border/70 px-3 py-2.5 flex flex-col sm:flex-row sm:items-center gap-2">
                <div class="relative flex-1">
                    <Search class="absolute left-2.5 top-1/2 -translate-y-1/2 size-3.5 text-muted-foreground" />
                    <Input v-model="filters.q" placeholder="Cari no. SO atau nama customer…" class="pl-8 h-9 rounded-md" />
                </div>
                <div class="flex items-center gap-2 flex-wrap">
                    <Select v-model="filters.status">
                        <SelectTrigger class="w-[160px] h-9 rounded-md">
                            <SelectValue placeholder="Status" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="ALL">Semua Status</SelectItem>
                            <SelectItem value="draft">Draft</SelectItem>
                            <SelectItem value="submitted">Submitted</SelectItem>
                            <SelectItem value="pending_credit_review">Pending Credit</SelectItem>
                            <SelectItem value="approved">Approved</SelectItem>
                            <SelectItem value="rejected">Rejected</SelectItem>
                            <SelectItem value="partially_delivered">Partial Delivered</SelectItem>
                            <SelectItem value="delivered">Delivered</SelectItem>
                            <SelectItem value="cancelled">Cancelled</SelectItem>
                        </SelectContent>
                    </Select>
                    <Select v-model="filters.customer_id">
                        <SelectTrigger class="w-[180px] h-9 rounded-md">
                            <SelectValue placeholder="Customer" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="ALL">Semua Customer</SelectItem>
                            <SelectItem v-for="c in customers" :key="c.id" :value="String(c.id)">
                                {{ c.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <Select v-model="filters.sales_id">
                        <SelectTrigger class="w-[160px] h-9 rounded-md">
                            <SelectValue placeholder="Sales" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="ALL">Semua Sales</SelectItem>
                            <SelectItem v-for="u in salesUsers" :key="u.id" :value="String(u.id)">
                                {{ u.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <Select v-model="filters.year">
                        <SelectTrigger class="w-[110px] h-9 rounded-md">
                            <SelectValue placeholder="Tahun" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="ALL">Semua Tahun</SelectItem>
                            <SelectItem v-for="y in years" :key="y" :value="String(y)">{{ y }}</SelectItem>
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
                        <TableHead class="pl-4">SO</TableHead>
                        <TableHead>Customer</TableHead>
                        <TableHead>Sales</TableHead>
                        <TableHead>Tgl SO</TableHead>
                        <TableHead class="text-right">Total</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead class="text-right pr-4">Aksi</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody class="text-sm">
                    <TableRow v-if="salesOrders.data.length === 0">
                        <TableCell colspan="7" class="text-center py-16">
                            <div class="flex flex-col items-center gap-2 text-muted-foreground">
                                <ShoppingBag class="size-7 opacity-40" />
                                <p class="text-sm">Belum ada SO.</p>
                            </div>
                        </TableCell>
                    </TableRow>
                    <TableRow v-for="so in salesOrders.data" :key="so.id" class="hover:bg-muted/30 transition-colors">
                        <TableCell class="pl-4 py-2.5">
                            <div class="flex items-center gap-2.5">
                                <div class="size-8 rounded-md bg-primary/10 text-primary flex items-center justify-center shrink-0">
                                    <ShoppingBag class="size-4" />
                                </div>
                                <Link :href="route('sales-orders.show', so.id)" class="font-medium text-foreground font-mono hover:text-primary transition-colors">
                                    {{ so.so_number }}
                                </Link>
                            </div>
                        </TableCell>
                        <TableCell>
                            <p class="font-medium">{{ so.customer?.name ?? '—' }}</p>
                            <p class="text-[11px] text-muted-foreground font-mono">{{ so.customer?.code }}</p>
                        </TableCell>
                        <TableCell class="text-xs">{{ so.sales?.name ?? '—' }}</TableCell>
                        <TableCell class="text-xs">{{ formatDate(so.so_date) }}</TableCell>
                        <TableCell class="text-right font-mono">{{ fmtRp(so.total) }}</TableCell>
                        <TableCell>
                            <SoStatusBadge :status="so.status" />
                        </TableCell>
                        <TableCell class="py-3 px-4 text-right whitespace-nowrap">
                            <ActionGroup>
                                <ActionButton :icon="Eye" label="Detail" as-child tone="brand">
                                    <Link :href="route('sales-orders.show', so.id)">
                                        <Eye class="w-4 h-4" />
                                    </Link>
                                </ActionButton>
                            </ActionGroup>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>

            <div v-if="salesOrders.data.length > 0" class="border-t border-border/70 px-4 py-2.5">
                <Pagination :meta="salesOrders" />
            </div>
        </section>
    </AppLayout>
</template>
