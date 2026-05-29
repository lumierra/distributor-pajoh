<script setup>
import { router, useForm } from '@inertiajs/vue3';
import {
    DollarSign,
    Edit,
    Factory,
    Loader2,
    Package,
    Plus,
    Ruler,
    Star,
    Trash2,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import ActionButton from '@/Components/Shared/ActionButton.vue';
import ActionGroup from '@/Components/Shared/ActionGroup.vue';
import AssignSupplierDialog from '@/Components/Products/AssignSupplierDialog.vue';
import SupplierUnitPriceDialog from '@/Components/Products/SupplierUnitPriceDialog.vue';
import UnitFormDialog from '@/Components/Products/UnitFormDialog.vue';
import { Button } from '@/Components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog';

const props = defineProps({
    open: { type: Boolean, default: false },
    productId: { type: Number, default: null },
    suppliers: { type: Array, default: () => [] },
    canUpdate: { type: Boolean, default: false },
});

const emit = defineEmits(['update:open']);

const product = ref(null);
const loading = ref(false);
const tab = ref('uom');

// Sub-dialogs state
const unitOpen = ref(false);
const assignSupplierOpen = ref(false);
const supplierUnitPriceOpen = ref(false);
const editingSupplierUnit = ref(null);

const tabs = computed(() => [
    { value: 'uom', label: `Satuan (${product.value?.units?.length ?? 0})`, icon: Ruler },
    {
        value: 'supplier',
        label: `Supplier & Harga (${product.value?.supplier_product_units?.length ?? 0})`,
        icon: Factory,
    },
]);

const existingSupplierIds = computed(
    () => (product.value?.supplier_products ?? []).map((sp) => sp.supplier_id),
);

async function fetchDetails() {
    if (!props.productId) return;
    loading.value = true;
    try {
        const res = await fetch(route('products.details', props.productId), {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });
        if (!res.ok) throw new Error('fetch failed');
        const data = await res.json();
        product.value = data.product ?? null;
    } catch {
        product.value = null;
    } finally {
        loading.value = false;
    }
}

watch(
    () => [props.open, props.productId],
    ([open, id]) => {
        if (open && id) {
            tab.value = 'uom';
            product.value = null;
            fetchDetails();
        }
    },
    { immediate: true },
);

function reloadAfterChild() {
    fetchDetails();
}

function openAddSupplierUnit() {
    editingSupplierUnit.value = null;
    supplierUnitPriceOpen.value = true;
}

function openEditSupplierUnit(spu) {
    editingSupplierUnit.value = spu;
    supplierUnitPriceOpen.value = true;
}

function destroySupplierUnit(spu) {
    if (!window.confirm('Hapus baris harga ini?')) return;
    useForm({}).delete(route('supplier-product-units.destroy', spu.id), {
        preserveScroll: true,
        onSuccess: () => reloadAfterChild(),
    });
}

function destroyUnit(u) {
    if (!window.confirm(`Hapus satuan ${u.name}?`)) return;
    useForm({}).delete(route('product-units.destroy', u.id), {
        preserveScroll: true,
        onSuccess: () => reloadAfterChild(),
    });
}

function destroySupplierLink(sp) {
    if (!window.confirm(`Lepas supplier ${sp.supplier?.name} dari produk ini?`)) return;
    useForm({}).delete(route('supplier-products.destroy', sp.id), {
        preserveScroll: true,
        onSuccess: () => reloadAfterChild(),
    });
}

function setPrimarySupplier(sp) {
    useForm({ is_primary: true }).put(route('supplier-products.update', sp.id), {
        preserveScroll: true,
        onSuccess: () => reloadAfterChild(),
    });
}

function close() {
    emit('update:open', false);
    // Reload Inertia index supaya counter/data terbaru sync
    router.reload({ only: ['products', 'stats'] });
}

function formatRupiah(v) {
    if (v === null || v === undefined) return '—';
    return 'Rp ' + new Intl.NumberFormat('id-ID').format(Number(v) || 0);
}
</script>

<template>
    <Dialog :open="open" @update:open="(v) => $emit('update:open', v)">
        <DialogContent class="sm:max-w-[900px] p-0 overflow-hidden">
            <DialogHeader class="px-5 pt-5 pb-3 border-b border-border/70">
                <div class="flex items-start gap-3">
                    <div class="size-10 rounded-md bg-emerald-50 text-emerald-700 flex items-center justify-center shrink-0 ring-1 ring-emerald-200">
                        <Package class="size-5" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <DialogTitle class="text-base font-bold tracking-tight truncate">
                            Kelola Produk — {{ product?.name ?? 'Memuat…' }}
                        </DialogTitle>
                        <DialogDescription class="text-xs text-muted-foreground mt-0.5">
                            <span v-if="product">
                                SKU <span class="font-mono">{{ product.sku }}</span>
                                <span v-if="product.category"> · {{ product.category.name }}</span>
                            </span>
                        </DialogDescription>
                    </div>
                </div>
            </DialogHeader>

            <div v-if="loading && !product" class="px-5 py-10 text-center text-sm text-muted-foreground">
                <Loader2 class="size-5 animate-spin mx-auto mb-2" />
                Memuat data produk…
            </div>

            <div v-else-if="product" class="px-5 py-4 max-h-[70vh] overflow-y-auto space-y-3">
                <!-- Tabs -->
                <div class="flex gap-1 rounded-md bg-muted/40 p-1 w-fit">
                    <button
                        v-for="t in tabs"
                        :key="t.value"
                        type="button"
                        :class="[
                            'flex items-center gap-2 px-3 py-1.5 rounded text-xs font-medium transition-colors',
                            tab === t.value
                                ? 'bg-card text-foreground shadow-sm ring-1 ring-foreground/5'
                                : 'text-muted-foreground hover:text-foreground',
                        ]"
                        @click="tab = t.value"
                    >
                        <component :is="t.icon" class="size-3.5" />
                        {{ t.label }}
                    </button>
                </div>

                <!-- Tab: Satuan -->
                <section v-show="tab === 'uom'" class="rounded-lg ring-1 ring-foreground/5 overflow-hidden">
                    <header class="border-b border-border/70 px-4 py-2.5 flex items-center justify-between bg-muted/30">
                        <h3 class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                            Satuan Produk
                        </h3>
                        <Button
                            v-if="canUpdate"
                            size="sm"
                            variant="secondary"
                            @click="unitOpen = true"
                        >
                            <Plus class="size-3.5" />
                            Tambah Satuan
                        </Button>
                    </header>
                    <ul v-if="product.units?.length" class="divide-y divide-border/60">
                        <li
                            v-for="u in product.units"
                            :key="u.id"
                            class="px-4 py-2.5 flex items-center gap-3 hover:bg-muted/20 transition-colors"
                        >
                            <div class="size-8 rounded-md bg-primary/10 text-primary flex items-center justify-center shrink-0">
                                <Ruler class="size-4" />
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2">
                                    <p class="font-medium text-sm truncate">{{ u.name }}</p>
                                    <span
                                        v-if="u.qty_to_base === 1"
                                        class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200"
                                    >
                                        Base
                                    </span>
                                </div>
                                <p class="text-[11px] text-muted-foreground font-mono">
                                    1 {{ u.name }} = {{ u.qty_to_base }} {{ product.base_unit?.name ?? 'base' }}
                                    <span v-if="u.barcode"> · barcode {{ u.barcode }}</span>
                                </p>
                            </div>
                            <ActionGroup v-if="canUpdate && u.qty_to_base !== 1">
                                <ActionButton :icon="Trash2" label="Hapus" tone="red" @click="destroyUnit(u)" />
                            </ActionGroup>
                        </li>
                    </ul>
                    <div v-else class="px-4 py-8 text-center text-xs text-muted-foreground">
                        <Ruler class="size-6 opacity-40 mx-auto mb-2" />
                        Belum ada satuan.
                    </div>
                </section>

                <!-- Tab: Supplier & Harga -->
                <section v-show="tab === 'supplier'" class="space-y-3">
                    <!-- Harga per (supplier × satuan) -->
                    <div class="rounded-lg ring-1 ring-foreground/5 overflow-hidden">
                        <header class="border-b border-border/70 px-4 py-2.5 flex items-center justify-between bg-muted/30">
                            <h3 class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                Harga per (Supplier × Satuan)
                            </h3>
                            <Button
                                v-if="canUpdate"
                                size="sm"
                                variant="secondary"
                                :disabled="!product.units?.length"
                                @click="openAddSupplierUnit"
                            >
                                <Plus class="size-3.5" />
                                Tambah Harga
                            </Button>
                        </header>
                        <div v-if="product.supplier_product_units?.length" class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead class="border-b border-border/70 bg-muted/20">
                                    <tr class="text-[10px] uppercase tracking-wider text-muted-foreground">
                                        <th class="text-left py-2 px-4">Supplier</th>
                                        <th class="text-left py-2 px-3">Satuan</th>
                                        <th class="text-right py-2 px-3">Modal</th>
                                        <th class="text-right py-2 px-3">Jual</th>
                                        <th class="text-center py-2 px-3">Status</th>
                                        <th class="text-right py-2 px-4">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-border/40">
                                    <tr
                                        v-for="spu in product.supplier_product_units"
                                        :key="spu.id"
                                        class="hover:bg-muted/20"
                                    >
                                        <td class="py-2 px-4">
                                            <p class="font-medium">{{ spu.supplier?.name ?? '—' }}</p>
                                            <p class="text-[10px] font-mono text-muted-foreground">{{ spu.supplier?.code }}</p>
                                        </td>
                                        <td class="py-2 px-3">
                                            <span class="font-medium">{{ spu.product_unit?.name }}</span>
                                        </td>
                                        <td class="py-2 px-3 text-right font-mono">{{ formatRupiah(spu.cost_price) }}</td>
                                        <td class="py-2 px-3 text-right font-mono">{{ formatRupiah(spu.sell_price) }}</td>
                                        <td class="py-2 px-3 text-center">
                                            <span
                                                v-if="spu.is_active"
                                                class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200"
                                            >Aktif</span>
                                            <span
                                                v-else
                                                class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-muted text-muted-foreground"
                                            >Nonaktif</span>
                                        </td>
                                        <td class="py-2 px-4">
                                            <div class="flex justify-end">
                                                <ActionGroup v-if="canUpdate">
                                                    <ActionButton :icon="Edit" label="Edit" tone="blue" @click="openEditSupplierUnit(spu)" />
                                                    <ActionButton :icon="Trash2" label="Hapus" tone="red" @click="destroySupplierUnit(spu)" />
                                                </ActionGroup>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div v-else class="px-4 py-8 text-center text-xs text-muted-foreground">
                            <DollarSign class="size-6 opacity-40 mx-auto mb-2" />
                            Belum ada harga supplier untuk produk ini.
                            <p v-if="!product.units?.length" class="mt-1">Tambah satuan dulu di tab Satuan.</p>
                        </div>
                    </div>

                    <!-- Supplier tagging -->
                    <div class="rounded-lg ring-1 ring-foreground/5 overflow-hidden">
                        <header class="border-b border-border/70 px-4 py-2.5 flex items-center justify-between bg-muted/30">
                            <h3 class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                Supplier Penyedia (tagging)
                            </h3>
                            <Button
                                v-if="canUpdate"
                                size="sm"
                                variant="outline"
                                @click="assignSupplierOpen = true"
                            >
                                <Plus class="size-3.5" />
                                Tautkan
                            </Button>
                        </header>
                        <ul v-if="product.supplier_products?.length" class="divide-y divide-border/60">
                            <li
                                v-for="sp in product.supplier_products"
                                :key="sp.id"
                                class="px-4 py-2 flex items-center gap-3 hover:bg-muted/20"
                            >
                                <Factory class="size-4 text-muted-foreground shrink-0" />
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <p class="font-medium text-sm truncate">{{ sp.supplier?.name ?? '—' }}</p>
                                        <span class="text-[10px] font-mono text-muted-foreground">{{ sp.supplier?.code }}</span>
                                        <span
                                            v-if="sp.is_primary"
                                            class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-semibold bg-warning-soft text-amber-700"
                                        >
                                            <Star class="size-3" /> Primary
                                        </span>
                                    </div>
                                </div>
                                <ActionGroup v-if="canUpdate">
                                    <ActionButton
                                        v-if="!sp.is_primary"
                                        :icon="Star"
                                        label="Set primary"
                                        tone="amber"
                                        @click="setPrimarySupplier(sp)"
                                    />
                                    <ActionButton :icon="Trash2" label="Lepas" tone="red" @click="destroySupplierLink(sp)" />
                                </ActionGroup>
                            </li>
                        </ul>
                        <div v-else class="px-4 py-6 text-center text-xs text-muted-foreground">
                            Belum ada supplier tertaut.
                        </div>
                    </div>
                </section>
            </div>

            <DialogFooter class="px-5 py-3 border-t border-border/70 bg-muted/30">
                <Button type="button" variant="outline" size="default" @click="close">Tutup</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <!-- Sub-dialogs (mounted di luar grid agar gak ke-affect overflow) -->
    <UnitFormDialog
        v-if="product"
        v-model:open="unitOpen"
        :product-id="product.id"
        :existing-levels="[]"
        @saved="reloadAfterChild"
    />
    <SupplierUnitPriceDialog
        v-if="product"
        v-model:open="supplierUnitPriceOpen"
        :product-id="product.id"
        :product-units="product.units ?? []"
        :suppliers="suppliers"
        :row="editingSupplierUnit"
        @saved="reloadAfterChild"
    />
    <AssignSupplierDialog
        v-if="product"
        v-model:open="assignSupplierOpen"
        :product-id="product.id"
        :suppliers="suppliers"
        :existing-supplier-ids="existingSupplierIds"
        @saved="reloadAfterChild"
    />
</template>
