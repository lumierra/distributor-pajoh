<script setup>
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import {
    AlertCircle,
    Ban,
    CheckCircle2,
    Eye,
    FileSpreadsheet,
    LogIn,
    MapPinOff,
    Pencil,
    Plus,
    RotateCcw,
    Search,
    Store,
    Tags,
    Trash2,
} from '@lucide/vue';
import { computed, reactive, ref, watch } from 'vue';
import ActionButton from '@/Components/Shared/ActionButton.vue';
import ActionGroup from '@/Components/Shared/ActionGroup.vue';
import PageHeader from '@/Components/Shared/PageHeader.vue';
import Pagination from '@/Components/Shared/Pagination.vue';
import { confirm } from '@/Composables/useConfirm';
import CustomerFormDialog from '@/Components/Customers/CustomerFormDialog.vue';
import CustomerImportDialog from '@/Components/Customers/CustomerImportDialog.vue';
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
    customers: { type: Object, required: true },
    types: { type: Array, required: true },
    filters: { type: Object, default: () => ({}) },
    stats: { type: Object, default: null },
    hasImportErrors: { type: Boolean, default: false },
});

const page = usePage();
const canCreate = computed(
    () =>
        page.props.auth?.user?.is_superadmin ||
        page.props.permissions?.['master.customer']?.create,
);
const canDelete = computed(() => page.props.auth?.user?.is_superadmin);

const ALL = 'all';

const filters = reactive({
    q: props.filters.q ?? '',
    type_id: props.filters.type_id ? String(props.filters.type_id) : ALL,
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
            route('customers.index'),
            {
                q: filters.q,
                type_id: filters.type_id === ALL ? '' : filters.type_id,
                active: filters.active === ALL ? '' : filters.active,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }, 300);
});

function reset() {
    filters.q = '';
    filters.type_id = ALL;
    filters.active = ALL;
}

const modalOpen = ref(false);
const editing = ref(null);
const importOpen = ref(false);

function openCreate() {
    editing.value = null;
    modalOpen.value = true;
}

function openImport() {
    importOpen.value = true;
}

function onImported() {
    router.reload({ only: ['customers', 'stats', 'hasImportErrors'] });
}

function openEdit(c) {
    editing.value = c;
    modalOpen.value = true;
}

function onSaved() {
    router.reload({ only: ['customers', 'stats'] });
}

function toggleActive(c) {
    useForm({}).post(route('customers.toggle-active', c.id), { preserveScroll: true });
}

async function destroy(c) {
    if (!(await confirm({ title: `Hapus customer ${c.name}?`, destructive: true }))) return;
    useForm({}).delete(route('customers.destroy', c.id), { preserveScroll: true });
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
        { label: 'Total Customer', value: props.stats.total ?? 0, icon: Store },
        { label: 'Aktif', value: props.stats.active ?? 0, icon: CheckCircle2 },
        { label: 'Nonaktif', value: props.stats.inactive ?? 0, icon: Ban },
        { label: 'Tanpa Koordinat', value: props.stats.without_geo ?? 0, icon: MapPinOff },
    ];
});
</script>

