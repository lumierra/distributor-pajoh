<script setup>
import { useForm } from '@inertiajs/vue3';
import { Check, ChevronsUpDown, Loader2, Package, PackagePlus, Plus, Search, Trash2, X } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import CurrencyInput from '@/Components/Shared/CurrencyInput.vue';
import { Button } from '@/Components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select';
import { Switch } from '@/Components/ui/switch';
import { Textarea } from '@/Components/ui/textarea';

const props = defineProps({
    open: { type: Boolean, default: false },
    /** null = create, object = edit. Saat edit, detail lengkap di-fetch. */
    product: { type: Object, default: null },
    categories: { type: Array, required: true },
    /** Master satuan dari /units */
    unitsMaster: { type: Array, default: () => [] },
    /** Daftar supplier aktif */
    suppliers: { type: Array, default: () => [] },
});

const emit = defineEmits(['update:open', 'saved']);

const isEdit = computed(() => !!props.product);
const loadingDetail = ref(false);

const form = useForm({
    supplier_id: null,
    sku: '',
    name: '',
    category_id: null,
    description: '',
    is_active: true,
    // units: [{ unit_id, qty_to_base, barcode }]
    units: [{ unit_id: null, qty_to_base: 1, barcode: '' }],
    // packages: [{ name, items: [{ unit_index, cost_price, sell_price }] }]
    packages: [{ name: 'Harga Reguler', items: [{ unit_index: 0, cost_price: 0, sell_price: 0 }] }],
});

function blankForm() {
    return {
        supplier_id: props.suppliers[0]?.id ?? null,
        sku: '',
        name: '',
        category_id: null,
        description: '',
        is_active: true,
        units: [{ unit_id: null, qty_to_base: 1, barcode: '' }],
        packages: [{ name: 'Harga Reguler', items: [{ unit_index: 0, cost_price: 0, sell_price: 0 }] }],
    };
}

watch(
    () => [props.open, props.product?.id],
    async ([open]) => {
        if (!open) return;
        supplierSearch.value = '';

        if (props.product) {
            await hydrateFromProduct(props.product.id);
        } else {
            form.defaults(blankForm());
            form.reset();
        }
        form.clearErrors();
    },
    { immediate: true },
);

/**
 * Edit mode: fetch detail lengkap (satuan + paket harga) lalu bentuk ulang
 * ke shape form. Baris paket menyimpan unit_index (posisi di form.units),
 * jadi kita map product_unit_id existing → index-nya.
 */
async function hydrateFromProduct(id) {
    loadingDetail.value = true;
    try {
        const res = await fetch(route('products.details', id), {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });
        if (!res.ok) throw new Error('fetch gagal');
        const { product } = await res.json();

        const units = (product.units ?? []).map((u) => ({
            unit_id: u.unit_id,
            qty_to_base: Number(u.qty_to_base),
            barcode: u.barcode ?? '',
            _product_unit_id: u.id, // untuk map paket
        }));
        const idxByProductUnitId = {};
        units.forEach((u, i) => {
            idxByProductUnitId[u._product_unit_id] = i;
        });

        const packages = (product.price_packages ?? []).map((pkg) => ({
            name: pkg.name,
            items: (pkg.items ?? []).map((it) => ({
                unit_index: idxByProductUnitId[it.product_unit_id] ?? 0,
                cost_price: Number(it.cost_price),
                sell_price: Number(it.sell_price),
            })),
        }));

        form.defaults({
            supplier_id: product.supplier_id,
            sku: product.sku ?? '',
            name: product.name ?? '',
            category_id: product.category_id ?? null,
            description: product.description ?? '',
            is_active: !!product.is_active,
            units: units.map(({ _product_unit_id, ...rest }) => rest),
            packages: packages.length ? packages : blankForm().packages,
        });
        form.reset();
    } catch {
        form.defaults(blankForm());
        form.reset();
    } finally {
        loadingDetail.value = false;
    }
}

