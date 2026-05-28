<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Ban,
    DollarSign,
    Edit,
    Factory,
    Info,
    Package,
    Plus,
    Ruler,
    Star,
    Trash2,
    UserCheck,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import ActionButton from '@/Components/Shared/ActionButton.vue';
import ActionGroup from '@/Components/Shared/ActionGroup.vue';
import PageHeader from '@/Components/Shared/PageHeader.vue';
import TabsPill from '@/Components/Shared/TabsPill.vue';
import AssignSupplierDialog from '@/Components/Products/AssignSupplierDialog.vue';
import ProductFormDialog from '@/Components/Products/ProductFormDialog.vue';
import UnitFormDialog from '@/Components/Products/UnitFormDialog.vue';
import { Button } from '@/Components/ui/button';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    product: { type: Object, required: true },
    categories: { type: Array, required: true },
    units: { type: Array, default: () => [] },
    suppliers: { type: Array, required: true },
    canUpdate: { type: Boolean, default: false },
    canDelete: { type: Boolean, default: false },
});

const tab = ref('info');
const tabs = computed(() => [
    { value: 'info', label: 'Info', icon: Info },
    { value: 'uom', label: `Satuan (${props.product.units?.length ?? 0})`, icon: Ruler },
    { value: 'supplier', label: `Supplier & Harga (${props.product.supplier_product_units?.length ?? 0})`, icon: Factory },
]);

const editOpen = ref(false);
const unitOpen = ref(false);
const assignSupplierOpen = ref(false);

const existingLevels = computed(() => (props.product.units ?? []).map((u) => u.level));
const existingSupplierIds = computed(
    () => (props.product.supplier_products ?? []).map((sp) => sp.supplier_id),
);

function onSaved() {
    router.reload({ only: ['product'] });
}

function toggleActive() {
    useForm({}).post(route('products.toggle-active', props.product.id), {
        preserveScroll: true,
    });
}

function destroyUnit(u) {
    if (!window.confirm(`Hapus unit ${u.name} (${u.level})?`)) return;
    useForm({}).delete(route('product-units.destroy', u.id), { preserveScroll: true });
}

function destroySupplierLink(sp) {
    if (!window.confirm(`Lepas supplier ${sp.supplier?.name} dari produk ini?`)) return;
    useForm({}).delete(route('supplier-products.destroy', sp.id), { preserveScroll: true });
}

function setPrimarySupplier(sp) {
    useForm({ is_primary: true }).put(route('supplier-products.update', sp.id), {
        preserveScroll: true,
    });
}

