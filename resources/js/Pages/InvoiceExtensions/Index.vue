<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import {
    CalendarClock,
    CheckCircle2,
    Clock,
    RotateCcw,
    X,
} from '@lucide/vue';
import { reactive, ref, watch } from 'vue';
import PageHeader from '@/Components/Shared/PageHeader.vue';
import Pagination from '@/Components/Shared/Pagination.vue';
import { Button } from '@/Components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
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
import { Textarea } from '@/Components/ui/textarea';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    extensions: { type: Object, required: true },
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
        router.get(
            route('invoice-extensions.index'),
            { status: filters.status === ALL ? '' : filters.status },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }, 300);
});

function reset() {
    filters.status = ALL;
}

const approveOpen = ref(false);
const rejectOpen = ref(false);
const targetLog = ref(null);
const approvedDate = ref('');
const rejectionReason = ref('');
const processing = ref(false);

function openApprove(log) {
    targetLog.value = log;
    approvedDate.value = log.new_due_date_requested;
    approveOpen.value = true;
}

function openReject(log) {
    targetLog.value = log;
    rejectionReason.value = '';
    rejectOpen.value = true;
}

function submitApprove() {
    processing.value = true;
    router.post(route('invoice-extensions.approve', targetLog.value.id), {
        approved_new_due_date: approvedDate.value,
    }, {
        preserveScroll: true,
        onSuccess: () => (approveOpen.value = false),
        onFinish: () => (processing.value = false),
    });
}

function submitReject() {
    processing.value = true;
    router.post(route('invoice-extensions.reject', targetLog.value.id), {
        rejection_reason: rejectionReason.value,
    }, {
        preserveScroll: true,
        onSuccess: () => (rejectOpen.value = false),
        onFinish: () => (processing.value = false),
    });
}

function formatDate(v) {
    if (!v) return '—';
    return new Date(v).toLocaleString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
}

function statusClass(s) {
    return {
        pending: 'bg-amber-50 text-amber-800',
        approved: 'bg-emerald-50 text-emerald-700',
        rejected: 'bg-red-50 text-red-700',
        cancelled: 'bg-muted text-muted-foreground',
    }[s] ?? 'bg-muted text-muted-foreground';
}

function statusDotClass(s) {
    return {
        pending: 'bg-amber-500',
        approved: 'bg-emerald-500',
        rejected: 'bg-red-500',
        cancelled: 'bg-muted-foreground/50',
    }[s] ?? 'bg-muted-foreground/50';
}
</script>

