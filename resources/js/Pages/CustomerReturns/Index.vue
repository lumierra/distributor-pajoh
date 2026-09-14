<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    CheckCircle2,
    Eye,
    Inbox,
    PackageX,
    Plus,
    RotateCcw,
    Search,
} from '@lucide/vue';
import { computed, reactive, watch } from 'vue';
import ActionButton from '@/Components/Shared/ActionButton.vue';
import ActionGroup from '@/Components/Shared/ActionGroup.vue';
import CrStatusBadge from '@/Components/CustomerReturns/CrStatusBadge.vue';
import PageHeader from '@/Components/Shared/PageHeader.vue';
import Pagination from '@/Components/Shared/Pagination.vue';
import StatTile from '@/Components/Shared/StatTile.vue';
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
    customerReturns: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    stats: { type: Object, default: null },
});

const page = usePage();
const canCreate = computed(
    () => page.props.auth?.user?.is_superadmin || page.props.permissions?.['returns.customer']?.create,
);

const ALL = 'all';
const filters = reactive({
    q: props.filters.q ?? '',
    status: props.filters.status || ALL,
    brand_tag: props.filters.brand_tag ?? '',
});

let timer = null;
watch(filters, () => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get(
            route('customer-returns.index'),
            {
                q: filters.q,
                status: filters.status === ALL ? '' : filters.status,
                brand_tag: filters.brand_tag,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }, 300);
});

function reset() {
    filters.q = '';
    filters.status = ALL;
    filters.brand_tag = '';
}

function formatDate(v) {
    if (!v) return '—';
    return new Date(v).toLocaleString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
}

function fmtRp(v) {
    return 'Rp ' + new Intl.NumberFormat('id-ID').format(Number(v) || 0);
}
</script>

<template>
    <Head title="Retur Customer" />

    <AppLayout>
        <PageHeader title="Retur Customer" description="Retur barang customer → sortir BAIK/BS → posting → credit note." :icon="PackageX">
            <template #actions>
                <Button v-if="canCreate" as-child size="default" class="rounded-full bg-brand text-white hover:bg-brand-dark">
                    <Link :href="route('customer-returns.create')">
                        <Plus class="size-4" /> Buat Retur
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <section v-if="stats" class="grid grid-cols-2 lg:grid-cols-5 gap-3 mb-4">
            <StatTile label="Total" :value="stats.total ?? 0" :icon="Inbox" />
            <StatTile label="Draft" :value="stats.draft ?? 0" :icon="Inbox" />
            <StatTile label="Sorted" :value="stats.sorted ?? 0" :icon="Inbox" />
            <StatTile label="Posted" :value="stats.posted ?? 0" :icon="CheckCircle2" />
            <StatTile label="Credited" :value="stats.credited ?? 0" :icon="CheckCircle2" />
        </section>

        <section class="rounded-2xl bg-card/70 backdrop-blur-xl ring-1 ring-foreground/6 shadow-sm overflow-hidden">
            <div class="border-b border-foreground/5 px-3 py-2.5 flex flex-col sm:flex-row sm:items-center gap-2">
                <div class="relative flex-1">
                    <Search class="absolute left-2.5 top-1/2 -translate-y-1/2 size-3.5 text-muted-foreground" />
                    <Input v-model="filters.q" placeholder="Cari no retur, customer…" class="pl-8 h-9 rounded-full bg-muted/50 border-transparent focus-visible:bg-card" />
                </div>
                <div class="flex items-center gap-2 flex-wrap">
                    <Select v-model="filters.status">
                        <SelectTrigger class="w-[140px] h-9 rounded-full bg-muted/50 border-transparent"><SelectValue placeholder="Status" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="ALL">Semua Status</SelectItem>
                            <SelectItem value="draft">Draft</SelectItem>
                            <SelectItem value="sorted">Sorted</SelectItem>
                            <SelectItem value="posted">Posted</SelectItem>
                            <SelectItem value="credited">Credited</SelectItem>
                            <SelectItem value="cancelled">Cancelled</SelectItem>
                        </SelectContent>
                    </Select>
                    <Input v-model="filters.brand_tag" placeholder="Brand" class="w-[140px] h-9 rounded-full bg-muted/50 border-transparent focus-visible:bg-card" />
                    <Button type="button" variant="ghost" size="default" class="rounded-full" @click="reset">
                        <RotateCcw class="size-3.5" /> Reset
                    </Button>
                </div>
            </div>

            <Table>
                <TableHeader>
                    <TableRow class="[&>th]:text-[11px] [&>th]:font-semibold [&>th]:uppercase [&>th]:tracking-wider [&>th]:text-muted-foreground [&>th]:py-2.5">
                        <TableHead class="pl-4">No. Retur</TableHead>
                        <TableHead>Customer</TableHead>
                        <TableHead>Invoice</TableHead>
                        <TableHead>Brand</TableHead>
                        <TableHead class="text-right">Nilai</TableHead>
                        <TableHead>Tgl</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead class="text-right pr-4">Aksi</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody class="text-sm">
                    <TableRow v-if="customerReturns.data.length === 0">
                        <TableCell colspan="8" class="text-center py-16">
                            <div class="flex flex-col items-center gap-2 text-muted-foreground">
                                <PackageX class="size-7 opacity-40" />
                                <p class="text-sm">Belum ada retur customer.</p>
                            </div>
                        </TableCell>
                    </TableRow>
                    <TableRow v-for="cr in customerReturns.data" :key="cr.id" class="hover:bg-foreground/2.5 border-foreground/5 transition-colors">
                        <TableCell class="pl-4 py-2.5 font-mono text-xs">
                            <Link :href="route('customer-returns.show', cr.id)" class="hover:text-primary">
                                {{ cr.return_number }}
                            </Link>
                        </TableCell>
                        <TableCell>
                            <p class="font-medium">{{ cr.customer?.name ?? '—' }}</p>
                            <p class="text-[12px] text-muted-foreground font-mono">{{ cr.customer?.code }}</p>
                        </TableCell>
                        <TableCell class="font-mono text-xs">{{ cr.invoice?.invoice_number ?? '—' }}</TableCell>
                        <TableCell class="text-xs">{{ cr.brand_tag ?? '—' }}</TableCell>
                        <TableCell class="text-right font-mono">{{ fmtRp(cr.total_value) }}</TableCell>
                        <TableCell class="text-xs">{{ formatDate(cr.return_date) }}</TableCell>
                        <TableCell>
                            <CrStatusBadge :status="cr.status" />
                        </TableCell>
                        <TableCell class="py-3 px-4 text-right whitespace-nowrap">
                            <ActionGroup>
                                <ActionButton :icon="Eye" label="Detail" as-child tone="brand">
                                    <Link :href="route('customer-returns.show', cr.id)">
                                        <Eye class="w-4 h-4" />
                                    </Link>
                                </ActionButton>
                            </ActionGroup>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>

            <div v-if="customerReturns.data.length > 0" class="border-t border-foreground/5 px-4 py-2.5">
                <Pagination :meta="customerReturns" />
            </div>
        </section>
    </AppLayout>
</template>
