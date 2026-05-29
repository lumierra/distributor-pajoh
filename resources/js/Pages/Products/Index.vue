<script setup>
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import {
    Ban,
    CheckCircle2,
    LogIn,
    Package,
    Pencil,
    Plus,
    RotateCcw,
    Search,
    Settings,
    Tags,
    Trash2,
} from '@lucide/vue';
import { computed, onMounted, reactive, ref, watch } from 'vue';
import ActionButton from '@/Components/Shared/ActionButton.vue';
import ActionGroup from '@/Components/Shared/ActionGroup.vue';
import PageHeader from '@/Components/Shared/PageHeader.vue';
import Pagination from '@/Components/Shared/Pagination.vue';
import StatCard from '@/Components/Shared/StatCard.vue';
import ProductFormDialog from '@/Components/Products/ProductFormDialog.vue';
import ProductManageDialog from '@/Components/Products/ProductManageDialog.vue';
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
    unitsMaster: { type: Array, default: () => [] },
    suppliers: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    stats: { type: Object, default: null },
});

const page = usePage();
const canCreate = computed(
    () =>
        page.props.auth?.user?.is_superadmin ||
        page.props.permissions?.['master.product']?.create,
);

const ALL = 'all';

const filters = reactive({
    q: props.filters.q ?? '',
    category: props.filters.category || ALL,
    active:
        props.filters.active === '' ||
        props.filters.active === null ||
        props.filters.active === undefined
            ? ALL
            : String(props.filters.active),
});

let timer = null;
watch(filters, () => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get(
            route('products.index'),
            {
                q: filters.q,
                category: filters.category === ALL ? '' : filters.category,
                active: filters.active === ALL ? '' : filters.active,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }, 300);
});

function reset() {
    filters.q = '';
    filters.category = ALL;
    filters.active = ALL;
}

const modalOpen = ref(false);
const editing = ref(null);
const manageOpen = ref(false);
const manageProductId = ref(null);

function openCreate() {
    editing.value = null;
    modalOpen.value = true;
}

function openEdit(p) {
    editing.value = p;
    modalOpen.value = true;
}

function openManage(p) {
    manageProductId.value = p.id;
    manageOpen.value = true;
}

function onSaved() {
    // Setelah create, auto-buka modal Kelola untuk produk yang baru dibuat
    // supaya admin langsung bisa set satuan tambahan / harga supplier.
    const newId = page.props.flash?.newly_created_product_id;
    router.reload({
        only: ['products', 'stats'],
        onSuccess: () => {
            if (newId) {
                manageProductId.value = newId;
                manageOpen.value = true;
            }
        },
    });
}

// Kalau page di-render dgn newly_created_product_id (dari flash redirect),
// auto buka modal Kelola
onMounted(() => {
    const newId = page.props.flash?.newly_created_product_id;
    if (newId) {
        manageProductId.value = newId;
        manageOpen.value = true;
    }
});

function toggleActive(p) {
    useForm({}).post(route('products.toggle-active', p.id), { preserveScroll: true });
}

function destroy(p) {
    if (! window.confirm(`Hapus produk ${p.name}?`)) return;
    useForm({}).delete(route('products.destroy', p.id), { preserveScroll: true });
}

