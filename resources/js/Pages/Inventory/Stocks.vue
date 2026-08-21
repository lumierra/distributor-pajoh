<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import {
    AlertTriangle,
    BookOpen,
    Clock,
    Eye,
    Package,
    PackageMinus,
    PackageX,
    RotateCcw,
    Search,
} from '@lucide/vue';
import { computed, reactive, watch } from 'vue';
import { formatBreakdown } from '@/lib/uom';
import ActionButton from '@/Components/Shared/ActionButton.vue';
import ActionGroup from '@/Components/Shared/ActionGroup.vue';
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
    products: { type: Object, required: true },
    categories: { type: Array, required: true },
    filters: { type: Object, default: () => ({}) },
});

const ALL = 'all';

const filters = reactive({
    q: props.filters.q ?? '',
    category_id: props.filters.category_id ? String(props.filters.category_id) : ALL,
    stock_status: props.filters.stock_status || ALL,
});

let timer = null;
watch(filters, () => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get(
            route('stocks.index'),
            {
                q: filters.q,
                category_id: filters.category_id === ALL ? '' : filters.category_id,
                stock_status: filters.stock_status === ALL ? '' : filters.stock_status,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }, 300);
});

function reset() {
    filters.q = '';
    filters.category_id = ALL;
    filters.stock_status = ALL;
}

function stockClass(qty) {
    if (qty <= 0) return 'text-red-700';
    if (qty <= 10) return 'text-amber-700';
    return 'text-emerald-700';
}

// Stok real (base unit) = on_hand − bonus.
function stockReal(p) {
    return Number(p.total_on_hand) - Number(p.total_bonus_pool);
}
</script>

