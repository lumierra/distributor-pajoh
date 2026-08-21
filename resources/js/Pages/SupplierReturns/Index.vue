<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    CheckCircle2,
    Eye,
    Inbox,
    PackageOpen,
    Plus,
    RotateCcw,
    Search,
    Send,
} from '@lucide/vue';
import { computed, reactive, watch } from 'vue';
import ActionButton from '@/Components/Shared/ActionButton.vue';
import ActionGroup from '@/Components/Shared/ActionGroup.vue';
import PageHeader from '@/Components/Shared/PageHeader.vue';
import Pagination from '@/Components/Shared/Pagination.vue';
import SrStatusBadge from '@/Components/SupplierReturns/SrStatusBadge.vue';
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
    supplierReturns: { type: Object, required: true },
    suppliers: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    stats: { type: Object, default: null },
});

const page = usePage();
const canCreate = computed(
    () => page.props.auth?.user?.is_superadmin || page.props.permissions?.['returns.supplier']?.create,
);

const ALL = 'all';
const filters = reactive({
    q: props.filters.q ?? '',
    status: props.filters.status || ALL,
    supplier_id: props.filters.supplier_id || ALL,
});

let timer = null;
watch(filters, () => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get(
            route('supplier-returns.index'),
            {
                q: filters.q,
                status: filters.status === ALL ? '' : filters.status,
                supplier_id: filters.supplier_id === ALL ? '' : filters.supplier_id,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }, 300);
});

function reset() {
    filters.q = '';
    filters.status = ALL;
    filters.supplier_id = ALL;
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
    <Head title="Retur Supplier" />

    <AppLayout>
        <PageHeader title="Retur Supplier" description="Klaim barang rusak ke supplier — workflow: draft → approved → sent → settled." :icon="PackageOpen">
            <template #actions>
                <Button v-if="canCreate" as-child size="default" variant="secondary">
                    <Link :href="route('supplier-returns.create')">
                        <Plus class="size-4" /> Buat Retur
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <section v-if="stats" class="grid grid-cols-2 lg:grid-cols-5 gap-3 mb-4">
            <StatCard label="Total" :value="stats.total ?? 0" tone="brand">
                <template #icon><Inbox class="size-5" /></template>
            </StatCard>
            <StatCard label="Draft" :value="stats.draft ?? 0" tone="brand">
                <template #icon><Inbox class="size-5" /></template>
            </StatCard>
            <StatCard label="Approved" :value="stats.approved ?? 0" tone="brand-orange">
                <template #icon><CheckCircle2 class="size-5" /></template>
            </StatCard>
            <StatCard label="Sent" :value="stats.sent ?? 0" tone="brand-orange">
                <template #icon><Send class="size-5" /></template>
            </StatCard>
            <StatCard label="Settled" :value="stats.settled ?? 0" tone="brand">
                <template #icon><CheckCircle2 class="size-5" /></template>
            </StatCard>
        </section>

        <section class="rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm overflow-hidden">
            <div class="border-b border-border/70 px-3 py-2.5 flex flex-col sm:flex-row sm:items-center gap-2">
                <div class="relative flex-1">
                    <Search class="absolute left-2.5 top-1/2 -translate-y-1/2 size-3.5 text-muted-foreground" />
                    <Input v-model="filters.q" placeholder="Cari no retur, supplier…" class="pl-8 h-9 rounded-md" />
                </div>
                <div class="flex items-center gap-2 flex-wrap">
                    <Select v-model="filters.status">
                        <SelectTrigger class="w-[140px] h-9 rounded-md"><SelectValue placeholder="Status" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="ALL">Semua Status</SelectItem>
                            <SelectItem value="draft">Draft</SelectItem>
                            <SelectItem value="approved">Approved</SelectItem>
                            <SelectItem value="sent">Sent</SelectItem>
                            <SelectItem value="settled">Settled</SelectItem>
                            <SelectItem value="cancelled">Cancelled</SelectItem>
                        </SelectContent>
                    </Select>
                    <Select v-model="filters.supplier_id">
                        <SelectTrigger class="w-[180px] h-9 rounded-md"><SelectValue placeholder="Supplier" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="ALL">Semua Supplier</SelectItem>
                            <SelectItem v-for="s in suppliers" :key="s.id" :value="String(s.id)">{{ s.name }}</SelectItem>
                        </SelectContent>
                    </Select>
                    <Button type="button" variant="outline" size="default" @click="reset">
                        <RotateCcw class="size-3.5" /> Reset
                    </Button>
                </div>
            </div>

            <Table>
                <TableHeader>
                    <TableRow class="[&>th]:text-[11px] [&>th]:font-semibold [&>th]:uppercase [&>th]:tracking-wider [&>th]:text-muted-foreground [&>th]:py-2.5">
                        <TableHead class="pl-4">No. Retur</TableHead>
                        <TableHead>Supplier</TableHead>
                        <TableHead>Reason</TableHead>
                        <TableHead class="text-right">Claim</TableHead>
                        <TableHead class="text-right">Settled</TableHead>
                        <TableHead>Tgl</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead class="text-right pr-4">Aksi</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody class="text-sm">
                    <TableRow v-if="supplierReturns.data.length === 0">
                        <TableCell colspan="8" class="text-center py-16">
                            <div class="flex flex-col items-center gap-2 text-muted-foreground">
                                <PackageOpen class="size-7 opacity-40" />
                                <p class="text-sm">Belum ada retur supplier.</p>
                            </div>
                        </TableCell>
                    </TableRow>
                    <TableRow v-for="sr in supplierReturns.data" :key="sr.id" class="hover:bg-muted/30 transition-colors">
                        <TableCell class="pl-4 py-2.5 font-mono text-xs">
                            <Link :href="route('supplier-returns.show', sr.id)" class="hover:text-primary">
                                {{ sr.return_number }}
                            </Link>
                        </TableCell>
                        <TableCell>
                            <p class="font-medium">{{ sr.supplier?.name ?? '—' }}</p>
                            <p class="text-[12px] text-muted-foreground font-mono">{{ sr.supplier?.code }}</p>
                        </TableCell>
                        <TableCell class="text-xs">{{ sr.reason_code ?? '—' }}</TableCell>
                        <TableCell class="text-right font-mono">{{ fmtRp(sr.claim_amount) }}</TableCell>
                        <TableCell class="text-right font-mono">{{ sr.settled_amount ? fmtRp(sr.settled_amount) : '—' }}</TableCell>
                        <TableCell class="text-xs">{{ formatDate(sr.return_date) }}</TableCell>
                        <TableCell>
                            <SrStatusBadge :status="sr.status" />
                        </TableCell>
                        <TableCell class="py-3 px-4 text-right whitespace-nowrap">
                            <ActionGroup>
                                <ActionButton :icon="Eye" label="Detail" as-child tone="brand">
                                    <Link :href="route('supplier-returns.show', sr.id)">
                                        <Eye class="w-4 h-4" />
                                    </Link>
                                </ActionButton>
                            </ActionGroup>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>

            <div v-if="supplierReturns.data.length > 0" class="border-t border-border/70 px-4 py-2.5">
                <Pagination :meta="supplierReturns" />
            </div>
        </section>
    </AppLayout>
</template>