/* ─────────── Supplier searchable picker ─────────── */
const supplierSearch = ref('');
const supplierOpen = ref(false);
const selectedSupplier = computed(() =>
    props.suppliers.find((s) => s.id === form.supplier_id) ?? null,
);
const filteredSuppliers = computed(() => {
    const q = supplierSearch.value.trim().toLowerCase();
    if (!q) return props.suppliers;
    return props.suppliers.filter(
        (s) => s.name.toLowerCase().includes(q) || (s.code ?? '').toLowerCase().includes(q),
    );
});
function pickSupplier(s) {
    form.supplier_id = s.id;
    supplierOpen.value = false;
    supplierSearch.value = '';
}

/* ─────────── Satuan ─────────── */
function addUnit() {
    form.units = [...form.units, { unit_id: null, qty_to_base: 2, barcode: '' }];
}
function removeUnit(idx) {
    if (form.units.length <= 1) return;
    form.units = form.units.filter((_, i) => i !== idx);
    // Rapikan referensi unit_index di paket: hapus baris yang menunjuk unit terhapus,
    // dan geser index yang lebih besar.
    form.packages = form.packages.map((pkg) => ({
        ...pkg,
        items: pkg.items
            .filter((it) => it.unit_index !== idx)
            .map((it) => ({ ...it, unit_index: it.unit_index > idx ? it.unit_index - 1 : it.unit_index })),
    }));
}
const baseUnitCount = computed(
    () => form.units.filter((u) => Number(u.qty_to_base) === 1).length,
);
function availableUnitsFor(rowIdx) {
    const usedIds = new Set(
        form.units.map((u, i) => (i === rowIdx ? null : u.unit_id)).filter((v) => v !== null),
    );
    return props.unitsMaster.filter((u) => !usedIds.has(u.id));
}
function unitLabel(idx) {
    const u = form.units[idx];
    if (!u || u.unit_id == null) return `Satuan #${idx + 1}`;
    const master = props.unitsMaster.find((m) => m.id === u.unit_id);
    return master?.name ?? `Satuan #${idx + 1}`;
}

/* ─────────── Paket Harga ─────────── */
function addPackage() {
    form.packages = [
        ...form.packages,
        { name: `Paket ${form.packages.length + 1}`, items: [{ unit_index: 0, cost_price: 0, sell_price: 0 }] },
    ];
}
function removePackage(pi) {
    if (form.packages.length <= 1) return;
    form.packages = form.packages.filter((_, i) => i !== pi);
}
function addPackageRow(pi) {
    // Pilih satuan pertama yang belum dipakai di paket ini.
    const used = new Set(form.packages[pi].items.map((it) => it.unit_index));
    const freeIdx = form.units.findIndex((_, i) => !used.has(i));
    form.packages[pi].items.push({
        unit_index: freeIdx >= 0 ? freeIdx : 0,
        cost_price: 0,
        sell_price: 0,
    });
}
function removePackageRow(pi, ri) {
    if (form.packages[pi].items.length <= 1) return;
    form.packages[pi].items.splice(ri, 1);
}
function availableUnitIndexesFor(pi, ri) {
    const used = new Set(
        form.packages[pi].items.map((it, i) => (i === ri ? -1 : it.unit_index)),
    );
    return form.units.map((_, i) => i).filter((i) => !used.has(i));
}

function close() {
    emit('update:open', false);
}

function submit() {
    // Bersihkan _product_unit_id sisa (kalau ada) sudah dilakukan saat hydrate.
    const opts = {
        preserveScroll: true,
        onSuccess: () => {
            close();
            emit('saved');
        },
    };
    if (isEdit.value) {
        form.put(route('products.update', props.product.id), opts);
    } else {
        form.post(route('products.store'), opts);
    }
}
</script>

