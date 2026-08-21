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
    group: { type: Object, required: true },
    availableSales: { type: Array, default: () => [] },
    availableProducts: { type: Array, default: () => [] },
    availableSuppliers: { type: Array, default: () => [] },
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
const supplierFilter = ref(''); // '' = semua, atau supplier_id string
const initialSelectedProductIds = new Set((props.group.products ?? []).map((p) => p.id));
const selectedProductIds = ref(new Set(initialSelectedProductIds));

// Paket harga terpilih per produk: {[product_id]: price_package_id|null}.
// Diisi dari pivot untuk produk yang sudah ada di group.
const initialPackageByProduct = (() => {
    const map = {};
    for (const p of props.group.products ?? []) {
        map[p.id] = p.pivot?.price_package_id ?? null;
    }
    return map;
})();
const packageByProduct = ref({ ...initialPackageByProduct });

function packagesFor(p) {
    return props.availableProducts.find((ap) => ap.id === p.id)?.packages ?? [];
}

function setPackage(productId, value) {
    packageByProduct.value = {
        ...packageByProduct.value,
        [productId]: value ? Number(value) : null,
    };
}

const filteredProducts = computed(() => {
    const q = productSearch.value.trim().toLowerCase();
    const supId = supplierFilter.value ? Number(supplierFilter.value) : null;
    return props.availableProducts.filter((p) => {
        if (supId && p.supplier_id !== supId) return false;
        if (q) {
            return (
                p.name.toLowerCase().includes(q)
                || (p.sku ?? '').toLowerCase().includes(q)
                || (p.supplier_name ?? '').toLowerCase().includes(q)
            );
        }
        return true;
    });
});

function toggleAllFiltered() {
    if (!canUpdate.value) return;
    const next = new Set(selectedProductIds.value);
    const allSelected = filteredProducts.value.every((p) => next.has(p.id));
    for (const p of filteredProducts.value) {
        if (allSelected) next.delete(p.id);
        else next.add(p.id);
    }
    selectedProductIds.value = next;
}

function toggleProduct(p) {
    if (!canUpdate.value) return;
    const next = new Set(selectedProductIds.value);
    if (next.has(p.id)) {
        next.delete(p.id);
    } else {
        next.add(p.id);
        // Default ke paket pertama kalau belum ada pilihan.
        if (packageByProduct.value[p.id] == null) {
            const pkgs = packagesFor(p);
            setPackage(p.id, pkgs.length > 0 ? pkgs[0].id : null);
        }
    }
    selectedProductIds.value = next;
}

const productsForm = useForm({ products: [] });
const productsDirty = computed(() => {
    if (selectedProductIds.value.size !== initialSelectedProductIds.size) return true;
    for (const id of selectedProductIds.value) {
        if (!initialSelectedProductIds.has(id)) return true;
        if ((packageByProduct.value[id] ?? null) !== (initialPackageByProduct[id] ?? null)) return true;
    }
    return false;
});

