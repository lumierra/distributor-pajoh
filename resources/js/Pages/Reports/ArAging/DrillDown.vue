<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, CalendarRange } from '@lucide/vue';
import PageHeader from '@/Components/Shared/PageHeader.vue';
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
    customer: { type: Object, required: true },
    invoices: { type: Array, required: true },
});

function fmt(v) {
    if (!v) return '—';
    return new Date(v).toLocaleString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
}

function fmtRp(v) {
    return 'Rp ' + new Intl.NumberFormat('id-ID').format(Number(v) || 0);
}

function isOverdue(due) {
    return due && new Date(due) < new Date();
}
</script>

<template>
    <Head :title="`AR Aging — ${customer.name}`" />

    <AppLayout>
        <PageHeader :title="`Outstanding — ${customer.name}`" :description="customer.code" :icon="CalendarRange">
            <template #actions>
                <Button as-child variant="outline">
                    <Link :href="route('reports.ar-aging')">
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
                        <TableHead>Due Date</TableHead>
                        <TableHead class="text-right">Total</TableHead>
                        <TableHead class="text-right">Paid</TableHead>
                        <TableHead class="text-right">Outstanding</TableHead>
                        <TableHead class="text-right pr-4">Status</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody class="text-sm">
                    <TableRow v-if="invoices.length === 0">
                        <TableCell colspan="7" class="text-center py-12 text-muted-foreground">Tidak ada outstanding.</TableCell>
                    </TableRow>
                    <TableRow v-for="inv in invoices" :key="inv.id">
                        <TableCell class="pl-4 font-mono text-xs">
                            <Link :href="route('invoices.show', inv.id)" class="hover:text-primary">{{ inv.invoice_number }}</Link>
                        </TableCell>
                        <TableCell class="text-xs">{{ fmt(inv.invoice_date) }}</TableCell>
                        <TableCell :class="['text-xs', isOverdue(inv.due_date) ? 'text-red-700 font-semibold' : '']">{{ fmt(inv.due_date) }}</TableCell>
                        <TableCell class="text-right font-mono">{{ fmtRp(inv.total) }}</TableCell>
                        <TableCell class="text-right font-mono text-muted-foreground">{{ fmtRp(inv.paid_amount) }}</TableCell>
                        <TableCell class="text-right font-mono font-semibold">{{ fmtRp(inv.outstanding) }}</TableCell>
                        <TableCell class="text-right pr-4 text-xs">{{ inv.status }}</TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </section>
    </AppLayout>
</template>