function formatDate(value) {
    if (!value) return '—';
    return new Date(value).toLocaleString('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
}

function formatRupiah(v) {
    if (v === null || v === undefined) return '—';
    return 'Rp ' + new Intl.NumberFormat('id-ID').format(Number(v) || 0);
}
</script>

<template>
    <Head :title="`Produk: ${product.name}`" />

    <AppLayout>
        <PageHeader
            :title="product.name"
            :description="`${product.sku} · ${product.category?.name ?? 'Tanpa kategori'}`"
            :icon="Package"
        >
            <template #actions>
                <Button as-child variant="ghost" size="default">
                    <Link :href="route('products.index')">
                        <ArrowLeft class="size-4" />
                        Daftar Produk
                    </Link>
                </Button>
                <Button v-if="canUpdate" size="default" variant="secondary" @click="editOpen = true">
                    <Edit class="size-4" />
                    Edit
                </Button>
            </template>
        </PageHeader>

        <!-- Summary strip -->
        <section class="grid grid-cols-1 lg:grid-cols-[1fr_auto] gap-4 mb-5">
            <div class="rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm p-4 flex items-start gap-4">
                <div class="size-12 rounded-md bg-primary/10 text-primary flex items-center justify-center shrink-0">
                    <Package class="size-6" />
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
                        {{ product.sku }}
                    </p>
                    <h2 class="text-base font-bold tracking-tight text-foreground truncate">
                        {{ product.name }}
                    </h2>
                    <p class="text-xs text-muted-foreground mt-1">
                        {{ product.brand || 'Tanpa brand' }} · Base: {{ product.base_unit?.name ?? '—' }}
                    </p>
                </div>
                <div class="flex flex-col items-end gap-1.5">
                    <span
                        v-if="product.is_active"
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
                </div>
            </div>
            <div v-if="canUpdate" class="flex flex-row lg:flex-col gap-2 lg:items-stretch lg:justify-center">
                <Button variant="outline" size="default" @click="toggleActive">
                    <component :is="product.is_active ? Ban : UserCheck" class="size-4" />
                    {{ product.is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                </Button>
            </div>
        </section>

        <div class="mb-4">
            <TabsPill v-model="tab" :tabs="tabs" />
        </div>

        <!-- Tab: Info -->
        <section v-show="tab === 'info'" class="space-y-4">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <div class="rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm">
                    <header class="border-b border-border/70 px-5 py-3">
                        <h3 class="text-sm font-semibold">Identitas</h3>
                    </header>
                    <dl class="px-5 py-3 grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                        <div>
                            <dt class="text-[11px] uppercase tracking-wider text-muted-foreground font-semibold">SKU</dt>
                            <dd class="font-mono mt-0.5">{{ product.sku }}</dd>
                        </div>
                        <div>
                            <dt class="text-[11px] uppercase tracking-wider text-muted-foreground font-semibold">Kategori</dt>
                            <dd class="mt-0.5">{{ product.category?.name || '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-[11px] uppercase tracking-wider text-muted-foreground font-semibold">Brand</dt>
                            <dd class="mt-0.5">{{ product.brand || '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-[11px] uppercase tracking-wider text-muted-foreground font-semibold">Base Unit</dt>
                            <dd class="mt-0.5">{{ product.base_unit?.name || '—' }}</dd>
                        </div>
                        <div class="sm:col-span-2">
                            <dt class="text-[11px] uppercase tracking-wider text-muted-foreground font-semibold">Dibuat</dt>
                            <dd class="mt-0.5">{{ formatDate(product.created_at) }}</dd>
                        </div>
                    </dl>
                </div>

                <div class="rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm">
                    <header class="border-b border-border/70 px-5 py-3">
                        <h3 class="text-sm font-semibold">Deskripsi & Catatan</h3>
                    </header>
                    <div class="px-5 py-3 text-sm space-y-3">
                        <div>
                            <p class="text-[11px] uppercase tracking-wider text-muted-foreground font-semibold">Deskripsi</p>
                            <p v-if="product.description" class="mt-1 text-foreground whitespace-pre-line">{{ product.description }}</p>
                            <p v-else class="mt-1 text-muted-foreground italic">Belum diisi</p>
                        </div>
                        <div>
                            <p class="text-[11px] uppercase tracking-wider text-muted-foreground font-semibold">Catatan</p>
                            <p v-if="product.notes" class="mt-1 text-foreground whitespace-pre-line">{{ product.notes }}</p>
                            <p v-else class="mt-1 text-muted-foreground italic">—</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Tab: UoM -->
        <section v-show="tab === 'uom'" class="rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm overflow-hidden">
            <header class="border-b border-border/70 px-5 py-3 flex items-center justify-between">
                <h3 class="text-sm font-semibold">Unit of Measure</h3>
                <Button
                    v-if="canUpdate && existingLevels.length < 3"
                    size="default"
                    variant="secondary"
                    @click="unitOpen = true"
                >
                    <Plus class="size-4" />
                    Tambah Unit
                </Button>
            </header>
            <ul v-if="product.units?.length" class="divide-y divide-border/60">
                <li
                    v-for="u in product.units"
                    :key="u.id"
                    class="px-5 py-3 flex items-center gap-3 hover:bg-muted/30 transition-colors"
                >
                    <div class="size-9 rounded-md bg-primary/10 text-primary flex items-center justify-center shrink-0">
                        <Ruler class="size-4" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2">
                            <p class="font-medium text-foreground truncate">{{ u.name }}</p>
                            <span
                                class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-primary/10 text-primary"
                            >
                                {{ u.level }}{{ u.level === 'KCL' ? ' (base)' : '' }}
                            </span>
                        </div>
                        <p class="text-xs text-muted-foreground font-mono">
                            1 {{ u.name }} = {{ u.qty_to_base }} {{ product.base_unit?.name ?? 'base' }}
                            <span v-if="u.barcode"> · barcode {{ u.barcode }}</span>
                        </p>
                    </div>
                    <ActionGroup v-if="canUpdate && u.level !== 'KCL'">
                        <ActionButton :icon="Trash2" label="Hapus" tone="red" @click="destroyUnit(u)" />
                    </ActionGroup>
                </li>
            </ul>
            <div v-else class="px-5 py-10 text-center text-sm text-muted-foreground">
                <Ruler class="size-7 opacity-40 mx-auto mb-2" />
                Belum ada unit.
            </div>
        </section>

        <!-- Tab: Supplier & Harga -->
        <section v-show="tab === 'supplier'" class="rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm overflow-hidden">
            <header class="border-b border-border/70 px-5 py-3 flex items-center justify-between">
                <h3 class="text-sm font-semibold">Supplier Penyedia</h3>
                <Button
                    v-if="canUpdate"
                    size="default"
                    variant="secondary"
                    @click="assignSupplierOpen = true"
                >
                    <Plus class="size-4" />
                    Tautkan Supplier
                </Button>
            </header>
            <ul v-if="product.supplier_products?.length" class="divide-y divide-border/60">
                <li
                    v-for="sp in product.supplier_products"
                    :key="sp.id"
                    class="px-5 py-3 flex items-center gap-3 hover:bg-muted/30 transition-colors"
                >
                    <div class="size-9 rounded-md bg-primary/10 text-primary flex items-center justify-center shrink-0">
                        <Factory class="size-4" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <p class="font-medium text-foreground truncate">
                                {{ sp.supplier?.name ?? '—' }}
                            </p>
                            <span class="text-[10px] font-mono text-muted-foreground">
                                {{ sp.supplier?.code }}
                            </span>
                            <span
                                v-if="sp.is_primary"
                                class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-semibold bg-warning-soft text-amber-700"
                            >
                                <Star class="size-3" /> Primary
                            </span>
                            <span
                                v-if="!sp.is_active"
                                class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-muted text-muted-foreground"
                            >
                                Nonaktif
                            </span>
                        </div>
                        <p class="text-xs text-muted-foreground">
                            <span v-if="sp.supplier_sku">SKU: {{ sp.supplier_sku }}</span>
                            <span v-if="sp.moq"> · MOQ: {{ sp.moq }}</span>
                        </p>
                    </div>
                    <ActionGroup v-if="canUpdate">
                        <ActionButton
                            v-if="!sp.is_primary"
                            :icon="Star"
                            label="Set primary"
                            tone="amber"
                            @click="setPrimarySupplier(sp)"
                        />
                        <ActionButton
                            :icon="Trash2"
                            label="Lepas"
                            tone="red"
                            @click="destroySupplierLink(sp)"
                        />
                    </ActionGroup>
                </li>
            </ul>
            <div v-else class="px-5 py-10 text-center text-sm text-muted-foreground">
                <Factory class="size-7 opacity-40 mx-auto mb-2" />
                Belum ada supplier yang ditautkan.
            </div>
        </section>

        <!-- Modals -->
        <ProductFormDialog
            v-model:open="editOpen"
            :product="product"
            :categories="categories"
            @saved="onSaved"
        />
        <UnitFormDialog
            v-model:open="unitOpen"
            :product-id="product.id"
            :existing-levels="existingLevels"
            @saved="onSaved"
        />
        <AssignSupplierDialog
            v-model:open="assignSupplierOpen"
            :product-id="product.id"
            :suppliers="suppliers"
            :existing-supplier-ids="existingSupplierIds"
            @saved="onSaved"
        />
    </AppLayout>
</template>
