<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { CalendarRange, Download, FileText, RefreshCw } from '@lucide/vue';
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
    customers: { type: Array, default: () => [] },
});

const ALL = 'all';
const filters = reactive({
    date: props.filters.date ?? '',
    customer_id: props.filters.customer_id ? String(props.filters.customer_id) : ALL,
});

let timer = null;
watch(filters, () => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get(
            route('reports.ar-aging'),
            {
                date: filters.date,
                customer_id: filters.customer_id === ALL ? '' : filters.customer_id,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }, 300);
});

function regenerate() {
    router.post(route('reports.regenerate'), {}, { preserveScroll: true });
}

function exportXlsx() {
    window.location.href = route('reports.export', { reportType: 'ar_aging', ...props.filters });
}

function exportPdf() {
    window.location.href = route('reports.export', { reportType: 'ar_aging', format: 'pdf', ...props.filters });
}

const bucketCategories = ['0-30', '31-60', '61-90', '>90'];
const bucketSeries = computed(() => [{
    name: 'Outstanding',
    data: [
        Number(props.kpi.bucket_0_30) || 0,
        Number(props.kpi.bucket_31_60) || 0,
        Number(props.kpi.bucket_61_90) || 0,
        Number(props.kpi.bucket_over_90) || 0,
    ],
}]);

function fmtRp(v) {
    return 'Rp ' + new Intl.NumberFormat('id-ID').format(Number(v) || 0);
}
</script>

<template>
    <Head title="AR Aging" />

    <AppLayout>
        <PageHeader title="AR Aging" description="Outstanding piutang per customer dibucket 0-30 / 31-60 / 61-90 / >90 hari." :icon="CalendarRange">
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

        <section class="grid grid-cols-2 lg:grid-cols-5 gap-3 mb-4">
            <StatCard label="Total Outstanding" :value="fmtRp(kpi.total_outstanding)" tone="brand-orange" />
            <StatCard label="0-30 hari" :value="fmtRp(kpi.bucket_0_30)" tone="brand" />
            <StatCard label="31-60 hari" :value="fmtRp(kpi.bucket_31_60)" tone="brand" />
            <StatCard label="61-90 hari" :value="fmtRp(kpi.bucket_61_90)" tone="brand-orange" />
            <StatCard label=">90 hari" :value="fmtRp(kpi.bucket_over_90)" tone="brand-orange" />
        </section>

        <section class="mb-4">
            <ReportChart title="Distribusi Outstanding per Bucket"
                type="bar"
                :categories="bucketCategories"
                :series="bucketSeries"
                :height="260"
                :format-y="(v) => fmtRp(v)" />
        </section>

        <section class="rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm overflow-hidden">
            <div class="border-b border-border/70 px-3 py-2.5 flex flex-wrap items-center gap-2">
                <Input v-model="filters.date" type="date" class="w-[150px] h-9" />
                <Select v-model="filters.customer_id">
                    <SelectTrigger class="w-[200px] h-9"><SelectValue placeholder="Customer" /></SelectTrigger>
                    <SelectContent>
                        <SelectItem :value="ALL">Semua Customer</SelectItem>
                        <SelectItem v-for="c in customers" :key="c.id" :value="String(c.id)">{{ c.name }}</SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <Table>
                <TableHeader>
                    <TableRow class="[&>th]:text-[11px] [&>th]:font-semibold [&>th]:uppercase [&>th]:tracking-wider [&>th]:text-muted-foreground [&>th]:py-2.5">
                        <TableHead class="pl-4">Customer</TableHead>
                        <TableHead class="text-right">0-30</TableHead>
                        <TableHead class="text-right">31-60</TableHead>
                        <TableHead class="text-right">61-90</TableHead>
                        <TableHead class="text-right">>90</TableHead>
                        <TableHead class="text-right pr-4">Total</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody class="text-sm">
                    <TableRow v-if="rows.length === 0">
                        <TableCell colspan="6" class="text-center py-16 text-muted-foreground text-sm">Belum ada snapshot AR aging.</TableCell>
                    </TableRow>
                    <TableRow v-for="row in rows" :key="row.id">
                        <TableCell class="pl-4 py-2.5">
                            <Link :href="route('reports.ar-aging.drill-down', row.customer_id)" class="hover:text-primary">
                                <p class="font-medium">{{ row.customer?.name ?? '—' }}</p>
                                <p class="text-[12px] text-muted-foreground font-mono">{{ row.customer?.code }}</p>
                            </Link>
                        </TableCell>
                        <TableCell class="text-right font-mono">{{ fmtRp(row.bucket_0_30) }}</TableCell>
                        <TableCell class="text-right font-mono">{{ fmtRp(row.bucket_31_60) }}</TableCell>
                        <TableCell class="text-right font-mono">{{ fmtRp(row.bucket_61_90) }}</TableCell>
                        <TableCell class="text-right font-mono text-red-700">{{ fmtRp(row.bucket_over_90) }}</TableCell>
                        <TableCell class="text-right font-mono font-semibold pr-4">{{ fmtRp(row.total_outstanding) }}</TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </section>
    </AppLayout>
</template>
