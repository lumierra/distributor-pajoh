<script setup>
import { Head, router } from '@inertiajs/vue3';
import { Activity, Download, FileText } from '@lucide/vue';
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
    bySales: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    salesUsers: { type: Array, default: () => [] },
});

const ALL = 'all';
const filters = reactive({
    from: props.filters.from ?? '',
    to: props.filters.to ?? '',
    sales_id: props.filters.sales_id ? String(props.filters.sales_id) : ALL,
});

let timer = null;
watch(filters, () => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get(route('reports.sales-activity'), {
            from: filters.from,
            to: filters.to,
            sales_id: filters.sales_id === ALL ? '' : filters.sales_id,
        }, { preserveState: true, preserveScroll: true, replace: true });
    }, 300);
});

function exportXlsx() {
    window.location.href = route('reports.export', { reportType: 'sales_activity', ...props.filters });
}

function exportPdf() {
    window.location.href = route('reports.export', { reportType: 'sales_activity', format: 'pdf', ...props.filters });
}

const salesCategories = computed(() => props.bySales.slice(0, 8).map((r) => r.sales?.name?.substring(0, 14) ?? '—'));
const salesSeries = computed(() => [
    { name: 'SO Count', data: props.bySales.slice(0, 8).map((r) => Number(r.so_count) || 0) },
    { name: 'Visits', data: props.bySales.slice(0, 8).map((r) => Number(r.total_visits) || 0) },
]);

function fmtRp(v) {
    return 'Rp ' + new Intl.NumberFormat('id-ID').format(Number(v) || 0);
}
</script>

<template>
    <Head title="Sales Activity" />

    <AppLayout>
        <PageHeader title="Sales Activity" description="SO count, value, approval rate per sales." :icon="Activity">
            <template #actions>
                <Button size="default" variant="outline" @click="exportPdf">
                    <FileText class="size-4" /> PDF
                </Button>
                <Button size="default" @click="exportXlsx">
                    <Download class="size-4" /> Export Excel
                </Button>
            </template>
        </PageHeader>

        <section class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
            <StatCard label="Total Sales Aktif" :value="kpi.sales ?? 0" tone="brand" />
            <StatCard label="SO Count" :value="kpi.so_count ?? 0" tone="brand" />
            <StatCard label="SO Value" :value="fmtRp(kpi.so_value)" tone="brand" />
            <StatCard label="Approved / Cancelled" :value="(kpi.so_approved ?? 0) + ' / ' + (kpi.so_cancelled ?? 0)" tone="brand-orange" />
        </section>

        <section v-if="bySales.length > 0" class="mb-4">
            <ReportChart title="Top 8 Sales: Visits vs SO Count"
                type="bar"
                :categories="salesCategories"
                :series="salesSeries"
                :height="280" />
        </section>

        <section class="rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm overflow-hidden">
            <div class="border-b border-border/70 px-3 py-2.5 flex flex-wrap items-center gap-2">
                <Input v-model="filters.from" type="date" class="w-[150px] h-9" />
                <span class="text-xs text-muted-foreground">→</span>
                <Input v-model="filters.to" type="date" class="w-[150px] h-9" />
                <Select v-model="filters.sales_id">
                    <SelectTrigger class="w-[200px] h-9"><SelectValue placeholder="Sales" /></SelectTrigger>
                    <SelectContent>
                        <SelectItem :value="ALL">Semua Sales</SelectItem>
                        <SelectItem v-for="s in salesUsers" :key="s.id" :value="String(s.id)">{{ s.name }}</SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <Table>
                <TableHeader>
                    <TableRow class="[&>th]:text-[10px] [&>th]:font-semibold [&>th]:uppercase [&>th]:tracking-wider [&>th]:text-muted-foreground [&>th]:py-2.5">
                        <TableHead class="pl-4">Sales</TableHead>
                        <TableHead class="text-right">SO Count</TableHead>
                        <TableHead class="text-right">SO Value</TableHead>
                        <TableHead class="text-right">Approved</TableHead>
                        <TableHead class="text-right pr-4">Cancelled</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody class="text-sm">
                    <TableRow v-if="bySales.length === 0">
                        <TableCell colspan="5" class="text-center py-16 text-muted-foreground text-sm">Belum ada snapshot sales activity.</TableCell>
                    </TableRow>
                    <TableRow v-for="row in bySales" :key="row.sales_id">
                        <TableCell class="pl-4 py-2.5 font-medium">{{ row.sales?.name ?? '—' }}</TableCell>
                        <TableCell class="text-right font-mono">{{ row.so_count }}</TableCell>
                        <TableCell class="text-right font-mono font-semibold">{{ fmtRp(row.so_value) }}</TableCell>
                        <TableCell class="text-right">{{ row.so_approved }}</TableCell>
                        <TableCell class="text-right pr-4">{{ row.so_cancelled }}</TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </section>
    </AppLayout>
</template>