function saveProducts() {
    productsForm.products = Array.from(selectedProductIds.value).map((id) => ({
        product_id: id,
        price_package_id: packageByProduct.value[id] ?? null,
    }));
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
                <Button as-child variant="ghost" size="default" class="rounded-full">
                    <Link :href="route('product-groups.index')">
                        <ArrowLeft class="size-4" />
                        Kembali
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <section class="mb-4 rounded-2xl bg-card/70 backdrop-blur-xl ring-1 ring-foreground/6 shadow-sm px-5 py-4 flex flex-wrap items-center gap-x-6 gap-y-2 text-sm">
            <div>
                <span class="text-[11px] uppercase tracking-wider text-muted-foreground font-semibold">Kode</span>
                <p class="font-mono mt-0.5">{{ group.code }}</p>
            </div>
            <div>
                <span class="text-[11px] uppercase tracking-wider text-muted-foreground font-semibold">Status</span>
                <p class="mt-0.5">
                    <span
                        :class="[
                            'inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[12px] font-medium',
                            group.is_active ? 'text-emerald-700' : 'text-muted-foreground',
                        ]"
                    >
                        <span
                            :class="[
                                'size-1.5 rounded-full',
                                group.is_active ? 'bg-emerald-500' : 'bg-muted-foreground/50',
                            ]"
                        />
                        {{ group.is_active ? 'Aktif' : 'Nonaktif' }}
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
            <TabsPill v-model="tab" :tabs="tabs" tone="brand" />
        </div>

        <!-- ─────────────── Tab: Produk ─────────────── -->
        <section v-show="tab === 'products'" class="rounded-2xl bg-card/70 backdrop-blur-xl ring-1 ring-foreground/6 shadow-sm overflow-hidden">
            <div class="px-4 py-3 flex flex-col sm:flex-row sm:items-center gap-2">
                <Select v-model="supplierFilter">
                    <SelectTrigger class="w-[200px] h-9 rounded-full bg-muted/50 border-transparent">
                        <SelectValue placeholder="Filter Supplier" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="">Semua Supplier</SelectItem>
                        <SelectItem v-for="s in availableSuppliers" :key="s.id" :value="String(s.id)">
                            {{ s.name }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <div class="relative flex-1">
                    <Search class="absolute left-3 top-1/2 -translate-y-1/2 size-3.5 text-muted-foreground" />
                    <Input
                        v-model="productSearch"
                        placeholder="Cari SKU / nama / supplier…"
                        class="pl-8 h-9 rounded-full bg-muted/50 border-transparent focus-visible:bg-card"
                    />
                </div>
                <div class="text-xs text-muted-foreground whitespace-nowrap">
                    Terpilih: <span class="font-semibold text-foreground">{{ selectedProductIds.size }}</span>
                </div>
                <Button
                    v-if="canUpdate"
                    type="button"
                    variant="outline"
                    size="default"
                    class="rounded-full"
                    :disabled="filteredProducts.length === 0"
                    @click="toggleAllFiltered"
                >
                    Pilih Semua
                </Button>
                <Button
                    v-if="canUpdate"
                    type="button"
                    size="default"
                    class="rounded-full bg-brand text-white hover:bg-brand-dark"
                    :disabled="!productsDirty || productsForm.processing"
                    @click="saveProducts"
                >
                    <Loader2 v-if="productsForm.processing" class="size-4 animate-spin" />
                    <Save v-else class="size-4" />
                    Simpan Produk
                </Button>
            </div>

            <Table class="border-t border-foreground/5">
                <TableHeader>
                    <TableRow class="[&>th]:text-[11px] [&>th]:font-semibold [&>th]:uppercase [&>th]:tracking-wider [&>th]:text-muted-foreground [&>th]:py-2.5 hover:bg-transparent">
                        <TableHead class="pl-4 w-10"></TableHead>
                        <TableHead>SKU</TableHead>
                        <TableHead>Nama Produk</TableHead>
                        <TableHead>Supplier</TableHead>
                        <TableHead class="w-56">Paket Harga (untuk sales)</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody class="text-sm">
                    <TableRow v-if="filteredProducts.length === 0">
                        <TableCell colspan="5" class="text-center py-10 text-muted-foreground">
                            Tidak ada produk yang cocok.
                        </TableCell>
                    </TableRow>
                    <TableRow
                        v-for="p in filteredProducts"
                        :key="p.id"
                        :class="[
                            'transition-colors cursor-pointer border-foreground/5',
                            selectedProductIds.has(p.id) ? 'bg-brand-light/40 hover:bg-brand-light/50' : 'hover:bg-foreground/2.5',
                        ]"
                        @click="toggleProduct(p)"
                    >
                        <TableCell class="pl-4 py-2.5">
                            <div
                                :class="[
                                    'size-5 rounded-full border flex items-center justify-center transition-colors',
                                    selectedProductIds.has(p.id)
                                        ? 'bg-brand border-brand text-white'
                                        : 'border-muted-foreground/30',
                                ]"
                            >
                                <Check v-if="selectedProductIds.has(p.id)" class="size-3.5" />
                            </div>
                        </TableCell>
                        <TableCell class="font-mono text-xs">{{ p.sku }}</TableCell>
                        <TableCell class="font-medium">{{ p.name }}</TableCell>
                        <TableCell class="text-muted-foreground">{{ p.supplier_name || '—' }}</TableCell>
                        <TableCell class="py-1.5" @click.stop>
                            <template v-if="selectedProductIds.has(p.id)">
                                <Select
                                    v-if="(p.packages ?? []).length > 0"
                                    :model-value="packageByProduct[p.id] ? String(packageByProduct[p.id]) : ''"
                                    :disabled="!canUpdate"
                                    @update:model-value="(v) => setPackage(p.id, v)"
                                >
                                    <SelectTrigger class="h-8 rounded-lg">
                                        <SelectValue placeholder="Paket default" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="pkg in p.packages"
                                            :key="pkg.id"
                                            :value="String(pkg.id)"
                                        >
                                            {{ pkg.name }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <span v-else class="text-xs text-muted-foreground italic">
                                    Belum ada paket harga
                                </span>
                            </template>
                            <span v-else class="text-xs text-muted-foreground/60">—</span>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </section>

        <!-- ─────────────── Tab: Sales ─────────────── -->
        <section v-show="tab === 'sales'" class="rounded-2xl bg-card/70 backdrop-blur-xl ring-1 ring-foreground/6 shadow-sm overflow-hidden">
            <div class="px-4 py-3 flex flex-col sm:flex-row sm:items-center gap-2">
                <div class="relative flex-1">
                    <Search class="absolute left-3 top-1/2 -translate-y-1/2 size-3.5 text-muted-foreground" />
                    <Input
                        v-model="salesSearch"
                        placeholder="Cari nama / username sales…"
                        class="pl-8 h-9 rounded-full bg-muted/50 border-transparent focus-visible:bg-card"
                    />
                </div>
                <Button
                    v-if="canUpdate"
                    type="button"
                    size="default"
                    class="rounded-full bg-brand text-white hover:bg-brand-dark"
                    :disabled="salesForm.processing"
                    @click="saveSales"
                >
                    <Loader2 v-if="salesForm.processing" class="size-4 animate-spin" />
                    <Save v-else class="size-4" />
                    Simpan Sales
                </Button>
            </div>

            <div class="px-4 py-3 text-[12px] text-muted-foreground bg-muted/40">
                Ceklis sales yang boleh menjual produk dari group ini. Isi <strong class="text-foreground">Limit bulanan</strong>
                kalau ingin membatasi total penjualan sales tsb dari group ini per bulan
                (kosong = tanpa limit).
            </div>

            <Table class="border-t border-foreground/5">
                <TableHeader>
                    <TableRow class="[&>th]:text-[11px] [&>th]:font-semibold [&>th]:uppercase [&>th]:tracking-wider [&>th]:text-muted-foreground [&>th]:py-2.5 hover:bg-transparent">
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
                            'transition-colors border-foreground/5',
                            assignments[s.id]?.selected ? 'bg-brand-light/40 hover:bg-brand-light/50' : 'hover:bg-foreground/2.5',
                        ]"
                    >
                        <TableCell class="pl-4 py-2.5">
                            <button
                                type="button"
                                :disabled="!canUpdate"
                                :class="[
                                    'size-5 rounded-full border flex items-center justify-center transition-colors',
                                    assignments[s.id]?.selected
                                        ? 'bg-brand border-brand text-white'
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
                                    class="h-8 w-40 rounded-lg text-right font-mono text-xs"
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