function formatDate(value) {
    if (!value) return '—';
    return new Date(value).toLocaleString('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
}
</script>

<template>
    <Head title="Daftar Produk" />

    <AppLayout>
        <PageHeader
            title="Produk"
            description="Master produk — satuan dinamis dgn konversi base unit + harga per (supplier × satuan)."
            :icon="Package"
        >
            <template #actions>
                <Button as-child variant="outline" size="default">
                    <Link :href="route('product-categories.index')">
                        <Tags class="size-4" />
                        Kategori
                    </Link>
                </Button>
                <Button v-if="canCreate" size="default" variant="secondary" @click="openCreate">
                    <Plus class="size-4" />
                    Tambah Produk
                </Button>
            </template>
        </PageHeader>

        <section v-if="stats" class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
            <StatCard label="Total Produk" :value="stats.total ?? 0" tone="brand">
                <template #icon><Package class="size-5" /></template>
            </StatCard>
            <StatCard label="Aktif" :value="stats.active ?? 0" tone="brand">
                <template #icon><CheckCircle2 class="size-5" /></template>
            </StatCard>
            <StatCard label="Nonaktif" :value="stats.inactive ?? 0" tone="brand">
                <template #icon><Ban class="size-5" /></template>
            </StatCard>
            <StatCard label="Kategori" :value="stats.categories ?? 0" tone="brand-orange">
                <template #icon><Tags class="size-5" /></template>
            </StatCard>
        </section>

        <section class="rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm overflow-hidden">
            <div class="border-b border-border/70 px-3 py-2.5 flex flex-col sm:flex-row sm:items-center gap-2">
                <div class="relative flex-1">
                    <Search class="absolute left-2.5 top-1/2 -translate-y-1/2 size-3.5 text-muted-foreground" />
                    <Input
                        v-model="filters.q"
                        placeholder="Cari nama atau SKU…"
                        class="pl-8 h-9 rounded-md"
                    />
                </div>
                <div class="flex items-center gap-2">
                    <Select v-model="filters.category">
                        <SelectTrigger class="w-[160px] h-9 rounded-md">
                            <SelectValue placeholder="Kategori" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="ALL">Semua kategori</SelectItem>
                            <SelectItem v-for="c in categories" :key="c.id" :value="c.code">
                                {{ c.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <Select v-model="filters.active">
                        <SelectTrigger class="w-[130px] h-9 rounded-md">
                            <SelectValue placeholder="Status" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="ALL">Semua Status</SelectItem>
                            <SelectItem value="1">Aktif</SelectItem>
                            <SelectItem value="0">Nonaktif</SelectItem>
                        </SelectContent>
                    </Select>
                    <Button type="button" variant="outline" size="default" @click="reset">
                        <RotateCcw class="size-3.5" />
                        Reset
                    </Button>
                </div>
            </div>

            <Table>
                <TableHeader>
                    <TableRow class="[&>th]:text-[10px] [&>th]:font-semibold [&>th]:uppercase [&>th]:tracking-wider [&>th]:text-muted-foreground [&>th]:py-2.5">
                        <TableHead class="pl-4">Produk</TableHead>
                        <TableHead>Kategori</TableHead>
                        <TableHead>Base</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead>Dibuat</TableHead>
                        <TableHead class="text-right pr-4">Aksi</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody class="text-sm">
                    <TableRow v-if="products.data.length === 0">
                        <TableCell colspan="6" class="text-center py-16">
                            <div class="flex flex-col items-center gap-2 text-muted-foreground">
                                <Package class="size-7 opacity-40" />
                                <p class="text-sm">Belum ada produk.</p>
                            </div>
                        </TableCell>
                    </TableRow>
                    <TableRow
                        v-for="p in products.data"
                        :key="p.id"
                        class="hover:bg-muted/30 transition-colors"
                    >
                        <TableCell class="pl-4 py-2.5">
                            <div class="flex items-center gap-2.5">
                                <div class="size-8 rounded-md bg-primary/10 text-primary flex items-center justify-center shrink-0">
                                    <Package class="size-4" />
                                </div>
                                <div class="min-w-0">
                                    <button
                                        type="button"
                                        class="font-medium text-foreground truncate leading-tight hover:text-primary transition-colors block text-left"
                                        @click="openManage(p)"
                                    >
                                        {{ p.name }}
                                    </button>
                                    <p class="text-[11px] text-muted-foreground font-mono leading-tight">
                                        {{ p.sku }}
                                    </p>
                                </div>
                            </div>
                        </TableCell>
                        <TableCell>
                            <span
                                v-if="p.category"
                                class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-medium bg-muted text-muted-foreground"
                            >
                                {{ p.category.name }}
                            </span>
                            <span v-else class="text-muted-foreground">—</span>
                        </TableCell>
                        <TableCell class="text-xs">
                            <span v-if="p.base_unit" class="font-mono">{{ p.base_unit.name }}</span>
                            <span v-else class="text-muted-foreground">—</span>
                        </TableCell>
                        <TableCell>
                            <span
                                v-if="p.is_active"
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
                        </TableCell>
                        <TableCell class="text-xs text-muted-foreground whitespace-nowrap">
                            {{ formatDate(p.created_at) }}
                        </TableCell>
                        <TableCell class="py-3 px-4 text-right whitespace-nowrap">
                            <ActionGroup>
                                <ActionButton
                                    :icon="Settings"
                                    label="Kelola (satuan, supplier & harga)"
                                    tone="brand"
                                    @click="openManage(p)"
                                />
                                <ActionButton
                                    :icon="Pencil"
                                    label="Edit"
                                    tone="blue"
                                    @click="openEdit(p)"
                                />
                                <ActionButton
                                    v-if="p.is_active"
                                    :icon="Ban"
                                    label="Nonaktifkan"
                                    tone="amber"
                                    @click="toggleActive(p)"
                                />
                                <ActionButton
                                    v-else
                                    :icon="LogIn"
                                    label="Aktifkan"
                                    tone="emerald"
                                    @click="toggleActive(p)"
                                />
                                <ActionButton
                                    :icon="Trash2"
                                    label="Hapus"
                                    tone="red"
                                    @click="destroy(p)"
                                />
                            </ActionGroup>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>

            <div v-if="products.data.length > 0" class="border-t border-border/70 px-4 py-2.5">
                <Pagination :meta="products" />
            </div>
        </section>

        <ProductFormDialog
            v-model:open="modalOpen"
            :product="editing"
            :categories="categories"
            :units-master="unitsMaster"
            :suppliers="suppliers"
            @saved="onSaved"
        />
        <ProductManageDialog
            v-model:open="manageOpen"
            :product-id="manageProductId"
            :suppliers="suppliers"
            :can-update="canCreate"
        />
    </AppLayout>
</template>