<template>
    <Head title="Invoice Extensions" />

    <AppLayout>
        <PageHeader title="Invoice Extensions" description="Permintaan perpanjangan jatuh tempo invoice." :icon="CalendarClock" />

        <section class="rounded-2xl bg-card/70 backdrop-blur-xl ring-1 ring-foreground/6 shadow-sm overflow-hidden">
            <div class="border-b border-foreground/5 px-3 py-2.5 flex items-center gap-2 flex-wrap">
                <Select v-model="filters.status">
                    <SelectTrigger class="w-[160px] h-9 rounded-full bg-muted/50 border-transparent"><SelectValue placeholder="Status" /></SelectTrigger>
                    <SelectContent>
                        <SelectItem :value="ALL">Semua Status</SelectItem>
                        <SelectItem value="pending">Pending</SelectItem>
                        <SelectItem value="approved">Approved</SelectItem>
                        <SelectItem value="rejected">Rejected</SelectItem>
                        <SelectItem value="cancelled">Cancelled</SelectItem>
                    </SelectContent>
                </Select>
                <Button type="button" variant="ghost" size="default" class="rounded-full" @click="reset">
                    <RotateCcw class="size-3.5" /> Reset
                </Button>
            </div>

            <Table>
                <TableHeader>
                    <TableRow class="[&>th]:text-[11px] [&>th]:font-semibold [&>th]:uppercase [&>th]:tracking-wider [&>th]:text-muted-foreground [&>th]:py-2.5">
                        <TableHead class="pl-4">Invoice</TableHead>
                        <TableHead>Customer</TableHead>
                        <TableHead>Old Due</TableHead>
                        <TableHead>Requested Due</TableHead>
                        <TableHead>Reason</TableHead>
                        <TableHead>Requester</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead class="text-right pr-4">Aksi</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody class="text-sm">
                    <TableRow v-if="extensions.data.length === 0">
                        <TableCell colspan="8" class="text-center py-16">
                            <div class="flex flex-col items-center gap-2 text-muted-foreground">
                                <CalendarClock class="size-7 opacity-40" />
                                <p class="text-sm">Belum ada permintaan extension.</p>
                            </div>
                        </TableCell>
                    </TableRow>
                    <TableRow v-for="log in extensions.data" :key="log.id" class="hover:bg-foreground/2.5 border-foreground/5 transition-colors">
                        <TableCell class="pl-4 py-2.5 font-mono text-xs">
                            <Link :href="route('invoices.show', log.invoice_id)" class="hover:text-primary">
                                {{ log.invoice?.invoice_number }}
                            </Link>
                        </TableCell>
                        <TableCell>
                            <p class="font-medium">{{ log.invoice?.customer?.name ?? '—' }}</p>
                            <p class="text-[12px] text-muted-foreground font-mono">{{ log.invoice?.customer?.code }}</p>
                        </TableCell>
                        <TableCell class="text-xs">{{ formatDate(log.old_due_date) }}</TableCell>
                        <TableCell class="text-xs font-semibold">{{ formatDate(log.new_due_date_requested) }}</TableCell>
                        <TableCell class="text-xs max-w-xs truncate" :title="log.request_reason">{{ log.request_reason }}</TableCell>
                        <TableCell class="text-xs">{{ log.requester?.name ?? '—' }}</TableCell>
                        <TableCell>
                            <span :class="['inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[11px] font-medium capitalize', statusClass(log.status)]">
                                <span :class="['size-1.5 rounded-full', statusDotClass(log.status)]" />
                                {{ log.status }}
                            </span>
                        </TableCell>
                        <TableCell class="py-3 px-4 text-right whitespace-nowrap">
                            <div v-if="log.status === 'pending'" class="flex gap-1 justify-end">
                                <Button size="sm" variant="outline" class="h-7 px-2 text-xs rounded-full" @click="openApprove(log)">
                                    <CheckCircle2 class="size-3" /> Approve
                                </Button>
                                <Button size="sm" variant="outline" class="h-7 px-2 text-xs rounded-full text-red-700 hover:bg-red-50" @click="openReject(log)">
                                    <X class="size-3" /> Reject
                                </Button>
                            </div>
                            <span v-else class="text-xs text-muted-foreground">—</span>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>

            <div v-if="extensions.data.length > 0" class="border-t border-foreground/5 px-4 py-2.5">
                <Pagination :meta="extensions" />
            </div>
        </section>

        <Dialog v-model:open="approveOpen">
            <DialogContent class="rounded-3xl gap-0">
                <DialogHeader>
                    <DialogTitle>Approve Extension</DialogTitle>
                    <DialogDescription>Set tanggal jatuh tempo baru untuk invoice ini.</DialogDescription>
                </DialogHeader>
                <div class="space-y-2">
                    <Label for="approved_date">Due Date Baru</Label>
                    <Input id="approved_date" v-model="approvedDate" type="date" />
                </div>
                <DialogFooter>
                    <Button variant="outline" class="rounded-full" @click="approveOpen = false">Batal</Button>
                    <Button class="rounded-full bg-brand text-white hover:bg-brand-dark" :disabled="processing || !approvedDate" @click="submitApprove">Approve</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="rejectOpen">
            <DialogContent class="rounded-3xl gap-0">
                <DialogHeader>
                    <DialogTitle>Reject Extension</DialogTitle>
                    <DialogDescription>Berikan alasan penolakan.</DialogDescription>
                </DialogHeader>
                <div class="space-y-2">
                    <Label for="rejection_reason">Alasan</Label>
                    <Textarea id="rejection_reason" v-model="rejectionReason" rows="3" required />
                </div>
                <DialogFooter>
                    <Button variant="outline" class="rounded-full" @click="rejectOpen = false">Batal</Button>
                    <Button variant="destructive" class="rounded-full" :disabled="processing || !rejectionReason.trim()" @click="submitReject">Reject</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
