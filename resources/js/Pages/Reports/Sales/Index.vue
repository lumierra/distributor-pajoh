<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { BarChart3, Download, FileText, RefreshCw } from '@lucide/vue';
import { computed, reactive, watch } from 'vue';
import ReportChart from '@/Components/Reports/ReportChart.vue';
import PageHeader from '@/Components/Shared/PageHeader.vue';
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
    kpi: { type: Object, default: () => ({}) },
    rows: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    salesUsers: { type: Array, default: () => [] },
    customers: { type: Array, default: () => [] },
    products: { type: Array, default: () => [] },
});

const ALL = 'all';
const filters = reactive({
    from: props.filters.from ?? '',
    to: props.filters.to ?? '',
    sales_id: props.filters.sales_id ? String(props.filters.sales_id) : ALL,
    customer_id: props.filters.customer_id ? String(props.filters.customer_id) : ALL,
    product_id: props.filters.product_id ? String(props.filters.product_id) : ALL,
});

let timer = null;
watch(filters, () => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get(
            route('reports.sales'),
            {
                from: filters.from,
                to: filters.to,
                sales_id: filters.sales_id === ALL ? '' : filters.sales_id,
                customer_id: filters.customer_id === ALL ? '' : filters.customer_id,
                product_id: filters.product_id === ALL ? '' : filters.product_id,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }, 300);
});

function regenerate() {
    router.post(route('reports.regenerate'), {}, { preserveScroll: true });
}

function exportXlsx() {
    window.location.href = route('reports.export', { reportType: 'sales_summary', ...props.filters });
}

function exportPdf() {
    window.location.href = route('reports.export', { reportType: 'sales_summary', format: 'pdf', ...props.filters });
}

const chartCategories = computed(() => [...props.rows].reverse().map((r) => r.snapshot_date));
const chartSeries = computed(() => [
    { name: 'Revenue', data: [...props.rows].reverse().map((r) => Number(r.revenue) || 0) },
    { name: 'Margin', data: [...props.rows].reverse().map((r) => Number(r.margin) || 0) },
]);

function fmtRp(v) {
    return 'Rp ' + new Intl.NumberFormat('id-ID').format(Number(v) || 0);
}

function fmtDate(v) {
    if (!v) return '—';
    return new Date(v).toLocaleString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
}
</script>

<template>
    <Head title="Laporan Penjualan" />

    <AppLayout>
        <PageHeader title="Laporan Penjualan" description="Aggregated dari snapshot harian. Revenue, cost, margin per dimensi." :icon="BarChart3">
            <template #actions>
                <Button variant="outline" size="default" @click="regenerate">
                    <RefreshCw class="size-4" /> Refresh
                </Button>
                <Button size="default" variant="outline" @click="exportPdf">
                    <FileText class="size-4" /> PDF
                </Button>
                <Button size="default" @click="exportXlsx">
                    <Download class="size-4" /> Export Excel
                </Button>
            </template>
        </PageHeader>

        <section class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
            <StatCard label="Invoices" :value="kpi.invoices ?? 0" tone="brand" />
            <StatCard label="Revenue" :value="fmtRp(kpi.revenue)" tone="brand" />
            <StatCard label="Margin" :value="fmtRp(kpi.margin)" tone="brand" />
            <StatCard label="Margin %" :value="(kpi.margin_percent ?? 0) + '%'" tone="brand-orange" />
        </section>

        <section v-if="rows.length > 0" class="mb-4">
            <ReportChart title="Revenue vs Margin per Tanggal"
                type="line"
                :categories="chartCategories"
                :series="chartSeries"
                :height="280"
                :format-y="(v) => fmtRp(v)" />
        </section>

        <section class="mb-3 text-xs text-right">
            <Link :href="route('reports.sales.drill-down', filters)" class="text-primary hover:underline">
                Lihat semua invoice (drill-down) →
            </Link>
        </section>

        <section class="rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm overflow-hidden">
            <div class="border-b border-border/70 px-3 py-2.5 flex flex-wrap items-center gap-2">
                <Input v-model="filters.from" type="date" class="w-[150px] h-9" />
                <span class="text-xs text-muted-foreground">→</span>
                <Input v-model="filters.to" type="date" class="w-[150px] h-9" />
                <Select v-model="filters.sales_id">
                    <SelectTrigger class="w-[170px] h-9"><SelectValue placeholder="Sales" /></SelectTrigger>
                    <SelectContent>
                        <SelectItem :value="ALL">Semua Sales</SelectItem>
                        <SelectItem v-for="s in salesUsers" :key="s.id" :value="String(s.id)">{{ s.name }}</SelectItem>
                    </SelectContent>
                </Select>
                <Select v-model="filters.customer_id">
                    <SelectTrigger class="w-[180px] h-9"><SelectValue placeholder="Customer" /></SelectTrigger>
                    <SelectContent>
                        <SelectItem :value="ALL">Semua Customer</SelectItem>
                        <SelectItem v-for="c in customers" :key="c.id" :value="String(c.id)">{{ c.name }}</SelectItem>
                    </SelectContent>
                </Select>
                <Select v-model="filters.product_id">
                    <SelectTrigger class="w-[180px] h-9"><SelectValue placeholder="Produk" /></SelectTrigger>
                    <SelectContent>
                        <SelectItem :value="ALL">Semua Produk</SelectItem>
                        <SelectItem v-for="p in products" :key="p.id" :value="String(p.id)">{{ p.name }}</SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <Table>
                <TableHeader>
                    <TableRow class="[&>th]:text-[10px] [&>th]:font-semibold [&>th]:uppercase [&>th]:tracking-wider [&>th]:text-muted-foreground [&>th]:py-2.5">
                        <TableHead class="pl-4">Tanggal</TableHead>
                        <TableHead class="text-right">Invoices</TableHead>
                        <TableHead class="text-right">Revenue</TableHead>
                        <TableHead class="text-right">Cost (HPP)</TableHead>
                        <TableHead class="text-right pr-4">Margin</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody class="text-sm">
                    <TableRow v-if="rows.length === 0">
                        <TableCell colspan="5" class="text-center py-16 text-muted-foreground text-sm">Belum ada data untuk filter ini.</TableCell>
                    </TableRow>
                    <TableRow v-for="(row, idx) in rows" :key="idx">
                        <TableCell class="pl-4 py-2.5">{{ fmtDate(row.snapshot_date) }}</TableCell>
                        <TableCell class="text-right">{{ row.invoice_count }}</TableCell>
                        <TableCell class="text-right font-mono">{{ fmtRp(row.revenue) }}</TableCell>
                        <TableCell class="text-right font-mono">{{ fmtRp(row.cost_total) }}</TableCell>
                        <TableCell class="text-right font-mono font-semibold pr-4">{{ fmtRp(row.margin) }}</TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </section>
    </AppLayout>
</template>
