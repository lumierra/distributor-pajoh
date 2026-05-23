<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { AlertTriangle, ArrowLeft, BookOpen, Package } from '@lucide/vue';
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
    product: { type: Object, required: true },
    balances: { type: Array, required: true },
});

function formatDate(v) {
    if (!v) return '—';
    return new Date(v).toLocaleString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
}

function expiredBadge(b) {
    if (!b.batch?.expired_date) return null;
    const days = Math.ceil((new Date(b.batch.expired_date) - new Date()) / (1000 * 60 * 60 * 24));
    if (days < 0) return { label: `Expired ${Math.abs(days)}h lalu`, class: 'bg-red-50 text-red-700 ring-red-200' };
    if (days <= 30) return { label: `Expire ${days}h lagi`, class: 'bg-warning-soft text-amber-700 ring-warning/30' };
    return null;
}
</script>

<template>
    <Head :title="`Stok: ${product.name}`" />

    <AppLayout>
        <PageHeader
            :title="`Stok — ${product.name}`"
            :description="`${product.sku} · ${product.category?.name ?? 'Tanpa kategori'}`"
            :icon="Package"
        >
            <template #actions>
                <Button as-child variant="ghost" size="default">
                    <Link :href="route('stocks.index')">
                        <ArrowLeft class="size-4" />
                        Daftar Stok
                    </Link>
                </Button>
                <Button as-child variant="outline" size="default">
                    <Link :href="route('stock-ledger.index', { product_id: product.id })">
                        <BookOpen class="size-4" />
                        Ledger Produk
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <section class="rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm overflow-hidden">
            <header class="border-b border-border/70 px-5 py-3">
                <h3 class="text-sm font-semibold">Stok per Batch (Base Unit: {{ product.base_unit?.name }})</h3>
            </header>
            <Table>
                <TableHeader>
                    <TableRow class="[&>th]:text-[10px] [&>th]:font-semibold [&>th]:uppercase [&>th]:tracking-wider [&>th]:text-muted-foreground [&>th]:py-2.5">
                        <TableHead class="pl-5">Batch</TableHead>
                        <TableHead>Supplier</TableHead>
                        <TableHead>Produksi</TableHead>
                        <TableHead>Expired</TableHead>
                        <TableHead class="text-right">On Hand</TableHead>
                        <TableHead class="text-right">Bonus</TableHead>
                        <TableHead class="text-right">Reserved</TableHead>
                        <TableHead class="text-right pr-5">Available</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody class="text-sm">
                    <TableRow v-if="balances.length === 0">
                        <TableCell colspan="8" class="text-center py-16 text-muted-foreground">
                            Belum ada batch — tunggu GRN posted.
                        </TableCell>
                    </TableRow>
                    <TableRow v-for="b in balances" :key="b.id" class="hover:bg-muted/20 transition-colors">
                        <TableCell class="pl-5 py-2.5">
                            <p class="font-mono text-xs font-medium">{{ b.batch?.batch_code }}</p>
                            <span
                                v-if="expiredBadge(b)"
                                :class="['mt-1 inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-semibold ring-1', expiredBadge(b).class]"
                            >
                                <AlertTriangle class="size-3" />
                                {{ expiredBadge(b).label }}
                            </span>
                        </TableCell>
                        <TableCell class="text-xs">
                            <span v-if="b.batch?.supplier">
                                {{ b.batch.supplier.name }}
                                <span class="text-muted-foreground font-mono">({{ b.batch.supplier.code }})</span>
                            </span>
                            <span v-else class="text-muted-foreground">—</span>
                        </TableCell>
                        <TableCell class="text-xs">{{ formatDate(b.batch?.production_date) }}</TableCell>
                        <TableCell class="text-xs">{{ formatDate(b.batch?.expired_date) }}</TableCell>
                        <TableCell class="text-right font-mono">{{ Number(b.qty_on_hand).toLocaleString('id-ID') }}</TableCell>
                        <TableCell class="text-right font-mono text-muted-foreground">{{ Number(b.qty_bonus_pool).toLocaleString('id-ID') }}</TableCell>
                        <TableCell class="text-right font-mono text-muted-foreground">{{ Number(b.qty_reserved).toLocaleString('id-ID') }}</TableCell>
                        <TableCell class="pr-5 text-right font-mono font-bold">{{ (Number(b.qty_on_hand) - Number(b.qty_reserved)).toLocaleString('id-ID') }}</TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </section>
    </AppLayout>
</template>
