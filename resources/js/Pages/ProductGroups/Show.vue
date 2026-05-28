<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Boxes,
    Check,
    Layers,
    Loader2,
    Save,
    Search,
    Users,
    X,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import PageHeader from '@/Components/Shared/PageHeader.vue';
import TabsPill from '@/Components/Shared/TabsPill.vue';
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
    group: { type: Object, required: true },
    availableSales: { type: Array, default: () => [] },
    availableProducts: { type: Array, default: () => [] },
});

const page = usePage();
const canUpdate = computed(
    () => page.props.auth?.user?.is_superadmin
        || page.props.permissions?.['master.product_group']?.update,
);

const tab = ref('products');
const tabs = computed(() => [
    {
        value: 'products',
        label: `Produk (${props.group.products?.length ?? 0})`,
        icon: Boxes,
    },
    {
        value: 'sales',
        label: `Sales (${props.group.sales_users?.length ?? 0})`,
        icon: Users,
    },
]);

/* ──────────────────────── Tab: Produk ──────────────────────── */

const productSearch = ref('');
const initialSelectedProductIds = new Set((props.group.products ?? []).map((p) => p.id));
const selectedProductIds = ref(new Set(initialSelectedProductIds));

const filteredProducts = computed(() => {
    const q = productSearch.value.trim().toLowerCase();
    if (!q) return props.availableProducts;
    return props.availableProducts.filter(
        (p) => p.name.toLowerCase().includes(q)
            || (p.sku ?? '').toLowerCase().includes(q)
            || (p.brand ?? '').toLowerCase().includes(q),
    );
});

function toggleProduct(p) {
    if (!canUpdate.value) return;
    const next = new Set(selectedProductIds.value);
    if (next.has(p.id)) next.delete(p.id);
    else next.add(p.id);
    selectedProductIds.value = next;
}

const productsForm = useForm({ product_ids: [] });
const productsDirty = computed(() => {
    if (selectedProductIds.value.size !== initialSelectedProductIds.size) return true;
    for (const id of selectedProductIds.value) {
        if (!initialSelectedProductIds.has(id)) return true;
    }
    return false;
});

function saveProducts() {
    productsForm.product_ids = Array.from(selectedProductIds.value);
    productsForm.put(route('product-groups.sync-products', props.group.id), {
        preserveScroll: true,
    });
}

/* ──────────────────────── Tab: Sales ──────────────────────── */

const salesSearch = ref('');

// {[user_id]: { selected: bool, monthly_limit: number|null }}
const assignments = ref(
    (() => {
        const map = {};
        for (const s of props.availableSales) {
            map[s.id] = { selected: false, monthly_limit: null };
        }
        for (const u of props.group.sales_users ?? []) {
            map[u.id] = {
                selected: true,
                monthly_limit: u.pivot?.monthly_limit !== null && u.pivot?.monthly_limit !== undefined
                    ? Number(u.pivot.monthly_limit)
                    : null,
            };
        }
        return map;
    })(),
);

const filteredSales = computed(() => {
    const q = salesSearch.value.trim().toLowerCase();
    if (!q) return props.availableSales;
    return props.availableSales.filter(
        (s) => s.name.toLowerCase().includes(q) || (s.username ?? '').toLowerCase().includes(q),
    );
});

function toggleSales(s) {
    if (!canUpdate.value) return;
    const a = assignments.value[s.id];
    a.selected = !a.selected;
}

const salesForm = useForm({ assignments: [] });

function saveSales() {
    salesForm.assignments = Object.entries(assignments.value)
        .filter(([, v]) => v.selected)
        .map(([uid, v]) => ({
            user_id: Number(uid),
            monthly_limit: v.monthly_limit === '' || v.monthly_limit === null ? null : Number(v.monthly_limit),
        }));
    salesForm.put(route('product-groups.sync-sales', props.group.id), {
        preserveScroll: true,
    });
}

function fmtRp(v) {
    if (v === null || v === undefined || v === '') return '—';
    return 'Rp ' + new Intl.NumberFormat('id-ID').format(Number(v) || 0);
}
</script>

