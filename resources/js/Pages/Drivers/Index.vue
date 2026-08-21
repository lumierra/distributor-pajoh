<script setup>
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import {
    Ban,
    CheckCircle2,
    LogIn,
    Pause,
    Pencil,
    Plus,
    RotateCcw,
    Search,
    Trash2,
    Truck,
    UserCog,
} from '@lucide/vue';
import { computed, reactive, ref, watch } from 'vue';
import ActionButton from '@/Components/Shared/ActionButton.vue';
import ActionGroup from '@/Components/Shared/ActionGroup.vue';
import PageHeader from '@/Components/Shared/PageHeader.vue';
import Pagination from '@/Components/Shared/Pagination.vue';
import { confirm } from '@/Composables/useConfirm';
import DriverFormDialog from '@/Components/Drivers/DriverFormDialog.vue';
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
    drivers: { type: Object, required: true },
    vehicles: { type: Array, required: true },
    filters: { type: Object, default: () => ({}) },
    stats: { type: Object, default: null },
});

const page = usePage();
const canCreate = computed(
    () =>
        page.props.auth?.user?.is_superadmin ||
        page.props.permissions?.['master.driver']?.create,
);
const canDelete = computed(
    () =>
        page.props.auth?.user?.is_superadmin ||
        page.props.permissions?.['master.driver']?.delete,
);

const ALL = 'all';

const filters = reactive({
    q: props.filters.q ?? '',
    status: props.filters.status || ALL,
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
            route('drivers.index'),
            {
                q: filters.q,
                status: filters.status === ALL ? '' : filters.status,
                active: filters.active === ALL ? '' : filters.active,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }, 300);
});

function reset() {
    filters.q = '';
    filters.status = ALL;
    filters.active = ALL;
}

const modalOpen = ref(false);
const editing = ref(null);

function openCreate() {
    editing.value = null;
    modalOpen.value = true;
}

function openEdit(d) {
    editing.value = d;
    modalOpen.value = true;
}

function onSaved() {
    router.reload({ only: ['drivers', 'stats'] });
}

function toggleActive(d) {
    useForm({}).post(route('drivers.toggle-active', d.id), { preserveScroll: true });
}

function setUnavailable(d) {
    useForm({}).post(route('drivers.set-unavailable', d.id), { preserveScroll: true });
}

async function destroy(d) {
    if (!(await confirm({ title: `Hapus driver ${d.name}?`, destructive: true }))) return;
    useForm({}).delete(route('drivers.destroy', d.id), { preserveScroll: true });
}

function userInitials(name) {
    if (!name) return '?';
    const parts = String(name).trim().split(/\s+/);
    if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase();
    return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
}

function statusBadgeClass(s) {
    return {
        idle: 'bg-emerald-50 text-emerald-700',
        on_delivery: 'bg-blue-50 text-blue-700',
        unavailable: 'bg-amber-50 text-amber-800',
    }[s] ?? 'bg-muted/70 text-muted-foreground';
}

const statTiles = computed(() => {
    if (!props.stats) return [];
    return [
        { label: 'Total Driver', value: props.stats.total ?? 0, icon: UserCog },
        { label: 'Aktif', value: props.stats.active ?? 0, icon: CheckCircle2 },
        { label: 'Idle', value: props.stats.idle ?? 0, icon: Pause },
        { label: 'On Delivery', value: props.stats.on_delivery ?? 0, icon: Truck },
    ];
});

function statusLabel(s) {
    return {
        idle: 'Idle',
        on_delivery: 'Lagi antar',
        unavailable: 'Unavailable',
    }[s] ?? s;
}
</script>

