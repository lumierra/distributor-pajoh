<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { Eye, FileMinus, RotateCcw, Search } from '@lucide/vue';
import { reactive, watch } from 'vue';
import CnStatusBadge from '@/Components/CreditNotes/CnStatusBadge.vue';
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
    creditNotes: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    stats: { type: Object, default: null },
});

const ALL = 'all';
const filters = reactive({
    q: props.filters.q ?? '',
    status: props.filters.status || ALL,
});

let timer = null;
watch(filters, () => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get(
            route('credit-notes.index'),
            {
                q: filters.q,
                status: filters.status === ALL ? '' : filters.status,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }, 300);
});

function reset() {
    filters.q = '';
    filters.status = ALL;
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
    <Head title="Credit Notes" />

    <AppLayout>
        <PageHeader title="Credit Notes" description="Credit Note hasil retur customer. Multi-apply ke invoice." :icon="FileMinus" />

        <section v-if="stats" class="grid grid-cols-2 lg:grid-cols-3 gap-3 mb-4">
            <StatCard label="Total CN" :value="stats.total ?? 0" tone="brand">
                <template #icon><FileMinus class="size-5" /></template>
            </StatCard>
            <StatCard label="Total Amount" :value="fmtRp(stats.total_amount)" tone="brand" />
            <StatCard label="Total Remaining" :value="fmtRp(stats.total_remaining)" tone="brand-orange" />
        </section>

        <section class="rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm overflow-hidden">
            <div class="border-b border-border/70 px-3 py-2.5 flex flex-col sm:flex-row sm:items-center gap-2">
                <div class="relative flex-1">
                    <Search class="absolute left-2.5 top-1/2 -translate-y-1/2 size-3.5 text-muted-foreground" />
                    <Input v-model="filters.q" placeholder="Cari no CN, customer…" class="pl-8 h-9 rounded-md" />
                </div>
                <div class="flex items-center gap-2">
                    <Select v-model="filters.status">
                        <SelectTrigger class="w-[160px] h-9 rounded-md"><SelectValue placeholder="Status" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="ALL">Semua</SelectItem>
                            <SelectItem value="open">Open</SelectItem>
                            <SelectItem value="applied">Partially Applied</SelectItem>
                            <SelectItem value="closed">Closed</SelectItem>
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
                        <TableHead class="pl-4">No. CN</TableHead>
                        <TableHead>Customer</TableHead>
                        <TableHead>CR Source</TableHead>
                        <TableHead>Tgl</TableHead>
                        <TableHead class="text-right">Amount</TableHead>
                        <TableHead class="text-right">Applied</TableHead>
                        <TableHead class="text-right">Remaining</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead class="text-right pr-4">Aksi</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody class="text-sm">
                    <TableRow v-if="creditNotes.data.length === 0">
                        <TableCell colspan="9" class="text-center py-16">
                            <div class="flex flex-col items-center gap-2 text-muted-foreground">
                                <FileMinus class="size-7 opacity-40" />
                                <p class="text-sm">Belum ada credit note.</p>
                            </div>
                        </TableCell>
                    </TableRow>
                    <TableRow v-for="cn in creditNotes.data" :key="cn.id" class="hover:bg-muted/30 transition-colors">
                        <TableCell class="pl-4 py-2.5 font-mono text-xs">
                            <Link :href="route('credit-notes.show', cn.id)" class="hover:text-primary">
                                {{ cn.cn_number }}
                            </Link>
                        </TableCell>
                        <TableCell>
                            <p class="font-medium">{{ cn.customer?.name ?? '—' }}</p>
                            <p class="text-[12px] text-muted-foreground font-mono">{{ cn.customer?.code }}</p>
                        </TableCell>
                        <TableCell class="font-mono text-xs">{{ cn.customer_return?.return_number ?? '—' }}</TableCell>
                        <TableCell class="text-xs">{{ formatDate(cn.cn_date) }}</TableCell>
                        <TableCell class="text-right font-mono">{{ fmtRp(cn.amount) }}</TableCell>
                        <TableCell class="text-right font-mono">{{ fmtRp(cn.applied_amount) }}</TableCell>
                        <TableCell class="text-right font-mono font-semibold">{{ fmtRp(cn.remaining_amount) }}</TableCell>
                        <TableCell>
                            <CnStatusBadge :status="cn.status" />
                        </TableCell>
                        <TableCell class="py-3 px-4 text-right whitespace-nowrap">
                            <Button as-child size="sm" variant="outline" class="h-7 px-2">
                                <Link :href="route('credit-notes.show', cn.id)">
                                    <Eye class="w-3.5 h-3.5" />
                                </Link>
                            </Button>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>

            <div v-if="creditNotes.data.length > 0" class="border-t border-border/70 px-4 py-2.5">
                <Pagination :meta="creditNotes" />
            </div>
        </section>
    </AppLayout>
</template>
