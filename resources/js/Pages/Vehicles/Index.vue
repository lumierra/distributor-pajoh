<script setup>
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import {
    Ban,
    CheckCircle2,
    Eye,
    LogIn,
    Pause,
    Pencil,
    Plus,
    RotateCcw,
    Search,
    Truck,
    Wrench,
} from '@lucide/vue';
import { computed, reactive, ref, watch } from 'vue';
import ActionButton from '@/Components/Shared/ActionButton.vue';
import ActionGroup from '@/Components/Shared/ActionGroup.vue';
import PageHeader from '@/Components/Shared/PageHeader.vue';
import Pagination from '@/Components/Shared/Pagination.vue';
import StatCard from '@/Components/Shared/StatCard.vue';
import VehicleFormDialog from '@/Components/Vehicles/VehicleFormDialog.vue';
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
    vehicles: { type: Object, required: true },
    types: { type: Array, required: true },
    filters: { type: Object, default: () => ({}) },
    stats: { type: Object, default: null },
});

const page = usePage();
const canCreate = computed(
    () =>
        page.props.auth?.user?.is_superadmin ||
        page.props.permissions?.['master.vehicle']?.create,
);

const ALL = 'all';

const filters = reactive({
    q: props.filters.q ?? '',
    type: props.filters.type || ALL,
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
            route('vehicles.index'),
            {
                q: filters.q,
                type: filters.type === ALL ? '' : filters.type,
                status: filters.status === ALL ? '' : filters.status,
                active: filters.active === ALL ? '' : filters.active,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }, 300);
});

function reset() {
    filters.q = '';
    filters.type = ALL;
    filters.status = ALL;
    filters.active = ALL;
}

const modalOpen = ref(false);
const editing = ref(null);

function openCreate() {
    editing.value = null;
    modalOpen.value = true;
}

function openEdit(v) {
    editing.value = v;
    modalOpen.value = true;
}

function onSaved() {
    router.reload({ only: ['vehicles', 'stats'] });
}

function toggleActive(v) {
    useForm({}).post(route('vehicles.toggle-active', v.id), { preserveScroll: true });
}

function statusBadgeClass(s) {
    return {
        idle: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
        on_delivery: 'bg-blue-50 text-blue-700 ring-blue-200',
        maintenance: 'bg-amber-50 text-amber-800 ring-amber-200',
    }[s] ?? 'bg-muted text-muted-foreground';
}
</script>

