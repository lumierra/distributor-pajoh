<script setup>
import { Head, router } from '@inertiajs/vue3';
import { Download, TrendingUp } from '@lucide/vue';
import { reactive, watch } from 'vue';
import PageHeader from '@/Components/Shared/PageHeader.vue';
import StatCard from '@/Components/Shared/StatCard.vue';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
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
    byProduct: { type: Array, default: () => [] },
    byCustomer: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});

const filters = reactive({
    from: props.filters.from ?? '',
    to: props.filters.to ?? '',
});

let timer = null;
watch(filters, () => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get(route('reports.margin'), { from: filters.from, to: filters.to }, {
            preserveState: true, preserveScroll: true, replace: true,
        });
    }, 300);
});

function exportXlsx() {
    window.location.href = route('reports.export', { reportType: 'margin', ...filters });
}

function fmtRp(v) {
    return 'Rp ' + new Intl.NumberFormat('id-ID').format(Number(v) || 0);
}
</script>

<template>
    <Head title="Laporan Margin" />

    <AppLayout>
        <PageHeader title="Laporan Margin" description="Margin per produk dan customer (revenue - HPP)." :icon="TrendingUp">
            <template #actions>
                <Button size="default" @click="exportXlsx">
                    <Download class="size-4" /> Export Excel
                </Button>
            </template>
        </PageHeader>

        <section class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
            <StatCard label="Revenue" :value="fmtRp(kpi.revenue)" tone="brand" />
            <StatCard label="Cost" :value="fmtRp(kpi.cost)" tone="brand" />
            <StatCard label="Margin" :value="fmtRp(kpi.margin)" tone="brand" />
            <StatCard label="Margin %" :value="(kpi.margin_percent ?? 0) + '%'" tone="brand-orange" />
        </section>

        <section class="rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm p-3 mb-4 flex items-center gap-2">
            <Input v-model="filters.from" type="date" class="w-[150px] h-9" />
            <span class="text-xs text-muted-foreground">→</span>
            <Input v-model="filters.to" type="date" class="w-[150px] h-9" />
        </section>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <section class="rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm overflow-hidden">
                <h3 class="text-sm font-semibold px-4 py-3 border-b">Top Produk by Margin</h3>
                <Table>
                    <TableHeader>
                        <TableRow class="[&>th]:text-[10px] [&>th]:font-semibold [&>th]:uppercase [&>th]:text-muted-foreground">
                            <TableHead class="pl-4">Produk</TableHead>
                            <TableHead class="text-right">Revenue</TableHead>
                            <TableHead class="text-right pr-4">Margin</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody class="text-sm">
                        <TableRow v-if="byProduct.length === 0">
                            <TableCell colspan="3" class="text-center py-10 text-muted-foreground text-sm">Belum ada data.</TableCell>
                        </TableRow>
                        <TableRow v-for="row in byProduct" :key="row.product_id">
                            <TableCell class="pl-4 py-2.5">{{ row.product?.name ?? '—' }}</TableCell>
                            <TableCell class="text-right font-mono">{{ fmtRp(row.revenue) }}</TableCell>
                            <TableCell class="text-right font-mono font-semibold pr-4">{{ fmtRp(row.margin) }}</TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </section>

            <section class="rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm overflow-hidden">
                <h3 class="text-sm font-semibold px-4 py-3 border-b">Top Customer by Margin</h3>
                <Table>
                    <TableHeader>
                        <TableRow class="[&>th]:text-[10px] [&>th]:font-semibold [&>th]:uppercase [&>th]:text-muted-foreground">
                            <TableHead class="pl-4">Customer</TableHead>
                            <TableHead class="text-right">Revenue</TableHead>
                            <TableHead class="text-right pr-4">Margin</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody class="text-sm">
                        <TableRow v-if="byCustomer.length === 0">
                            <TableCell colspan="3" class="text-center py-10 text-muted-foreground text-sm">Belum ada data.</TableCell>
                        </TableRow>
                        <TableRow v-for="row in byCustomer" :key="row.customer_id">
                            <TableCell class="pl-4 py-2.5">{{ row.customer?.name ?? '—' }}</TableCell>
                            <TableCell class="text-right font-mono">{{ fmtRp(row.revenue) }}</TableCell>
                            <TableCell class="text-right font-mono font-semibold pr-4">{{ fmtRp(row.margin) }}</TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </section>
        </div>
    </AppLayout>
</template>
