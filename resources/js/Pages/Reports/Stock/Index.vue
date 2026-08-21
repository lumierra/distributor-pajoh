<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { Download, FileText, Package, RefreshCw } from '@lucide/vue';
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
    products: { type: Array, default: () => [] },
});

const ALL = 'all';
const filters = reactive({
    date: props.filters.date ?? '',
    product_id: props.filters.product_id ? String(props.filters.product_id) : ALL,
});

let timer = null;
watch(filters, () => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get(
            route('reports.stock'),
            {
                date: filters.date,
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
    window.location.href = route('reports.export', { reportType: 'stock_position', ...props.filters });
}

function exportPdf() {
    window.location.href = route('reports.export', { reportType: 'stock_position', format: 'pdf', ...props.filters });
}

const topProducts = computed(() => [...props.rows].slice(0, 8));
const chartCategories = computed(() => topProducts.value.map((r) => r.product?.name?.substring(0, 14) ?? '—'));
const chartSeries = computed(() => [
    { name: 'Stock Value', data: topProducts.value.map((r) => Number(r.stock_value) || 0) },
]);

function fmtRp(v) {
    return 'Rp ' + new Intl.NumberFormat('id-ID').format(Number(v) || 0);
}
</script>

<template>
    <Head title="Posisi Stok" />

    <AppLayout>
        <PageHeader title="Posisi Stok" description="Snapshot stok per produk × batch dengan avg cost & stock value." :icon="Package">
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
            <StatCard label="Produk Aktif" :value="kpi.products ?? 0" tone="brand" />
            <StatCard label="Total Qty (base)" :value="(kpi.qty_total ?? 0).toLocaleString('id-ID')" tone="brand" />
            <StatCard label="Total Value" :value="fmtRp(kpi.total_value)" tone="brand" />
            <StatCard label="Stale Batches (>60d)" :value="kpi.stale_batches ?? 0" tone="brand-orange" />
        </section>

        <section v-if="topProducts.length > 0" class="mb-4">
            <ReportChart title="Top 8 Produk by Stock Value"
                type="bar"
                :categories="chartCategories"
                :series="chartSeries"
                :height="280"
                :format-y="(v) => fmtRp(v)" />
        </section>

        <section class="rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm overflow-hidden">
            <div class="border-b border-border/70 px-3 py-2.5 flex flex-wrap items-center gap-2">
                <Input v-model="filters.date" type="date" class="w-[150px] h-9" />
                <Select v-model="filters.product_id">
                    <SelectTrigger class="w-[200px] h-9"><SelectValue placeholder="Produk" /></SelectTrigger>
                    <SelectContent>
                        <SelectItem :value="ALL">Semua Produk</SelectItem>
                        <SelectItem v-for="p in products" :key="p.id" :value="String(p.id)">{{ p.name }}</SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <Table>
                <TableHeader>
                    <TableRow class="[&>th]:text-[11px] [&>th]:font-semibold [&>th]:uppercase [&>th]:tracking-wider [&>th]:text-muted-foreground [&>th]:py-2.5">
                        <TableHead class="pl-4">Produk</TableHead>
                        <TableHead>Batch</TableHead>
                        <TableHead class="text-right">Qty</TableHead>
                        <TableHead class="text-right">Reserved</TableHead>
                        <TableHead class="text-right">Avg Cost</TableHead>
                        <TableHead class="text-right">Value</TableHead>
                        <TableHead class="text-right pr-4">Days Idle</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody class="text-sm">
                    <TableRow v-if="rows.length === 0">
                        <TableCell colspan="7" class="text-center py-16 text-muted-foreground text-sm">Belum ada snapshot stok.</TableCell>
                    </TableRow>
                    <TableRow v-for="row in rows" :key="row.id">
                        <TableCell class="pl-4 py-2.5">
                            <Link :href="route('reports.stock.drill-down', { productId: row.product_id, batchId: row.batch_id ?? '' })" class="hover:text-primary">
                                <p class="font-medium">{{ row.product?.name ?? '—' }}</p>
                                <p class="text-[12px] text-muted-foreground font-mono">{{ row.product?.sku }}</p>
                            </Link>
                        </TableCell>
                        <TableCell class="font-mono text-xs">{{ row.batch?.batch_code ?? '—' }}</TableCell>
                        <TableCell class="text-right font-mono">{{ row.qty_on_hand_base }}</TableCell>
                        <TableCell class="text-right font-mono text-muted-foreground">{{ row.qty_reserved_base }}</TableCell>
                        <TableCell class="text-right font-mono">{{ fmtRp(row.avg_cost) }}</TableCell>
                        <TableCell class="text-right font-mono font-semibold">{{ fmtRp(row.stock_value) }}</TableCell>
                        <TableCell class="text-right pr-4">{{ row.days_since_last_movement ?? '—' }}</TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </section>
    </AppLayout>
</template>