<template>
    <Head :title="`${group.name} — Product Group`" />

    <AppLayout>
        <PageHeader
            :title="group.name"
            :description="group.description || 'Atur produk yang masuk ke group ini dan sales mana yang boleh menjualnya.'"
            :icon="Layers"
        >
            <template #actions>
                <Button as-child variant="ghost" size="default">
                    <Link :href="route('product-groups.index')">
                        <ArrowLeft class="size-4" />
                        Kembali
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <section class="mb-4 rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm px-5 py-3 flex flex-wrap items-center gap-x-6 gap-y-2 text-sm">
            <div>
                <span class="text-[11px] uppercase tracking-wider text-muted-foreground font-semibold">Kode</span>
                <p class="font-mono mt-0.5">{{ group.code }}</p>
            </div>
            <div>
                <span class="text-[11px] uppercase tracking-wider text-muted-foreground font-semibold">Status</span>
                <p class="mt-0.5">
                    <span
                        v-if="group.is_active"
                        class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-medium bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200"
                    >
                        Aktif
                    </span>
                    <span
                        v-else
                        class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-medium bg-muted text-muted-foreground"
                    >
                        Nonaktif
                    </span>
                </p>
            </div>
            <div>
                <span class="text-[11px] uppercase tracking-wider text-muted-foreground font-semibold">Produk</span>
                <p class="mt-0.5 font-medium">{{ group.products?.length ?? 0 }}</p>
            </div>
            <div>
                <span class="text-[11px] uppercase tracking-wider text-muted-foreground font-semibold">Sales</span>
                <p class="mt-0.5 font-medium">{{ group.sales_users?.length ?? 0 }}</p>
            </div>
        </section>

        <div class="mb-4">
            <TabsPill v-model="tab" :tabs="tabs" />
        </div>

        <!-- ─────────────── Tab: Produk ─────────────── -->
        <section v-show="tab === 'products'" class="rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm overflow-hidden">
            <div class="border-b border-border/70 px-3 py-2.5 flex flex-col sm:flex-row sm:items-center gap-2">
                <div class="relative flex-1">
                    <Search class="absolute left-2.5 top-1/2 -translate-y-1/2 size-3.5 text-muted-foreground" />
                    <Input
                        v-model="productSearch"
                        placeholder="Cari SKU / nama / brand…"
                        class="pl-8 h-9 rounded-md"
                    />
                </div>
                <div class="text-xs text-muted-foreground">
                    Terpilih: <span class="font-semibold text-foreground">{{ selectedProductIds.size }}</span>
                </div>
                <Button
                    v-if="canUpdate"
                    type="button"
                    variant="secondary"
                    size="default"
                    :disabled="!productsDirty || productsForm.processing"
                    @click="saveProducts"
                >
                    <Loader2 v-if="productsForm.processing" class="size-4 animate-spin" />
                    <Save v-else class="size-4" />
                    Simpan Produk
                </Button>
            </div>

            <Table>
                <TableHeader>
                    <TableRow class="[&>th]:text-[10px] [&>th]:font-semibold [&>th]:uppercase [&>th]:tracking-wider [&>th]:text-muted-foreground [&>th]:py-2.5">
                        <TableHead class="pl-4 w-10"></TableHead>
                        <TableHead>SKU</TableHead>
                        <TableHead>Nama Produk</TableHead>
                        <TableHead>Brand</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody class="text-sm">
                    <TableRow v-if="filteredProducts.length === 0">
                        <TableCell colspan="4" class="text-center py-10 text-muted-foreground">
                            Tidak ada produk yang cocok.
                        </TableCell>
                    </TableRow>
                    <TableRow
                        v-for="p in filteredProducts"
                        :key="p.id"
                        :class="[
                            'hover:bg-muted/30 transition-colors cursor-pointer',
                            selectedProductIds.has(p.id) ? 'bg-emerald-50/40' : '',
                        ]"
                        @click="toggleProduct(p)"
                    >
                        <TableCell class="pl-4 py-2.5">
                            <div
                                :class="[
                                    'size-5 rounded border flex items-center justify-center transition-colors',
                                    selectedProductIds.has(p.id)
                                        ? 'bg-emerald-600 border-emerald-600 text-white'
                                        : 'border-muted-foreground/30',
                                ]"
                            >
                                <Check v-if="selectedProductIds.has(p.id)" class="size-3.5" />
                            </div>
                        </TableCell>
                        <TableCell class="font-mono text-xs">{{ p.sku }}</TableCell>
                        <TableCell class="font-medium">{{ p.name }}</TableCell>
                        <TableCell class="text-muted-foreground">{{ p.brand || '—' }}</TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </section>

        <!-- ─────────────── Tab: Sales ─────────────── -->
        <section v-show="tab === 'sales'" class="rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm overflow-hidden">
            <div class="border-b border-border/70 px-3 py-2.5 flex flex-col sm:flex-row sm:items-center gap-2">
                <div class="relative flex-1">
                    <Search class="absolute left-2.5 top-1/2 -translate-y-1/2 size-3.5 text-muted-foreground" />
                    <Input
                        v-model="salesSearch"
                        placeholder="Cari nama / username sales…"
                        class="pl-8 h-9 rounded-md"
                    />
                </div>
                <Button
                    v-if="canUpdate"
                    type="button"
                    variant="secondary"
                    size="default"
                    :disabled="salesForm.processing"
                    @click="saveSales"
                >
                    <Loader2 v-if="salesForm.processing" class="size-4 animate-spin" />
                    <Save v-else class="size-4" />
                    Simpan Sales
                </Button>
            </div>

            <div class="px-4 py-2 text-[11px] text-muted-foreground bg-muted/30 border-b border-border/70">
                Ceklis sales yang boleh menjual produk dari group ini. Isi <strong>Limit bulanan</strong>
                kalau ingin membatasi total penjualan sales tsb dari group ini per bulan
                (kosong = tanpa limit).
            </div>

            <Table>
                <TableHeader>
                    <TableRow class="[&>th]:text-[10px] [&>th]:font-semibold [&>th]:uppercase [&>th]:tracking-wider [&>th]:text-muted-foreground [&>th]:py-2.5">
                        <TableHead class="pl-4 w-10"></TableHead>
                        <TableHead>Sales</TableHead>
                        <TableHead class="text-right">Limit bulanan (Rp)</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody class="text-sm">
                    <TableRow v-if="filteredSales.length === 0">
                        <TableCell colspan="3" class="text-center py-10 text-muted-foreground">
                            Tidak ada sales aktif.
                        </TableCell>
                    </TableRow>
                    <TableRow
                        v-for="s in filteredSales"
                        :key="s.id"
                        :class="[
                            'hover:bg-muted/30 transition-colors',
                            assignments[s.id]?.selected ? 'bg-emerald-50/40' : '',
                        ]"
                    >
                        <TableCell class="pl-4 py-2.5">
                            <button
                                type="button"
                                :disabled="!canUpdate"
                                :class="[
                                    'size-5 rounded border flex items-center justify-center transition-colors',
                                    assignments[s.id]?.selected
                                        ? 'bg-emerald-600 border-emerald-600 text-white'
                                        : 'border-muted-foreground/30',
                                ]"
                                @click="toggleSales(s)"
                            >
                                <Check v-if="assignments[s.id]?.selected" class="size-3.5" />
                            </button>
                        </TableCell>
                        <TableCell>
                            <p class="font-medium">{{ s.name }}</p>
                            <p class="text-xs text-muted-foreground font-mono">@{{ s.username }}</p>
                        </TableCell>
                        <TableCell class="text-right">
                            <div v-if="assignments[s.id]?.selected" class="flex justify-end">
                                <Input
                                    v-model="assignments[s.id].monthly_limit"
                                    type="number"
                                    min="0"
                                    step="1000"
                                    placeholder="tanpa limit"
                                    class="h-8 w-40 text-right font-mono text-xs"
                                />
                            </div>
                            <span v-else class="text-muted-foreground text-xs">—</span>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </section>
    </AppLayout>
</template>
