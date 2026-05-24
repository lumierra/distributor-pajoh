<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, BarChart3 } from '@lucide/vue';
import PageHeader from '@/Components/Shared/PageHeader.vue';
import Pagination from '@/Components/Shared/Pagination.vue';
import { Button } from '@/Components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table';
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps({
    invoices: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
});

function fmtRp(v) {
    return 'Rp ' + new Intl.NumberFormat('id-ID').format(Number(v) || 0);
}

function fmt(v) {
    if (!v) return '—';
    return new Date(v).toLocaleString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
}
</script>

<template>
    <Head title="Sales Drill-Down" />

    <AppLayout>
        <PageHeader title="Sales Drill-Down" :description="`Filter: ${JSON.stringify(filters)}`" :icon="BarChart3">
            <template #actions>
                <Button as-child variant="outline">
                    <Link :href="route('reports.sales')">
                        <ArrowLeft class="size-4" /> Kembali
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <section class="rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm overflow-hidden">
            <Table>
                <TableHeader>
                    <TableRow class="[&>th]:text-[10px] [&>th]:uppercase [&>th]:text-muted-foreground [&>th]:py-2.5">
                        <TableHead class="pl-4">Invoice</TableHead>
                        <TableHead>Tgl</TableHead>
                        <TableHead>Customer</TableHead>
                        <TableHead>Sales</TableHead>
                        <TableHead class="text-right">Total</TableHead>
                        <TableHead class="text-right">Paid</TableHead>
                        <TableHead class="text-right pr-4">Outstanding</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody class="text-sm">
                    <TableRow v-if="invoices.data.length === 0">
                        <TableCell colspan="7" class="text-center py-12 text-muted-foreground">Tidak ada invoice.</TableCell>
                    </TableRow>
                    <TableRow v-for="inv in invoices.data" :key="inv.id">
                        <TableCell class="pl-4 font-mono text-xs">
                            <Link :href="route('invoices.show', inv.id)" class="hover:text-primary">{{ inv.invoice_number }}</Link>
                        </TableCell>
                        <TableCell class="text-xs">{{ fmt(inv.invoice_date) }}</TableCell>
                        <TableCell>{{ inv.customer?.name ?? '—' }}</TableCell>
                        <TableCell class="text-xs">{{ inv.sales?.name ?? '—' }}</TableCell>
                        <TableCell class="text-right font-mono">{{ fmtRp(inv.total) }}</TableCell>
                        <TableCell class="text-right font-mono text-muted-foreground">{{ fmtRp(inv.paid_amount) }}</TableCell>
                        <TableCell class="text-right font-mono font-semibold pr-4">{{ fmtRp(inv.outstanding) }}</TableCell>
                    </TableRow>
                </TableBody>
            </Table>

            <div v-if="invoices.data.length > 0" class="border-t border-border/70 px-4 py-2.5">
                <Pagination :meta="invoices" />
            </div>
        </section>
    </AppLayout>
</template>
