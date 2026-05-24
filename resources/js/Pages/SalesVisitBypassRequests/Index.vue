<script setup>
import { Head, router } from '@inertiajs/vue3';
import { CheckCircle2, ShieldOff, XCircle } from '@lucide/vue';
import { reactive, watch } from 'vue';
import PageHeader from '@/Components/Shared/PageHeader.vue';
import Pagination from '@/Components/Shared/Pagination.vue';
import { Button } from '@/Components/ui/button';
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
    requests: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
});

const ALL = 'all';
const filters = reactive({
    status: props.filters.status || ALL,
});

let timer = null;
watch(filters, () => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get(route('sales-visit-bypass-requests.index'), {
            status: filters.status === ALL ? '' : filters.status,
        }, { preserveState: true, preserveScroll: true, replace: true });
    }, 300);
});

function approve(id) {
    router.post(route('sales-visit-bypass-requests.approve', id), {}, { preserveScroll: true });
}

function reject(id) {
    if (!confirm('Reject bypass ini?')) return;
    router.post(route('sales-visit-bypass-requests.reject', id), { notes: '' }, { preserveScroll: true });
}

function fmt(v) {
    if (!v) return '—';
    return new Date(v).toLocaleString('id-ID');
}

function statusBadge(s) {
    return {
        pending: 'bg-amber-50 text-amber-800 ring-amber-200',
        approved: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
        rejected: 'bg-red-50 text-red-700 ring-red-200',
        expired: 'bg-muted text-muted-foreground ring-border',
    }[s] ?? 'bg-muted text-muted-foreground';
}
</script>

<template>
    <Head title="Bypass Requests" />

    <AppLayout>
        <PageHeader title="Geofence Bypass Requests" description="Sales request bypass radius outlet (valid 30 menit setelah approve)." :icon="ShieldOff" />

        <section class="rounded-lg bg-card ring-1 ring-foreground/5 shadow-sm overflow-hidden">
            <div class="border-b border-border/70 px-3 py-2.5 flex items-center gap-2">
                <Select v-model="filters.status">
                    <SelectTrigger class="w-[160px] h-9"><SelectValue placeholder="Status" /></SelectTrigger>
                    <SelectContent>
                        <SelectItem :value="ALL">Semua</SelectItem>
                        <SelectItem value="pending">Pending</SelectItem>
                        <SelectItem value="approved">Approved</SelectItem>
                        <SelectItem value="rejected">Rejected</SelectItem>
                        <SelectItem value="expired">Expired</SelectItem>
                    </SelectContent>
                </Select>
            </div>
            <Table>
                <TableHeader>
                    <TableRow class="[&>th]:text-[10px] [&>th]:uppercase [&>th]:text-muted-foreground [&>th]:py-2.5">
                        <TableHead class="pl-4">Tgl</TableHead>
                        <TableHead>Sales</TableHead>
                        <TableHead>Customer</TableHead>
                        <TableHead>Reason</TableHead>
                        <TableHead>Distance</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead>Expires</TableHead>
                        <TableHead class="text-right pr-4">Aksi</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody class="text-sm">
                    <TableRow v-if="requests.data.length === 0">
                        <TableCell colspan="8" class="text-center py-12 text-muted-foreground text-sm">Tidak ada request.</TableCell>
                    </TableRow>
                    <TableRow v-for="r in requests.data" :key="r.id">
                        <TableCell class="pl-4 text-xs">{{ fmt(r.requested_at) }}</TableCell>
                        <TableCell>{{ r.sales?.name ?? '—' }}</TableCell>
                        <TableCell>{{ r.customer?.name ?? '—' }}</TableCell>
                        <TableCell class="text-xs max-w-xs truncate" :title="r.reason">{{ r.reason }}</TableCell>
                        <TableCell class="text-xs">{{ r.distance_meter ? `${r.distance_meter}m` : '—' }}</TableCell>
                        <TableCell>
                            <span :class="['inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-medium ring-1 capitalize', statusBadge(r.status)]">
                                {{ r.status }}
                            </span>
                        </TableCell>
                        <TableCell class="text-xs">{{ fmt(r.expires_at) }}</TableCell>
                        <TableCell class="text-right pr-4">
                            <template v-if="r.status === 'pending'">
                                <Button size="sm" variant="outline" class="h-7 px-2" @click="approve(r.id)">
                                    <CheckCircle2 class="size-3.5" />
                                </Button>
                                <Button size="sm" variant="outline" class="h-7 px-2 text-red-700 ml-1" @click="reject(r.id)">
                                    <XCircle class="size-3.5" />
                                </Button>
                            </template>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
            <div v-if="requests.data.length > 0" class="border-t border-border/70 px-4 py-2.5">
                <Pagination :meta="requests" />
            </div>
        </section>
    </AppLayout>
</template>
