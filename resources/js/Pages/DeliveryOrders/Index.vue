<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    CheckCircle2,
    ClipboardList,
    Eye,
    FileText,
    Package,
    PackageCheck,
    Plus,
    RotateCcw,
    Search,
    Truck,
} from '@lucide/vue';
import { computed, reactive, watch } from 'vue';
import ActionButton from '@/Components/Shared/ActionButton.vue';
import ActionGroup from '@/Components/Shared/ActionGroup.vue';
import DoStatusBadge from '@/Components/DeliveryOrders/DoStatusBadge.vue';
import PageHeader from '@/Components/Shared/PageHeader.vue';
import Pagination from '@/Components/Shared/Pagination.vue';
import StatCard from '@/Components/Shared/StatCard.vue';
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
    deliveryOrders: { type: Object, required: true },
    drivers: { type: Array, required: true },
    vehicles: { type: Array, required: true },
    filters: { type: Object, default: () => ({}) },
    stats: { type: Object, default: null },
});

const page = usePage();
const canCreate = computed(
    () =>
        page.props.auth?.user?.is_superadmin ||
        page.props.permissions?.['sales.do']?.create,
);

const ALL = 'all';
const filters = reactive({
    q: props.filters.q ?? '',
    status: props.filters.status || ALL,
    driver_id: props.filters.driver_id ? String(props.filters.driver_id) : ALL,
    vehicle_id: props.filters.vehicle_id ? String(props.filters.vehicle_id) : ALL,
});

let timer = null;
watch(filters, () => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get(
            route('delivery-orders.index'),
            {
                q: filters.q,
                status: filters.status === ALL ? '' : filters.status,
                driver_id: filters.driver_id === ALL ? '' : filters.driver_id,
                vehicle_id: filters.vehicle_id === ALL ? '' : filters.vehicle_id,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }, 300);
});

function reset() {
    filters.q = '';
    filters.status = ALL;
    filters.driver_id = ALL;
    filters.vehicle_id = ALL;
}

function formatDate(value) {
    if (!value) return '—';
    return new Date(value).toLocaleString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
}
</script>