<template>
    <Head title="Daftar Customer" />

    <AppLayout>
        <PageHeader
            title="Customer"
            description="Master outlet/toko. Tier harga, sales penanggung jawab, koordinat GPS."
            :icon="Store"
        >
            <template #actions>
                <Button as-child variant="outline" size="default" class="rounded-full">
                    <Link :href="route('customer-types.index')">
                        <Tags class="size-4" />
                        Tipe
                    </Link>
                </Button>
                <Button
                    v-if="hasImportErrors"
                    as-child
                    variant="outline"
                    size="default"
                    class="rounded-full text-amber-700 ring-1 ring-amber-200 hover:bg-amber-50"
                >
                    <a :href="route('customers.import.errors')">
                        <AlertCircle class="size-4" />
                        Download Error
                    </a>
                </Button>
                <Button v-if="canCreate" variant="outline" size="default" class="rounded-full" @click="openImport">
                    <FileSpreadsheet class="size-4" />
                    Import Excel
                </Button>
                <Button
                    v-if="canCreate"
                    size="default"
                    class="rounded-full bg-brand text-white hover:bg-brand-dark"
                    @click="openCreate"
                >
                    <Plus class="size-4" />
                    Tambah Customer
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

        <section class="rounded-2xl bg-card/70 backdrop-blur-xl ring-1 ring-foreground/6 shadow-sm overflow-hidden">
            <div class="px-4 py-3 flex flex-col sm:flex-row sm:items-center gap-2">
                <div class="relative flex-1">
                    <Search class="absolute left-3 top-1/2 -translate-y-1/2 size-3.5 text-muted-foreground" />
                    <Input
                        v-model="filters.q"
                        placeholder="Cari nama, kode, atau pemilik…"
                        class="pl-8 h-9 rounded-full bg-muted/50 border-transparent focus-visible:bg-card"
                    />
                </div>
                <div class="flex items-center gap-2 flex-wrap">
                    <Select v-model="filters.type_id">
                        <SelectTrigger class="w-[150px] h-9 rounded-full bg-muted/50 border-transparent">
                            <SelectValue placeholder="Tipe" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="ALL">Semua Tipe</SelectItem>
                            <SelectItem v-for="t in types" :key="t.id" :value="String(t.id)">
                                {{ t.name }}
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
                    <TableRow class="[&>th]:text-[11px] [&>th]:font-semibold [&>th]:uppercase [&>th]:tracking-wider [&>th]:text-muted-foreground [&>th]:py-2.5 hover:bg-transparent">
                        <TableHead class="pl-4">Customer</TableHead>
                        <TableHead>Tipe</TableHead>
                        <TableHead>Sales</TableHead>
                        <TableHead>Kota</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead class="text-right pr-4">Aksi</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody class="text-sm">
                    <TableRow v-if="customers.data.length === 0">
                        <TableCell colspan="7" class="text-center py-16">
                            <div class="flex flex-col items-center gap-2 text-muted-foreground">
                                <Store class="size-7 opacity-40" />
                                <p class="text-sm">Belum ada customer.</p>
                            </div>
                        </TableCell>
                    </TableRow>
                    <TableRow v-for="c in customers.data" :key="c.id" class="hover:bg-foreground/2.5 transition-colors border-foreground/5">
                        <TableCell class="pl-4 py-2.5">
                            <div class="flex items-center gap-3">
                                <div class="size-9 rounded-full bg-primary text-primary-foreground flex items-center justify-center shrink-0">
                                    <Store class="size-4" />
                                </div>
                                <div class="min-w-0">
                                    <Link
                                        :href="route('customers.show', c.id)"
                                        class="font-medium text-foreground truncate leading-tight hover:text-primary transition-colors block"
                                    >
                                        {{ c.name }}
                                    </Link>
                                    <p class="text-[12px] text-muted-foreground font-mono leading-tight">
                                        {{ c.code }}
                                    </p>
                                </div>
                            </div>
                        </TableCell>
                        <TableCell>
                            <span
                                v-if="c.type"
                                class="inline-flex items-center px-2.5 py-1 rounded-full text-[12px] font-medium bg-muted/70 text-muted-foreground"
                            >
                                {{ c.type.name }}
                            </span>
                            <span v-else class="text-muted-foreground">—</span>
                        </TableCell>
                        <TableCell class="text-xs">
                            <span v-if="c.assigned_sales">{{ c.assigned_sales.name }}</span>
                            <span v-else class="text-muted-foreground">—</span>
                        </TableCell>
                        <TableCell class="text-xs">
                            <span v-if="c.city">{{ c.city }}</span>
                            <span v-else class="text-muted-foreground">—</span>
                        </TableCell>
                        <TableCell>
                            <span
                                :class="[
                                    'inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[12px] font-medium',
                                    c.is_active ? 'text-emerald-700' : 'text-muted-foreground',
                                ]"
                            >
                                <span
                                    :class="[
                                        'size-1.5 rounded-full',
                                        c.is_active ? 'bg-emerald-500' : 'bg-muted-foreground/50',
                                    ]"
                                />
                                {{ c.is_active ? 'Aktif' : 'Nonaktif' }}
                            </span>
                        </TableCell>
                        <TableCell class="py-3 px-4 text-right whitespace-nowrap">
                            <div class="flex justify-end">
                                <ActionGroup class="rounded-full">
                                    <ActionButton :icon="Eye" label="Detail" as-child tone="brand">
                                        <Link :href="route('customers.show', c.id)">
                                            <Eye class="w-4 h-4" />
                                        </Link>
                                    </ActionButton>
                                    <ActionButton :icon="Pencil" label="Edit" tone="blue" @click="openEdit(c)" />
                                    <ActionButton
                                        v-if="c.is_active"
                                        :icon="Ban"
                                        label="Nonaktifkan"
                                        tone="amber"
                                        @click="toggleActive(c)"
                                    />
                                    <ActionButton
                                        v-else
                                        :icon="LogIn"
                                        label="Aktifkan"
                                        tone="emerald"
                                        @click="toggleActive(c)"
                                    />
                                    <ActionButton
                                        v-if="canDelete"
                                        :icon="Trash2"
                                        label="Hapus"
                                        tone="red"
                                        @click="destroy(c)"
                                    />
                                </ActionGroup>
                            </div>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>

            <div v-if="customers.data.length > 0" class="border-t border-foreground/5 px-4 py-3">
                <Pagination :meta="customers" />
            </div>
        </section>

        <CustomerFormDialog
            v-model:open="modalOpen"
            :customer="editing"
            :types="types"
            :sales-users="[]"
            :can-edit-credit-limit="false"
            :can-edit-assigned-sales="true"
            @saved="onSaved"
        />
        <CustomerImportDialog
            v-model:open="importOpen"
            @saved="onImported"
        />
    </AppLayout>
</template>
