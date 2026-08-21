<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Package } from '@lucide/vue';
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
    product: { type: Object, default: null },
    batch: { type: Object, default: null },
    ledgers: { type: Object, required: true },
});

function fmt(v) {
    if (!v) return '—';
    return new Date(v).toLocaleString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function typeBadge(t) {
    if (t.includes('_in')) return 'bg-emerald-50 text-emerald-700 ring-emerald-200';
    if (t.includes('_out') || t === 'write_off') return 'bg-red-50 text-red-700 ring-red-200';
    return 'bg-muted text-muted-foreground';
}
</script>

<template>
    <Head title="Stock Ledger Drill-Down" />

    <AppLayout>
        <PageHeader :title="`Ledger — ${product?.name ?? 'Product'}`" :description="batch ? `Batch: ${batch.batch_code}` : 'All batches'" :icon="Package">
            <template #actions>
                <Button as-child variant="outline">
                    <Link :href="route('reports.stock')">
                        <ArrowLeft class="size-4" /> Kembali
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <section class="rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm overflow-hidden">
            <Table>
                <TableHeader>
                    <TableRow class="[&>th]:text-[11px] [&>th]:uppercase [&>th]:text-muted-foreground [&>th]:py-2.5">
                        <TableHead class="pl-4">Tgl</TableHead>
                        <TableHead>Type</TableHead>
                        <TableHead>Batch</TableHead>
                        <TableHead class="text-right">In</TableHead>
                        <TableHead class="text-right">Out</TableHead>
                        <TableHead class="text-right">Cost</TableHead>
                        <TableHead>Ref</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody class="text-sm">
                    <TableRow v-if="ledgers.data.length === 0">
                        <TableCell colspan="7" class="text-center py-12 text-muted-foreground">Tidak ada movement.</TableCell>
                    </TableRow>
                    <TableRow v-for="l in ledgers.data" :key="l.id">
                        <TableCell class="pl-4 text-xs">{{ fmt(l.created_at) }}</TableCell>
                        <TableCell>
                            <span :class="['inline-flex items-center px-1.5 py-0.5 rounded text-[11px] font-semibold uppercase ring-1', typeBadge(l.type)]">
                                {{ l.type }}
                            </span>
                        </TableCell>
                        <TableCell class="font-mono text-xs">{{ l.batch?.batch_code ?? '—' }}</TableCell>
                        <TableCell class="text-right font-mono">{{ l.qty_in > 0 ? l.qty_in : '' }}</TableCell>
                        <TableCell class="text-right font-mono">{{ l.qty_out > 0 ? l.qty_out : '' }}</TableCell>
                        <TableCell class="text-right font-mono">{{ Number(l.cost_price).toLocaleString('id-ID') }}</TableCell>
                        <TableCell class="font-mono text-xs">{{ l.ref_type }}#{{ l.ref_id }}</TableCell>
                    </TableRow>
                </TableBody>
            </Table>

            <div v-if="ledgers.data.length > 0" class="border-t border-border/70 px-4 py-2.5">
                <Pagination :meta="ledgers" />
            </div>
        </section>
    </AppLayout>
</template>