<template>
    <Dialog :open="open" @update:open="(v) => $emit('update:open', v)">
        <DialogContent class="sm:max-w-[860px] p-0 overflow-hidden rounded-3xl gap-0">
            <DialogHeader class="px-6 pt-6 pb-4">
                <div class="flex items-start gap-3.5">
                    <div class="size-11 rounded-full bg-brand-light/70 text-brand flex items-center justify-center shrink-0">
                        <component :is="isEdit ? Package : PackagePlus" class="size-5" />
                    </div>
                    <div class="flex-1 min-w-0 pt-0.5">
                        <DialogTitle class="text-base font-semibold tracking-tight">
                            {{ isEdit ? `Edit Produk — ${product.name}` : 'Tambah Produk' }}
                        </DialogTitle>
                        <DialogDescription class="text-xs text-muted-foreground mt-0.5">
                            1 produk = 1 supplier. Tentukan satuan + minimal 1 paket harga (satuan → modal & jual).
                        </DialogDescription>
                    </div>
                </div>
            </DialogHeader>

            <div v-if="loadingDetail" class="px-6 py-14 text-center text-sm text-muted-foreground">
                <Loader2 class="size-5 animate-spin mx-auto mb-2" />
                Memuat data produk…
            </div>

            <form v-else class="px-6 pb-2 space-y-5 max-h-[72vh] overflow-y-auto" @submit.prevent="submit">
                <!-- ── Supplier + Identitas ── -->
                <section class="space-y-3.5">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
                        Supplier & Identitas
                    </p>

                    <!-- Supplier searchable -->
                    <div class="space-y-1">
                        <Label class="text-xs font-medium">Supplier *</Label>
                        <div class="relative">
                            <button
                                type="button"
                                class="flex h-10 w-full items-center justify-between rounded-xl bg-input/20 border border-input px-3 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring/30"
                                @click="supplierOpen = !supplierOpen"
                            >
                                <span :class="selectedSupplier ? 'text-foreground' : 'text-muted-foreground'">
                                    {{ selectedSupplier ? `${selectedSupplier.name} · ${selectedSupplier.code}` : 'Pilih supplier…' }}
                                </span>
                                <ChevronsUpDown class="size-4 text-muted-foreground shrink-0" />
                            </button>

                            <div
                                v-if="supplierOpen"
                                class="absolute z-20 mt-1.5 w-full rounded-2xl bg-popover ring-1 ring-foreground/10 shadow-lg overflow-hidden"
                            >
                                <div class="relative border-b border-foreground/5 p-2">
                                    <Search class="absolute left-4 top-1/2 -translate-y-1/2 size-3.5 text-muted-foreground" />
                                    <Input
                                        v-model="supplierSearch"
                                        placeholder="Cari supplier (nama / kode)…"
                                        class="pl-8 h-9 rounded-full bg-muted/50 border-transparent"
                                        autofocus
                                    />
                                </div>
                                <ul class="max-h-56 overflow-y-auto py-1">
                                    <li v-if="filteredSuppliers.length === 0" class="px-3 py-3 text-xs text-muted-foreground text-center">
                                        Tidak ada supplier cocok.
                                    </li>
                                    <li
                                        v-for="s in filteredSuppliers"
                                        :key="s.id"
                                        class="px-3 py-2 flex items-center gap-2.5 hover:bg-foreground/5 cursor-pointer transition-colors"
                                        @click="pickSupplier(s)"
                                    >
                                        <div
                                            :class="[
                                                'size-4 rounded-full flex items-center justify-center shrink-0',
                                                form.supplier_id === s.id ? 'bg-brand text-white' : 'ring-1 ring-muted-foreground/30',
                                            ]"
                                        >
                                            <Check v-if="form.supplier_id === s.id" class="size-3" />
                                        </div>
                                        <div class="min-w-0">
                                            <p class="text-sm font-medium truncate">{{ s.name }}</p>
                                            <p class="text-[11px] text-muted-foreground font-mono">{{ s.code }}</p>
                                        </div>
                                    </li>
                                </ul>
                            </div>
                        </div>
                        <p v-if="form.errors.supplier_id" class="text-xs text-destructive">{{ form.errors.supplier_id }}</p>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                        <div class="space-y-1">
                            <Label class="text-xs font-medium">Nama Produk *</Label>
                            <Input v-model="form.name" required class="h-10 rounded-xl" />
                            <p v-if="form.errors.name" class="text-xs text-destructive">{{ form.errors.name }}</p>
                        </div>
                        <div class="space-y-1">
                            <Label class="text-xs font-medium">Kode Produk (SKU) *</Label>
                            <Input v-model="form.sku" required class="h-10 rounded-xl font-mono uppercase" placeholder="cth: WFR-001" />
                            <p v-if="form.errors.sku" class="text-xs text-destructive">{{ form.errors.sku }}</p>
                        </div>
                        <div class="space-y-1">
                            <Label class="text-xs font-medium">Kategori (opsional)</Label>
                            <Select
                                :model-value="form.category_id ? String(form.category_id) : ''"
                                @update:model-value="(v) => (form.category_id = v ? Number(v) : null)"
                            >
                                <SelectTrigger class="h-10 w-full rounded-xl">
                                    <SelectValue placeholder="Tanpa kategori" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="c in categories" :key="c.id" :value="String(c.id)">
                                        {{ c.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="space-y-1">
                            <Label class="text-xs font-medium">Deskripsi (opsional)</Label>
                            <Input v-model="form.description" class="h-10 rounded-xl" />
                        </div>
                    </div>
                </section>

                <!-- ── Satuan ── -->
                <section class="space-y-3 pt-3 border-t border-foreground/5">
                    <div class="flex items-center justify-between">
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">Satuan Produk</p>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            class="rounded-full"
                            :disabled="form.units.length >= unitsMaster.length"
                            @click="addUnit"
                        >
                            <Plus class="size-3" /> Tambah Satuan
                        </Button>
                    </div>
                    <p v-if="form.errors.units" class="text-xs text-destructive">{{ form.errors.units }}</p>

                    <div
                        v-for="(u, idx) in form.units"
                        :key="idx"
                        class="rounded-2xl bg-muted/40 p-3.5 grid grid-cols-[1fr_130px_1fr_auto] gap-2 items-end"
                    >
                        <div class="space-y-1">
                            <Label class="text-xs">Satuan</Label>
                            <Select
                                :model-value="u.unit_id ? String(u.unit_id) : ''"
                                @update:model-value="(v) => (u.unit_id = v ? Number(v) : null)"
                            >
                                <SelectTrigger class="h-10 w-full rounded-xl bg-card">
                                    <SelectValue placeholder="Pilih satuan" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="m in availableUnitsFor(idx)" :key="m.id" :value="String(m.id)">
                                        {{ m.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="space-y-1">
                            <Label class="text-xs">Qty ke base</Label>
                            <Input v-model="u.qty_to_base" type="number" min="1" required class="h-10 rounded-xl bg-card font-mono text-right" />
                        </div>
                        <div class="space-y-1">
                            <Label class="text-xs">Barcode (opsional)</Label>
                            <Input v-model="u.barcode" class="h-10 rounded-xl bg-card font-mono" placeholder="EAN-13" />
                        </div>
                        <button
                            type="button"
                            class="size-10 rounded-xl hover:bg-destructive/10 text-muted-foreground hover:text-destructive flex items-center justify-center transition-colors disabled:opacity-40"
                            :disabled="form.units.length <= 1"
                            @click="removeUnit(idx)"
                        >
                            <Trash2 class="size-3.5" />
                        </button>
                    </div>

                    <div class="rounded-2xl bg-muted/40 px-4 py-3 text-xs flex items-start gap-2.5">
                        <Package class="size-4 text-muted-foreground shrink-0 mt-0.5" />
                        <p class="text-muted-foreground leading-relaxed">
                            Satuan dgn <strong class="text-foreground">Qty ke base = 1</strong> jadi base unit (sumber stok).
                            Wajib tepat 1 base. Mis: PCS qty=1, PAK qty=10, KARDUS qty=240.
                            <span v-if="baseUnitCount === 0" class="text-destructive block mt-1 font-medium">Belum ada base unit (qty=1).</span>
                            <span v-if="baseUnitCount > 1" class="text-destructive block mt-1 font-medium">Hanya boleh 1 base unit (qty=1).</span>
                        </p>
                    </div>
                </section>

                <!-- ── Paket Harga ── -->
                <section class="space-y-3 pt-3 border-t border-foreground/5">
                    <div class="flex items-center justify-between">
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">Paket Harga</p>
                        <Button type="button" variant="outline" size="sm" class="rounded-full" @click="addPackage">
                            <Plus class="size-3" /> Tambah Paket
                        </Button>
                    </div>
                    <p v-if="form.errors.packages" class="text-xs text-destructive">{{ form.errors.packages }}</p>

                    <div
                        v-for="(pkg, pi) in form.packages"
                        :key="pi"
                        class="rounded-2xl ring-1 ring-brand/15 bg-brand-light/30 overflow-hidden"
                    >
                        <header class="px-4 py-3 flex items-center gap-2 border-b border-brand/10">
                            <Input
                                v-model="pkg.name"
                                class="h-9 rounded-xl bg-card font-semibold text-sm flex-1"
                                placeholder="Nama paket (mis. Harga Grosir)"
                            />
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon-sm"
                                class="rounded-full text-muted-foreground hover:text-destructive shrink-0"
                                :disabled="form.packages.length <= 1"
                                @click="removePackage(pi)"
                            >
                                <X class="size-4" />
                            </Button>
                        </header>

                        <div class="p-3 space-y-2">
                            <div
                                v-for="(item, ri) in pkg.items"
                                :key="ri"
                                class="grid grid-cols-[1.2fr_1fr_1fr_auto] gap-2 items-end"
                            >
                                <div class="space-y-1">
                                    <Label v-if="ri === 0" class="text-[11px] text-muted-foreground">Satuan</Label>
                                    <Select
                                        :model-value="String(item.unit_index)"
                                        @update:model-value="(v) => (item.unit_index = Number(v))"
                                    >
                                        <SelectTrigger class="h-9 w-full rounded-xl bg-card text-sm">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem
                                                v-for="i in availableUnitIndexesFor(pi, ri)"
                                                :key="i"
                                                :value="String(i)"
                                            >
                                                {{ unitLabel(i) }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div class="space-y-1">
                                    <Label v-if="ri === 0" class="text-[11px] text-muted-foreground">Harga modal</Label>
                                    <CurrencyInput v-model="item.cost_price" class="h-9 rounded-xl bg-card font-mono text-sm" />
                                </div>
                                <div class="space-y-1">
                                    <Label v-if="ri === 0" class="text-[11px] text-muted-foreground">Harga jual</Label>
                                    <CurrencyInput v-model="item.sell_price" class="h-9 rounded-xl bg-card font-mono text-sm" />
                                </div>
                                <button
                                    type="button"
                                    class="size-9 rounded-xl hover:bg-destructive/10 text-muted-foreground hover:text-destructive flex items-center justify-center transition-colors disabled:opacity-40"
                                    :disabled="pkg.items.length <= 1"
                                    @click="removePackageRow(pi, ri)"
                                >
                                    <Trash2 class="size-3.5" />
                                </button>
                            </div>

                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                class="rounded-full text-brand hover:bg-brand/10"
                                :disabled="pkg.items.length >= form.units.length"
                                @click="addPackageRow(pi)"
                            >
                                <Plus class="size-3.5" /> Tambah baris satuan
                            </Button>
                        </div>
                    </div>
                </section>

                <!-- ── Status ── -->
                <div class="flex items-center justify-between rounded-2xl bg-muted/40 px-4 py-3">
                    <div>
                        <Label class="text-xs font-medium block cursor-pointer mb-0">Status aktif</Label>
                        <p class="text-[12px] text-muted-foreground mt-0.5">Produk nonaktif tidak muncul di dropdown SO/PO.</p>
                    </div>
                    <Switch v-model="form.is_active" />
                </div>
            </form>

            <DialogFooter class="px-6 py-4 gap-2">
                <Button type="button" variant="outline" size="default" class="rounded-full" @click="close">Batal</Button>
                <Button
                    type="button"
                    size="default"
                    class="rounded-full bg-brand text-white hover:bg-brand-dark"
                    :disabled="form.processing || loadingDetail"
                    @click="submit"
                >
                    <Loader2 v-if="form.processing" class="size-4 animate-spin" />
                    {{ form.processing ? 'Menyimpan…' : isEdit ? 'Simpan' : 'Buat Produk' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
