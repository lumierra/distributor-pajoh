<script setup>
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import {
    Ban,
    Building2,
    CheckCircle2,
    Eye,
    Factory,
    Layers,
    LogIn,
    Pencil,
    Plus,
    RotateCcw,
    Search,
    Trash2,
} from '@lucide/vue';
import { computed, reactive, ref, watch } from 'vue';
import ActionButton from '@/Components/Shared/ActionButton.vue';
import ActionGroup from '@/Components/Shared/ActionGroup.vue';
import PageHeader from '@/Components/Shared/PageHeader.vue';
import Pagination from '@/Components/Shared/Pagination.vue';
import SupplierFormDialog from '@/Components/Suppliers/SupplierFormDialog.vue';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/Components/ui/alert-dialog';
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
    suppliers: { type: Object, required: true },
    categories: { type: Array, required: true },
    filters: { type: Object, default: () => ({}) },
    stats: { type: Object, default: null },
});

const page = usePage();
const canCreate = computed(
    () =>
        page.props.auth?.user?.is_superadmin ||
        page.props.permissions?.['master.supplier']?.create,
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
            route('suppliers.index'),
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

// Modal state
const modalOpen = ref(false);
const editing = ref(null);

function openCreate() {
    editing.value = null;
    modalOpen.value = true;
}

function openEdit(s) {
    editing.value = s;
    modalOpen.value = true;
}

function onSaved() {
    router.reload({ only: ['suppliers', 'stats'] });
}

function toggleActive(s) {
    useForm({}).post(route('suppliers.toggle-active', s.id), { preserveScroll: true });
}

// Konfirmasi hapus — pakai AlertDialog terkontrol, bukan window.confirm bawaan browser.
const deleteTarget = ref(null);
const deleteOpen = ref(false);

function askDestroy(s) {
    deleteTarget.value = s;
    deleteOpen.value = true;
}

function confirmDestroy() {
    if (!deleteTarget.value) return;
    useForm({}).delete(route('suppliers.destroy', deleteTarget.value.id), { preserveScroll: true });
}

function formatDate(value) {
    if (!value) return '—';
    return new Date(value).toLocaleString('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
}

const statTiles = computed(() => {
    if (!props.stats) return [];
    return [
        { label: 'Total Supplier', value: props.stats.total ?? 0, icon: Factory },
        { label: 'Aktif', value: props.stats.active ?? 0, icon: CheckCircle2 },
        { label: 'Nonaktif', value: props.stats.inactive ?? 0, icon: Ban },
        { label: 'Kategori', value: props.stats.categories ?? 0, icon: Layers },
    ];
});
</script>

<template>
    <Head title="Daftar Supplier" />

    <AppLayout>
        <PageHeader
            title="Supplier"
            description="Master data supplier — bank account, dokumen, dan riwayat transaksi."
            :icon="Factory"
        >
            <template #actions>
                <Button as-child variant="outline" size="default" class="rounded-full">
                    <Link :href="route('supplier-categories.index')">
                        <Layers class="size-4" />
                        Kategori
                    </Link>
                </Button>
                <Button
                    v-if="canCreate"
                    size="default"
                    class="rounded-full bg-brand text-white hover:bg-brand-dark"
                    @click="openCreate"
                >
                    <Plus class="size-4" />
                    Tambah Supplier
                </Button>
            </template>
        </PageHeader>

        <!-- ── Stat tiles — soft red tint, angka+label kiri, ikon kanan ── -->
        <section v-if="stats" class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
            <div
                v-for="tile in statTiles"
                :key="tile.label"
                class="rounded-2xl bg-brand-light/60 ring-1 ring-brand/10 shadow-sm px-4 py-3.5 flex items-center justify-between gap-3 transition-all duration-200 hover:ring-brand/25 hover:shadow-md hover:-translate-y-0.5"
            >
                <div class="min-w-0">
                    <p class="text-lg font-semibold tracking-tight leading-tight text-brand-dark">
                        {{ tile.value }}
                    </p>
                    <p class="text-[12px] text-brand-dark/70 truncate leading-tight mt-0.5">
                        {{ tile.label }}
                    </p>
                </div>
                <div
                    class="size-9 rounded-full bg-brand/10 text-brand flex items-center justify-center shrink-0"
                >
                    <component :is="tile.icon" class="size-4.5" />
                </div>
            </div>
        </section>

        <!-- Filter toolbar + Table -->
        <section class="rounded-2xl bg-card/70 backdrop-blur-xl ring-1 ring-foreground/6 shadow-sm overflow-hidden">
            <div class="px-4 py-3 flex flex-col sm:flex-row sm:items-center gap-2">
                <div class="relative flex-1">
                    <Search
                        class="absolute left-3 top-1/2 -translate-y-1/2 size-3.5 text-muted-foreground"
                    />
                    <Input
                        v-model="filters.q"
                        placeholder="Cari nama, kode, atau NPWP…"
                        class="pl-8 h-9 rounded-full bg-muted/50 border-transparent focus-visible:bg-card"
                    />
                </div>
                <div class="flex items-center gap-2">
                    <Select v-model="filters.category">
                        <SelectTrigger class="w-[160px] h-9 rounded-full bg-muted/50 border-transparent">
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
                        <SelectTrigger class="w-[130px] h-9 rounded-full bg-muted/50 border-transparent">
                            <SelectValue placeholder="Status" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="ALL">Semua Status</SelectItem>
                            <SelectItem value="1">Aktif</SelectItem>
                            <SelectItem value="0">Nonaktif</SelectItem>
                        </SelectContent>
                    </Select>
                    <Button
                        type="button"
                        variant="ghost"
                        size="default"
                        class="rounded-full"
                        @click="reset"
                    >
                        <RotateCcw class="size-3.5" />
                        Reset
                    </Button>
                </div>
            </div>

            <Table class="border-t border-foreground/5">
                <TableHeader>
                    <TableRow
                        class="[&>th]:text-[11px] [&>th]:font-semibold [&>th]:uppercase [&>th]:tracking-wider [&>th]:text-muted-foreground [&>th]:py-2.5 hover:bg-transparent"
                    >
                        <TableHead class="pl-4">Supplier</TableHead>
                        <TableHead>Kategori</TableHead>
                        <TableHead>PIC</TableHead>
                        <TableHead>Phone</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead>Dibuat</TableHead>
                        <TableHead class="text-right pr-4">Aksi</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody class="text-sm">
                    <TableRow v-if="suppliers.data.length === 0">
                        <TableCell colspan="7" class="text-center py-16">
                            <div class="flex flex-col items-center gap-2 text-muted-foreground">
                                <Factory class="size-7 opacity-40" />
                                <p class="text-sm">Belum ada supplier.</p>
                            </div>
                        </TableCell>
                    </TableRow>
                    <TableRow
                        v-for="s in suppliers.data"
                        :key="s.id"
                        class="hover:bg-foreground/2.5 transition-colors border-foreground/5"
                    >
                        <TableCell class="pl-4 py-2.5">
                            <div class="flex items-center gap-3">
                                <div
                                    class="size-9 rounded-full bg-primary text-primary-foreground flex items-center justify-center shrink-0"
                                >
                                    <Factory class="size-4" />
                                </div>
                                <div class="min-w-0">
                                    <Link
                                        :href="route('suppliers.show', s.id)"
                                        class="font-medium text-foreground truncate leading-tight hover:text-primary transition-colors block"
                                    >
                                        {{ s.name }}
                                    </Link>
                                    <p class="text-[12px] text-muted-foreground font-mono leading-tight">
                                        {{ s.code }}
                                    </p>
                                </div>
                            </div>
                        </TableCell>
                        <TableCell>
                            <span
                                v-if="s.category"
                                class="inline-flex items-center px-2.5 py-1 rounded-full text-[12px] font-medium bg-muted/70 text-muted-foreground"
                            >
                                {{ s.category.name }}
                            </span>
                            <span v-else class="text-muted-foreground">—</span>
                        </TableCell>
                        <TableCell class="text-xs">
                            <span v-if="s.contact_person_name">{{ s.contact_person_name }}</span>
                            <span v-else class="text-muted-foreground">—</span>
                        </TableCell>
                        <TableCell>
                            <span v-if="s.phone" class="text-xs font-mono">{{ s.phone }}</span>
                            <span v-else class="text-muted-foreground">—</span>
                        </TableCell>
                        <TableCell>
                            <span
                                :class="[
                                    'inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[12px] font-medium',
                                    s.is_active ? 'text-emerald-700' : 'text-muted-foreground',
                                ]"
                            >
                                <span
                                    :class="[
                                        'size-1.5 rounded-full',
                                        s.is_active ? 'bg-emerald-500' : 'bg-muted-foreground/50',
                                    ]"
                                />
                                {{ s.is_active ? 'Aktif' : 'Nonaktif' }}
                            </span>
                        </TableCell>
                        <TableCell class="text-xs text-muted-foreground whitespace-nowrap">
                            {{ formatDate(s.created_at) }}
                        </TableCell>
                        <TableCell class="py-3 px-4 text-right whitespace-nowrap">
                            <div class="flex justify-end">
                                <ActionGroup class="rounded-full">
                                    <ActionButton :icon="Eye" label="Detail" as-child tone="brand">
                                        <Link :href="route('suppliers.show', s.id)">
                                            <Eye class="w-4 h-4" />
                                        </Link>
                                    </ActionButton>
                                    <ActionButton
                                        :icon="Pencil"
                                        label="Edit"
                                        tone="blue"
                                        @click="openEdit(s)"
                                    />
                                    <ActionButton
                                        v-if="s.is_active"
                                        :icon="Ban"
                                        label="Nonaktifkan"
                                        tone="amber"
                                        @click="toggleActive(s)"
                                    />
                                    <ActionButton
                                        v-else
                                        :icon="LogIn"
                                        label="Aktifkan"
                                        tone="emerald"
                                        @click="toggleActive(s)"
                                    />
                                    <ActionButton
                                        :icon="Trash2"
                                        label="Hapus"
                                        tone="red"
                                        @click="askDestroy(s)"
                                    />
                                </ActionGroup>
                            </div>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>

            <div v-if="suppliers.data.length > 0" class="border-t border-foreground/5 px-4 py-3">
                <Pagination :meta="suppliers" />
            </div>
        </section>

        <SupplierFormDialog
            v-model:open="modalOpen"
            :supplier="editing"
            :categories="categories"
            @saved="onSaved"
        />

        <!-- Konfirmasi hapus supplier -->
        <AlertDialog v-model:open="deleteOpen">
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>Hapus supplier {{ deleteTarget?.name }}?</AlertDialogTitle>
                    <AlertDialogDescription>
                        Supplier akan dihapus. Tindakan ini tidak bisa dibatalkan.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel>Batal</AlertDialogCancel>
                    <AlertDialogAction
                        class="bg-destructive/15 text-destructive hover:bg-destructive/25"
                        @click="confirmDestroy"
                    >
                        Hapus
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    </AppLayout>
</template>
