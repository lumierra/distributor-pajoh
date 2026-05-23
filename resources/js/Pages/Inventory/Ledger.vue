<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowDown, ArrowLeft, ArrowUp, BookOpen, Package, RotateCcw } from '@lucide/vue';
import { reactive, watch } from 'vue';
import PageHeader from '@/Components/Shared/PageHeader.vue';
import Pagination from '@/Components/Shared/Pagination.vue';
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
    ledger: { type: Object, required: true },
    products: { type: Array, required: true },
    types: { type: Array, required: true },
    filters: { type: Object, default: () => ({}) },
});

const ALL = 'all';

const filters = reactive({
    product_id: props.filters.product_id ? String(props.filters.product_id) : ALL,
    type: props.filters.type || ALL,
    from: props.filters.from ?? '',
    to: props.filters.to ?? '',
});

let timer = null;
watch(filters, () => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get(
            route('stock-ledger.index'),
            {
                product_id: filters.product_id === ALL ? '' : filters.product_id,
                type: filters.type === ALL ? '' : filters.type,
                from: filters.from || '',
                to: filters.to || '',
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }, 300);
});

function reset() {
    filters.product_id = ALL;
    filters.type = ALL;
    filters.from = '';
    filters.to = '';
}

function formatDateTime(v) {
    if (!v) return '—';
    return new Date(v).toLocaleString('id-ID', {
        day: '2-digit', month: 'short', year: 'numeric',
        hour: '2-digit', minute: '2-digit',
    });
}

function typeClass(t) {
    if (t.endsWith('_in') || t === 'return_in') return 'bg-emerald-50 text-emerald-700 ring-emerald-200';
    if (t.endsWith('_out') || t === 'write_off') return 'bg-red-50 text-red-700 ring-red-200';
    return 'bg-muted text-muted-foreground';
}
</script>

<template>
    <Head title="Stock Ledger" />

    <AppLayout>
        <PageHeader title="Stock Ledger" description="Journal mutasi stok (immutable). Source of truth." :icon="BookOpen">
            <template #actions>
                <Button as-child variant="ghost" size="default">
                    <Link :href="route('stocks.index')">
                        <ArrowLeft class="size-4" />
                        Stok per Produk
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <section class="rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm overflow-hidden">
            <div class="border-b border-border/70 px-3 py-2.5 flex flex-col sm:flex-row sm:items-center gap-2">
                <Select v-model="filters.product_id">
                    <SelectTrigger class="h-9 rounded-md w-full sm:w-[260px]">
                        <SelectValue placeholder="Pilih produk" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem :value="ALL">Semua Produk</SelectItem>
                        <SelectItem v-for="p in products" :key="p.id" :value="String(p.id)">
                            {{ p.name }} <span class="text-xs text-muted-foreground font-mono ml-1">{{ p.sku }}</span>
                        </SelectItem>
                    </SelectContent>
                </Select>
                <Select v-model="filters.type">
                    <SelectTrigger class="w-[180px] h-9 rounded-md">
                        <SelectValue placeholder="Tipe" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem :value="ALL">Semua Tipe</SelectItem>
                        <SelectItem v-for="t in types" :key="t" :value="t">{{ t }}</SelectItem>
                    </SelectContent>
                </Select>
                <Input v-model="filters.from" type="date" class="h-9 w-[150px]" placeholder="Dari" />
                <Input v-model="filters.to" type="date" class="h-9 w-[150px]" placeholder="Sampai" />
                <Button type="button" variant="outline" size="default" @click="reset">
                    <RotateCcw class="size-3.5" /> Reset
                </Button>
            </div>

            <Table>
                <TableHeader>
                    <TableRow class="[&>th]:text-[10px] [&>th]:font-semibold [&>th]:uppercase [&>th]:tracking-wider [&>th]:text-muted-foreground [&>th]:py-2.5">
                        <TableHead class="pl-4">Waktu</TableHead>
                        <TableHead>Produk</TableHead>
                        <TableHead>Batch</TableHead>
                        <TableHead>Tipe</TableHead>
                        <TableHead class="text-right">In</TableHead>
                        <TableHead class="text-right">Out</TableHead>
                        <TableHead class="text-right">Cost/Unit</TableHead>
                        <TableHead>Ref</TableHead>
                        <TableHead class="pr-4">By</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody class="text-sm">
                    <TableRow v-if="ledger.data.length === 0">
                        <TableCell colspan="9" class="text-center py-16 text-muted-foreground">
                            Tidak ada entri ledger.
                        </TableCell>
                    </TableRow>
                    <TableRow v-for="l in ledger.data" :key="l.id" class="hover:bg-muted/20 transition-colors">
                        <TableCell class="pl-4 py-2 text-xs whitespace-nowrap">{{ formatDateTime(l.created_at) }}</TableCell>
                        <TableCell class="text-xs">
                            <p class="font-medium">{{ l.product?.name }}</p>
                            <p class="text-[10px] text-muted-foreground font-mono">{{ l.product?.sku }}</p>
                        </TableCell>
                        <TableCell class="text-xs font-mono">{{ l.batch?.batch_code ?? '—' }}</TableCell>
                        <TableCell>
                            <span :class="['inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-semibold ring-1', typeClass(l.type)]">
                                <ArrowUp v-if="l.qty_in > 0" class="size-3" />
                                <ArrowDown v-else class="size-3" />
                                {{ l.type }}
                            </span>
                            <span v-if="l.is_bonus_pool" class="ml-1 inline-flex items-center px-1 py-0.5 rounded text-[9px] font-bold bg-amber-100 text-amber-800">
                                BONUS
                            </span>
                        </TableCell>
                        <TableCell class="text-right font-mono text-emerald-700">
                            {{ l.qty_in > 0 ? Number(l.qty_in).toLocaleString('id-ID') : '—' }}
                        </TableCell>
                        <TableCell class="text-right font-mono text-red-700">
                            {{ l.qty_out > 0 ? Number(l.qty_out).toLocaleString('id-ID') : '—' }}
                        </TableCell>
                        <TableCell class="text-right font-mono text-xs text-muted-foreground">
                            {{ Number(l.cost_price) > 0 ? 'Rp ' + Number(l.cost_price).toLocaleString('id-ID') : '—' }}
                        </TableCell>
                        <TableCell class="text-xs font-mono">{{ l.ref_type }}#{{ l.ref_id }}</TableCell>
                        <TableCell class="pr-4 text-xs">{{ l.creator?.name ?? '—' }}</TableCell>
                    </TableRow>
                </TableBody>
            </Table>

            <div v-if="ledger.data.length > 0" class="border-t border-border/70 px-4 py-2.5">
                <Pagination :meta="ledger" />
            </div>
        </section>
    </AppLayout>
</template>