<template>
    <Head title="Daftar Driver" />

    <AppLayout>
        <PageHeader
            title="Driver"
            description="Master supir untuk pengiriman. Bukan akun aplikasi."
            :icon="UserCog"
        >
            <template #actions>
                <Button
                    v-if="canCreate"
                    size="default"
                    class="rounded-full bg-brand text-white hover:bg-brand-dark"
                    @click="openCreate"
                >
                    <Plus class="size-4" />
                    Tambah Driver
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
                        placeholder="Cari nama, kode, atau no. HP…"
                        class="pl-8 h-9 rounded-full bg-muted/50 border-transparent focus-visible:bg-card"
                    />
                </div>
                <div class="flex items-center gap-2">
                    <Select v-model="filters.status">
                        <SelectTrigger class="w-[150px] h-9 rounded-full bg-muted/50 border-transparent">
                            <SelectValue placeholder="Status" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="ALL">Semua Status</SelectItem>
                            <SelectItem value="idle">Idle</SelectItem>
                            <SelectItem value="on_delivery">On Delivery</SelectItem>
                            <SelectItem value="unavailable">Unavailable</SelectItem>
                        </SelectContent>
                    </Select>
                    <Select v-model="filters.active">
                        <SelectTrigger class="w-[130px] h-9 rounded-full bg-muted/50 border-transparent">
                            <SelectValue placeholder="Aktif" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="ALL">Semua Aktif</SelectItem>
                            <SelectItem value="1">Aktif</SelectItem>
                            <SelectItem value="0">Nonaktif</SelectItem>
                        </SelectContent>
                    </Select>
                    <Button type="button" variant="ghost" size="default" class="rounded-full" @click="reset">
                        <RotateCcw class="size-3.5" /> Reset
                    </Button>
                </div>
            </div>

            <Table class="border-t border-foreground/5">
                <TableHeader>
                    <TableRow class="[&>th]:text-[11px] [&>th]:font-semibold [&>th]:uppercase [&>th]:tracking-wider [&>th]:text-muted-foreground [&>th]:py-2.5 hover:bg-transparent">
                        <TableHead class="pl-4">Driver</TableHead>
                        <TableHead>No. HP / WA</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead>Aktif</TableHead>
                        <TableHead class="text-center pr-4">Aksi</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody class="text-sm">
                    <TableRow v-if="drivers.data.length === 0">
                        <TableCell colspan="5" class="text-center py-16">
                            <div class="flex flex-col items-center gap-2 text-muted-foreground">
                                <UserCog class="size-7 opacity-40" />
                                <p class="text-sm">Belum ada driver.</p>
                            </div>
                        </TableCell>
                    </TableRow>
                    <TableRow v-for="d in drivers.data" :key="d.id" class="hover:bg-foreground/2.5 transition-colors border-foreground/5">
                        <TableCell class="pl-4 py-2.5">
                            <div class="flex items-center gap-3">
                                <div class="size-9 rounded-full bg-primary text-primary-foreground flex items-center justify-center text-[12px] font-semibold shadow-sm shrink-0">
                                    {{ userInitials(d.name) }}
                                </div>
                                <div class="min-w-0">
                                    <p class="font-medium text-foreground truncate leading-tight">{{ d.name }}</p>
                                    <p class="text-[12px] text-muted-foreground font-mono leading-tight">{{ d.code }}</p>
                                </div>
                            </div>
                        </TableCell>
                        <TableCell>
                            <span v-if="d.whatsapp" class="text-xs font-mono">{{ d.whatsapp }}</span>
                            <span v-else class="text-muted-foreground">—</span>
                        </TableCell>
                        <TableCell>
                            <span
                                :class="['inline-flex items-center px-2.5 py-1 rounded-full text-[12px] font-medium', statusBadgeClass(d.status)]"
                            >
                                {{ statusLabel(d.status) }}
                            </span>
                        </TableCell>
                        <TableCell>
                            <span
                                :class="[
                                    'inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[12px] font-medium',
                                    d.is_active ? 'text-emerald-700' : 'text-muted-foreground',
                                ]"
                            >
                                <span
                                    :class="[
                                        'size-1.5 rounded-full',
                                        d.is_active ? 'bg-emerald-500' : 'bg-muted-foreground/50',
                                    ]"
                                />
                                {{ d.is_active ? 'Aktif' : 'Nonaktif' }}
                            </span>
                        </TableCell>
                        <TableCell class="py-3 px-4 text-center whitespace-nowrap">
                            <div class="flex justify-center">
                                <ActionGroup class="rounded-full">
                                    <ActionButton :icon="Pencil" label="Edit" tone="blue" @click="openEdit(d)" />
                                    <ActionButton :icon="Pause" label="Idle / Unavailable" tone="amber" @click="setUnavailable(d)" />
                                    <ActionButton
                                        v-if="d.is_active"
                                        :icon="Ban"
                                        label="Nonaktifkan"
                                        tone="amber"
                                        @click="toggleActive(d)"
                                    />
                                    <ActionButton
                                        v-else
                                        :icon="LogIn"
                                        label="Aktifkan"
                                        tone="emerald"
                                        @click="toggleActive(d)"
                                    />
                                    <ActionButton
                                        v-if="canDelete"
                                        :icon="Trash2"
                                        label="Hapus"
                                        tone="red"
                                        @click="destroy(d)"
                                    />
                                </ActionGroup>
                            </div>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>

            <div v-if="drivers.data.length > 0" class="border-t border-foreground/5 px-4 py-3">
                <Pagination :meta="drivers" />
            </div>
        </section>

        <DriverFormDialog
            v-model:open="modalOpen"
            :driver="editing"
            :vehicles="vehicles"
            @saved="onSaved"
        />
    </AppLayout>
</template>