<template>
    <Head title="Daftar Vehicle" />

    <AppLayout>
        <PageHeader
            title="Vehicle"
            description="Armada pengiriman. STNK & KIR wajib aktif untuk DO."
            :icon="Truck"
        >
            <template #actions>
                <Button v-if="canCreate" size="default" variant="secondary" @click="openCreate">
                    <Plus class="size-4" />
                    Tambah Vehicle
                </Button>
            </template>
        </PageHeader>

        <section v-if="stats" class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
            <StatCard label="Total" :value="stats.total ?? 0" tone="brand">
                <template #icon><Truck class="size-5" /></template>
            </StatCard>
            <StatCard label="Aktif" :value="stats.active ?? 0" tone="brand">
                <template #icon><CheckCircle2 class="size-5" /></template>
            </StatCard>
            <StatCard label="Idle" :value="stats.idle ?? 0" tone="brand">
                <template #icon><Pause class="size-5" /></template>
            </StatCard>
            <StatCard label="Maintenance" :value="stats.maintenance ?? 0" tone="brand-orange">
                <template #icon><Wrench class="size-5" /></template>
            </StatCard>
        </section>

        <section class="rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm overflow-hidden">
            <div class="border-b border-border/70 px-3 py-2.5 flex flex-col sm:flex-row sm:items-center gap-2">
                <div class="relative flex-1">
                    <Search class="absolute left-2.5 top-1/2 -translate-y-1/2 size-3.5 text-muted-foreground" />
                    <Input
                        v-model="filters.q"
                        placeholder="Cari plat, kode, brand, atau model…"
                        class="pl-8 h-9 rounded-md"
                    />
                </div>
                <div class="flex items-center gap-2 flex-wrap">
                    <Select v-model="filters.type">
                        <SelectTrigger class="w-[140px] h-9 rounded-md">
                            <SelectValue placeholder="Tipe" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="ALL">Semua Tipe</SelectItem>
                            <SelectItem v-for="t in types" :key="t" :value="t">{{ t }}</SelectItem>
                        </SelectContent>
                    </Select>
                    <Select v-model="filters.status">
                        <SelectTrigger class="w-[150px] h-9 rounded-md">
                            <SelectValue placeholder="Status" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="ALL">Semua Status</SelectItem>
                            <SelectItem value="idle">Idle</SelectItem>
                            <SelectItem value="on_delivery">On Delivery</SelectItem>
                            <SelectItem value="maintenance">Maintenance</SelectItem>
                        </SelectContent>
                    </Select>
                    <Select v-model="filters.active">
                        <SelectTrigger class="w-[130px] h-9 rounded-md">
                            <SelectValue placeholder="Aktif" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="ALL">Semua Aktif</SelectItem>
                            <SelectItem value="1">Aktif</SelectItem>
                            <SelectItem value="0">Nonaktif</SelectItem>
                        </SelectContent>
                    </Select>
                    <Button type="button" variant="outline" size="default" @click="reset">
                        <RotateCcw class="size-3.5" /> Reset
                    </Button>
                </div>
            </div>

            <Table>
                <TableHeader>
                    <TableRow class="[&>th]:text-[10px] [&>th]:font-semibold [&>th]:uppercase [&>th]:tracking-wider [&>th]:text-muted-foreground [&>th]:py-2.5">
                        <TableHead class="pl-4">Vehicle</TableHead>
                        <TableHead>Tipe</TableHead>
                        <TableHead>Kapasitas</TableHead>
                        <TableHead>Service Berikut</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead>Aktif</TableHead>
                        <TableHead class="text-right pr-4">Aksi</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody class="text-sm">
                    <TableRow v-if="vehicles.data.length === 0">
                        <TableCell colspan="7" class="text-center py-16">
                            <div class="flex flex-col items-center gap-2 text-muted-foreground">
                                <Truck class="size-7 opacity-40" />
                                <p class="text-sm">Belum ada vehicle.</p>
                            </div>
                        </TableCell>
                    </TableRow>
                    <TableRow v-for="v in vehicles.data" :key="v.id" class="hover:bg-muted/30 transition-colors">
                        <TableCell class="pl-4 py-2.5">
                            <div class="flex items-center gap-2.5">
                                <div class="size-8 rounded-md bg-primary/10 text-primary flex items-center justify-center shrink-0">
                                    <Truck class="size-4" />
                                </div>
                                <div class="min-w-0">
                                    <Link
                                        :href="route('vehicles.show', v.id)"
                                        class="font-medium text-foreground truncate leading-tight hover:text-primary transition-colors block font-mono"
                                    >
                                        {{ v.plate_number }}
                                    </Link>
                                    <p class="text-[11px] text-muted-foreground font-mono leading-tight">
                                        {{ v.code }}<span v-if="v.brand"> · {{ v.brand }} {{ v.model }}</span>
                                    </p>
                                </div>
                            </div>
                        </TableCell>
                        <TableCell>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-medium bg-muted text-muted-foreground">
                                {{ v.type }}
                            </span>
                        </TableCell>
                        <TableCell class="text-xs font-mono">
                            <span v-if="v.capacity_kg">{{ Number(v.capacity_kg).toLocaleString('id-ID') }} kg</span>
                            <span v-else class="text-muted-foreground">—</span>
                        </TableCell>
                        <TableCell class="text-xs">
                            <span v-if="v.next_service_date">{{ new Date(v.next_service_date).toLocaleDateString('id-ID') }}</span>
                            <span v-else class="text-muted-foreground">—</span>
                        </TableCell>
                        <TableCell>
                            <span
                                :class="['inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-medium ring-1', statusBadgeClass(v.status)]"
                            >
                                {{ v.status }}
                            </span>
                        </TableCell>
                        <TableCell>
                            <span
                                v-if="v.is_active"
                                class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-medium bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200"
                            >Aktif</span>
                            <span v-else class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-medium bg-muted text-muted-foreground">Nonaktif</span>
                        </TableCell>
                        <TableCell class="py-3 px-4 text-right whitespace-nowrap">
                            <ActionGroup>
                                <ActionButton :icon="Eye" label="Detail" as-child tone="brand">
                                    <Link :href="route('vehicles.show', v.id)">
                                        <Eye class="w-4 h-4" />
                                    </Link>
                                </ActionButton>
                                <ActionButton :icon="Pencil" label="Edit" tone="blue" @click="openEdit(v)" />
                                <ActionButton
                                    v-if="v.is_active"
                                    :icon="Ban"
                                    label="Nonaktifkan"
                                    tone="amber"
                                    @click="toggleActive(v)"
                                />
                                <ActionButton
                                    v-else
                                    :icon="LogIn"
                                    label="Aktifkan"
                                    tone="emerald"
                                    @click="toggleActive(v)"
                                />
                            </ActionGroup>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>

            <div v-if="vehicles.data.length > 0" class="border-t border-border/70 px-4 py-2.5">
                <Pagination :meta="vehicles" />
            </div>
        </section>

        <VehicleFormDialog
            v-model:open="modalOpen"
            :vehicle="editing"
            :types="types"
            @saved="onSaved"
        />
    </AppLayout>
</template>