<template>
    <Head title="Surat Jalan (DO)" />

    <AppLayout>
        <PageHeader title="Surat Jalan" description="Delivery Order — pengiriman barang dari SO approved ke customer." :icon="Truck">
            <template #actions>
                <Button v-if="canCreate" as-child size="default" variant="secondary">
                    <Link :href="route('delivery-orders.create')">
                        <Plus class="size-4" /> Buat DO
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <section v-if="stats" class="grid grid-cols-2 lg:grid-cols-6 gap-3 mb-4">
            <StatCard label="Total DO" :value="stats.total ?? 0" tone="brand">
                <template #icon><Truck class="size-5" /></template>
            </StatCard>
            <StatCard label="Draft" :value="stats.draft ?? 0" tone="brand">
                <template #icon><FileText class="size-5" /></template>
            </StatCard>
            <StatCard label="Picking" :value="stats.picking ?? 0" tone="brand">
                <template #icon><ClipboardList class="size-5" /></template>
            </StatCard>
            <StatCard label="Packed" :value="stats.packed ?? 0" tone="brand">
                <template #icon><Package class="size-5" /></template>
            </StatCard>
            <StatCard label="In Transit" :value="stats.in_transit ?? 0" tone="brand-orange">
                <template #icon><Truck class="size-5" /></template>
            </StatCard>
            <StatCard label="Delivered" :value="stats.delivered ?? 0" tone="brand">
                <template #icon><CheckCircle2 class="size-5" /></template>
            </StatCard>
        </section>

        <section class="rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm overflow-hidden">
            <div class="border-b border-border/70 px-3 py-2.5 flex flex-col sm:flex-row sm:items-center gap-2">
                <div class="relative flex-1">
                    <Search class="absolute left-2.5 top-1/2 -translate-y-1/2 size-3.5 text-muted-foreground" />
                    <Input v-model="filters.q" placeholder="Cari no DO, SO, atau customer…" class="pl-8 h-9 rounded-md" />
                </div>
                <div class="flex items-center gap-2 flex-wrap">
                    <Select v-model="filters.status">
                        <SelectTrigger class="w-[150px] h-9 rounded-md">
                            <SelectValue placeholder="Status" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="ALL">Semua Status</SelectItem>
                            <SelectItem value="draft">Draft</SelectItem>
                            <SelectItem value="picking">Picking</SelectItem>
                            <SelectItem value="packed">Packed</SelectItem>
                            <SelectItem value="in_transit">In Transit</SelectItem>
                            <SelectItem value="delivered">Delivered</SelectItem>
                            <SelectItem value="partial_returned">Partial Return</SelectItem>
                            <SelectItem value="cancelled">Cancelled</SelectItem>
                        </SelectContent>
                    </Select>
                    <Select v-model="filters.driver_id">
                        <SelectTrigger class="w-[160px] h-9 rounded-md">
                            <SelectValue placeholder="Driver" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="ALL">Semua Driver</SelectItem>
                            <SelectItem v-for="d in drivers" :key="d.id" :value="String(d.id)">
                                {{ d.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <Select v-model="filters.vehicle_id">
                        <SelectTrigger class="w-[160px] h-9 rounded-md">
                            <SelectValue placeholder="Vehicle" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="ALL">Semua Vehicle</SelectItem>
                            <SelectItem v-for="v in vehicles" :key="v.id" :value="String(v.id)">
                                {{ v.plate_number }}
                            </SelectItem>
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
                        <TableHead class="pl-4">DO</TableHead>
                        <TableHead>SO</TableHead>
                        <TableHead>Customer</TableHead>
                        <TableHead>Tgl DO</TableHead>
                        <TableHead>Driver / Vehicle</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead class="text-right pr-4">Aksi</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody class="text-sm">
                    <TableRow v-if="deliveryOrders.data.length === 0">
                        <TableCell colspan="7" class="text-center py-16">
                            <div class="flex flex-col items-center gap-2 text-muted-foreground">
                                <Truck class="size-7 opacity-40" />
                                <p class="text-sm">Belum ada DO.</p>
                            </div>
                        </TableCell>
                    </TableRow>
                    <TableRow v-for="do_ in deliveryOrders.data" :key="do_.id" class="hover:bg-muted/30 transition-colors">
                        <TableCell class="pl-4 py-2.5">
                            <div class="flex items-center gap-2.5">
                                <div class="size-8 rounded-md bg-primary/10 text-primary flex items-center justify-center shrink-0">
                                    <Truck class="size-4" />
                                </div>
                                <Link :href="route('delivery-orders.show', do_.id)" class="font-medium text-foreground font-mono hover:text-primary transition-colors">
                                    {{ do_.do_number }}
                                </Link>
                            </div>
                        </TableCell>
                        <TableCell class="font-mono text-xs">
                            <Link :href="route('sales-orders.show', do_.sales_order_id)" class="hover:text-primary">
                                {{ do_.sales_order?.so_number }}
                            </Link>
                        </TableCell>
                        <TableCell>
                            <p class="font-medium">{{ do_.customer?.name ?? '—' }}</p>
                            <p class="text-[11px] text-muted-foreground font-mono">{{ do_.customer?.code }}</p>
                        </TableCell>
                        <TableCell class="text-xs">{{ formatDate(do_.do_date) }}</TableCell>
                        <TableCell class="text-xs">
                            <p v-if="do_.driver">{{ do_.driver.name }}</p>
                            <p v-if="do_.vehicle" class="font-mono text-muted-foreground">{{ do_.vehicle.plate_number }}</p>
                            <span v-if="!do_.driver && !do_.vehicle" class="text-muted-foreground">—</span>
                        </TableCell>
                        <TableCell>
                            <DoStatusBadge :status="do_.status" />
                        </TableCell>
                        <TableCell class="py-3 px-4 text-right whitespace-nowrap">
                            <ActionGroup>
                                <ActionButton :icon="Eye" label="Detail" as-child tone="brand">
                                    <Link :href="route('delivery-orders.show', do_.id)">
                                        <Eye class="w-4 h-4" />
                                    </Link>
                                </ActionButton>
                                <ActionButton v-if="do_.pdf_path" :icon="FileText" label="Surat Jalan" as-child tone="blue">
                                    <a :href="route('delivery-orders.pdf', do_.id)" target="_blank" rel="noopener">
                                        <FileText class="w-4 h-4" />
                                    </a>
                                </ActionButton>
                            </ActionGroup>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>

            <div v-if="deliveryOrders.data.length > 0" class="border-t border-border/70 px-4 py-2.5">
                <Pagination :meta="deliveryOrders" />
            </div>
        </section>
    </AppLayout>
</template>