<template>
    <Head title="Stok per Produk" />

    <AppLayout>
        <PageHeader title="Stok per Produk" description="Ringkasan stok semua produk — jumlah on-hand, reserved, dan jumlah batch." :icon="Package">
            <template #actions>
                <Button as-child variant="outline" size="default">
                    <Link :href="route('stock-ledger.index')">
                        <BookOpen class="size-4" />
                        Stock Ledger
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <section class="rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm overflow-hidden">
            <div class="border-b border-border/70 px-3 py-2.5 flex flex-col sm:flex-row sm:items-center gap-2">
                <div class="relative flex-1">
                    <Search class="absolute left-2.5 top-1/2 -translate-y-1/2 size-3.5 text-muted-foreground" />
                    <Input v-model="filters.q" placeholder="Cari produk, SKU…" class="pl-8 h-9 rounded-md" />
                </div>
                <div class="flex items-center gap-2 flex-wrap">
                    <Select v-model="filters.category_id">
                        <SelectTrigger class="w-[160px] h-9 rounded-md">
                            <SelectValue placeholder="Kategori" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="ALL">Semua Kategori</SelectItem>
                            <SelectItem v-for="c in categories" :key="c.id" :value="String(c.id)">{{ c.name }}</SelectItem>
                        </SelectContent>
                    </Select>
                    <Select v-model="filters.stock_status">
                        <SelectTrigger class="w-[160px] h-9 rounded-md">
                            <SelectValue placeholder="Status Stok" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="ALL">Semua Stok</SelectItem>
                            <SelectItem value="in">Ada stok</SelectItem>
                            <SelectItem value="low">Stok rendah (≤10)</SelectItem>
                            <SelectItem value="out">Habis (0/minus)</SelectItem>
                        </SelectContent>
                    </Select>
                    <Button type="button" variant="outline" size="default" @click="reset">
                        <RotateCcw class="size-3.5" /> Reset
                    </Button>
                </div>
            </div>

            <Table>
                <TableHeader>
                    <TableRow class="[&>th]:text-[11px] [&>th]:font-semibold [&>th]:uppercase [&>th]:tracking-wider [&>th]:text-muted-foreground [&>th]:py-2.5">
                        <TableHead class="pl-4">Produk</TableHead>
                        <TableHead>Kategori</TableHead>
                        <TableHead class="text-right">Stok Real</TableHead>
                        <TableHead class="text-right">Bonus</TableHead>
                        <TableHead class="text-right">Pending</TableHead>
                        <TableHead class="text-right">Batch</TableHead>
                        <TableHead class="text-right pr-4">Aksi</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody class="text-sm">
                    <TableRow v-if="products.data.length === 0">
                        <TableCell colspan="7" class="text-center py-16">
                            <div class="flex flex-col items-center gap-2 text-muted-foreground">
                                <Package class="size-7 opacity-40" />
                                <p class="text-sm">Tidak ada produk.</p>
                            </div>
                        </TableCell>
                    </TableRow>
                    <TableRow v-for="p in products.data" :key="p.id" class="hover:bg-muted/30 transition-colors">
                        <TableCell class="pl-4 py-2.5">
                            <div class="flex items-center gap-2.5">
                                <div class="size-8 rounded-md bg-primary/10 text-primary flex items-center justify-center shrink-0">
                                    <Package class="size-4" />
                                </div>
                                <div class="min-w-0">
                                    <Link
                                        :href="route('stocks.show', p.id)"
                                        class="font-medium text-foreground hover:text-primary transition-colors block leading-tight"
                                    >
                                        {{ p.name }}
                                    </Link>
                                    <p class="text-[12px] text-muted-foreground font-mono leading-tight">
                                        {{ p.sku }}
                                    </p>
                                </div>
                            </div>
                        </TableCell>
                        <TableCell class="text-xs">
                            <span v-if="p.category" class="inline-flex items-center px-2 py-0.5 rounded-md text-[12px] font-medium bg-muted text-muted-foreground">
                                {{ p.category.name }}
                            </span>
                            <span v-else class="text-muted-foreground">—</span>
                        </TableCell>
                        <TableCell :class="['text-right font-mono', stockClass(Number(p.total_on_hand) - Number(p.total_bonus_pool))]">
                            {{ stockReal(p) > 0 ? formatBreakdown(stockReal(p), p.units) : '0' }}
                            <span v-if="stockReal(p) <= 0" class="text-[11px] text-muted-foreground font-sans ml-0.5">{{ p.base_unit?.name }}</span>
                        </TableCell>
                        <TableCell class="text-right text-xs font-mono text-muted-foreground">
                            <span v-if="Number(p.total_bonus_pool) > 0" class="text-foreground">{{ formatBreakdown(Number(p.total_bonus_pool), p.units) }}</span>
                            <span v-else>0</span>
                        </TableCell>
                        <TableCell class="text-right text-xs">
                            <span v-if="Number(p.pending_qty) > 0" class="font-mono text-amber-700 inline-flex items-center gap-1">
                                <Clock class="size-3" />
                                {{ formatBreakdown(Number(p.pending_qty), p.units) }}
                            </span>
                            <span v-else class="text-muted-foreground font-mono">—</span>
                        </TableCell>
                        <TableCell class="text-right text-xs font-mono">{{ p.batch_count }}</TableCell>
                        <TableCell class="py-3 px-4 text-right whitespace-nowrap">
                            <ActionGroup>
                                <ActionButton :icon="Eye" label="Detail batch" as-child tone="brand">
                                    <Link :href="route('stocks.show', p.id)">
                                        <Eye class="w-4 h-4" />
                                    </Link>
                                </ActionButton>
                            </ActionGroup>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>

            <!-- Keterangan kolom -->
            <div class="border-t border-foreground/5 px-5 py-3 text-[11px] text-muted-foreground leading-relaxed">
                <p><strong class="text-foreground">Stok Real</strong> = barang fisik siap jual (on-hand − bonus). Berkurang <strong class="text-foreground">saat SO di-approve</strong> (barang langsung keluar), bukan menunggu DO dikirim.</p>
                <p class="mt-0.5"><strong class="text-foreground">Bonus</strong> = stok gratis (pool terpisah, tidak ikut dijual). <strong class="text-foreground">Pending</strong> = barang dipesan/menyusul yang belum datang (belum jadi stok). <strong class="text-foreground">Batch</strong> = jumlah batch aktif produk ini.</p>
                <p class="mt-0.5">Jumlah ditampilkan dalam satuan terbesar (mis. 85 KRT, atau 85 KRT + 3 PACK bila ada eceran).</p>
            </div>

            <div v-if="products.data.length > 0" class="border-t border-foreground/5 px-4 py-3">
                <Pagination :meta="products" />
            </div>
        </section>
    </AppLayout>
</template>
